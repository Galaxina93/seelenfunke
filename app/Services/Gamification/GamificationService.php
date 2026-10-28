<?php

namespace App\Services\Gamification;

use App\Models\Marketing\MarketingVoucher;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\Product\ProductReview;
use Illuminate\Support\Str;

class GamificationService
{
    private static bool $hangarColumnsChecked = false;

    public function ensureHangarColumns(): void
    {
        if (self::$hangarColumnsChecked) {
            return;
        }
        self::$hangarColumnsChecked = true;

        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('customer_gamifications', 'active_ship_skin')) {
                \Illuminate\Support\Facades\Schema::table('customer_gamifications', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('customer_gamifications', 'unlocked_skins')) {
                        $table->json('unlocked_skins')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('customer_gamifications', 'active_ship_skin')) {
                        $table->string('active_ship_skin', 64)->default('default');
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('customer_gamifications', 'unlocked_hall_auras')) {
                        $table->json('unlocked_hall_auras')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('customer_gamifications', 'active_hall_aura')) {
                        $table->string('active_hall_aura', 64)->default('default');
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('customer_gamifications', 'hero_motto_key')) {
                        $table->string('hero_motto_key', 64)->nullable();
                    }
                });
            }
        } catch (\Throwable $e) {
            // Ignorieren falls DB-Treiber gerade kein Schema-Update erlaubt
        }
    }

    public function isGameModeActive(?Customer $customer = null): bool
    {
        $customer = $customer ?? \Illuminate\Support\Facades\Auth::guard('customer')->user();
        if (!$customer) {
            return false;
        }

        return (bool) CustomerGamification::where('customer_id', $customer->id)->value('is_active');
    }

    public function getProfile(Customer $customer): CustomerGamification
    {
        $this->ensureHangarColumns();

        return CustomerGamification::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'is_active' => false,
                'titles_progress' => $this->getInitialTitlesProgress(),
            ]
        );
    }

    public function getUpgradeCost(?int $currentLevel = 1): int
    {
        $level = max(1, $currentLevel ?? 1);
        if ($level >= GameConfig::MAX_LEVEL) return 0;

        // Level 1 bis 9 (Aufstieg bis Level 10: Seelengott-Form & Rabattgutscheine)
        if ($level < 10) {
            return (int) round(pow($level, 1.5) * 10) + 5;
        }

        // Prestige-Level 10 bis 99 (Aufstieg auf Level 11 bis 100):
        // Sanft ansteigende Prestige-Kurve (300 Funken bis max. 1.250 Funken pro Stufe)
        return min(1250, 300 + (($level - 10) * 20));
    }

    public function refillEnergyIfDue(CustomerGamification $profile, int $maxEnergy = 5): CustomerGamification
    {
        if (!$profile->is_active) {
            return $profile;
        }

        $now = now();
        $lastRefill = $profile->last_energy_refill_at ? \Carbon\Carbon::parse($profile->last_energy_refill_at) : null;
        if ($profile->energy_balance < $maxEnergy && (!$lastRefill || $lastRefill->diffInMinutes($now, false) >= 180)) {
            $profile->energy_balance = $maxEnergy;
            $profile->last_energy_refill_at = $now;
            $profile->save();
        }

        return $profile;
    }

    public function upgradeLevel(Customer $customer): array
    {
        $this->ensureHangarColumns();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($customer) {
            $profile = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
            if (!$profile || !$profile->is_active) {
                return ['success' => false, 'message' => 'Spielmodus ist nicht aktiviert.'];
            }

            $cost = $this->getUpgradeCost($profile->level);

            if ($profile->level >= GameConfig::MAX_LEVEL) {
                return ['success' => false, 'message' => 'Maximales Prestige-Level (100) erreicht.'];
            }
            if ($profile->funken_balance < $cost) {
                return ['success' => false, 'message' => 'Nicht genügend Funken.'];
            }

            $profile->funken_balance -= $cost;
            $profile->level += 1;
            $profile->save();

            // Prüfe auf Coupon (nur Level 1-10) und Prestige-Meilensteine (Level 15-100)
            $rewardMessage = $this->checkAndIssueCoupon($profile, $profile->level);
            $this->syncHangarUnlocks($profile);

            $prestigeMilestones = GameConfig::getPrestigeMilestones();
            if (!$rewardMessage && isset($prestigeMilestones[$profile->level])) {
                $rewardMessage = $prestigeMilestones[$profile->level]['name'] . ' freigeschaltet!';
            }

            $defaultMsg = $profile->level <= GameConfig::FUNKI_EVOLUTION_MAX_LEVEL
                ? 'Neue Funki-Evolutionsstufe erreicht!'
                : 'Prestige-Level ' . $profile->level . ' erreicht!';

            return [
                'success' => true,
                'new_level' => $profile->level,
                'reward' => $rewardMessage ?? $defaultMsg
            ];
        });
    }

    public function generateUniqueMilestoneCode(int $level): string
    {
        // 8 Zeichen Zufallstoken: 36^8 = 2,82 Billionen Permutationen, unerratbar
        do {
            $token = strtoupper(Str::random(8));
            $code = 'FUNKI-L' . $level . '-' . $token;
        } while (MarketingVoucher::where('code', $code)->exists());

        return $code;
    }

    private function checkAndIssueCoupon(CustomerGamification $profile, int $newLevel): ?string
    {
        $rewards = GameConfig::getLevelRewards();

        if (array_key_exists($newLevel, $rewards)) {
            $unlocked = is_array($profile->unlocked_coupons) ? $profile->unlocked_coupons : [];

            // Duplicate Minting Prevention
            if (isset($unlocked[(string)$newLevel]) || isset($unlocked[$newLevel])) {
                return null;
            }

            $customer = $profile->customer ?? ($profile->customer_id ? Customer::find($profile->customer_id) : null);
            if (!$customer || empty($customer->email)) {
                return null;
            }
            $email = $customer->email;

            // Prüfen, ob in DB bereits ein Coupon für diesen Kunden und dieses Level existiert
            if ($email) {
                $existingVoucher = MarketingVoucher::where('recipient_email', $email)
                    ->where('code', 'like', 'FUNKI-L' . $newLevel . '-%')
                    ->first();

                if ($existingVoucher) {
                    $unlocked[(string)$newLevel] = $existingVoucher->code;
                    $profile->unlocked_coupons = $unlocked;
                    $profile->save();
                    return $rewards[$newLevel]['name'] . ' freigeschaltet!';
                }
            }

            $rewardConfig = $rewards[$newLevel];
            $code = $this->generateUniqueMilestoneCode($newLevel);

            MarketingVoucher::create([
                'title'           => 'Level ' . $newLevel . ' Belohnung (' . ($email ?: 'System') . ')',
                'code'            => $code,
                'recipient_email' => $email,
                'type'            => 'percent',
                'value'           => $rewardConfig['value'],
                'usage_limit'     => 1,
                'is_active'       => true,
                'valid_from'      => now(),
                'mode'            => 'manual',
            ]);

            $unlocked[(string)$newLevel] = $code; // Als String speichern!

            $profile->unlocked_coupons = $unlocked;
            $profile->save();

            return $rewardConfig['name'] . ' freigeschaltet!';
        }

        return null;
    }

    public function syncUnlockedRewards(CustomerGamification $profile): void
    {
        if (!$profile->is_active) {
            return;
        }

        $this->ensureHangarColumns();

        \Illuminate\Support\Facades\DB::transaction(function () use ($profile) {
            $lockedProfile = CustomerGamification::where('id', $profile->id)->lockForUpdate()->first();
            if (!$lockedProfile || !$lockedProfile->is_active) {
                return;
            }

            $this->syncHangarUnlocks($lockedProfile);

            $rewards = GameConfig::getLevelRewards();
            $unlocked = is_array($lockedProfile->unlocked_coupons) ? $lockedProfile->unlocked_coupons : [];
            $changed = false;
            $customer = $lockedProfile->customer ?? ($lockedProfile->customer_id ? Customer::find($lockedProfile->customer_id) : null);
            if (!$customer || empty($customer->email)) {
                return;
            }
            $email = $customer->email;

            for ($i = 1; $i <= $lockedProfile->level; $i++) {
                if (!isset($rewards[$i])) {
                    continue;
                }

                $existingCode = $unlocked[(string)$i] ?? $unlocked[$i] ?? null;

                // 1. Wenn Code im Profil steht: Prüfen, ob der Voucher in der DB wirklich existiert
                if ($existingCode) {
                    $voucherExists = MarketingVoucher::where('code', $existingCode)->exists();
                    if ($voucherExists) {
                        continue;
                    }
                    // Falls der Code in unlocked_coupons steht, aber in DB gelöscht/nicht existent ist -> wird unten repariert
                }

                // 2. Prüfen, ob in DB bereits ein Coupon für diesen Kunden und dieses Level existiert
                if ($email) {
                    $dbVoucher = MarketingVoucher::where('recipient_email', $email)
                        ->where('code', 'like', 'FUNKI-L' . $i . '-%')
                        ->first();

                    if ($dbVoucher) {
                        $unlocked[(string)$i] = $dbVoucher->code;
                        $changed = true;
                        continue;
                    }
                }

                // 3. Neu erzeugen (atomar, kollisionssicher & unerratbar)
                $rewardConfig = $rewards[$i];
                $code = $this->generateUniqueMilestoneCode($i);

                MarketingVoucher::create([
                    'title'           => 'Nachgereicht: Level ' . $i . ' Belohnung (' . ($email ?: 'System') . ')',
                    'code'            => $code,
                    'recipient_email' => $email,
                    'type'            => 'percent',
                    'value'           => $rewardConfig['value'],
                    'usage_limit'     => 1,
                    'is_active'       => true,
                    'valid_from'      => now(),
                    'mode'            => 'manual',
                ]);

                $unlocked[(string)$i] = $code;
                $changed = true;
            }

            if ($changed) {
                $lockedProfile->unlocked_coupons = $unlocked;
                $lockedProfile->save();
                $profile->unlocked_coupons = $unlocked;
            }
        });
    }

    public function addFunken(Customer $customer, int $amount, string $source = 'purchase'): void
    {
        if ($amount <= 0) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $amount, $source) {
            $profile = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();

            if (!$profile || !$profile->is_active) {
                return;
            }

            if ($source === 'website_hunt') {
                if ($profile->last_spark_collection_date && !$profile->last_spark_collection_date->isToday()) {
                    $profile->sparks_collected_today = 0;
                }
                if ($profile->sparks_collected_today >= GameConfig::DAILY_SPARK_LIMIT) {
                    return;
                }

                $profile->sparks_collected_today += 1;
                $profile->last_spark_collection_date = now();

                $progress = is_array($profile->titles_progress) && !empty($profile->titles_progress)
                    ? $profile->titles_progress
                    : $this->getInitialTitlesProgress();
                $progress['sammler'] = (int) ($progress['sammler'] ?? 0) + 1;
                $profile->titles_progress = $progress;
            }

            $profile->funken_balance += $amount;
            $profile->funken_total_earned += $amount;

            $progress = is_array($profile->titles_progress) && !empty($profile->titles_progress)
                ? $profile->titles_progress
                : $this->getInitialTitlesProgress();
            $progress['funkenkoenig'] = (int) $profile->funken_total_earned;
            $profile->titles_progress = $progress;

            $profile->save();
        });
    }

    public function rewardOrderFunken(OrderOrder $order): void
    {
        if (!$order->customer_id) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $profile = CustomerGamification::where('customer_id', $order->customer_id)->lockForUpdate()->first();
            if (!$profile || !$profile->is_active) {
                return;
            }

            $progress = is_array($profile->titles_progress) && !empty($profile->titles_progress)
                ? $profile->titles_progress
                : $this->getInitialTitlesProgress();

            $rewardedIds = is_array($progress['_rewarded_order_ids'] ?? null) ? $progress['_rewarded_order_ids'] : [];
            if (in_array((string) $order->id, $rewardedIds, true)) {
                return;
            }

            $orderSparks = max(5, (int) floor(((int) $order->total_price) / 200));
            $rewardedIds[] = (string) $order->id;
            $progress['_rewarded_order_ids'] = array_values(array_unique($rewardedIds));

            $profile->funken_balance += $orderSparks;
            $profile->funken_total_earned += $orderSparks;
            $progress['funkenkoenig'] = (int) $profile->funken_total_earned;
            $profile->titles_progress = $progress;
            $profile->save();

            $this->syncDynamicTitles($profile);
        });
    }

    public function reverseOrderFunken(OrderOrder $order): void
    {
        if (!$order->customer_id) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $profile = CustomerGamification::where('customer_id', $order->customer_id)->lockForUpdate()->first();
            if (!$profile) {
                return;
            }

            $progress = is_array($profile->titles_progress) && !empty($profile->titles_progress)
                ? $profile->titles_progress
                : $this->getInitialTitlesProgress();

            $rewardedIds = is_array($progress['_rewarded_order_ids'] ?? null) ? $progress['_rewarded_order_ids'] : [];
            if (in_array((string) $order->id, $rewardedIds, true)) {
                $orderSparks = max(5, (int) floor(((int) $order->total_price) / 200));
                $profile->funken_balance = max(0, (int) $profile->funken_balance - $orderSparks);
                $profile->funken_total_earned = max(0, (int) $profile->funken_total_earned - $orderSparks);
                $progress['funkenkoenig'] = (int) $profile->funken_total_earned;
                $progress['_rewarded_order_ids'] = array_values(array_diff($rewardedIds, [(string) $order->id]));
                $profile->titles_progress = $progress;
                $profile->save();
            }

            $this->syncDynamicTitles($profile);
        });
    }

    public function getCurrentModelPath(int $level): string
    {
        $milestones = GameConfig::getAppearanceMilestones();
        $currentModel = 'funki_lvl_1_rags';
        foreach ($milestones as $milestoneLevel => $modelName) {
            if ($level >= $milestoneLevel) $currentModel = $modelName;
            else break;
        }
        return asset('shop/customer/gamification/models/' . $currentModel . '.glb');
    }

    public function incrementTitleProgress(CustomerGamification $profile, string $titleId, int $amount = 1): void
    {
        if (!$profile->is_active) {
            return;
        }

        $progress = $profile->titles_progress;
        if (!is_array($progress) || empty($progress)) $progress = $this->getInitialTitlesProgress();
        if (!array_key_exists($titleId, $progress)) $progress[$titleId] = 0;

        $progress[$titleId] += $amount;
        $profile->update(['titles_progress' => $progress]);
    }

    public function syncDynamicTitles(CustomerGamification $profile): void
    {
        if (!$profile->is_active) {
            return;
        }

        $customer = $profile->customer;
        if (!$customer) return;

        $progress = $profile->titles_progress;
        if (!is_array($progress)) $progress = $this->getInitialTitlesProgress();
        $tz = config('app.timezone', 'Europe/Berlin');

        // REVIEWS
        $reviews = ProductReview::where('customer_id', $customer->id)->where('status', 'approved')->get();
        $progress['botschafter'] = $reviews->count();
        $progress['treuer_bewerter'] = $reviews->where('rating', 5)->count();
        $progress['bildreporter'] = $reviews->filter(fn($r) => !empty($r->media))->count();
        $progress['wortgewandt'] = $reviews->filter(fn($r) => mb_strlen($r->content ?? '') >= 100)->count();

        // ORDERS (nur bezahlte und nicht stornierte/erstattete Bestellungen zählen für Ränge)
        $orders = OrderOrder::where('customer_id', $customer->id)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->with('items.product.categories')
            ->get();
        $progress['schatzhueter'] = $orders->count();
        $progress['massenkaeufer'] = (int) $orders->flatMap(fn($o) => $o->items)->sum('quantity');

        $progress['freudebringer'] = $orders->filter(function ($order) {
            if (isset($order->is_different_shipping_address) && $order->is_different_shipping_address) return true;
            if (($order->shipping_address_id ?? null) && ($order->billing_address_id ?? null) && ($order->shipping_address_id !== $order->billing_address_id)) {
                return true;
            }
            $billing = is_array($order->billing_address) ? $order->billing_address : [];
            $shipping = is_array($order->shipping_address) ? $order->shipping_address : [];
            $shipStreet = trim((string) ($shipping['street'] ?? $shipping['address'] ?? ''));
            $shipCity = trim((string) ($shipping['city'] ?? ''));
            if ($shipStreet === '' && $shipCity === '') {
                return false;
            }
            $norm = fn(array $a) => mb_strtolower(trim(implode('|', [
                $a['first_name'] ?? '',
                $a['last_name'] ?? '',
                $a['street'] ?? $a['address'] ?? '',
                $a['postal_code'] ?? $a['zip'] ?? '',
                $a['city'] ?? '',
            ])));
            return $norm($billing) !== $norm($shipping);
        })->count();

        $progress['entdecker'] = $orders->flatMap(fn($o) => $o->items->flatMap(function ($i) {
            if (!$i->product) {
                return [];
            }
            if (!empty($i->product->category_id)) {
                return [$i->product->category_id];
            }
            return $i->product->categories?->pluck('id')->all() ?? [];
        }))->filter()->unique()->count();
        $progress['nachtschwaermer'] = $orders->filter(function ($o) use ($tz) {
            if (!$o->created_at) return false;
            $hour = (int) $o->created_at->copy()->timezone($tz)->hour;
            return $hour >= 22 || $hour <= 4;
        })->count();
        $progress['wochenend_shopper'] = $orders->filter(fn($o) => $o->created_at && $o->created_at->copy()->timezone($tz)->isWeekend())->count();
        $progress['wiederholungstaeter'] = $orders->filter(fn($o) => (bool) $o->created_at)->map(fn($o) => $o->created_at->format('Y-m'))->unique()->count();

        // PROFIL (immer ganzzahlig runden, damit keine Nachkommastellen in der Anzeige entstehen)
        $progress['treue_seele'] = max(0, $customer->created_at ? (int) floor($customer->created_at->diffInDays(now())) : 0);
        $progress['funkenkoenig'] = (int) ($profile->funken_total_earned ?? 0);

        $updatePayload = ['titles_progress' => $progress];
        if ($profile->active_title) {
            $configs = GameConfig::getTitles();
            $activeKey = $profile->active_title;
            $minReq = $configs[$activeKey]['tiers']['silber']['req'] ?? PHP_INT_MAX;
            if (!isset($configs[$activeKey]) || (int) ($progress[$activeKey] ?? 0) < $minReq) {
                $updatePayload['active_title'] = null;
            }
        }

        $profile->update($updatePayload);
    }

    public function evaluateTitles(CustomerGamification $profile): array
    {
        $this->syncDynamicTitles($profile);
        $progress = $profile->fresh()->titles_progress ?? $this->getInitialTitlesProgress();

        $configs = GameConfig::getTitles();
        $evaluated = [];
        $diamondsCount = 0;

        foreach ($configs as $key => $config) {
            $currentValue = (int) floor((float) ($progress[$key] ?? 0));
            $currentTier = 'grau';
            $nextReq = (int) $config['tiers']['silber']['req'];
            $nextTierName = $config['tiers']['silber']['name'];
            $nextTierLabel = 'Silber';
            $maxReq = (int) $config['tiers']['diamant']['req'];

            if ($currentValue >= $maxReq) {
                $currentTier = 'diamant';
                $nextReq = $maxReq;
                $nextTierName = $config['tiers']['diamant']['name'];
                $nextTierLabel = 'Diamant';
                $diamondsCount++;
            } elseif ($currentValue >= $config['tiers']['gold']['req']) {
                $currentTier = 'gold';
                $nextReq = $maxReq;
                $nextTierName = $config['tiers']['diamant']['name'];
                $nextTierLabel = 'Diamant';
            } elseif ($currentValue >= $config['tiers']['silber']['req']) {
                $currentTier = 'silber';
                $nextReq = (int) $config['tiers']['gold']['req'];
                $nextTierName = $config['tiers']['gold']['name'];
                $nextTierLabel = 'Gold';
            }

            $percentage = $currentValue >= $maxReq
                ? 100
                : ($nextReq > 0 ? min(100, (int) round(($currentValue / $nextReq) * 100)) : 100);

            $evaluated[$key] = [
                'name' => $config['name'],
                'description' => $config['description'],
                'current_value' => $currentValue,
                'tier' => $currentTier,
                'tier_name' => $currentTier === 'grau' ? 'Noch gesperrt' : $config['tiers'][$currentTier]['name'],
                'next_req' => $nextReq,
                'next_tier_name' => $nextTierName,
                'next_tier_label' => $nextTierLabel,
                'max_req' => $maxReq,
                'percentage' => $percentage,
            ];
        }

        $megaTitles = GameConfig::getMegaTitles();
        $currentMegaTitle = null;
        $nextMegaTitle = $megaTitles[0] ?? null;

        foreach ($megaTitles as $index => $mega) {
            if ($diamondsCount >= $mega['req']) {
                $currentMegaTitle = $mega;
                $currentMegaTitle['rank'] = $index + 1;
                $nextMegaTitle = $megaTitles[$index + 1] ?? null;
            }
        }

        return [
            'titles' => $evaluated,
            'diamonds_count' => $diamondsCount,
            'diamond_count' => $diamondsCount,
            'total_titles' => count($configs),
            'mega_title' => $currentMegaTitle,
            'next_mega_title' => $nextMegaTitle,
            'is_seelengott' => $diamondsCount >= count($configs),
        ];
    }

    /**
     * Ermittelt konsistent den aktuell aktiven Rangnamen des Kunden für Profil, Modal und Rangliste.
     * Zum Start hat der Kunde keinen Rang (null). Erst wenn er einen Titel freigeschaltet
     * und ausgewählt hat, wird dessen Rang-Bezeichnung zurückgegeben.
     */
    public function resolveActiveRankName(CustomerGamification $profile, ?array $titlesData = null): ?string
    {
        if (!$profile->is_active) {
            return null;
        }

        $titlesData = $titlesData ?? $this->evaluateTitles($profile);
        $activeKey = $profile->active_title;

        if ($activeKey && isset($titlesData['titles'][$activeKey])) {
            $selectedTitle = $titlesData['titles'][$activeKey];
            if (($selectedTitle['tier'] ?? 'grau') !== 'grau' && !empty($selectedTitle['tier_name'])) {
                return $selectedTitle['tier_name'];
            }
        }

        return null;
    }

    private function getInitialTitlesProgress(): array
    {
        return [
            'spieler' => 0, 'sammler' => 0, 'botschafter' => 0, 'bildreporter' => 0,
            'wortgewandt' => 0, 'treuer_bewerter' => 0, 'schatzhueter' => 0,
            'massenkaeufer' => 0, 'entdecker' => 0, 'freudebringer' => 0,
            'nachtschwaermer' => 0, 'wochenend_shopper' => 0, 'treue_seele' => 0,
            'wiederholungstaeter' => 0, 'funkenkoenig' => 0
        ];
    }

    public function hasVerifiedOrder(CustomerGamification $profile): bool
    {
        $progress = is_array($profile->titles_progress) ? $profile->titles_progress : [];
        if (((int) ($progress['schatzhueter'] ?? 0)) >= 1) {
            return true;
        }
        if (!$profile->customer_id) {
            return false;
        }
        return OrderOrder::where('customer_id', $profile->customer_id)
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhereIn('status', ['processing', 'shipped', 'completed', 'paid']);
            })
            ->exists();
    }

    public function hasVerifiedFiveStarReview(CustomerGamification $profile): bool
    {
        $progress = is_array($profile->titles_progress) ? $profile->titles_progress : [];
        if (((int) ($progress['treuer_bewerter'] ?? 0)) >= 1) {
            return true;
        }
        if (!$profile->customer_id) {
            return false;
        }
        return ProductReview::where('customer_id', $profile->customer_id)
            ->where('status', 'approved')
            ->where('rating', 5)
            ->exists();
    }

    public function resolveBattleshipUnlockStatus(CustomerGamification $profile, ?array $unlockedSkins = null): array
    {
        $fighterKeys = GameConfig::getFighterSkinKeys();
        if (!$profile->is_active) {
            return [
                'has_verified_order'    => false,
                'has_five_star_review'  => false,
                'has_all_fighters'      => false,
                'unlocked_fighters_cnt' => 1,
                'total_fighters_cnt'    => count($fighterKeys),
                'completed_premises'    => 0,
                'is_unlocked'           => false,
            ];
        }

        $skins = $unlockedSkins ?? (is_array($profile->unlocked_skins) ? $profile->unlocked_skins : ['default']);
        if (!in_array('default', $skins, true)) {
            $skins[] = 'default';
        }

        $unlockedFightersCount = 0;
        foreach ($fighterKeys as $fKey) {
            if (in_array($fKey, $skins, true)) {
                $unlockedFightersCount++;
            }
        }

        $hasOrder = $this->hasVerifiedOrder($profile);
        $hasReview = $this->hasVerifiedFiveStarReview($profile);
        $hasAllFighters = ($unlockedFightersCount >= count($fighterKeys));

        return [
            'has_verified_order'    => $hasOrder,
            'has_five_star_review'  => $hasReview,
            'has_all_fighters'      => $hasAllFighters,
            'unlocked_fighters_cnt' => $unlockedFightersCount,
            'total_fighters_cnt'    => count($fighterKeys),
            'completed_premises'    => ($hasOrder ? 1 : 0) + ($hasReview ? 1 : 0) + ($hasAllFighters ? 1 : 0),
            'is_unlocked'           => $hasOrder && $hasReview && $hasAllFighters,
        ];
    }

    public function syncHangarUnlocks(CustomerGamification $profile): void
    {
        if (!$profile->is_active) {
            return;
        }

        $this->ensureHangarColumns();

        $skins = is_array($profile->unlocked_skins) ? $profile->unlocked_skins : ['default'];
        if (!in_array('default', $skins, true)) {
            $skins[] = 'default';
        }

        $auras = is_array($profile->unlocked_hall_auras) ? $profile->unlocked_hall_auras : ['default'];
        if (!in_array('default', $auras, true)) {
            $auras[] = 'default';
        }

        $lvl = (int) ($profile->level ?? 1);
        $isVipBuyer = $this->hasVerifiedOrder($profile);
        $allSkins = GameConfig::getShipSkins();

        // Erst alle Gleiter & Jäger prüfen
        foreach ($allSkins as $key => $cfg) {
            if ($key === 'default' || ($cfg['category'] ?? 'fighter') === 'battleship') continue;
            if (!empty($cfg['vip_buyer_unlock']) && $isVipBuyer && !in_array($key, $skins, true)) {
                $skins[] = $key;
            }
            if (($cfg['min_level'] ?? 1) > 1 && $lvl >= (int) $cfg['min_level'] && !in_array($key, $skins, true)) {
                $skins[] = $key;
            }
        }

        // Danach prüfen, ob die 3 Prämissen für Schlachtschiffe erfüllt sind
        $battleshipStatus = $this->resolveBattleshipUnlockStatus($profile, $skins);
        if ($battleshipStatus['is_unlocked']) {
            foreach ($allSkins as $key => $cfg) {
                if (($cfg['category'] ?? 'fighter') !== 'battleship') continue;
                $cost = (int) ($cfg['price_sparks'] ?? 0);
                // Schlachtschiffe mit reinem Level-Meilenstein (ohne Funken-Preis) automatisch freischalten, sobald Kategorie offen
                if ($cost === 0 && ($cfg['min_level'] ?? 1) > 1 && $lvl >= (int) $cfg['min_level'] && !in_array($key, $skins, true)) {
                    $skins[] = $key;
                }
            }
        }

        foreach (GameConfig::getHallAuras() as $key => $cfg) {
            if ($key === 'default') continue;
            if (!empty($cfg['vip_buyer_unlock']) && $isVipBuyer && !in_array($key, $auras, true)) {
                $auras[] = $key;
            }
            if (($cfg['min_level'] ?? 1) > 1 && $lvl >= (int) $cfg['min_level'] && !in_array($key, $auras, true)) {
                $auras[] = $key;
            }
        }

        $activeSkin = $profile->active_ship_skin ?: 'default';
        if (!in_array($activeSkin, $skins, true)) {
            $activeSkin = 'default';
        }

        $activeAura = $profile->active_hall_aura ?: 'default';
        if (!in_array($activeAura, $auras, true)) {
            $activeAura = 'default';
        }

        $mottoKey = $profile->hero_motto_key ?: 'default';
        $mottos = GameConfig::getHeroMottos();
        if (!isset($mottos[$mottoKey])) {
            $mottoKey = 'default';
        }

        $changed = ($profile->unlocked_skins !== array_values($skins))
            || ($profile->unlocked_hall_auras !== array_values($auras))
            || ($profile->active_ship_skin !== $activeSkin)
            || ($profile->active_hall_aura !== $activeAura)
            || ($profile->hero_motto_key !== $mottoKey);

        if ($changed) {
            try {
                $profile->unlocked_skins = array_values($skins);
                $profile->unlocked_hall_auras = array_values($auras);
                $profile->active_ship_skin = $activeSkin;
                $profile->active_hall_aura = $activeAura;
                $profile->hero_motto_key = $mottoKey;
                $profile->save();
            } catch (\Throwable $e) {
                // Fallback falls Spalten noch nicht migriert sind
            }
        }
    }

    public function resolveHangarData(CustomerGamification $profile): array
    {
        $this->ensureHangarColumns();

        $allSkins = GameConfig::getShipSkins();
        $allAuras = GameConfig::getHallAuras();
        $allMottos = GameConfig::getHeroMottos();
        $allMottosDetails = GameConfig::getHeroMottosDetails();
        $spaceStations = GameConfig::getSpaceStationsPlaceholder();

        if (!$profile->is_active) {
            return [
                'skins'               => $allSkins,
                'unlocked_skins'      => ['default'],
                'active_skin_key'     => 'default',
                'active_skin'         => $allSkins['default'],
                'auras'               => $allAuras,
                'unlocked_auras'      => ['default'],
                'active_aura_key'     => 'default',
                'active_aura'         => $allAuras['default'],
                'mottos'              => $allMottos,
                'mottos_details'      => $allMottosDetails,
                'active_motto_key'    => 'default',
                'active_motto'        => $allMottos['default'],
                'active_motto_detail' => $allMottosDetails['default'],
                'is_vip_buyer'        => false,
                'battleship_unlock'   => $this->resolveBattleshipUnlockStatus($profile, ['default']),
                'space_stations'      => $spaceStations,
            ];
        }

        $unlockedSkins = is_array($profile->unlocked_skins) ? $profile->unlocked_skins : ['default'];
        if (!in_array('default', $unlockedSkins, true)) {
            $unlockedSkins[] = 'default';
        }

        $unlockedAuras = is_array($profile->unlocked_hall_auras) ? $profile->unlocked_hall_auras : ['default'];
        if (!in_array('default', $unlockedAuras, true)) {
            $unlockedAuras[] = 'default';
        }

        $activeSkinKey = ($profile->active_ship_skin && isset($allSkins[$profile->active_ship_skin]) && in_array($profile->active_ship_skin, $unlockedSkins, true))
            ? $profile->active_ship_skin
            : 'default';

        $activeAuraKey = ($profile->active_hall_aura && isset($allAuras[$profile->active_hall_aura]) && in_array($profile->active_hall_aura, $unlockedAuras, true))
            ? $profile->active_hall_aura
            : 'default';

        $activeMottoKey = ($profile->hero_motto_key && isset($allMottos[$profile->hero_motto_key]))
            ? $profile->hero_motto_key
            : 'default';

        return [
            'skins'               => $allSkins,
            'unlocked_skins'      => $unlockedSkins,
            'active_skin_key'     => $activeSkinKey,
            'active_skin'         => $allSkins[$activeSkinKey],
            'auras'               => $allAuras,
            'unlocked_auras'      => $unlockedAuras,
            'active_aura_key'     => $activeAuraKey,
            'active_aura'         => $allAuras[$activeAuraKey],
            'mottos'              => $allMottos,
            'mottos_details'      => $allMottosDetails,
            'active_motto_key'    => $activeMottoKey,
            'active_motto'        => $allMottos[$activeMottoKey],
            'active_motto_detail' => $allMottosDetails[$activeMottoKey] ?? $allMottosDetails['default'],
            'is_vip_buyer'        => $this->hasVerifiedOrder($profile),
            'battleship_unlock'   => $this->resolveBattleshipUnlockStatus($profile, $unlockedSkins),
            'space_stations'      => $spaceStations,
        ];
    }

    public function buyOrEquipShipSkin(Customer $customer, string $skinKey): array
    {
        $this->ensureHangarColumns();
        $allSkins = GameConfig::getShipSkins();
        if (!isset($allSkins[$skinKey])) {
            return ['success' => false, 'message' => 'Ungültiger Schiff-Skin.'];
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $skinKey, $allSkins) {
            $profile = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
            if (!$profile || !$profile->is_active) {
                return ['success' => false, 'message' => 'Profil nicht aktiv.'];
            }

            $this->syncHangarUnlocks($profile);
            $unlocked = is_array($profile->unlocked_skins) ? $profile->unlocked_skins : ['default'];
            $skinCfg = $allSkins[$skinKey];

            if (in_array($skinKey, $unlocked, true)) {
                $profile->active_ship_skin = $skinKey;
                $profile->save();
                return [
                    'success' => true,
                    'action'  => 'equipped',
                    'message' => '🚀 ' . $skinCfg['name'] . ' im Hangar ausgerüstet!',
                ];
            }

            // Prüfe Schlachtschiff-Prämissen (1. Kauf, 2. 5-Sterne-Bewertung, 3. Alle 6 Jäger)
            if (($skinCfg['category'] ?? 'fighter') === 'battleship') {
                $bStatus = $this->resolveBattleshipUnlockStatus($profile, $unlocked);
                if (!$bStatus['is_unlocked']) {
                    return [
                        'success' => false,
                        'message' => '🔒 Schlachtschiffe erfordern: 1 verifizierten Kauf, 1 verifizierte 5-Sterne-Bewertung und alle 6 Gleiter & Jäger!',
                    ];
                }
            }

            $reqLevel = (int) ($skinCfg['min_level'] ?? 1);
            if ($reqLevel > 1 && (int) ($profile->level ?? 1) < $reqLevel) {
                return [
                    'success' => false,
                    'message' => '🔒 Erfordert mindestens Prestige-Level ' . $reqLevel . ' (aktuell Level ' . ((int) ($profile->level ?? 1)) . ').',
                ];
            }

            $cost = (int) ($skinCfg['price_sparks'] ?? 0);
            if ($profile->funken_balance < $cost) {
                return [
                    'success' => false,
                    'message' => 'Nicht genügend Funken (' . ($cost - $profile->funken_balance) . ' ✨ fehlen).',
                ];
            }

            $profile->funken_balance -= $cost;
            $unlocked[] = $skinKey;
            $profile->unlocked_skins = array_values(array_unique($unlocked));
            $profile->active_ship_skin = $skinKey;
            $profile->save();

            return [
                'success' => true,
                'action'  => 'purchased',
                'message' => '🎉 ' . $skinCfg['name'] . ' freigeschaltet & ausgerüstet!',
            ];
        });
    }

    public function buyOrEquipHallAura(Customer $customer, string $auraKey): array
    {
        $this->ensureHangarColumns();
        $allAuras = GameConfig::getHallAuras();
        if (!isset($allAuras[$auraKey])) {
            return ['success' => false, 'message' => 'Ungültige Ruhmes-Aura.'];
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $auraKey, $allAuras) {
            $profile = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
            if (!$profile || !$profile->is_active) {
                return ['success' => false, 'message' => 'Profil nicht aktiv.'];
            }

            $this->syncHangarUnlocks($profile);
            $unlocked = is_array($profile->unlocked_hall_auras) ? $profile->unlocked_hall_auras : ['default'];
            $auraCfg = $allAuras[$auraKey];

            if (in_array($auraKey, $unlocked, true)) {
                $profile->active_hall_aura = $auraKey;
                $profile->save();
                return [
                    'success' => true,
                    'action'  => 'equipped',
                    'message' => '✨ Ruhmes-Aura "' . $auraCfg['name'] . '" aktiviert!',
                ];
            }

            $cost = (int) ($auraCfg['price_sparks'] ?? 0);
            if ($profile->funken_balance < $cost) {
                return [
                    'success' => false,
                    'message' => 'Nicht genügend Funken (' . ($cost - $profile->funken_balance) . ' ✨ fehlen).',
                ];
            }

            $profile->funken_balance -= $cost;
            $unlocked[] = $auraKey;
            $profile->unlocked_hall_auras = array_values(array_unique($unlocked));
            $profile->active_hall_aura = $auraKey;
            $profile->save();

            return [
                'success' => true,
                'action'  => 'purchased',
                'message' => '🏆 Ruhmes-Aura "' . $auraCfg['name'] . '" für die Halle der Legenden freigeschaltet!',
            ];
        });
    }

    public function selectHeroMotto(Customer $customer, string $mottoKey): array
    {
        $this->ensureHangarColumns();
        $mottos = GameConfig::getHeroMottos();
        if (!isset($mottos[$mottoKey])) {
            return ['success' => false, 'message' => 'Ungültiger Wahlspruch.'];
        }

        $profile = CustomerGamification::where('customer_id', $customer->id)->first();
        if (!$profile || !$profile->is_active) {
            return ['success' => false, 'message' => 'Spielmodus ist nicht aktiviert.'];
        }

        $profile->hero_motto_key = $mottoKey;
        $profile->save();

        $details = GameConfig::getHeroMottosDetails()[$mottoKey] ?? null;
        $buffTxt = $details ? (' (' . $details['buff_label'] . ' aktiv)') : '';

        return [
            'success' => true,
            'message' => '💬 Helden-Motto & Moral-Buff aktualisiert' . $buffTxt . '!',
        ];
    }
}

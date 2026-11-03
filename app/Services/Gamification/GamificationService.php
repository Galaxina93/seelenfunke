<?php

namespace App\Services\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Customer\CustomerGamificationEventLog;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Models\Product\ProductReview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GamificationService
{
    private static bool $columnsChecked = false;

    public function ensureHangarColumns(): void
    {
        if (self::$columnsChecked) {
            return;
        }
        self::$columnsChecked = true;

        try {
            if (!Schema::hasColumn('customer_gamifications', 'active_background') || !Schema::hasColumn('customer_gamifications', 'player_name')) {
                Schema::table('customer_gamifications', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!Schema::hasColumn('customer_gamifications', 'active_background')) {
                        $table->string('active_background', 64)->default('default');
                    }
                    if (!Schema::hasColumn('customer_gamifications', 'unlocked_backgrounds')) {
                        $table->json('unlocked_backgrounds')->nullable();
                    }
                    if (!Schema::hasColumn('customer_gamifications', 'player_name')) {
                        $table->string('player_name', 40)->nullable();
                    }
                    if (!Schema::hasColumn('customer_gamifications', 'use_real_name')) {
                        $table->boolean('use_real_name')->default(false);
                    }
                    if (!Schema::hasColumn('customer_gamifications', 'ranking_opt_in')) {
                        $table->boolean('ranking_opt_in')->default(false);
                    }
                });
            }
        } catch (\Throwable $e) {
            // Ignorieren falls DB-Treiber kein Schema-Update erlaubt
        }
    }

    public function isGameModeActive(?Customer $customer = null): bool
    {
        $customer = $customer ?? Auth::guard('customer')->user();
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
                'is_active'           => false,
                'level'               => 1,
                'funken_balance'      => 0,
                'funken_total_earned' => 0,
                'titles_progress'     => $this->getInitialTitlesProgress(),
            ]
        );
    }

    /**
     * Prüft, ob der Kunde mindestens eine gültige (nicht stornierte/erstattete) Bestellung
     * abgeschlossen hat oder bereits Funken durch Käufe besitzt.
     */
    public function hasCompletedOrder(Customer $customer): bool
    {
        $hasOrder = OrderOrder::where('customer_id', $customer->id)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhereIn('status', ['completed', 'shipped', 'delivered', 'processing']);
            })
            ->exists();

        if ($hasOrder) {
            return true;
        }

        $profile = CustomerGamification::where('customer_id', $customer->id)->first();
        return $profile && ($profile->is_active || (int) $profile->funken_total_earned > 0);
    }

    /**
     * Berechnet die Seelenfunken für eine bezahlte Bestellung prozentual anhand des Produktwertes (Variante A = 100 %):
     * 1,00 € bezahlter Produktwert = 1 Seelenfunke (kaufmännisch gerundet).
     * Versandkosten, Express-Gebühren und der Kauf von Geschenkgutscheinen bringen keine Funken.
     * Aktionsrabatte (außer Geschenkgutschein-Einlösung als Zahlungsmittel) werden abgezogen.
     */
    public function calculateOrderSparks(OrderOrder $order): int
    {
        $order->loadMissing('items.product');

        $eligibleCents = 0;
        if ($order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $isGiftVoucher = ($item->product && $item->product->type === 'geschenkgutschein')
                    || !empty($item->configuration['is_gift_voucher']);
                if ($isGiftVoucher) {
                    continue;
                }
                $eligibleCents += (int) ($item->total_price ?? ((int) $item->unit_price * (int) $item->quantity));
            }
        } else {
            $subtotal = (int) ($order->subtotal_price ?? 0);
            if ($subtotal > 0) {
                $eligibleCents = $subtotal;
            } else {
                $eligibleCents = max(
                    0,
                    (int) ($order->total_price ?? 0)
                    - (int) ($order->shipping_price ?? 0)
                    - (int) ($order->express_price ?? 0)
                );
            }
        }

        // Rabatte aus Promo-Coupons abziehen (Geschenkgutschein-Einlösung ist ein Zahlungsmittel)
        $discountAmount = (int) ($order->discount_amount ?? 0);
        if ($discountAmount > 0) {
            $isGiftVoucherPayment = false;
            if (!empty($order->coupon_code)) {
                $isGiftVoucherPayment = \App\Models\Marketing\MarketingGiftVoucher::where('code', $order->coupon_code)->exists();
            }
            if (!$isGiftVoucherPayment) {
                $eligibleCents = max(0, $eligibleCents - $discountAmount);
            }
        }

        return GameConfig::calculateSparksForCents($eligibleCents);
    }

    /**
     * Synchronisiert das Funki-Level (1 bis 10) anhand der Lebenszeit-Funken.
     */
    public function syncLevelFromLifetimeSparks(CustomerGamification $profile, bool $allowDowngrade = false): int
    {
        $calculatedLevel = GameConfig::getLevelForLifetimeSparks((int) $profile->funken_total_earned);
        $currentLevel = min(GameConfig::MAX_LEVEL, max(1, (int) ($profile->level ?: 1)));

        $newLevel = $allowDowngrade
            ? $calculatedLevel
            : min(GameConfig::MAX_LEVEL, max($currentLevel, $calculatedLevel));

        if ((int) $profile->level !== $newLevel) {
            $profile->level = $newLevel;
        }

        return $newLevel;
    }

    public function getUpgradeCost(?int $currentLevel = 1): int
    {
        $level = max(1, $currentLevel ?? 1);
        if ($level >= GameConfig::MAX_LEVEL) {
            return 0;
        }

        return (int) round(pow($level, 1.5) * 10) + 5;
    }

    public function refillEnergyIfDue(CustomerGamification $profile, int $maxEnergy = 5): CustomerGamification
    {
        return $profile;
    }

    public function upgradeLevel(Customer $customer): array
    {
        $this->ensureHangarColumns();

        return DB::transaction(function () use ($customer) {
            $profile = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
            if (!$profile || !$profile->is_active) {
                return ['success' => false, 'message' => 'Das Seelenfunken-Treueprogramm ist nicht aktiviert.'];
            }

            $cost = $this->getUpgradeCost($profile->level);

            if ($profile->level >= GameConfig::MAX_LEVEL) {
                return ['success' => false, 'message' => 'Maximales Funki-Level (10) erreicht.'];
            }
            if ($profile->funken_balance < $cost) {
                return ['success' => false, 'message' => 'Nicht genügend Funken.'];
            }

            $profile->funken_balance -= $cost;
            $profile->level = min(GameConfig::MAX_LEVEL, (int) $profile->level + 1);
            $profile->save();

            $rewardMessage = $this->checkAndIssueCoupon($profile, (int) $profile->level);
            $defaultMsg = 'Neue Funki-Evolutionsstufe (Level ' . $profile->level . ') & neuer Hintergrund freigeschaltet!';

            $this->logGamificationEvent(
                (string) $customer->id,
                'level_up',
                'progression',
                'Aufstieg auf Level ' . $profile->level,
                $rewardMessage ?? $defaultMsg,
                -$cost,
                (int) $profile->funken_balance,
                ['new_level' => (int) $profile->level, 'cost_sparks' => $cost]
            );

            return [
                'success'   => true,
                'new_level' => (int) $profile->level,
                'reward'    => $rewardMessage ?? $defaultMsg,
            ];
        });
    }

    /**
     * Löst gesammelte Seelenfunken direkt in einen einmalig nutzbaren Rabatt-Gutschein ein:
     * 60 Funken = 5 %, 150 Funken = 10 %, 350 Funken = 15 %
     * Das Lebenszeit-Konto (funken_total_earned) und Funkis Level bleiben dabei vollständig erhalten!
     */
    public function redeemSparksForVoucher(Customer $customer, string $voucherKey): array
    {
        $vouchers = GameConfig::getRedeemableVouchers();
        if (!isset($vouchers[$voucherKey])) {
            return ['success' => false, 'message' => 'Ungültige Gutschein-Stufe ausgewählt.'];
        }

        $voucherConfig = $vouchers[$voucherKey];
        $cost = (int) $voucherConfig['cost_sparks'];
        $percent = (int) $voucherConfig['percent'];
        $prefix = (string) $voucherConfig['code_prefix'];

        if (empty($customer->email)) {
            return ['success' => false, 'message' => 'Für die Erstellung eines persönlichen Gutscheins wird eine gültige E-Mail-Adresse benötigt.'];
        }

        return DB::transaction(function () use ($customer, $voucherKey, $voucherConfig, $cost, $percent, $prefix) {
            $profile = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
            if (!$profile || !$profile->is_active) {
                return ['success' => false, 'message' => 'Bitte aktiviere zuerst das Seelenfunken-Sammelspiel.'];
            }

            if ((int) $profile->funken_balance < $cost) {
                $missing = $cost - (int) $profile->funken_balance;
                return [
                    'success' => false,
                    'message' => 'Dir fehlen noch ' . number_format($missing, 0, ',', '.') . ' ✨ Seelenfunken für den ' . $voucherConfig['name'] . '.',
                ];
            }

            // Eindeutigen, kryptographisch zufälligen Gutscheincode generieren
            do {
                $token = strtoupper(Str::random(8));
                $code = $prefix . '-' . $token;
            } while (MarketingVoucher::where('code', $code)->exists());

            // Nur das auszugebende Guthaben reduzieren – Lebenszeit-Funken & Funki-Level bleiben erhalten!
            $profile->funken_balance = max(0, (int) $profile->funken_balance - $cost);

            MarketingVoucher::create([
                'title'           => 'Seelenfunken-Prämie: ' . $voucherConfig['name'] . ' (' . $customer->email . ')',
                'code'            => $code,
                'recipient_email' => $customer->email,
                'type'            => 'percent',
                'value'           => $percent,
                'usage_limit'     => 1,
                'used_count'      => 0,
                'is_active'       => true,
                'valid_from'      => now(),
                'mode'            => 'manual',
            ]);

            $unlocked = is_array($profile->unlocked_coupons) ? $profile->unlocked_coupons : [];
            $unlocked[$voucherKey . '_' . strtolower(Str::random(4))] = $code;
            $unlocked[$voucherKey] = $code;
            $profile->unlocked_coupons = $unlocked;
            $profile->save();

            $this->logGamificationEvent(
                (string) $customer->id,
                'voucher_redeemed',
                'reward',
                $voucherConfig['name'] . ' eingelöst',
                'Persönlicher Einmal-Gutscheincode ' . $code . ' (' . $percent . '% Rabatt) für ' . $cost . ' Funken erstellt.',
                -$cost,
                (int) $profile->funken_balance,
                ['voucher_key' => $voucherKey, 'code' => $code, 'percent' => $percent]
            );

            return [
                'success'          => true,
                'code'             => $code,
                'percent'          => $percent,
                'discount_percent' => $percent,
                'cost_sparks'      => $cost,
                'new_balance'      => (int) $profile->funken_balance,
                'message'          => $voucherConfig['name'] . ' (' . $code . ') erfolgreich freigeschaltet!',
            ];
        });
    }

    /**
     * Liefert alle vom Kunden ersammelten persönlichen Funki-Gutscheine inkl. Einlösestatus.
     */
    public function getCustomerActiveVouchers(Customer $customer): array
    {
        if (empty($customer->email)) {
            return [];
        }

        return MarketingVoucher::where('recipient_email', $customer->email)
            ->where('code', 'like', 'FUNKI-%')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (MarketingVoucher $v) {
                $isUsed = !$v->is_active || ($v->usage_limit !== null && (int) $v->used_count >= (int) $v->usage_limit);
                return [
                    'id'         => $v->id,
                    'code'       => $v->code,
                    'title'      => $v->title,
                    'value'      => (int) $v->value,
                    'type'       => $v->type,
                    'is_used'    => $isUsed,
                    'created_at' => $v->created_at ? $v->created_at->format('d.m.Y') : '',
                ];
            })
            ->all();
    }

    public function generateUniqueMilestoneCode(int $level): string
    {
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

            if (isset($unlocked[(string) $newLevel]) || isset($unlocked[$newLevel])) {
                return null;
            }

            $customer = $profile->customer ?? ($profile->customer_id ? Customer::find($profile->customer_id) : null);
            if (!$customer || empty($customer->email)) {
                return null;
            }
            $email = $customer->email;

            $existingVoucher = MarketingVoucher::where('recipient_email', $email)
                ->where('code', 'like', 'FUNKI-L' . $newLevel . '-%')
                ->first();

            if ($existingVoucher) {
                $unlocked[(string) $newLevel] = $existingVoucher->code;
                $profile->unlocked_coupons = $unlocked;
                $profile->save();
                return $rewards[$newLevel]['name'] . ' freigeschaltet!';
            }

            $rewardConfig = $rewards[$newLevel];
            $code = $this->generateUniqueMilestoneCode($newLevel);

            MarketingVoucher::create([
                'title'           => 'Level ' . $newLevel . ' Belohnung (' . $email . ')',
                'code'            => $code,
                'recipient_email' => $email,
                'type'            => 'percent',
                'value'           => $rewardConfig['value'],
                'usage_limit'     => 1,
                'is_active'       => true,
                'valid_from'      => now(),
                'mode'            => 'manual',
            ]);

            $unlocked[(string) $newLevel] = $code;
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

        DB::transaction(function () use ($profile) {
            $lockedProfile = CustomerGamification::where('id', $profile->id)->lockForUpdate()->first();
            if (!$lockedProfile || !$lockedProfile->is_active) {
                return;
            }

            $rewards = GameConfig::getLevelRewards();
            $unlocked = is_array($lockedProfile->unlocked_coupons) ? $lockedProfile->unlocked_coupons : [];
            $changed = false;
            $customer = $lockedProfile->customer ?? ($lockedProfile->customer_id ? Customer::find($lockedProfile->customer_id) : null);
            if (!$customer || empty($customer->email)) {
                return;
            }
            $email = $customer->email;

            for ($i = 1; $i <= min(GameConfig::MAX_LEVEL, (int) $lockedProfile->level); $i++) {
                if (!isset($rewards[$i])) {
                    continue;
                }

                $existingCode = $unlocked[(string) $i] ?? $unlocked[$i] ?? null;

                if ($existingCode && MarketingVoucher::where('code', $existingCode)->exists()) {
                    continue;
                }

                $dbVoucher = MarketingVoucher::where('recipient_email', $email)
                    ->where('code', 'like', 'FUNKI-L' . $i . '-%')
                    ->first();

                if ($dbVoucher) {
                    $unlocked[(string) $i] = $dbVoucher->code;
                    $changed = true;
                    continue;
                }

                $rewardConfig = $rewards[$i];
                $code = $this->generateUniqueMilestoneCode($i);

                MarketingVoucher::create([
                    'title'           => 'Nachgereicht: Level ' . $i . ' Belohnung (' . $email . ')',
                    'code'            => $code,
                    'recipient_email' => $email,
                    'type'            => 'percent',
                    'value'           => $rewardConfig['value'],
                    'usage_limit'     => 1,
                    'is_active'       => true,
                    'valid_from'      => now(),
                    'mode'            => 'manual',
                ]);

                $unlocked[(string) $i] = $code;
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

        DB::transaction(function () use ($customer, $amount, $source) {
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

            $this->syncLevelFromLifetimeSparks($profile);

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

        DB::transaction(function () use ($order) {
            $this->ensureHangarColumns();
            $profile = CustomerGamification::where('customer_id', $order->customer_id)->lockForUpdate()->first();
            if (!$profile) {
                $profile = CustomerGamification::create([
                    'customer_id'         => $order->customer_id,
                    'is_active'           => false,
                    'level'               => 1,
                    'funken_balance'      => 0,
                    'funken_total_earned' => 0,
                    'titles_progress'     => $this->getInitialTitlesProgress(),
                ]);
                $profile = CustomerGamification::where('id', $profile->id)->lockForUpdate()->first() ?? $profile;
            }

            $progress = is_array($profile->titles_progress) && !empty($profile->titles_progress)
                ? $profile->titles_progress
                : $this->getInitialTitlesProgress();

            $rewardedIds = is_array($progress['_rewarded_order_ids'] ?? null) ? $progress['_rewarded_order_ids'] : [];
            if (in_array((string) $order->id, $rewardedIds, true)) {
                return;
            }

            $orderSparks = $this->calculateOrderSparks($order);
            $rewardedIds[] = (string) $order->id;
            $progress['_rewarded_order_ids'] = array_values(array_unique($rewardedIds));

            $rewardedAmounts = is_array($progress['_rewarded_order_amounts'] ?? null) ? $progress['_rewarded_order_amounts'] : [];
            $rewardedAmounts[(string) $order->id] = $orderSparks;
            $progress['_rewarded_order_amounts'] = $rewardedAmounts;

            $profile->funken_balance += $orderSparks;
            $profile->funken_total_earned += $orderSparks;
            $this->syncLevelFromLifetimeSparks($profile);

            $progress['funkenkoenig'] = (int) $profile->funken_total_earned;
            $profile->titles_progress = $progress;
            $profile->save();

            $this->logGamificationEvent(
                (string) $order->customer_id,
                'order_reward',
                'purchase',
                'Bestell-Funken gutgeschrieben (#' . ($order->order_number ?: $order->id) . ')',
                '+' . $orderSparks . ' Seelenfunken für deinen Einkauf erhalten (Funki Level ' . $profile->level . ').',
                $orderSparks,
                (int) $profile->funken_balance,
                ['order_id' => (string) $order->id, 'sparks' => $orderSparks, 'level' => (int) $profile->level]
            );

            $this->syncDynamicTitles($profile);
        });
    }

    public function reverseOrderFunken(OrderOrder $order): void
    {
        if (!$order->customer_id) {
            return;
        }

        DB::transaction(function () use ($order) {
            $this->ensureHangarColumns();
            $profile = CustomerGamification::where('customer_id', $order->customer_id)->lockForUpdate()->first();
            if (!$profile) {
                return;
            }

            $progress = is_array($profile->titles_progress) && !empty($profile->titles_progress)
                ? $profile->titles_progress
                : $this->getInitialTitlesProgress();

            $rewardedIds = is_array($progress['_rewarded_order_ids'] ?? null) ? $progress['_rewarded_order_ids'] : [];
            if (in_array((string) $order->id, $rewardedIds, true)) {
                $rewardedAmounts = is_array($progress['_rewarded_order_amounts'] ?? null) ? $progress['_rewarded_order_amounts'] : [];
                $orderSparks = isset($rewardedAmounts[(string) $order->id])
                    ? (int) $rewardedAmounts[(string) $order->id]
                    : $this->calculateOrderSparks($order);

                $profile->funken_balance = max(0, (int) $profile->funken_balance - $orderSparks);
                $profile->funken_total_earned = max(0, (int) $profile->funken_total_earned - $orderSparks);
                $this->syncLevelFromLifetimeSparks($profile, true);

                unset($rewardedAmounts[(string) $order->id]);
                $progress['_rewarded_order_amounts'] = $rewardedAmounts;

                $reversedAmounts = is_array($progress['_reversed_order_amounts'] ?? null) ? $progress['_reversed_order_amounts'] : [];
                $reversedAmounts[(string) $order->id] = $orderSparks;
                $progress['_reversed_order_amounts'] = $reversedAmounts;

                $progress['funkenkoenig'] = (int) $profile->funken_total_earned;
                $progress['_rewarded_order_ids'] = array_values(array_diff($rewardedIds, [(string) $order->id]));
                $profile->titles_progress = $progress;
                $profile->save();

                $this->logGamificationEvent(
                    (string) $order->customer_id,
                    'order_reversal',
                    'purchase',
                    'Bestell-Funken storniert (#' . ($order->order_number ?: $order->id) . ')',
                    '-' . $orderSparks . ' Seelenfunken wegen Stornierung/Erstattung abgezogen.',
                    -$orderSparks,
                    (int) $profile->funken_balance,
                    ['order_id' => (string) $order->id, 'sparks' => $orderSparks]
                );
            }

            $this->syncDynamicTitles($profile);
        });
    }

    /**
     * Belohnt den Kunden für eine ehrliche, freigegebene Produktbewertung mit Seelenfunken.
     * Manipulationssicher: Pro gekauftem Produkt (product_id) kann die Belohnung nur einmalig
     * gutgeschrieben werden (kein Farming durch Löschen und Neu-Schreiben).
     */
    public function rewardReviewFunken(ProductReview $review): int
    {
        if (!$review->customer_id || !$review->product_id || $review->status !== 'approved') {
            return 0;
        }

        return DB::transaction(function () use ($review) {
            $this->ensureHangarColumns();
            $profile = CustomerGamification::where('customer_id', $review->customer_id)->lockForUpdate()->first();
            if (!$profile) {
                $profile = CustomerGamification::create([
                    'customer_id'         => $review->customer_id,
                    'is_active'           => false,
                    'level'               => 1,
                    'funken_balance'      => 0,
                    'funken_total_earned' => 0,
                    'titles_progress'     => $this->getInitialTitlesProgress(),
                ]);
                $profile = CustomerGamification::where('id', $profile->id)->lockForUpdate()->first() ?? $profile;
            }

            $progress = is_array($profile->titles_progress) && !empty($profile->titles_progress)
                ? $profile->titles_progress
                : $this->getInitialTitlesProgress();

            $rewardedProductIds = is_array($progress['_rewarded_review_product_ids'] ?? null)
                ? $progress['_rewarded_review_product_ids']
                : [];
            $productKey = (string) $review->product_id;

            if (in_array($productKey, $rewardedProductIds, true)) {
                $this->syncDynamicTitles($profile);
                return 0;
            }

            $rewardSparks = GameConfig::REVIEW_SPARK_REWARD;
            $rewardedProductIds[] = $productKey;
            $progress['_rewarded_review_product_ids'] = array_values(array_unique($rewardedProductIds));

            $profile->funken_balance += $rewardSparks;
            $profile->funken_total_earned += $rewardSparks;
            $this->syncLevelFromLifetimeSparks($profile);

            $progress['funkenkoenig'] = (int) $profile->funken_total_earned;
            $progress['botschafter'] = ProductReview::where('customer_id', $review->customer_id)
                ->where('status', 'approved')
                ->count();
            $profile->titles_progress = $progress;
            $profile->save();

            $this->logGamificationEvent(
                (string) $review->customer_id,
                'review_reward',
                'review',
                'Belohnung für ehrliche Produktbewertung',
                '+' . $rewardSparks . ' Seelenfunken als Dankeschön für deine verifizierte Bewertung erhalten.',
                $rewardSparks,
                (int) $profile->funken_balance,
                ['review_id' => (string) $review->id, 'product_id' => $productKey, 'sparks' => $rewardSparks]
            );

            $this->syncDynamicTitles($profile);

            return $rewardSparks;
        });
    }

    /**
     * Zieht die Bewertungs-Funken wieder ab, falls eine belohnte Bewertung gelöscht oder abgelehnt wird.
     */
    public function reverseReviewFunken(ProductReview $review): void
    {
        if (!$review->customer_id || !$review->product_id) {
            return;
        }

        DB::transaction(function () use ($review) {
            $this->ensureHangarColumns();
            $profile = CustomerGamification::where('customer_id', $review->customer_id)->lockForUpdate()->first();
            if (!$profile) {
                return;
            }

            $progress = is_array($profile->titles_progress) && !empty($profile->titles_progress)
                ? $profile->titles_progress
                : $this->getInitialTitlesProgress();

            $rewardedProductIds = is_array($progress['_rewarded_review_product_ids'] ?? null)
                ? $progress['_rewarded_review_product_ids']
                : [];
            $productKey = (string) $review->product_id;

            if (in_array($productKey, $rewardedProductIds, true)) {
                $rewardSparks = GameConfig::REVIEW_SPARK_REWARD;
                $oldBalance = (int) $profile->funken_balance;
                $fullyReversed = $oldBalance >= $rewardSparks;

                $profile->funken_balance = max(0, $oldBalance - $rewardSparks);
                $profile->funken_total_earned = max(0, (int) $profile->funken_total_earned - $rewardSparks);
                $this->syncLevelFromLifetimeSparks($profile, true);

                // Nur freigeben, wenn die 15 Funken auch vollständig vom Guthaben abgezogen werden konnten (verhindert Ausgeben -> Löschen -> Neu-Bewerten Exploit)
                if ($fullyReversed) {
                    $progress['_rewarded_review_product_ids'] = array_values(array_diff($rewardedProductIds, [$productKey]));
                }
                $progress['funkenkoenig'] = (int) $profile->funken_total_earned;
                $profile->titles_progress = $progress;
                $profile->save();

                $this->logGamificationEvent(
                    (string) $review->customer_id,
                    'review_reversal',
                    'review',
                    'Bewertungs-Funken zurückgebucht',
                    '-' . min($oldBalance, $rewardSparks) . ' Seelenfunken wegen Löschung/Ablehnung der Bewertung abgezogen.',
                    -min($oldBalance, $rewardSparks),
                    (int) $profile->funken_balance,
                    ['review_id' => (string) $review->id, 'product_id' => $productKey]
                );
            }

            $this->syncDynamicTitles($profile);
        });
    }

    /**
     * Admin-Verwaltung für die Benutzerverwaltung (/admin/user-management):
     * Erlaubt das sichere Anpassen von Funken-Guthaben, Lebenszeit-Funken, Funki-Level (1-10),
     * Aktiv-Status sowie der DSGVO-Haken (Halle der Helden Opt-In, Echtname/Spielername).
     */
    public function adminAdjustCustomerGamification(
        Customer $customer,
        int $funkenBalance,
        int $funkenTotalEarned,
        int $level,
        bool $isActive,
        ?bool $rankingOptIn = null,
        ?bool $useRealName = null,
        ?string $playerName = null
    ): CustomerGamification {
        return DB::transaction(function () use (
            $customer,
            $funkenBalance,
            $funkenTotalEarned,
            $level,
            $isActive,
            $rankingOptIn,
            $useRealName,
            $playerName
        ) {
            $profile = $this->getProfile($customer);
            $locked = CustomerGamification::where('id', $profile->id)->lockForUpdate()->first() ?? $profile;

            $oldBalance = (int) $locked->funken_balance;
            $locked->funken_balance = max(0, $funkenBalance);
            $locked->funken_total_earned = max(0, $funkenTotalEarned);
            $locked->level = min(GameConfig::MAX_LEVEL, max(1, $level));
            $locked->is_active = $isActive;

            if ($rankingOptIn !== null) {
                $locked->ranking_opt_in = $rankingOptIn;
            }
            if ($useRealName !== null) {
                $locked->use_real_name = $useRealName;
            }
            if ($playerName !== null) {
                $cleanPlayerName = trim(strip_tags($playerName));
                $locked->player_name = $cleanPlayerName !== '' ? mb_substr($cleanPlayerName, 0, 35) : null;
            }

            $progress = is_array($locked->titles_progress) && !empty($locked->titles_progress)
                ? $locked->titles_progress
                : $this->getInitialTitlesProgress();
            $progress['funkenkoenig'] = (int) $locked->funken_total_earned;
            $locked->titles_progress = $progress;
            $locked->save();

            $delta = (int) $locked->funken_balance - $oldBalance;
            $this->logGamificationEvent(
                (string) $customer->id,
                'admin_adjustment',
                'admin',
                'Admin-Anpassung in der Benutzerverwaltung',
                'Funken-Guthaben: ' . $locked->funken_balance . ' ✨ | Gesamt: ' . $locked->funken_total_earned . ' ✨ | Funki-Level: ' . $locked->level . ' | Halle der Helden: ' . ($locked->ranking_opt_in ? 'Ja' : 'Nein'),
                $delta,
                (int) $locked->funken_balance,
                [
                    'level'               => (int) $locked->level,
                    'funken_balance'      => (int) $locked->funken_balance,
                    'funken_total_earned' => (int) $locked->funken_total_earned,
                    'is_active'           => (bool) $locked->is_active,
                    'ranking_opt_in'      => (bool) $locked->ranking_opt_in,
                    'use_real_name'       => (bool) $locked->use_real_name,
                    'player_name'         => $locked->player_name,
                ]
            );

            return $locked;
        });
    }

    public function getCurrentImagePath(int $level): string
    {
        $cfg = GameConfig::getLevelConfig($level);
        return asset($cfg['image_path']);
    }

    public function getCurrentModelPath(int $level): string
    {
        return $this->getCurrentImagePath($level);
    }

    public function incrementTitleProgress(CustomerGamification $profile, string $titleId, int $amount = 1): void
    {
        if (!$profile->is_active) {
            return;
        }

        $progress = $profile->titles_progress;
        if (!is_array($progress) || empty($progress)) {
            $progress = $this->getInitialTitlesProgress();
        }
        if (!array_key_exists($titleId, $progress)) {
            $progress[$titleId] = 0;
        }

        $progress[$titleId] += $amount;
        $profile->update(['titles_progress' => $progress]);
    }

    public function syncDynamicTitles(CustomerGamification $profile): void
    {
        $customer = $profile->customer;
        if (!$customer) {
            return;
        }

        $progress = $profile->titles_progress;
        if (!is_array($progress)) {
            $progress = $this->getInitialTitlesProgress();
        }
        $tz = config('app.timezone', 'Europe/Berlin');

        $reviews = ProductReview::where('customer_id', $customer->id)->where('status', 'approved')->get();
        $progress['botschafter'] = $reviews->count();
        $progress['treuer_bewerter'] = $reviews->where('rating', 5)->count();
        $progress['bildreporter'] = $reviews->filter(fn($r) => !empty($r->media))->count();
        $progress['wortgewandt'] = $reviews->filter(fn($r) => mb_strlen($r->content ?? '') >= 100)->count();

        $orders = OrderOrder::where('customer_id', $customer->id)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->with('items.product.categories')
            ->get();
        $progress['schatzhueter'] = $orders->count();
        $progress['massenkaeufer'] = (int) $orders->flatMap(fn($o) => $o->items)->sum('quantity');

        $progress['freudebringer'] = $orders->filter(function ($order) {
            if (isset($order->is_different_shipping_address) && $order->is_different_shipping_address) {
                return true;
            }
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
            if (!$o->created_at) {
                return false;
            }
            $hour = (int) $o->created_at->copy()->timezone($tz)->hour;
            return $hour >= 22 || $hour <= 4;
        })->count();

        $progress['wochenend_shopper'] = $orders->filter(fn($o) => $o->created_at && $o->created_at->copy()->timezone($tz)->isWeekend())->count();
        $progress['wiederholungstaeter'] = $orders->filter(fn($o) => (bool) $o->created_at)->map(fn($o) => $o->created_at->format('Y-m'))->unique()->count();
        $progress['treue_seele'] = max(0, $customer->created_at ? (int) floor($customer->created_at->diffInDays(now())) : 0);
        $progress['funkenkoenig'] = (int) ($profile->funken_total_earned ?? 0);

        $updatePayload = ['titles_progress' => $progress];
        if ($profile->active_title) {
            $configs = GameConfig::getTitles();
            $activeKey = (string) $profile->active_title;
            $currentLevel = min(GameConfig::MAX_LEVEL, max(1, (int) ($profile->level ?: 1)));

            if ($activeKey === 'seelengott') {
                $allDiamonds = true;
                foreach ($configs as $key => $cfg) {
                    $maxReq = (int) ($cfg['tiers']['diamant']['req'] ?? PHP_INT_MAX);
                    if ((int) ($progress[$key] ?? 0) < $maxReq) {
                        $allDiamonds = false;
                        break;
                    }
                }
                if (!$allDiamonds || $currentLevel < GameConfig::MAX_LEVEL) {
                    $updatePayload['active_title'] = null;
                }
            } elseif (str_starts_with($activeKey, 'level_')) {
                $lvl = (int) str_replace('level_', '', $activeKey);
                if ($lvl < 1 || $lvl > $currentLevel || !isset(GameConfig::getLevelTitles()[$lvl])) {
                    $updatePayload['active_title'] = null;
                }
            } else {
                $minReq = $configs[$activeKey]['tiers']['silber']['req'] ?? PHP_INT_MAX;
                if (!isset($configs[$activeKey]) || (int) ($progress[$activeKey] ?? 0) < $minReq) {
                    $updatePayload['active_title'] = null;
                }
            }
        }

        $profile->update($updatePayload);
    }

    public function evaluateTitles(CustomerGamification $profile): array
    {
        $this->syncDynamicTitles($profile);
        $freshProfile = $profile->fresh() ?? $profile;
        $progress = $freshProfile->titles_progress ?? $this->getInitialTitlesProgress();
        $currentLevel = min(GameConfig::MAX_LEVEL, max(1, (int) ($freshProfile->level ?: 1)));

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
                'key'             => $key,
                'name'            => $config['name'],
                'description'     => $config['description'],
                'icon'            => $config['icon'] ?? '🏆',
                'current_value'   => $currentValue,
                'tier'            => $currentTier,
                'tier_name'       => $currentTier === 'grau' ? 'Noch gesperrt' : $config['tiers'][$currentTier]['name'],
                'next_req'        => $nextReq,
                'next_tier_name'  => $nextTierName,
                'next_tier_label' => $nextTierLabel,
                'max_req'         => $maxReq,
                'percentage'      => $percentage,
            ];
        }

        $levelTitles = [];
        foreach (GameConfig::getLevelTitles() as $lvl => $lvlCfg) {
            $levelTitles[$lvl] = [
                'level'    => $lvl,
                'key'      => $lvlCfg['key'],
                'name'     => $lvlCfg['name'],
                'subtitle' => $lvlCfg['subtitle'],
                'unlocked' => $currentLevel >= $lvl,
            ];
        }

        $isSeelengott = $diamondsCount >= count($configs)
            && ($currentLevel >= GameConfig::MAX_LEVEL || (int) ($progress['funkenkoenig'] ?? 0) >= 5000);

        $megaTitles = GameConfig::getMegaTitles();
        $currentMegaTitle = null;
        $nextMegaTitle = $megaTitles[0] ?? null;

        foreach ($megaTitles as $index => $mega) {
            if ($diamondsCount >= $mega['req']) {
                if ($mega['req'] >= count($configs) && !$isSeelengott) {
                    $nextMegaTitle = $mega;
                    break;
                }
                $currentMegaTitle = $mega;
                $currentMegaTitle['rank'] = $index + 1;
                $nextMegaTitle = $megaTitles[$index + 1] ?? null;
            }
        }

        return [
            'titles'          => $evaluated,
            'level_titles'    => $levelTitles,
            'current_level'   => $currentLevel,
            'diamonds_count'  => $diamondsCount,
            'diamond_count'   => $diamondsCount,
            'total_titles'    => count($configs),
            'mega_title'      => $currentMegaTitle,
            'next_mega_title' => $nextMegaTitle,
            'is_seelengott'   => $isSeelengott,
        ];
    }

    public function resolveActiveRankName(CustomerGamification $profile, ?array $titlesData = null): ?string
    {
        if (!$profile->is_active || empty($profile->active_title)) {
            return null;
        }

        $titlesData = $titlesData ?? $this->evaluateTitles($profile);
        $activeKey = (string) $profile->active_title;

        if ($activeKey === 'seelengott') {
            return !empty($titlesData['is_seelengott']) ? GameConfig::ULTIMATE_TITLE_NAME : null;
        }

        if (str_starts_with($activeKey, 'level_')) {
            $lvl = (int) str_replace('level_', '', $activeKey);
            $lvlTitle = $titlesData['level_titles'][$lvl] ?? null;
            if ($lvlTitle && !empty($lvlTitle['unlocked'])) {
                return $lvlTitle['name'];
            }
            return null;
        }

        $titleInfo = $titlesData['titles'][$activeKey] ?? null;
        if (!$titleInfo || ($titleInfo['tier'] ?? 'grau') === 'grau') {
            return null;
        }

        return $titleInfo['tier_name'] ?? null;
    }

    /**
     * Erlaubt dem Kunden im Dashboard / Funki-Modal einen freigeschalteten Titel
     * (aus den 5 Kern-Titeln, den Level 1-10 Titeln oder "Seelengott unter Sterblichen") aktiv auszuwählen.
     */
    public function selectCustomerTitle(Customer $customer, ?string $titleKey): array
    {
        $profile = $this->getProfile($customer);
        if (!$profile->is_active) {
            return ['success' => false, 'message' => 'Bitte aktiviere zuerst das Seelenfunken-Sammelspiel.'];
        }

        if ($titleKey === null || $titleKey === '') {
            $profile->update(['active_title' => null]);
            return ['success' => true, 'rank_name' => null, 'message' => 'Titel-Auswahl zurückgesetzt.'];
        }

        $titlesData = $this->evaluateTitles($profile);

        if ($titleKey === 'seelengott') {
            if (empty($titlesData['is_seelengott'])) {
                return ['success' => false, 'message' => 'Der Titel "' . GameConfig::ULTIMATE_TITLE_NAME . '" erfordert Level 10 und alle 5 Titel auf Max-Stufe (Diamant).'];
            }
            $profile->update(['active_title' => 'seelengott']);
            return ['success' => true, 'rank_name' => GameConfig::ULTIMATE_TITLE_NAME, 'message' => 'Göttlicher Rang "' . GameConfig::ULTIMATE_TITLE_NAME . '" ausgerüstet!'];
        }

        if (str_starts_with($titleKey, 'level_')) {
            $lvl = (int) str_replace('level_', '', $titleKey);
            $lvlInfo = $titlesData['level_titles'][$lvl] ?? null;
            if (!$lvlInfo || empty($lvlInfo['unlocked'])) {
                return ['success' => false, 'message' => 'Dieser Level-Titel ist noch nicht freigeschaltet.'];
            }
            $profile->update(['active_title' => $titleKey]);
            return ['success' => true, 'rank_name' => $lvlInfo['name'], 'message' => 'Level-Titel "' . $lvlInfo['name'] . '" ausgerüstet!'];
        }

        $titleInfo = $titlesData['titles'][$titleKey] ?? null;
        if (!$titleInfo || ($titleInfo['tier'] ?? 'grau') === 'grau') {
            return ['success' => false, 'message' => 'Dieser Titel ist noch gesperrt (mindestens Silber-Stufe erforderlich).'];
        }

        $profile->update(['active_title' => $titleKey]);
        return ['success' => true, 'rank_name' => $titleInfo['tier_name'], 'message' => 'Titel "' . $titleInfo['tier_name'] . '" ausgerüstet!'];
    }

    /**
     * Aktualisiert die Einstellungen für die Halle der Helden (DSGVO-Opt-In, Spielername vs. Echtname)
     * inklusive weltweiter Namensprüfung (Gewalt, NS/Extremismus, Hetze, Mindestlänge 3-20 Zeichen & Eindeutigkeit).
     */
    public function updateHallOfFameSettings(
        Customer $customer,
        bool $rankingOptIn,
        bool $useRealName,
        ?string $playerName = null
    ): array {
        $this->ensureHangarColumns();
        $profile = $this->getProfile($customer);

        $rawPlayerName = trim(strip_tags((string) $playerName));
        $rawPlayerName = preg_replace('/\s+/u', ' ', $rawPlayerName ?? '') ?: '';

        if ($rankingOptIn && !$useRealName && $rawPlayerName === '') {
            return [
                'success' => false,
                'message' => 'Bitte gib einen Spielernamen (mindestens 3 bis 20 Zeichen) ein oder wähle die Anzeige deines Echtnamens.',
            ];
        }

        $validatedPlayerName = null;
        if ($rawPlayerName !== '') {
            $validation = PlayerNameValidator::validate($rawPlayerName, (string) $customer->id, false);
            if (empty($validation['valid'])) {
                return [
                    'success' => false,
                    'message' => $validation['error'] ?? 'Dieser Spielername ist nicht zulässig.',
                ];
            }
            $validatedPlayerName = $validation['sanitized'];
        }

        $oldRankingOptIn = (bool) $profile->ranking_opt_in;
        $oldUseRealName = (bool) $profile->use_real_name;
        $oldPlayerName = (string) ($profile->player_name ?? '');

        $profile->ranking_opt_in = $rankingOptIn;
        $profile->use_real_name = $useRealName;
        $profile->player_name = $validatedPlayerName;
        $profile->save();

        $hofChanges = [];
        if ($oldRankingOptIn !== (bool) $profile->ranking_opt_in) {
            $hofChanges['ranking_opt_in'] = ['old' => $oldRankingOptIn, 'new' => (bool) $profile->ranking_opt_in];
        }
        if ($oldUseRealName !== (bool) $profile->use_real_name) {
            $hofChanges['use_real_name'] = ['old' => $oldUseRealName, 'new' => (bool) $profile->use_real_name];
        }
        if ($oldPlayerName !== (string) ($profile->player_name ?? '')) {
            $hofChanges['player_name'] = ['old' => $oldPlayerName, 'new' => (string) ($profile->player_name ?? '')];
        }
        if (!empty($hofChanges)) {
            $this->recordUserProfileChangeLog($customer, $hofChanges, 'Kunde (im Frontend)');
        }

        return [
            'success'        => true,
            'ranking_opt_in' => (bool) $profile->ranking_opt_in,
            'use_real_name'  => (bool) $profile->use_real_name,
            'player_name'    => $profile->player_name,
            'message'        => $rankingOptIn
                ? 'Halle der Helden aktualisiert! Du wirst als „' . $this->resolvePublicDisplayName($customer, $profile) . '“ angezeigt. ✨'
                : 'Deine Einstellungen wurden gespeichert (Sichtbarkeit in der Halle der Helden deaktiviert).',
        ];
    }

    /**
     * Liest die gespeicherten Audio-Einstellungen (Lautstärke 0-100, Stummschaltung & „Musik laufen lassen“) für das Funki-Modal.
     */
    public function getAudioSettings(?CustomerGamification $profile = null): array
    {
        if (!$profile || !is_array($profile->titles_progress)) {
            return [
                'volume'       => 35,
                'muted'        => true,
                'keep_playing' => false,
            ];
        }

        $progress = $profile->titles_progress;
        $volume = isset($progress['_audio_volume']) ? max(0, min(100, (int) $progress['_audio_volume'])) : 35;
        $muted = array_key_exists('_audio_muted', $progress) ? (bool) $progress['_audio_muted'] : true;
        $keepPlaying = !empty($progress['_audio_keep_playing']);

        return [
            'volume'       => $volume,
            'muted'        => $muted,
            'keep_playing' => $keepPlaying,
        ];
    }

    /**
     * Speichert die Audio-Einstellungen (Lautstärke, Stummschaltung & „Musik laufen lassen“) dauerhaft in den Kundeneinstellungen.
     */
    public function updateAudioSettings(Customer $customer, int $volumePercent, bool $isMuted, ?bool $keepPlaying = null): array
    {
        $clampedVolume = max(0, min(100, $volumePercent));

        return DB::transaction(function () use ($customer, $clampedVolume, $isMuted, $keepPlaying) {
            $profile = $this->getProfile($customer);
            $locked = CustomerGamification::where('id', $profile->id)->lockForUpdate()->first() ?? $profile;

            $progress = is_array($locked->titles_progress) && !empty($locked->titles_progress)
                ? $locked->titles_progress
                : $this->getInitialTitlesProgress();

            $progress['_audio_volume'] = $clampedVolume;
            $progress['_audio_muted'] = $isMuted;
            if ($keepPlaying !== null) {
                $progress['_audio_keep_playing'] = $keepPlaying;
            }
            $locked->titles_progress = $progress;
            $locked->save();

            return [
                'success'      => true,
                'volume'       => $clampedVolume,
                'muted'        => $isMuted,
                'keep_playing' => (bool) ($progress['_audio_keep_playing'] ?? false),
            ];
        });
    }

    /**
     * Ermittelt den öffentlichen Anzeigenamen (Spielername oder Echtname Vorname + Initial).
     */
    public function resolvePublicDisplayName(?Customer $customer, ?CustomerGamification $profile = null): string
    {
        if (!$customer) {
            return 'Seelenfunke-Mitglied';
        }

        $realNameFormatted = trim($customer->first_name . ($customer->last_name ? ' ' . mb_substr($customer->last_name, 0, 1) . '.' : ''));
        if ($realNameFormatted === '') {
            $realNameFormatted = 'Seelenfunke-Mitglied';
        }

        if (!$profile) {
            return $realNameFormatted;
        }

        if (!$profile->use_real_name && !empty(trim((string) $profile->player_name))) {
            return trim((string) $profile->player_name);
        }

        return $realNameFormatted;
    }

    /**
     * Erzeugt die Profil-Darstellungsdaten eines Kunden für das öffentliche Profil-Modal (z. B. bei Produktbewertungen)
     * sowie für die Halle der Helden.
     */
    public function getPublicProfileCardData(Customer $customer, ?CustomerGamification $profile = null): array
    {
        $this->ensureHangarColumns();
        $rawProfile = $profile ?? $customer->gamification ?? CustomerGamification::where('customer_id', $customer->id)->first();
        $activeProfile = ($rawProfile && $rawProfile->is_active) ? $rawProfile : null;

        $level = $activeProfile
            ? min(GameConfig::MAX_LEVEL, max(1, (int) ($activeProfile->level ?: GameConfig::getLevelForLifetimeSparks((int) $activeProfile->funken_total_earned))))
            : 1;

        $displayLevel = $level;
        if ($activeProfile) {
            $activeBg = (string) ($activeProfile->active_background ?? 'default');
            if (str_starts_with($activeBg, 'stage_')) {
                $chosen = (int) str_replace('stage_', '', $activeBg);
                if ($chosen >= 1 && $chosen <= $level) {
                    $displayLevel = $chosen;
                }
            }
        }

        $lvlCfg = GameConfig::getLevelConfig($displayLevel);
        $titlesData = $activeProfile ? $this->evaluateTitles($activeProfile) : null;
        $defaultRankName = GameConfig::getLevelTitles()[$level]['name'] ?? 'Kleiner Seelenfunke';
        $activeRankName = ($activeProfile && $titlesData)
            ? ($this->resolveActiveRankName($activeProfile, $titlesData) ?: $defaultRankName)
            : $defaultRankName;

        // Eigenes Profilfoto nur ausliefern, wenn kein aktives Pseudonym (use_real_name = false + player_name) genutzt wird
        $usesPseudonym = $activeProfile && !$activeProfile->use_real_name && !empty(trim((string) $activeProfile->player_name));
        $customPhotoUrl = null;
        if (!$usesPseudonym && $customer->profile && !empty($customer->profile->photo_path)) {
            $pp = $customer->profile->photo_path;
            $customPhotoUrl = (str_starts_with($pp, 'shopverwaltung/images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/'))
                ? asset($pp)
                : (\Illuminate\Support\Facades\Storage::url($pp));
        }

        return [
            'display_name'        => $this->resolvePublicDisplayName($customer, $activeProfile),
            'level'               => $level,
            'display_level'       => $displayLevel,
            'funki_name'          => $lvlCfg['name'] ?? ('Funki Level ' . $displayLevel),
            'badge'               => $lvlCfg['badge'] ?? ('STUFE ' . $displayLevel),
            'bg_name'             => $lvlCfg['bg_name'] ?? 'Stilles Manufaktur-Atelier',
            'bg_subtitle'         => $lvlCfg['bg_subtitle'] ?? '',
            'image_url'           => asset($lvlCfg['image_path'] ?? 'shop/customer/gamification/models/images/original/funki_lvl_1_rags.png'),
            'video_url'           => !empty($lvlCfg['video_url']) ? asset(ltrim($lvlCfg['video_url'], '/')) : null,
            'video_filter_css'    => $lvlCfg['video_filter_css'] ?? 'none',
            'video_transform_css' => $lvlCfg['video_transform_css'] ?? 'scale(1.0)',
            'circle_css'          => $lvlCfg['circle_css'] ?? '',
            'accent_hex'          => $lvlCfg['accent_hex'] ?? '#c5a059',
            'custom_photo_url'    => $customPhotoUrl,
            'rank_name'           => $activeRankName,
            'is_seelengott'       => !empty($titlesData['is_seelengott']),
            'diamonds_count'      => (int) ($titlesData['diamonds_count'] ?? 0),
            'total_titles'        => (int) ($titlesData['total_titles'] ?? 5),
            'funken_total_earned' => (int) ($activeProfile?->funken_total_earned ?? 0),
            'member_since'        => $customer->created_at ? $customer->created_at->format('m/Y') : '',
        ];
    }

    /**
     * Liefert die Rangliste für die „Halle der Helden“ (nur aktive Kunden mit DSGVO-Opt-In).
     */
    public function getHallOfFameLeaders(int $limit = 30): array
    {
        return $this->getHallOfFamePaginated(1, $limit)['items'];
    }

    /**
     * Liefert die paginierte Rangliste für die „Halle der Helden“ (nur aktive Kunden mit DSGVO-Opt-In).
     */
    public function getHallOfFamePaginated(int $page = 1, int $perPage = 10): array
    {
        $this->ensureHangarColumns();

        $perPage = max(1, min(50, $perPage));
        $baseQuery = CustomerGamification::with([
                'customer:id,first_name,last_name,created_at',
                'customer.profile:id,customer_id,photo_path',
            ])
            ->where('is_active', true)
            ->where('ranking_opt_in', true)
            ->whereHas('customer')
            ->orderByDesc('funken_total_earned')
            ->orderByDesc('level')
            ->orderBy('id');

        $total = (int) (clone $baseQuery)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = max(1, min($lastPage, $page));

        $profiles = $baseQuery
            ->skip(($currentPage - 1) * $perPage)
            ->take($perPage)
            ->get();

        $currentCustomerId = Auth::guard('customer')->id();
        $leaders = [];
        $offset = ($currentPage - 1) * $perPage;

        foreach ($profiles as $index => $profile) {
            if (!$profile->customer) {
                continue;
            }
            $card = $this->getPublicProfileCardData($profile->customer, $profile);
            $card['rank_position'] = $offset + $index + 1;
            $card['is_current_user'] = $currentCustomerId && (string) $currentCustomerId === (string) $profile->customer_id;
            $leaders[] = $card;
        }

        return [
            'items'        => $leaders,
            'current_page' => $currentPage,
            'last_page'    => $lastPage,
            'total'        => $total,
            'per_page'     => $perPage,
        ];
    }

    public function getInitialTitlesProgress(): array
    {
        $progress = [];
        foreach (array_keys(GameConfig::getTitles()) as $key) {
            $progress[$key] = 0;
        }
        return $progress;
    }

    public const ANNUAL_BIRTHDAY_SPARKS = 50;
    public const ANNUAL_ANNIVERSARY_SPARKS = 50;

    /**
     * Protokolliert Profil-, Adress-, Geburtsdatums- und DSGVO-Änderungen eines Kunden im SystemLog.
     */
    public function recordUserProfileChangeLog(
        Customer $customer,
        array $changes,
        string $actor = 'Kunde (im Frontend)',
        string $actionId = 'user:profile_updated_frontend',
        string $title = 'Benutzerprofil selbstständig aktualisiert'
    ): void {
        if (empty($changes)) {
            return;
        }

        try {
            \App\Models\System\SystemLog::create([
                'type'        => 'system',
                'action_id'   => $actionId,
                'title'       => $title,
                'message'     => "Das Profil von '{$customer->email}' wurde von {$actor} geändert (" . count($changes) . " Feld(er)).",
                'status'      => 'success',
                'payload'     => [
                    'actor'       => $actor,
                    'user_id'     => (string) $customer->id,
                    'target_user' => (string) $customer->email,
                    'changes'     => $changes,
                    'ip'          => request()?->ip(),
                ],
                'started_at'  => now(),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Nicht-blockierend falls SystemLog in Minimal-Tests nicht geladen ist
        }
    }

    /**
     * Menschenlesbare Feldbezeichnungen für das Admin-Änderungsprotokoll.
     */
    public static function getProfileFieldLabels(): array
    {
        return [
            'first_name'          => 'Vorname',
            'last_name'           => 'Nachname',
            'email'               => 'E-Mail-Adresse',
            'birthday'            => 'Geburtsdatum',
            'phone_number'        => 'Telefonnummer',
            'street'              => 'Straße',
            'house_number'        => 'Hausnummer',
            'postal'              => 'PLZ',
            'city'                => 'Stadt',
            'country'             => 'Land',
            'customer_type'       => 'Kundentyp',
            'is_business'         => 'Gewerblich (B2B)',
            'company_name'        => 'Firmenname',
            'vat_id'              => 'USt-IdNr.',
            'internal_note'       => 'Interne Admin-Notiz',
            'is_verified'         => 'E-Mail verifiziert',
            'about'               => 'Über mich',
            'url'                 => 'Webseite',
            'password'            => 'Passwort',
            'gamification_active' => 'DSGVO: Seelenfunken-Treueprogramm',
            'is_active'           => 'DSGVO: Seelenfunken-Treueprogramm',
            'ranking_opt_in'      => 'DSGVO: Halle der Helden Opt-In',
            'use_real_name'       => 'DSGVO: Echtname in Halle der Helden',
            'player_name'                => 'Spielername (Halle der Helden)',
            'ai_data_consent'            => 'DSGVO: KI-Support-Freigabe',
            'receive_birthday_emails'    => 'E-Mail: 🎂 Geburtstags-Funke',
            'receive_anniversary_emails' => 'E-Mail: 🕯️ Kauf-Jubiläum (Vorfreude-Funken)',
        ];
    }

    /**
     * Formatiert einen einzelnen Feldwert im Log sauber für die Admin-Ansicht.
     */
    public static function formatProfileLogValue(string $field, mixed $val): string
    {
        if ($val === null || $val === '') {
            return '— (leer)';
        }
        if (is_bool($val)) {
            return $val ? 'Aktiviert (Ja)' : 'Deaktiviert (Nein)';
        }
        if (in_array($field, ['is_business', 'is_verified', 'gamification_active', 'is_active', 'ranking_opt_in', 'use_real_name', 'ai_data_consent', 'receive_birthday_emails', 'receive_anniversary_emails'], true)) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'Aktiviert (Ja)' : 'Deaktiviert (Nein)';
        }
        if ($field === 'birthday') {
            try {
                if (is_array($val) && isset($val['date'])) {
                    return \Carbon\Carbon::parse($val['date'])->format('d.m.Y');
                }
                return \Carbon\Carbon::parse((string) $val)->format('d.m.Y');
            } catch (\Throwable $e) {
                return (string) $val;
            }
        }
        if (is_array($val)) {
            return json_encode($val, JSON_UNESCAPED_UNICODE);
        }

        return (string) $val;
    }

    /**
     * Lädt das komplette Änderungsprotokoll (Profil, Adresse, Geburtsdatum, DSGVO) eines Benutzers für die Admin-Benutzerverwaltung.
     */
    public function getUserProfileChangeLogs(string $userId, ?string $email = null, int $limit = 60): array
    {
        try {
            if (!Schema::hasTable('system_logs')) {
                return [];
            }

            $logs = \App\Models\System\SystemLog::query()
                ->whereIn('action_id', ['user:profile_updated_frontend', 'user:update', 'user:create'])
                ->orderByDesc('created_at')
                ->limit(300)
                ->get();

            $labels = self::getProfileFieldLabels();
            $matched = [];

            foreach ($logs as $log) {
                $payload = is_array($log->payload) ? $log->payload : [];
                $logUserId = (string) ($payload['user_id'] ?? '');
                $logTargetEmail = mb_strtolower(trim((string) ($payload['target_user'] ?? '')));
                $checkEmail = $email ? mb_strtolower(trim($email)) : '';

                $isMatch = ($logUserId !== '' && $logUserId === (string) $userId)
                    || ($checkEmail !== '' && $logTargetEmail !== '' && $logTargetEmail === $checkEmail);

                if (!$isMatch) {
                    continue;
                }

                $rawChanges = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
                $formattedChanges = [];

                foreach ($rawChanges as $field => $diff) {
                    $oldRaw = is_array($diff) ? ($diff['old'] ?? null) : null;
                    $newRaw = is_array($diff) ? ($diff['new'] ?? null) : $diff;

                    $formattedChanges[] = [
                        'field'     => (string) $field,
                        'label'     => $labels[$field] ?? (string) $field,
                        'old'       => self::formatProfileLogValue((string) $field, $oldRaw),
                        'new'       => self::formatProfileLogValue((string) $field, $newRaw),
                        'old_raw'   => $oldRaw,
                        'new_raw'   => $newRaw,
                    ];
                }

                $actor = (string) ($payload['actor'] ?? ($log->action_id === 'user:profile_updated_frontend' ? 'Kunde (im Frontend)' : 'Admin'));
                $isCustomerActor = $log->action_id === 'user:profile_updated_frontend' || str_contains(mb_strtolower($actor), 'kunde');

                $matched[] = [
                    'id'                => (string) $log->id,
                    'action_id'         => (string) $log->action_id,
                    'title'             => (string) $log->title,
                    'actor'             => $actor,
                    'is_customer_actor' => $isCustomerActor,
                    'ip'                => $payload['ip'] ?? null,
                    'created_at'        => $log->created_at ? $log->created_at->format('d.m.Y H:i') : '',
                    'created_at_human'  => $log->created_at ? $log->created_at->diffForHumans() : '',
                    'changes'           => $formattedChanges,
                ];

                if (count($matched) >= $limit) {
                    break;
                }
            }

            return $matched;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Ermittelt anhand des Kunden-Änderungsprotokolls (SystemLog), wie oft der Kunde sein bereits gesetztes Geburtsdatum
     * selbstständig geändert/korrigiert hat (Erst-Eintrag von leer -> Datum zählt als 0 Korrekturen; 1 Tippfehler-Korrektur ist erlaubt).
     */
    public function countCustomerBirthdayCorrections(Customer $customer): int
    {
        $logs = $this->getUserProfileChangeLogs((string) $customer->id, $customer->email, 100);
        $corrections = 0;

        foreach ($logs as $entry) {
            if (empty($entry['is_customer_actor'])) {
                continue;
            }
            foreach ($entry['changes'] as $change) {
                if (($change['field'] ?? '') === 'birthday') {
                    $oldRaw = $change['old_raw'] ?? null;
                    $newRaw = $change['new_raw'] ?? null;
                    $oldStr = $this->normalizeBirthdayDate($oldRaw);
                    $newStr = $this->normalizeBirthdayDate($newRaw);

                    // Wenn vorher bereits ein Datum stand und der Kunde es auf ein anderes Datum geändert hat -> zählt als Korrektur
                    if ($oldStr !== '' && $newStr !== '' && $oldStr !== $newStr) {
                        $corrections++;
                    }
                }
            }
        }

        return $corrections;
    }

    /**
     * Prüft, ob der Kunde sein Geburtsdatum noch selbst ändern darf:
     * - Noch kein Geburtsdatum hinterlegt: Ja (Erst-Angabe)
     * - Geburtsdatum hinterlegt, aber laut Log noch 0 Korrekturen: Ja (1× Tippfehler-Korrektur erlaubt!)
     * - Laut Log bereits >= 1× vom Kunden korrigiert: Nein (ab dann gesperrt gegen Missbrauch)
     */
    public function canCustomerChangeBirthday(Customer $customer): bool
    {
        $currentBirthday = $this->normalizeBirthdayDate($customer->profile?->birthday);
        if ($currentBirthday === '') {
            return true;
        }

        return $this->countCustomerBirthdayCorrections($customer) < 1;
    }

    private function normalizeBirthdayDate(mixed $val): string
    {
        if (empty($val)) {
            return '';
        }
        try {
            if ($val instanceof \DateTimeInterface) {
                return $val->format('Y-m-d');
            }
            if (is_array($val) && !empty($val['date'])) {
                return \Carbon\Carbon::parse($val['date'])->format('Y-m-d');
            }
            return \Carbon\Carbon::parse((string) $val)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Speichert oder korrigiert das Geburtsdatum des Kunden (inkl. automatischer Log-Prüfung auf max. 1 Selbst-Korrektur).
     */
    public function updateCustomerBirthday(Customer $customer, ?string $birthdayInput): array
    {
        $normalizedNew = $this->normalizeBirthdayDate($birthdayInput);
        if ($normalizedNew === '') {
            return [
                'success' => false,
                'message' => 'Bitte gib ein gültiges Geburtsdatum ein.',
            ];
        }

        try {
            $parsed = \Carbon\Carbon::parse($normalizedNew)->startOfDay();
            if ($parsed->isFuture() || $parsed->age < 14 || $parsed->age > 115) {
                return [
                    'success' => false,
                    'message' => 'Bitte gib ein realistisches Geburtsdatum ein (Mindestalter 14 Jahre).',
                ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Ungültiges Datumsformat.',
            ];
        }

        $profileModel = $customer->profile ?: $customer->profile()->firstOrCreate([]);
        $oldBirthday = $this->normalizeBirthdayDate($profileModel->birthday);

        if ($oldBirthday === $normalizedNew) {
            return [
                'success'  => true,
                'birthday' => $normalizedNew,
                'message'  => 'Dein Geburtsdatum (' . \Carbon\Carbon::parse($normalizedNew)->format('d.m.Y') . ') ist gespeichert.',
            ];
        }

        // Wenn bereits ein Geburtsdatum gesetzt war, prüfe über das Kunden-Log, ob schon 1× korrigiert wurde
        if ($oldBirthday !== '' && !$this->canCustomerChangeBirthday($customer)) {
            return [
                'success' => false,
                'message' => 'Dein Geburtsdatum wurde laut Änderungsprotokoll bereits einmal korrigiert und kann aus Sicherheitsgründen nicht erneut geändert werden.',
            ];
        }

        $profileModel->birthday = $normalizedNew;
        $profileModel->save();
        $customer->setRelation('profile', $profileModel);

        $this->recordUserProfileChangeLog($customer, [
            'birthday' => [
                'old' => $oldBirthday ?: null,
                'new' => $normalizedNew,
            ],
        ], 'Kunde (im Frontend)');

        // Falls heute der Geburtstag ist und dieses Jahr noch kein Bonus vergeben wurde -> direkt prüfen
        $bonusCheck = $this->processAnnualLoyaltySparksForCustomer($customer, true);
        $wasCorrection = $oldBirthday !== '';

        $msg = $wasCorrection
            ? 'Geburtsdatum einmalig auf den ' . \Carbon\Carbon::parse($normalizedNew)->format('d.m.Y') . ' korrigiert! ✨'
            : 'Geburtsdatum (' . \Carbon\Carbon::parse($normalizedNew)->format('d.m.Y') . ') gespeichert! Du erhältst jedes Jahr zum Geburtstag +' . self::ANNUAL_BIRTHDAY_SPARKS . ' ✨ Seelenfunken.';

        if (!empty($bonusCheck['birthday_awarded'])) {
            $msg = '🎂 Alles Gute zum Geburtstag! Funki hat dir soeben +' . self::ANNUAL_BIRTHDAY_SPARKS . ' ✨ Geburtstags-Funken gutgeschrieben!';
        }

        return [
            'success'           => true,
            'birthday'          => $normalizedNew,
            'was_correction'    => $wasCorrection,
            'can_change_again'  => $this->canCustomerChangeBirthday($customer),
            'birthday_awarded'  => !empty($bonusCheck['birthday_awarded']),
            'message'           => $msg,
        ];
    }

    /**
     * Liefert den Status von Geburtstags-Funke & Manufaktur-Jahrestags-Funke für das Funki-Modal (Tab "🎁 Rabatte").
     */
    public function getLoyaltyMilestonesStatus(Customer $customer, ?CustomerGamification $gamification = null): array
    {
        $gamification = $gamification ?? $this->getProfile($customer);
        $progress = is_array($gamification->titles_progress) ? $gamification->titles_progress : [];
        $currentYear = (int) now()->year;

        $birthdayStr = $this->normalizeBirthdayDate($customer->profile?->birthday);
        $correctionsCount = $this->countCustomerBirthdayCorrections($customer);
        $canChangeBirthday = $birthdayStr === '' || $correctionsCount < 1;

        $firstPaidOrder = OrderOrder::where('customer_id', $customer->id)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->oldest('created_at')
            ->first();

        $anniversaryDateFormatted = $firstPaidOrder?->created_at
            ? $firstPaidOrder->created_at->format('d.m.')
            : null;
        $memberSinceFormatted = $firstPaidOrder?->created_at
            ? $firstPaidOrder->created_at->format('d.m.Y')
            : null;

        return [
            'birthday'                => $birthdayStr,
            'birthday_formatted'      => $birthdayStr !== '' ? \Carbon\Carbon::parse($birthdayStr)->format('d.m.Y') : null,
            'birthday_day_month'      => $birthdayStr !== '' ? \Carbon\Carbon::parse($birthdayStr)->format('d.m.') : null,
            'birthday_sparks'         => self::ANNUAL_BIRTHDAY_SPARKS,
            'birthday_awarded_year'   => (int) ($progress['_birthday_bonus_year'] ?? 0),
            'birthday_awarded_this_year' => ((int) ($progress['_birthday_bonus_year'] ?? 0)) === $currentYear,
            'birthday_corrections'    => $correctionsCount,
            'can_change_birthday'     => $canChangeBirthday,
            'has_first_order'         => (bool) $firstPaidOrder,
            'anniversary_day_month'   => $anniversaryDateFormatted,
            'member_since_formatted'  => $memberSinceFormatted,
            'anniversary_sparks'      => self::ANNUAL_ANNIVERSARY_SPARKS,
            'anniversary_awarded_this_year' => ((int) ($progress['_anniversary_bonus_year'] ?? 0)) === $currentYear,
        ];
    }

    /**
     * Prüft und vergibt automatisch die jährlichen Geburtstags- (+50 ✨) und Manufaktur-Jahrestags-Funken (+50 ✨)
     * inklusive Versand der Manufaktur-E-Mail (streng max. 1× pro Kalenderjahr).
     */
    public function processAnnualLoyaltySparksForCustomer(Customer $customer, bool $sendEmail = true, ?\Carbon\Carbon $referenceDate = null): array
    {
        $now = $referenceDate ? $referenceDate->copy() : now();
        $currentYear = (int) $now->year;

        $gamification = CustomerGamification::where('customer_id', $customer->id)->first();
        if (!$gamification || !$gamification->is_active) {
            return ['birthday_awarded' => false, 'anniversary_awarded' => false];
        }

        $birthdayAwarded = false;
        $anniversaryAwarded = false;

        // 1. Geburtstags-Funke (+50 ✨ am Geburtstag, max. 1× pro Kalenderjahr)
        $birthdayStr = $this->normalizeBirthdayDate($customer->profile?->birthday);
        if ($birthdayStr !== '') {
            $bday = \Carbon\Carbon::parse($birthdayStr);
            $isBirthdayToday = ($bday->month === $now->month && $bday->day === $now->day)
                || ($bday->month === 2 && $bday->day === 29 && !$now->isLeapYear() && $now->month === 2 && $now->day === 28);

            $progress = is_array($gamification->titles_progress) ? $gamification->titles_progress : $this->getInitialTitlesProgress();
            $lastBirthdayYear = (int) ($progress['_birthday_bonus_year'] ?? 0);

            if ($isBirthdayToday && $lastBirthdayYear < $currentYear) {
                $awarded = DB::transaction(function () use ($customer, $gamification, $currentYear) {
                    $locked = CustomerGamification::where('id', $gamification->id)->lockForUpdate()->first();
                    if (!$locked || !$locked->is_active) {
                        return false;
                    }
                    $prog = is_array($locked->titles_progress) ? $locked->titles_progress : $this->getInitialTitlesProgress();
                    if (((int) ($prog['_birthday_bonus_year'] ?? 0)) >= $currentYear) {
                        return false;
                    }

                    $prog['_birthday_bonus_year'] = $currentYear;
                    $prog['_last_loyalty_banner'] = [
                        'type'       => 'birthday',
                        'sparks'     => self::ANNUAL_BIRTHDAY_SPARKS,
                        'year'       => $currentYear,
                        'awarded_at' => now()->toIso8601String(),
                    ];

                    $locked->funken_balance = (int) $locked->funken_balance + self::ANNUAL_BIRTHDAY_SPARKS;
                    $locked->funken_total_earned = (int) $locked->funken_total_earned + self::ANNUAL_BIRTHDAY_SPARKS;
                    $locked->titles_progress = $prog;
                    $this->syncLevelFromLifetimeSparks($locked);
                    $locked->save();

                    $this->logGamificationEvent(
                        (string) $customer->id,
                        'annual_birthday_' . $currentYear,
                        'loyalty',
                        '🎂 Jährlicher Geburtstags-Funke (' . $currentYear . ')',
                        'Funki hat dir zum Geburtstag +' . self::ANNUAL_BIRTHDAY_SPARKS . ' Seelenfunken geschenkt.',
                        self::ANNUAL_BIRTHDAY_SPARKS,
                        (int) $locked->funken_balance,
                        ['year' => $currentYear, 'type' => 'birthday']
                    );

                    return $locked;
                });

                if ($awarded instanceof CustomerGamification) {
                    $birthdayAwarded = true;
                    $gamification = $awarded;
                    if ($sendEmail && !empty($customer->email) && $this->shouldSendLoyaltyEmail($customer, 'birthday')) {
                        $this->sendLoyaltyMail($customer, $gamification, 'birthday', self::ANNUAL_BIRTHDAY_SPARKS);
                    }
                }
            }
        }

        // 2. Manufaktur-Jahrestags-Funke (+50 ✨, 14 Tage vor oder am Jahrestag der ersten bezahlten Bestellung, ab dem 1. Jubiläumsjahr)
        $firstPaidOrder = OrderOrder::where('customer_id', $customer->id)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->oldest('created_at')
            ->first();

        if ($firstPaidOrder && $firstPaidOrder->created_at) {
            $orderDate = $firstPaidOrder->created_at->copy()->startOfDay();
            $daysSinceFirstOrder = $orderDate->diffInDays($now->copy()->startOfDay(), false);

            if ($daysSinceFirstOrder >= 330) {
                $anniversaryThisYear = \Carbon\Carbon::create($currentYear, $orderDate->month, min($orderDate->day, 28))->startOfDay();
                $daysUntilAnniversary = $now->copy()->startOfDay()->diffInDays($anniversaryThisYear, false);

                // Zwischen 14 Tage vor dem Jahrestag und dem Jahrestag selbst
                $isInAnniversaryWindow = ($daysUntilAnniversary >= 0 && $daysUntilAnniversary <= 14);
                $progress = is_array($gamification->titles_progress) ? $gamification->titles_progress : $this->getInitialTitlesProgress();
                $lastAnnivYear = (int) ($progress['_anniversary_bonus_year'] ?? 0);

                if ($isInAnniversaryWindow && $lastAnnivYear < $currentYear) {
                    $awardedAnniv = DB::transaction(function () use ($customer, $gamification, $currentYear) {
                        $locked = CustomerGamification::where('id', $gamification->id)->lockForUpdate()->first();
                        if (!$locked || !$locked->is_active) {
                            return false;
                        }
                        $prog = is_array($locked->titles_progress) ? $locked->titles_progress : $this->getInitialTitlesProgress();
                        if (((int) ($prog['_anniversary_bonus_year'] ?? 0)) >= $currentYear) {
                            return false;
                        }

                        $prog['_anniversary_bonus_year'] = $currentYear;
                        $prog['_last_loyalty_banner'] = [
                            'type'       => 'anniversary',
                            'sparks'     => self::ANNUAL_ANNIVERSARY_SPARKS,
                            'year'       => $currentYear,
                            'awarded_at' => now()->toIso8601String(),
                        ];

                        $locked->funken_balance = (int) $locked->funken_balance + self::ANNUAL_ANNIVERSARY_SPARKS;
                        $locked->funken_total_earned = (int) $locked->funken_total_earned + self::ANNUAL_ANNIVERSARY_SPARKS;
                        $locked->titles_progress = $prog;
                        $this->syncLevelFromLifetimeSparks($locked);
                        $locked->save();

                        $this->logGamificationEvent(
                            (string) $customer->id,
                            'annual_anniversary_' . $currentYear,
                            'loyalty',
                            '🕯️ Jährlicher Manufaktur-Jahrestags-Funke (' . $currentYear . ')',
                            'Vorfreude-Bonus zum Jahrestag deiner ersten Bestellung bei Mein Seelenfunke.',
                            self::ANNUAL_ANNIVERSARY_SPARKS,
                            (int) $locked->funken_balance,
                            ['year' => $currentYear, 'type' => 'anniversary']
                        );

                        return $locked;
                    });

                    if ($awardedAnniv instanceof CustomerGamification) {
                        $anniversaryAwarded = true;
                        $gamification = $awardedAnniv;
                        if ($sendEmail && !empty($customer->email) && $this->shouldSendLoyaltyEmail($customer, 'anniversary')) {
                            $this->sendLoyaltyMail($customer, $gamification, 'anniversary', self::ANNUAL_ANNIVERSARY_SPARKS);
                        }
                    }
                }
            }
        }

        return [
            'birthday_awarded'    => $birthdayAwarded,
            'anniversary_awarded' => $anniversaryAwarded,
        ];
    }

    /**
     * Prüft, ob der Kunde dem Erhalt der automatischen Geburtstags- ("birthday") bzw.
     * Kauf-Jubiläums-E-Mail ("anniversary") über die Seelenpost-/Newsletter-Verwaltung zugestimmt hat.
     */
    public function shouldSendLoyaltyEmail(Customer $customer, string $type): bool
    {
        $col = $type === 'birthday' ? 'receive_birthday_emails' : 'receive_anniversary_emails';

        try {
            $profile = $customer->profile()->first();
            if ($profile && isset($profile->{$col}) && $profile->{$col} === false) {
                return false;
            }

            if (!empty($customer->email) && Schema::hasTable('marketing_newsletter_subscribers') && Schema::hasColumn('marketing_newsletter_subscribers', $col)) {
                $sub = \App\Models\Marketing\MarketingNewsletterSubscriber::where('email', $customer->email)->first();
                if ($sub && isset($sub->{$col}) && $sub->{$col} === false) {
                    return false;
                }
            }
        } catch (\Throwable $e) {
            // Im Fehlerfall Standard true
        }

        return true;
    }

    /**
     * Führt den täglichen Check für alle aktiven Seelenfunken-Teilnehmer aus (für den Artisan-Befehl / Scheduler).
     */
    public function processAllAnnualLoyaltySparks(bool $sendEmail = true, ?\Carbon\Carbon $referenceDate = null): array
    {
        $birthdayCount = 0;
        $anniversaryCount = 0;

        $profiles = CustomerGamification::with(['customer.profile'])
            ->where('is_active', true)
            ->whereHas('customer')
            ->get();

        foreach ($profiles as $profile) {
            if (!$profile->customer) {
                continue;
            }
            $res = $this->processAnnualLoyaltySparksForCustomer($profile->customer, $sendEmail, $referenceDate);
            if (!empty($res['birthday_awarded'])) {
                $birthdayCount++;
            }
            if (!empty($res['anniversary_awarded'])) {
                $anniversaryCount++;
            }
        }

        return [
            'birthday_awarded_count'    => $birthdayCount,
            'anniversary_awarded_count' => $anniversaryCount,
        ];
    }

    private function sendLoyaltyMail(Customer $customer, CustomerGamification $gamification, string $type, int $sparks): void
    {
        try {
            $balance = (int) $gamification->funken_balance;
            $level = max(1, (int) $gamification->level);

            $nextVoucherHint = null;
            foreach (GameConfig::getRedeemableVouchers() as $voucher) {
                $cost = (int) ($voucher['cost_sparks'] ?? $voucher['cost'] ?? 250);
                $pct = (int) ($voucher['percent'] ?? $voucher['value'] ?? 5);
                if ($balance < $cost) {
                    $missing = $cost - $balance;
                    $nextVoucherHint = "Dir fehlen nur noch {$missing} ✨ Funken bis zu deinem {$pct}% Rabatt-Gutschein!";
                    break;
                }
            }
            if (!$nextVoucherHint) {
                $nextVoucherHint = 'Du hast bereits genügend Seelenfunken gesammelt, um dir sofort einen Rabatt-Gutschein im Kundenbereich freizuschalten!';
            }

            if ($type === 'birthday') {
                $payload = [
                    'subject'           => '🎂 Alles Liebe zum Geburtstag, ' . ($customer->first_name ?: 'liebes Mitglied') . '! (+50 ✨ Seelenfunken)',
                    'headline'          => 'Alles Liebe zum Geburtstag! 🎂✨',
                    'first_name'        => $customer->first_name,
                    'intro_text'        => "zu deinem Ehrentag wünschen wir dir von Herzen alles Gute, Gesundheit und viele leuchtende Momente!\n\nAls kleines Geburtstagsgeschenk aus unserer Manufaktur hat Funki deinem Konto soeben +{$sparks} Seelenfunken gutgeschrieben.",
                    'badge_text'        => 'GEBURTSTAGS-GESCHENK VON FUNKI',
                    'awarded_sparks'    => $sparks,
                    'new_balance'       => $balance,
                    'funki_level'       => $level,
                    'next_voucher_hint' => $nextVoucherHint,
                    'dashboard_url'     => url('/customer/dashboard'),
                    'mail_type'         => 'birthday',
                    'unsubscribe_url'   => url('/newsletter?tab=unsubscribe&type=birthday&email=' . urlencode((string) $customer->email)),
                ];
            } else {
                $payload = [
                    'subject'           => '✨ Dein Manufaktur-Jahrestag naht! Funki schenkt dir +50 Seelenfunken',
                    'headline'          => 'Danke für deine Treue! 🕯️✨',
                    'first_name'        => $customer->first_name,
                    'intro_text'        => "vor fast genau einem Jahr hast du dein erstes persönliches Kunstwerk bei Mein-Seelenfunke gestaltet. Vielleicht steht ja schon bald wieder ein besonderer Anlass oder Jahrestag bevor?\n\nAls Dankeschön für deine Treue hat Funki deinem Konto heute +{$sparks} Vorfreude-Funken gutgeschrieben.",
                    'badge_text'        => 'JAHRESTAGS-TREUEBONUS VON FUNKI',
                    'awarded_sparks'    => $sparks,
                    'new_balance'       => $balance,
                    'funki_level'       => $level,
                    'next_voucher_hint' => $nextVoucherHint,
                    'dashboard_url'     => url('/customer/dashboard'),
                    'mail_type'         => 'anniversary',
                    'unsubscribe_url'   => url('/newsletter?tab=unsubscribe&type=anniversary&email=' . urlencode((string) $customer->email)),
                ];
            }

            \Illuminate\Support\Facades\Mail::to($customer->email)->send(new \App\Mail\GamificationLoyaltySparksMail($payload));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Konnte Loyalty-Sparks-E-Mail nicht senden: ' . $e->getMessage());
        }
    }

    public function logGamificationEvent(
        string $customerId,
        string $eventType,
        string $category,
        string $title,
        string $description = '',
        int $sparksDelta = 0,
        int $balanceAfter = 0,
        array $meta = []
    ): void {
        try {
            if (Schema::hasTable('customer_gamification_event_logs')) {
                CustomerGamificationEventLog::create([
                    'customer_id'   => $customerId,
                    'event_type'    => $eventType,
                    'category'      => $category,
                    'title'         => $title,
                    'description'   => $description,
                    'sparks_delta'  => $sparksDelta,
                    'balance_after' => $balanceAfter,
                    'meta'          => $meta,
                ]);
            }
        } catch (\Throwable $e) {
            // Nicht-blockierend falls Tabelle in Minimal-Tests noch nicht existiert
        }
    }
}

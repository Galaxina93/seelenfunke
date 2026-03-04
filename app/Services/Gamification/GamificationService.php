<?php

namespace App\Services\Gamification;

use App\Models\Funki\FunkiVoucher;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\Order;
use App\Models\Product\ProductReview;
use Illuminate\Support\Str;

class GamificationService
{
    public function getProfile(Customer $customer): CustomerGamification
    {
        return CustomerGamification::firstOrCreate(
            ['customer_id' => $customer->id],
            ['titles_progress' => $this->getInitialTitlesProgress()]
        );
    }

    public function getUpgradeCost(?int $currentLevel = 1): int
    {
        $level = $currentLevel ?? 1;
        if ($level >= GameConfig::MAX_LEVEL) return 0;
        return (int) round(pow($level, 1.5) * 10) + 5;
    }

    public function upgradeLevel(Customer $customer): array
    {
        $profile = $this->getProfile($customer);
        $cost = $this->getUpgradeCost($profile->level);

        if ($profile->level >= GameConfig::MAX_LEVEL) return ['success' => false, 'message' => 'Maximales Level erreicht.'];
        if ($profile->funken_balance < $cost) return ['success' => false, 'message' => 'Nicht genügend Funken.'];

        $profile->funken_balance -= $cost;
        $profile->level += 1;
        $profile->save();

        $rewardMessage = $this->checkAndIssueCoupon($profile, $profile->level);

        return [
            'success' => true,
            'new_level' => $profile->level,
            'reward' => $rewardMessage ?? 'Neue Form freigeschaltet!'
        ];
    }

    private function checkAndIssueCoupon(CustomerGamification $profile, int $newLevel): ?string
    {
        $rewards = GameConfig::getLevelRewards();

        if (array_key_exists($newLevel, $rewards)) {
            $rewardConfig = $rewards[$newLevel];
            $code = 'FUNKI-L' . $newLevel . '-' . strtoupper(Str::random(5));
            $email = $profile->customer ? $profile->customer->email : 'System';

            FunkiVoucher::create([
                'title'           => 'Level ' . $newLevel . ' Belohnung (' . $email . ')',
                'code'            => $code,
                'type'            => 'percent',
                'value'           => $rewardConfig['value'],
                'usage_limit'     => 1,
                'is_active'       => true,
                'valid_from'      => now(),
                'mode'            => 'manual',
            ]);

            $unlocked = is_array($profile->unlocked_coupons) ? $profile->unlocked_coupons : [];
            $unlocked[(string)$newLevel] = $code; // Als String speichern!

            $profile->unlocked_coupons = $unlocked;
            $profile->save();

            return $rewardConfig['name'] . ' freigeschaltet!';
        }

        return null;
    }

    public function syncUnlockedRewards(CustomerGamification $profile): void
    {
        $rewards = GameConfig::getLevelRewards();
        $unlocked = is_array($profile->unlocked_coupons) ? $profile->unlocked_coupons : [];
        $changed = false;

        for ($i = 1; $i <= $profile->level; $i++) {
            // Prüfen ob es für dieses Level eine Belohnung gibt und ob sie fehlt
            if (isset($rewards[$i]) && !isset($unlocked[(string)$i]) && !isset($unlocked[$i])) {
                $rewardConfig = $rewards[$i];
                $code = 'FUNKI-L' . $i . '-' . strtoupper(Str::random(5));
                $email = $profile->customer ? $profile->customer->email : 'System';

                FunkiVoucher::create([
                    'title'           => 'Nachgereicht: Level ' . $i . ' Belohnung (' . $email . ')',
                    'code'            => $code,
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
        }

        if ($changed) {
            $profile->unlocked_coupons = $unlocked;
            $profile->save();
        }
    }

    public function addFunken(Customer $customer, int $amount, string $source = 'purchase'): void
    {
        $profile = $this->getProfile($customer);

        if ($source === 'website_hunt') {
            if ($profile->last_spark_collection_date && !$profile->last_spark_collection_date->isToday()) {
                $profile->sparks_collected_today = 0;
            }
            if ($profile->sparks_collected_today >= GameConfig::DAILY_SPARK_LIMIT) return;

            $profile->sparks_collected_today += 1;
            $profile->last_spark_collection_date = now();
            $this->incrementTitleProgress($profile, 'sammler');
        }

        $profile->funken_balance += $amount;
        $profile->funken_total_earned += $amount;
        $profile->save();
    }

    public function getCurrentModelPath(int $level): string
    {
        $milestones = GameConfig::getAppearanceMilestones();
        $currentModel = 'funki_lvl_1_rags';
        foreach ($milestones as $milestoneLevel => $modelName) {
            if ($level >= $milestoneLevel) $currentModel = $modelName;
            else break;
        }
        return asset('storage/funki/models/' . $currentModel . '.glb');
    }

    public function incrementTitleProgress(CustomerGamification $profile, string $titleId, int $amount = 1): void
    {
        $progress = $profile->titles_progress;
        if (!is_array($progress) || empty($progress)) $progress = $this->getInitialTitlesProgress();
        if (!array_key_exists($titleId, $progress)) $progress[$titleId] = 0;

        $progress[$titleId] += $amount;
        $profile->update(['titles_progress' => $progress]);
    }

    public function syncDynamicTitles(CustomerGamification $profile): void
    {
        $customer = $profile->customer;
        if (!$customer) return;

        $progress = $profile->titles_progress;
        if (!is_array($progress)) $progress = $this->getInitialTitlesProgress();

        // REVIEWS
        $reviews = ProductReview::where('customer_id', $customer->id)->where('status', 'approved')->get();
        $progress['botschafter'] = $reviews->count();
        $progress['treuer_bewerter'] = $reviews->where('rating', 5)->count();
        $progress['bildreporter'] = $reviews->filter(fn($r) => !empty($r->media))->count();
        $progress['wortgewandt'] = $reviews->filter(fn($r) => strlen($r->content ?? '') >= 100)->count();

        // ORDERS
        $orders = Order::where('customer_id', $customer->id)->where('payment_status', 'paid')->with('items.product')->get();
        $progress['schatzhueter'] = $orders->count();
        $progress['massenkaeufer'] = $orders->flatMap(fn($o) => $o->items)->sum('quantity');

        $progress['freudebringer'] = $orders->filter(function ($order) {
            if (isset($order->is_different_shipping_address) && $order->is_different_shipping_address) return true;
            return ($order->shipping_address_id ?? null) && ($order->billing_address_id ?? null) && ($order->shipping_address_id !== $order->billing_address_id);
        })->count();

        $progress['entdecker'] = $orders->flatMap(fn($o) => $o->items->map(fn($i) => $i->product->category_id ?? null))->filter()->unique()->count();
        $progress['nachtschwaermer'] = $orders->filter(fn($o) => $o->created_at->hour >= 22 || $o->created_at->hour <= 4)->count();
        $progress['wochenend_shopper'] = $orders->filter(fn($o) => $o->created_at->isWeekend())->count();
        $progress['wiederholungstaeter'] = $orders->map(fn($o) => $o->created_at->format('Y-m'))->unique()->count();

        // PROFIL
        $progress['treue_seele'] = max(0, $customer->created_at ? $customer->created_at->diffInDays(now()) : 0);
        $progress['funkenkoenig'] = $profile->funken_total_earned ?? 0;

        $profile->update(['titles_progress' => $progress]);
    }

    public function evaluateTitles(CustomerGamification $profile): array
    {
        $this->syncDynamicTitles($profile);
        $progress = $profile->fresh()->titles_progress ?? $this->getInitialTitlesProgress();

        $configs = GameConfig::getTitles();
        $evaluated = [];
        $diamondsCount = 0;

        foreach ($configs as $key => $config) {
            $currentValue = $progress[$key] ?? 0;
            $currentTier = 'grau';
            $nextReq = $config['tiers']['silber']['req'];
            $maxReq = $config['tiers']['diamant']['req'];

            if ($currentValue >= $maxReq) {
                $currentTier = 'diamant';
                $nextReq = $maxReq;
                $diamondsCount++;
            } elseif ($currentValue >= $config['tiers']['gold']['req']) {
                $currentTier = 'gold';
                $nextReq = $maxReq;
            } elseif ($currentValue >= $config['tiers']['silber']['req']) {
                $currentTier = 'silber';
                $nextReq = $config['tiers']['gold']['req'];
            }

            $evaluated[$key] = [
                'name' => $config['name'],
                'description' => $config['description'],
                'current_value' => $currentValue,
                'tier' => $currentTier,
                'tier_name' => $config['tiers'][$currentTier]['name'],
                'next_req' => $nextReq,
                'max_req' => $maxReq,
                'percentage' => $currentValue >= $maxReq ? 100 : min(100, ($currentValue / $nextReq) * 100)
            ];
        }

        $megaTitles = GameConfig::getMegaTitles();
        $currentMegaTitle = $megaTitles[0];
        $currentMegaTitle['rank'] = 0;
        $nextMegaTitle = $megaTitles[1] ?? null;

        foreach ($megaTitles as $index => $mega) {
            if ($diamondsCount >= $mega['req']) {
                $currentMegaTitle = $mega;
                $currentMegaTitle['rank'] = $index;
                $nextMegaTitle = $megaTitles[$index + 1] ?? null;
            }
        }

        return [
            'titles' => $evaluated,
            'diamonds_count' => $diamondsCount,
            'mega_title' => $currentMegaTitle,
            'next_mega_title' => $nextMegaTitle,
            'is_seelengott' => $diamondsCount >= 15
        ];
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
}

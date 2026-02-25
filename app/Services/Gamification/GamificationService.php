<?php

namespace App\Services\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Funki\FunkiVoucher;
use Illuminate\Support\Str;

class GamificationService
{
    /**
     * Lädt oder erstellt das Gamification-Profil für einen Kunden
     */
    public function getProfile(Customer $customer): CustomerGamification
    {
        return CustomerGamification::firstOrCreate(
            ['customer_id' => $customer->id],
            ['titles_progress' => $this->getInitialTitlesProgress()]
        );
    }

    /**
     * Exponentielle Kostenberechnung für das nächste Level.
     * Beispiel: Lvl 1->2 (15), Lvl 5->6 (165), Lvl 20->21 (1340)
     */
    public function getUpgradeCost(?int $currentLevel = 1): int
    {
        // Fallback, falls null übergeben wird
        $level = $currentLevel ?? 1;

        if ($level >= GameConfig::MAX_LEVEL) {
            return 0;
        }

        // Base-Cost = 10, Multiplikator = 1.5 auf das Level
        return (int) round(pow($level, 1.5) * 10) + 5;
    }

    /**
     * Führt das Upgrade durch, wenn genug Funken vorhanden sind.
     */
    public function upgradeLevel(Customer $customer): array
    {
        $profile = $this->getProfile($customer);
        $cost = $this->getUpgradeCost($profile->level);

        if ($profile->level >= GameConfig::MAX_LEVEL) {
            return ['success' => false, 'message' => 'Maximales Level erreicht.'];
        }

        if ($profile->funken_balance < $cost) {
            return ['success' => false, 'message' => 'Nicht genügend Funken.'];
        }

        // Abzug und Level-Up
        $profile->funken_balance -= $cost;
        $profile->level += 1;
        $profile->save();

        // Prüfen ob es eine Belohnung für das neue Level gibt
        $reward = $this->checkAndIssueReward($customer, $profile->level);

        return [
            'success' => true,
            'new_level' => $profile->level,
            'reward' => $reward
        ];
    }

    /**
     * Fügt Funken hinzu (durch Kauf oder Suchen)
     */
    public function addFunken(Customer $customer, int $amount, string $source = 'purchase'): void
    {
        $profile = $this->getProfile($customer);

        if ($source === 'website_hunt') {
            if ($profile->last_spark_collection_date && !$profile->last_spark_collection_date->isToday()) {
                $profile->sparks_collected_today = 0;
            }

            if ($profile->sparks_collected_today >= GameConfig::DAILY_SPARK_LIMIT) {
                return; // Tageslimit erreicht
            }
            $profile->sparks_collected_today += 1;
            $profile->last_spark_collection_date = now();

            // Sammler-Titel pushen
            $this->incrementTitleProgress($profile, 'sammler');
        }

        $profile->funken_balance += $amount;
        $profile->funken_total_earned += $amount;
        $profile->save();
    }

    /**
     * Prüft, ob ein neues Level eine Belohnung freigeschaltet hat
     */
    private function checkAndIssueReward(Customer $customer, int $newLevel): ?string
    {
        $rewards = GameConfig::getLevelRewards();

        if (array_key_exists($newLevel, $rewards)) {
            $rewardConfig = $rewards[$newLevel];

            // Echten Gutschein in der Datenbank anlegen
            $code = 'FUNKI-' . $newLevel . '-' . strtoupper(Str::random(6));

            // Dummy: Hier würdest du dein echtes Gutschein-Model aufrufen
            // FunkiVoucher::create([...])

            return $rewardConfig['name'] . ' freigeschaltet! (Code: ' . $code . ')';
        }

        return null;
    }

    /**
     * Holt das aktuell anzuzeigende 3D-Modell basierend auf dem Level
     */
    public function getCurrentModelPath(int $level): string
    {
        $milestones = GameConfig::getAppearanceMilestones();
        $currentModel = 'funki_lvl_1_rags'; // Fallback

        foreach ($milestones as $milestoneLevel => $modelName) {
            if ($level >= $milestoneLevel) {
                $currentModel = $modelName;
            } else {
                break;
            }
        }

        // Rückgabe des öffentlichen Pfads zum GLB Modell
        return asset('storage/funki/models/' . $currentModel . '.glb');
    }

    // --- TITEL LOGIK ---

    public function incrementTitleProgress(CustomerGamification $profile, string $titleId, int $amount = 1): void
    {
        $progress = $profile->titles_progress ?? $this->getInitialTitlesProgress();
        if (isset($progress[$titleId])) {
            $progress[$titleId] += $amount;
            $profile->titles_progress = $progress;
            $profile->save();
        }
    }

    public function evaluateTitles(CustomerGamification $profile): array
    {
        $configs = GameConfig::getTitles();
        $progress = $profile->titles_progress ?? $this->getInitialTitlesProgress();
        $evaluated = [];

        $allDiamond = true;

        foreach ($configs as $key => $config) {
            $currentValue = $progress[$key] ?? 0;
            $currentTier = 'grau';
            $nextReq = $config['tiers']['silber']['req'];
            $maxReq = $config['tiers']['diamant']['req'];

            if ($currentValue >= $config['tiers']['diamant']['req']) {
                $currentTier = 'diamant';
                $nextReq = $maxReq;
            } elseif ($currentValue >= $config['tiers']['gold']['req']) {
                $currentTier = 'gold';
                $nextReq = $config['tiers']['diamant']['req'];
            } elseif ($currentValue >= $config['tiers']['silber']['req']) {
                $currentTier = 'silber';
                $nextReq = $config['tiers']['gold']['req'];
            } else {
                $allDiamond = false;
            }

            $evaluated[$key] = [
                'name' => $config['name'],
                'description' => $config['description'],
                'icon' => $config['icon'],
                'current_value' => $currentValue,
                'tier' => $currentTier,
                'tier_name' => $config['tiers'][$currentTier]['name'],
                'next_req' => $nextReq,
                'max_req' => $maxReq,
                'percentage' => $currentValue >= $maxReq ? 100 : min(100, ($currentValue / $nextReq) * 100)
            ];
        }

        return [
            'titles' => $evaluated,
            'is_seelengott' => $allDiamond
        ];
    }

    private function getInitialTitlesProgress(): array
    {
        return [
            'sammler' => 0,
            'botschafter' => 0,
            'freudebringer' => 0,
            'schatzhueter' => 0,
            'entdecker' => 0,
        ];
    }
}

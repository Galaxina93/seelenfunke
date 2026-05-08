<?php

namespace App\Livewire\Customer\Gamification;

use App\Models\Customer\CustomerGamification;
use App\Models\Marketing\MarketingVoucher;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\GameConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Carbon\Carbon;

#[Layout('components.layouts.customer_layout')]
class GameProfileComponent extends Component
{
    public $currentEnergy = 0;
    public $timeUntilNextEnergy = '';
    public $personalHighscoreFF = 0;
    public $globalHighscoreFF = 0;
    public $hasOptedIn = false;

    public $currentRankName = 'Funken-Novize';
    public $level = 1;

    public $modelPath;
    public $imagePath;
    public $titlesData = [];

    public $balance = 0;
    public $energyBalance = 5;
    public $maxEnergy = 5;
    public $progressPercentage = 0;
    public $missingSparks = 0;
    public $upgradeCost = 0;
    public $canUpgrade = false;
    public $isMaxLevel = false;

    public $activeTitleKey = null;

    public $unlockedCoupons = [];
    public $milestonesConfig = [];

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if ($user) {
            $profile = CustomerGamification::where('customer_id', $user->id)->first();
            if ($profile && $profile->is_active) {
                $this->hasOptedIn = true;
                $gameService = app(GamificationService::class);
                $this->loadGamificationData($gameService, $profile);
            } else {
                $this->redirect(route('customer.games'));
                return;
            }
        }
        $this->updateEnergyState();
    }

    public function optIn()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = CustomerGamification::firstOrCreate(
            ['customer_id' => $user->id],
            [
                'is_active' => true,
                'energy_balance' => 5,
                'level' => 1,
                'active_title' => 'apprentice'
            ]
        );

        if (!$profile->is_active) {
            $profile->is_active = true;
            // Removed opt_in_date as it doesn't exist in migration
            $profile->save();
        }

        $this->hasOptedIn = true;
        $gameService = app(GamificationService::class);
        $this->loadGamificationData($gameService, $profile);
        $this->updateEnergyState();
    }

    #[On('energy-updated')]
    public function updateEnergyState()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile) {
            $this->currentEnergy = $profile->energy_balance;

            // Format time until next energy if balancing is enabled (assuming typical cron setup)
            // Just a placeholder for the frontend if energy is 0
            if ($this->currentEnergy <= 0) {
                // Next refill is exactly 3 hours after the last refill timestamp.
                $lastRefill = $profile->last_energy_refill_at ? \Carbon\Carbon::parse($profile->last_energy_refill_at) : now();
                $nextRefill = $lastRefill->copy()->addHours(3);
                $diff = now()->diffInMinutes($nextRefill, false);
                $this->timeUntilNextEnergy = $diff > 0 ? (int)$diff : 0;
            }
            // Fetch highscores for Funkenflug
            $this->personalHighscoreFF = $profile->funkenflug_highscore ?? 0;
            $this->globalHighscoreFF = CustomerGamification::max('funkenflug_highscore') ?? 0;
        }
    }

    public function render()
    {
        return view('livewire.customer.gamification.game-profile-component');
    }

    public function selectTitle($titleKey, GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if ($user) {
            $profile = $gameService->getProfile($user);
            $profile->update(['active_title' => $titleKey]);

            $this->loadGamificationData($gameService, $profile);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Titel erfolgreich ausgerüstet!']);
        }
    }

    public function loadGamificationData(GamificationService $gameService, $profile)
    {
        $now = Carbon::now();
        $lastRefill = $profile->last_energy_refill_at ? Carbon::parse($profile->last_energy_refill_at) : null;
        if (!$lastRefill || $lastRefill->diffInHours($now) >= 3) {
            $profile->energy_balance = $this->maxEnergy;
            $profile->last_energy_refill_at = $now;
            $profile->save();
        }

        $gameService->syncUnlockedRewards($profile);
        $profile->refresh();

        $this->level = $profile->level ?? 1;
        $this->balance = $profile->funken_balance;
        $this->energyBalance = $profile->energy_balance ?? 5;

        $this->milestonesConfig = GameConfig::getLevelRewards();

        $rawCoupons = is_array($profile->unlocked_coupons) ? $profile->unlocked_coupons : [];
        $formattedCoupons = [];

        if (!empty($rawCoupons)) {
            $dbVouchers = MarketingVoucher::whereIn('code', array_values($rawCoupons))->get()->keyBy('code');

            foreach ($rawCoupons as $lvl => $code) {
                $dbVoucher = $dbVouchers->get($code);
                $isUsed = false;

                if ($dbVoucher) {
                    $isUsed = $dbVoucher->usage_limit !== null && $dbVoucher->used_count >= $dbVoucher->usage_limit;
                }

                $formattedCoupons['lvl_' . $lvl] = [
                    'code' => $code,
                    'is_used' => $isUsed
                ];
            }
        }
        $this->unlockedCoupons = $formattedCoupons;

        $this->isMaxLevel = $this->level >= GameConfig::MAX_LEVEL;
        $this->upgradeCost = $gameService->getUpgradeCost($this->level);
        $this->canUpgrade = !$this->isMaxLevel && ($this->balance >= $this->upgradeCost);

        if ($this->isMaxLevel) {
            $this->progressPercentage = 100;
            $this->missingSparks = 0;
        } else {
            $this->missingSparks = max(0, $this->upgradeCost - $this->balance);
            $cost = $this->upgradeCost > 0 ? $this->upgradeCost : 1;
            $this->progressPercentage = min(100, round(($this->balance / $cost) * 100));
        }

        $milestones = GameConfig::getAppearanceMilestones();
        $currentModelName = 'funki_lvl_1_rags';
        foreach ($milestones as $milestoneLevel => $modelName) {
            if ($this->level >= $milestoneLevel) {
                $currentModelName = $modelName;
            } else {
                break;
            }
        }

        $this->modelPath = asset('shop/customer/gamification/models/' . $currentModelName . '.glb');
        $this->imagePath = asset('shop/customer/gamification/models/images/original/' . $currentModelName . '.png');

        $this->titlesData = $gameService->evaluateTitles($profile);
        $this->activeTitleKey = $profile->active_title;

        if ($this->activeTitleKey === 'mega_title') {
            $this->currentRankName = $this->titlesData['mega_title']['name'] ?? 'Ein Funke im Wind';
        } elseif ($this->activeTitleKey && isset($this->titlesData['titles'][$this->activeTitleKey])) {
            $tier = $this->titlesData['titles'][$this->activeTitleKey]['tier'];
            if ($tier !== 'grau') {
                $this->currentRankName = $this->titlesData['titles'][$this->activeTitleKey]['tier_name'];
            } else {
                $this->currentRankName = $this->getRankName($this->level);
            }
        } else {
            $this->currentRankName = $this->getRankName($this->level);
        }
    }

    public function upgrade(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        $result = $gameService->upgradeLevel($user);

        if ($result['success']) {
            $profile = $gameService->getProfile($user);
            $this->loadGamificationData($gameService, $profile);

            $this->dispatch('funki-level-up', ['level' => $result['new_level'], 'reward' => $result['reward'], 'newModelPath' => $this->modelPath, 'newImagePath' => $this->imagePath]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Level Up!']);
            $this->dispatch('sparks-awarded');
        }
    }

    private function getRankName(int $level): string
    {
        if ($level >= 10) return 'Seelengott';
        if ($level >= 8) return 'Meister';
        if ($level >= 6) return 'Kunsthandwerker';
        if ($level >= 4) return 'Lehrling';
        if ($level >= 2) return 'Helfer';
        return 'Funken-Novize';
    }

    /**
     * Keine Parameter-Injektion mehr, sondern sicheres Auflösen über app()
     */
    public function consumeEnergy()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            \Log::warning('consumeEnergy: SystemUser not found in customer guard');
            return false;
        }

        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        if ($profile && $profile->energy_balance > 0) {
            // 1. Energie abziehen
            $profile->energy_balance -= 1;
            $profile->save();

            // 2. Den Titel für "Spiele gespielt" hochzählen
            $gameService = app(GamificationService::class);
            $gameService->incrementTitleProgress($profile, 'spieler');

            // 3. Events abfeuern (für HUD Updates)
            $this->dispatch('energy-updated');
            return true;
        }

        /*\Log::warning('consumeEnergy: Profile missing or energy is 0', [
            'user_id' => $user->id,
            'profile_found' => !!$profile,
            'energy_balance' => $profile ? $profile->energy_balance : 'N/A'
        ]);*/

        $this->dispatch('notify', ['type' => 'error', 'message' => 'Nicht genug Seelen-Energie!']);
        return false;
    }

    public function rewardGameSparks($amount)
    {
        $safeAmount = min(intval($amount), 50);

        if ($safeAmount > 0) {
            $user = Auth::guard('customer')->user();
            $profile = CustomerGamification::where('customer_id', $user->id)->first();

            if ($profile) {
                // Funken gutschreiben
                $profile->funken_balance += $safeAmount;
                $profile->funken_total_earned += $safeAmount;
                $profile->save();

                $this->dispatch('sparks-awarded');
            }
        }
    }

    public function saveGameRecord($distance, $funkenCollected)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile) {
            $safeAmount = min(intval($funkenCollected), 500); // Sicherheitslimit

            if ($safeAmount > 0) {
                $profile->funken_balance += $safeAmount;
                $profile->funken_total_earned += $safeAmount;
            }

            // Highscore für Funkenflug basierend auf Distanz aktualisieren
            $dist = intval($distance);
            if ($dist > ($profile->funkenflug_highscore ?? 0)) {
                $profile->funkenflug_highscore = $dist;
            }

            $profile->save();

            if ($safeAmount > 0) {
                $this->dispatch('sparks-awarded');
            }

            // Sync Frontend State
            $this->personalHighscoreFF = $profile->funkenflug_highscore;
            $this->globalHighscoreFF = max($this->globalHighscoreFF, $dist);
        }
    }
}

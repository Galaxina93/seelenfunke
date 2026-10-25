<?php

namespace App\Livewire\Customer\Gamification;

use App\Models\Customer\CustomerGamification;
use App\Models\Marketing\MarketingVoucher;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\GameConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Carbon\Carbon;

#[Layout('components.layouts.customer_layout')]
class GameProfileComponent extends Component
{
    #[Locked]
    public $currentEnergy = 0;
    #[Locked]
    public $timeUntilNextEnergy = '';
    #[Locked]
    public $personalHighscoreFF = 0;
    #[Locked]
    public $globalHighscoreFF = 0;
    #[Locked]
    public $hasOptedIn = false;

    #[Locked]
    public $currentRankName = null;
    #[Locked]
    public $level = 1;

    #[Locked]
    public $modelPath;
    #[Locked]
    public $imagePath;
    #[Locked]
    public $titlesData = [];

    #[Locked]
    public $balance = 0;
    #[Locked]
    public $energyBalance = 5;
    #[Locked]
    public $maxEnergy = 5;
    #[Locked]
    public $progressPercentage = 0;
    #[Locked]
    public $missingSparks = 0;
    #[Locked]
    public $upgradeCost = 0;
    #[Locked]
    public $canUpgrade = false;
    #[Locked]
    public $isMaxLevel = false;

    #[Locked]
    public $activeTitleKey = null;

    #[Locked]
    public $unlockedCoupons = [];
    #[Locked]
    public $milestonesConfig = [];

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }
        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile && $profile->is_active) {
            $this->hasOptedIn = true;
            $gameService = app(GamificationService::class);
            $this->loadGamificationData($gameService, $profile);
        } else {
            $this->redirect(route('customer.games'));
            return;
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
                'active_title' => null
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

    #[On('sparks-awarded')]
    public function refreshSparksState(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;
        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile && $profile->is_active) {
            $this->loadGamificationData($gameService, $profile);
        }
    }

    #[On('energy-updated')]
    public function updateEnergyState()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile) {
            if ($profile->is_active) {
                $profile = app(GamificationService::class)->refillEnergyIfDue($profile, $this->maxEnergy);
            }
            $this->currentEnergy = $profile->energy_balance;
            $this->energyBalance = $profile->energy_balance;

            if ($this->currentEnergy <= 0) {
                $lastRefill = $profile->last_energy_refill_at ? \Carbon\Carbon::parse($profile->last_energy_refill_at) : now();
                $nextRefill = $lastRefill->copy()->addHours(3);
                $diff = (int) ceil(now()->diffInMinutes($nextRefill, false));
                $this->timeUntilNextEnergy = max(1, $diff);
            } else {
                $this->timeUntilNextEnergy = '';
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
        if ($titleKey !== null && !is_string($titleKey)) {
            return;
        }

        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = $gameService->getProfile($user);
        $titlesData = $gameService->evaluateTitles($profile);

        if ($titleKey === null || $titleKey === '') {
            $profile->update(['active_title' => null]);
            $this->loadGamificationData($gameService, $profile);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Rang aktualisiert!']);
            return;
        }

        $isUnlocked = false;
        if (isset($titlesData['titles'][$titleKey])) {
            $tier = $titlesData['titles'][$titleKey]['tier'] ?? 'grau';
            $isUnlocked = ($tier !== 'grau');
        }

        if (!$isUnlocked) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Dieser Titel ist noch nicht freigeschaltet!']);
            return;
        }

        $newActiveTitle = ($profile->active_title === $titleKey) ? null : $titleKey;
        $profile->update(['active_title' => $newActiveTitle]);

        $this->loadGamificationData($gameService, $profile);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $newActiveTitle ? 'Rang erfolgreich ausgewählt!' : 'Rang erfolgreich abgewählt!'
        ]);
    }

    public function loadGamificationData(GamificationService $gameService, $profile)
    {
        $gameService->refillEnergyIfDue($profile, $this->maxEnergy);

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
                    $isUsed = ($dbVoucher->usage_limit !== null && $dbVoucher->used_count >= $dbVoucher->usage_limit)
                        || !$dbVoucher->is_active
                        || ($dbVoucher->valid_until && $dbVoucher->valid_until->isPast());
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
        $this->activeTitleKey = ($profile->active_title && isset($this->titlesData['titles'][$profile->active_title]) && ($this->titlesData['titles'][$profile->active_title]['tier'] ?? 'grau') !== 'grau')
            ? $profile->active_title
            : null;
        $this->currentRankName = $gameService->resolveActiveRankName($profile, $this->titlesData);
    }

    public function upgrade(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;
        $result = $gameService->upgradeLevel($user);

        if ($result['success']) {
            $profile = $gameService->getProfile($user);
            $this->loadGamificationData($gameService, $profile);

            $this->dispatch('funki-level-up', level: $result['new_level'], reward: $result['reward'], newModelPath: $this->modelPath, newImagePath: $this->imagePath);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Level Up!']);
        }
    }
}

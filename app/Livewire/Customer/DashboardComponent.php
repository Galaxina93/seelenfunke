<?php

namespace App\Livewire\Customer;

use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\GameConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Carbon\Carbon;

#[Layout('components.layouts.customer_layout')]
class DashboardComponent extends Component
{
    public $hasOptedIn = false;
    public $currentRankName = 'Funken-Novize';
    public $level = 1;

    public $modelPath;
    public $imagePath;
    public $titlesData = [];
    public $profileSteps = [];

    // Neue Eigenschaften für den runden Fortschrittsbalken und Stats
    public $balance = 0;
    public $energyBalance = 5;
    public $maxEnergy = 5;
    public $progressPercentage = 0;
    public $missingSparks = 0;
    public $upgradeCost = 0;
    public $canUpgrade = false;
    public $isMaxLevel = false;

    public function mount(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $profile = $gameService->getProfile($user);
        $this->hasOptedIn = $profile->is_active;

        $this->checkProfileSteps($user);

        if ($this->hasOptedIn) {
            $this->loadGamificationData($gameService, $profile);
        }
    }

    #[On('sparks-awarded')]
    #[On('energy-updated')]
    public function refreshData(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if ($user) {
            $profile = $gameService->getProfile($user);
            $this->loadGamificationData($gameService, $profile);
        }
    }

    public function optIn(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        $profile = $gameService->getProfile($user);
        $profile->is_active = true;

        if ($profile->funken_balance === 0 && $profile->level === 1) {
            $profile->funken_balance = 15;
            $profile->energy_balance = 5;
            $profile->last_energy_refill_at = Carbon::now();
        }
        $profile->save();

        $this->hasOptedIn = true;
        $this->loadGamificationData($gameService, $profile);

        $this->dispatch('sparks-awarded');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Willkommen in der Manufaktur der Magie!']);
    }

    private function checkProfileSteps($user)
    {
        $this->profileSteps = [];
        $p = $user->profile;

        $needsProfileInfo = empty($user->first_name) || empty($user->last_name) ||
            empty($p->street) || empty($p->city) || empty($p->house_number) || empty($p->postal);

        if ($needsProfileInfo) {
            $this->profileSteps[] = ['label' => 'Profil Informationen', 'action' => "\$dispatch('open-profile-modal', {tab: 'profile'})"];
        }
    }

    public function loadGamificationData(GamificationService $gameService, $profile)
    {
        // Tägliche Energie Auffüllung
        $now = Carbon::now();
        $lastRefill = $profile->last_energy_refill_at ? Carbon::parse($profile->last_energy_refill_at) : null;
        if (!$lastRefill || !$lastRefill->isSameDay($now)) {
            $profile->energy_balance = $this->maxEnergy;
            $profile->last_energy_refill_at = $now;
            $profile->save();
        }

        // Gamification Werte berechnen
        $this->level = $profile->level ?? 1;
        $this->balance = $profile->funken_balance;
        $this->energyBalance = $profile->energy_balance ?? 5;

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

        // Richtiges Modell laden
        $milestones = GameConfig::getAppearanceMilestones();
        $currentModelName = 'funki_lvl_1_rags';
        foreach ($milestones as $milestoneLevel => $modelName) {
            if ($this->level >= $milestoneLevel) {
                $currentModelName = $modelName;
            } else {
                break;
            }
        }

        $this->modelPath = asset('storage/funki/models/' . $currentModelName . '.glb');
        $this->imagePath = asset('storage/funki/models/images/' . $currentModelName . '.png');
        $this->currentRankName = $this->getRankName($this->level);

        $evaluation = $gameService->evaluateTitles($profile);
        $this->titlesData = $evaluation['titles'];
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

    public function render()
    {
        return view('livewire.customer.dashboard-component');
    }
}

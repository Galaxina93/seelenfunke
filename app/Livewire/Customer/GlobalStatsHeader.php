<?php

namespace App\Livewire\Customer;

use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\GameConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;

class GlobalStatsHeader extends Component
{
    public $level = 1;
    public $balance = 0;
    public $energyBalance = 5;
    public $maxEnergy = 5;
    public $progressPercentage = 0;
    public $missingSparks = 0;
    public $upgradeCost = 0;
    public $canUpgrade = false;
    public $isMaxLevel = false;
    public $nextVoucherLevel = 5;

    public array $voucherMilestones = [5, 10, 15, 20, 30, 40, 50, 60, 80];

    public function mount(GamificationService $gameService)
    {
        $this->loadData($gameService);
    }

    #[On('sparks-awarded')]
    #[On('energy-updated')]
    #[On('funki-level-up')]
    public function refreshStats(GamificationService $gameService)
    {
        $this->loadData($gameService);
    }

    public function loadData(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if (!$profile || !$profile->is_active) return; // Noch nicht opt-in

        // Energie Refill Check (Täglich)
        $now = Carbon::now();
        $lastRefill = $profile->last_energy_refill_at ? Carbon::parse($profile->last_energy_refill_at) : null;
        if (!$lastRefill || !$lastRefill->isSameDay($now)) {
            $profile->energy_balance = $this->maxEnergy;
            $profile->last_energy_refill_at = $now;
            $profile->save();
        }

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

        // Nächstes Gutschein-Level berechnen
        foreach($this->voucherMilestones as $milestone) {
            if ($this->level < $milestone) {
                $this->nextVoucherLevel = $milestone;
                break;
            }
        }
    }

    public function upgrade(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        $result = $gameService->upgradeLevel($user);

        if ($result['success']) {
            $this->loadData($gameService);
            $this->dispatch('funki-level-up', ['level' => $result['new_level'], 'reward' => $result['reward']]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Level Up!']);
        }
    }

    public function render()
    {
        return view('livewire.customer.global-stats-header');
    }
}

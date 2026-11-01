<?php

namespace App\Livewire\Customer\Gamification;

use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\GameConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class GameGlobalStatsHeader extends Component
{
    #[Locked]
    public int $level = 1;
    #[Locked]
    public int $balance = 0;
    #[Locked]
    public int $totalEarned = 0;
    #[Locked]
    public int $progressPercentage = 0;
    #[Locked]
    public int $missingSparks = 0;
    #[Locked]
    public int $upgradeCost = 0;
    #[Locked]
    public bool $isMaxLevel = false;
    #[Locked]
    public bool $hasOptedIn = false;

    public function mount(GamificationService $gameService)
    {
        $this->loadData($gameService);
    }

    #[On('sparks-awarded')]
    #[On('funki-level-up')]
    #[On('gamification-mode-changed')]
    public function refreshStats(GamificationService $gameService)
    {
        $this->loadData($gameService);
    }

    public function loadData(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if (!$profile || !$profile->is_active) {
            $this->hasOptedIn = false;
            return;
        }

        $this->hasOptedIn = true;
        $this->level = max(1, min(GameConfig::MAX_LEVEL, (int) ($profile->funki_level ?? $profile->level ?? 1)));
        $this->balance = (int) ($profile->funken_balance ?? 0);
        $this->totalEarned = (int) ($profile->funken_total_earned ?? 0);
        $this->isMaxLevel = $this->level >= GameConfig::MAX_LEVEL;
        $this->upgradeCost = GameConfig::getNextLevelCost($this->level);

        if ($this->isMaxLevel) {
            $this->progressPercentage = 100;
            $this->missingSparks = 0;
        } else {
            $currentFloor = GameConfig::getLevels()[$this->level]['cost'] ?? 0;
            $span = max(1, $this->upgradeCost - $currentFloor);
            $gainedInStage = max(0, $this->totalEarned - $currentFloor);
            $this->missingSparks = max(0, $this->upgradeCost - $this->totalEarned);
            $this->progressPercentage = min(100, (int) round(($gainedInStage / $span) * 100));
        }
    }

    public function render()
    {
        return view('livewire.customer.gamification.game-global-stats-header');
    }
}

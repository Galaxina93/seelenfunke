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
        $this->balance = (int) ($profile->funken_balance ?? 0);
        $this->totalEarned = (int) ($profile->funken_total_earned ?? 0);

        $progressData = GameConfig::getLevelProgressData($this->totalEarned);
        $this->level = (int) ($progressData['level'] ?? 1);
        $this->isMaxLevel = (bool) ($progressData['is_max_level'] ?? ($this->level >= GameConfig::MAX_LEVEL));
        $this->upgradeCost = (int) ($progressData['next_level_threshold'] ?? GameConfig::getNextLevelCost($this->level));
        $this->progressPercentage = (int) ($progressData['progress_percentage'] ?? 0);
        $this->missingSparks = (int) ($progressData['missing_sparks'] ?? 0);
    }

    public function loadStats(GamificationService $gameService)
    {
        $this->loadData($gameService);
    }

    public function render()
    {
        return view('livewire.customer.gamification.game-global-stats-header');
    }
}

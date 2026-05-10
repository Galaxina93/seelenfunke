<?php

namespace App\Livewire\Customer\Gamification;

use App\Models\Customer\CustomerGamification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.customer_layout')]
class GameGlobalRankingComponent extends Component
{
    public $hasOptedIn = false;
    public $activeTab = 'classic';

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile) {
            $this->hasOptedIn = $profile->ranking_opt_in;
        }
    }

    public function optIn()
    {
        $user = Auth::guard('customer')->user();
        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        if ($profile) {
            $profile->update(['ranking_opt_in' => true]);
            $this->hasOptedIn = true;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Willkommen in der Halle der Legenden!']);
        }
    }

    public function render()
    {
        $rankings = collect();

        // Rangliste nur laden, wenn der Kunde eingewilligt hat (Performance & Datenschutz)
        if ($this->hasOptedIn) {
            $query = CustomerGamification::with('customer')
                ->where('is_active', true)
                ->where('ranking_opt_in', true);

            if ($this->activeTab === 'funkenflug') {
                $query->where('funkenflug_highscore', '>', 0)
                      ->orderBy('funkenflug_highscore', 'desc');
            } else {
                $query->orderBy('level', 'desc')
                      ->orderBy('funken_total_earned', 'desc');
            }

            $rankings = $query->take(50)->get();
        }

        return view('livewire.customer.gamification.game-global-ranking-component', [
            'rankings' => $rankings
        ]);
    }

    public function getAvatarForLevel($level)
    {
        $map = [
            1 => 'funki_lvl_1_rags.png',
            2 => 'funki_lvl_2_basic.png',
            3 => 'funki_lvl_3_helper.png',
            4 => 'funki_lvl_4_novice.png',
            5 => 'funki_lvl_5_apprentice.png',
            6 => 'funki_lvl_6_craftsman.png',
            7 => 'funki_lvl_7_artisan.png',
            8 => 'funki_lvl_8_master.png',
            9 => 'funki_lvl_9_lightbringer.png',
            10 => 'funki_lvl_10_god_of_soul.png',
        ];

        // Cap level between 1 and 10 for the image mapping
        $clampedLevel = max(1, min(10, (int)$level));
        $filename = $map[$clampedLevel] ?? 'funki_lvl_1_rags.png';

        return asset('shop/customer/gamification/models/images/original/' . $filename);
    }

    public function getActiveTitleName(CustomerGamification $profile): string
    {
        $activeKey = $profile->active_title;
        if (!$activeKey) {
            return $this->getRankName($profile->level);
        }

        if ($activeKey === 'mega_title') {
            $diamondsCount = $this->calculateDiamondsCount($profile);
            $megaTitles = \App\Services\Gamification\GameConfig::getMegaTitles();
            $currentMegaTitle = $megaTitles[0];
            foreach ($megaTitles as $mega) {
                if ($diamondsCount >= $mega['req']) {
                    $currentMegaTitle = $mega;
                }
            }
            return $currentMegaTitle['name'];
        }

        $titlesConfig = \App\Services\Gamification\GameConfig::getTitles();
        if (isset($titlesConfig[$activeKey])) {
            $config = $titlesConfig[$activeKey];
            $progress = $profile->titles_progress ?? [];
            $currentValue = $progress[$activeKey] ?? 0;
            
            $currentTier = 'grau';
            if ($currentValue >= $config['tiers']['diamant']['req']) {
                $currentTier = 'diamant';
            } elseif ($currentValue >= $config['tiers']['gold']['req']) {
                $currentTier = 'gold';
            } elseif ($currentValue >= $config['tiers']['silber']['req']) {
                $currentTier = 'silber';
            }

            if ($currentTier !== 'grau') {
                return $config['tiers'][$currentTier]['name'];
            }
        }

        return $this->getRankName($profile->level);
    }

    private function calculateDiamondsCount(CustomerGamification $profile): int
    {
        $progress = $profile->titles_progress ?? [];
        $configs = \App\Services\Gamification\GameConfig::getTitles();
        $diamonds = 0;
        foreach ($configs as $key => $config) {
            $val = $progress[$key] ?? 0;
            if ($val >= $config['tiers']['diamant']['req']) {
                $diamonds++;
            }
        }
        return $diamonds;
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
}

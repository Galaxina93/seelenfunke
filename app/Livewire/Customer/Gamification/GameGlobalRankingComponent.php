<?php

namespace App\Livewire\Customer\Gamification;

use App\Models\Customer\CustomerGamification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;

#[Layout('components.layouts.customer_layout')]
class GameGlobalRankingComponent extends Component
{
    #[Locked]
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
        if (!$user) return;
        $profile = CustomerGamification::firstOrCreate(
            ['customer_id' => $user->id],
            [
                'is_active' => true,
                'energy_balance' => 5,
                'level' => 1,
                'active_title' => null,
            ]
        );

        $profile->update([
            'is_active' => true,
            'ranking_opt_in' => true,
        ]);
        $this->hasOptedIn = true;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Willkommen in der Halle der Legenden!']);
    }

    public function updatedActiveTab($val): void
    {
        if (!in_array((string) $val, ['classic', 'funkenflug'], true)) {
            $this->activeTab = 'classic';
        }
    }

    public function render()
    {
        if (!in_array((string) $this->activeTab, ['classic', 'funkenflug'], true)) {
            $this->activeTab = 'classic';
        }

        $rankings = collect();

        // Rangliste nur laden, wenn der Kunde eingewilligt hat (Performance & Datenschutz)
        if ($this->hasOptedIn) {
            $query = CustomerGamification::with('customer')
                ->has('customer')
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
        $titlesConfig = \App\Services\Gamification\GameConfig::getTitles();

        if ($activeKey && isset($titlesConfig[$activeKey])) {
            $config = $titlesConfig[$activeKey];
            $progress = $profile->titles_progress ?? [];
            $currentValue = (int) floor((float) ($progress[$activeKey] ?? 0));

            if ($activeKey === 'funkenkoenig') {
                $currentValue = max($currentValue, (int) ($profile->funken_total_earned ?? 0));
            } elseif ($activeKey === 'treue_seele' && $profile->customer?->created_at) {
                $currentValue = max($currentValue, (int) floor($profile->customer->created_at->diffInDays(now())));
            }

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

        return 'Kein Rang';
    }
}

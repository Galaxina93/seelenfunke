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

        // Einheitliches Spieler-Cockpit unter /games (öffnet direkt die Ruhmeshalle)
        return redirect()->to(route('customer.games') . '?panel=ranking');
    }

    public function optIn()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;
        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if (!$profile || !$profile->is_active) {
            return redirect()->route('customer.games');
        }

        $profile->update([
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

    public function getShipSkinForProfile(CustomerGamification $profile): array
    {
        $skins = \App\Services\Gamification\GameConfig::getShipSkins();
        $activeKey = $profile->active_ship_skin ?: 'default';
        return $skins[$activeKey] ?? $skins['default'];
    }

    public function getHallAuraForProfile(CustomerGamification $profile): array
    {
        $auras = \App\Services\Gamification\GameConfig::getHallAuras();
        $activeKey = $profile->active_hall_aura ?: 'default';
        return $auras[$activeKey] ?? $auras['default'];
    }

    public function getHeroMottoForProfile(CustomerGamification $profile): string
    {
        $mottos = \App\Services\Gamification\GameConfig::getHeroMottos();
        $key = $profile->hero_motto_key ?: 'default';
        return $mottos[$key] ?? $mottos['default'];
    }

    public function getPrestigeBadgeForLevel(int $level): ?array
    {
        $milestones = \App\Services\Gamification\GameConfig::getPrestigeMilestones();
        $matched = null;
        foreach ($milestones as $reqLevel => $info) {
            if ($level >= $reqLevel) {
                $matched = array_merge($info, ['level' => $reqLevel]);
            }
        }
        return $matched;
    }

    public function getUnlockedTrophiesForProfile(CustomerGamification $profile): array
    {
        $titlesConfig = \App\Services\Gamification\GameConfig::getTitles();
        $progress = $profile->titles_progress ?? [];
        $trophies = [];

        foreach ($titlesConfig as $key => $config) {
            $val = (int) floor((float) ($progress[$key] ?? 0));
            if ($key === 'funkenkoenig') {
                $val = max($val, (int) ($profile->funken_total_earned ?? 0));
            } elseif ($key === 'treue_seele' && $profile->customer?->created_at) {
                $val = max($val, (int) floor($profile->customer->created_at->diffInDays(now())));
            }

            $tier = null;
            if ($val >= $config['tiers']['diamant']['req']) {
                $tier = 'Diamant';
            } elseif ($val >= $config['tiers']['gold']['req']) {
                $tier = 'Gold';
            } elseif ($val >= $config['tiers']['silber']['req']) {
                $tier = 'Silber';
            }

            if ($tier) {
                $trophies[] = [
                    'name' => $config['name'],
                    'tier' => $tier,
                    'title_name' => $config['tiers'][strtolower($tier)]['name'] ?? $config['name'],
                ];
            }
        }

        return $trophies;
    }

    public function getInspectorPayload(CustomerGamification $profile, int $rank): array
    {
        $ship = $this->getShipSkinForProfile($profile);
        $aura = $this->getHallAuraForProfile($profile);
        $prestige = $this->getPrestigeBadgeForLevel((int) $profile->level);
        $displayName = ($profile->customer?->first_name ?? 'Anonym') . ' ' . ($profile->customer?->last_name ? mb_substr($profile->customer->last_name, 0, 1) . '.' : '');

        return [
            'rank' => $rank,
            'display_name' => trim($displayName),
            'level' => (int) $profile->level,
            'avatar_url' => $this->getAvatarForLevel($profile->level),
            'active_title' => $this->getActiveTitleName($profile),
            'ship_icon' => $ship['icon'] ?? '🚀',
            'ship_name' => $ship['name'] ?? 'Seelenfunke Standard-Gleiter',
            'ship_rarity' => $ship['rarity'] ?? 'Klassisch',
            'ship_perk' => $ship['perk_summary'] ?? 'Standard-Konfiguration',
            'aura_key' => $profile->active_hall_aura ?: 'none',
            'aura_name' => $aura['name'] ?? 'Klassischer Eintrag',
            'aura_icon' => $aura['icon'] ?? '✨',
            'aura_badge' => $aura['badge'] ?? null,
            'motto' => $this->getHeroMottoForProfile($profile),
            'prestige_name' => $prestige['name'] ?? ($profile->level >= 10 ? 'Seelengott' : 'Aufstrebender Funke'),
            'prestige_icon' => $prestige['icon'] ?? ($profile->level >= 10 ? '⚡' : '✨'),
            'funken_total' => number_format((int) $profile->funken_total_earned, 0, ',', '.'),
            'highscore' => number_format((int) $profile->funkenflug_highscore, 0, ',', '.'),
            'trophies' => $this->getUnlockedTrophiesForProfile($profile),
        ];
    }
}

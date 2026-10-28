<?php

namespace App\Livewire\Customer\Gamification;

use App\Models\Customer\CustomerGamification;
use App\Models\Marketing\MarketingVoucher;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\GameConfig;
use App\Services\System\SystemSecurityService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Carbon\Carbon;

#[Layout('components.layouts.customer_layout')]
class GameGamesComponent extends Component
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
    #[Locked]
    public $prestigeMilestones = [];
    #[Locked]
    public $shipSkins = [];
    #[Locked]
    public $unlockedSkins = ['default'];
    #[Locked]
    public $activeShipSkinKey = 'default';
    #[Locked]
    public $activeShipSkin = [];
    #[Locked]
    public $hallAuras = [];
    #[Locked]
    public $unlockedAuras = ['default'];
    #[Locked]
    public $activeHallAuraKey = 'default';
    #[Locked]
    public $heroMottos = [];
    #[Locked]
    public $heroMottosDetails = [];
    #[Locked]
    public $activeMottoKey = 'default';
    #[Locked]
    public $activeMottoDetail = [];
    #[Locked]
    public $isVipBuyer = false;
    #[Locked]
    public $battleshipUnlock = [];
    #[Locked]
    public $spaceStationsPlaceholder = [];
    #[Locked]
    public $rankingOptIn = false;
    public $rankingTab = 'classic';
    public $leftHandedMode = false;

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }
        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile && $profile->is_active) {
            $this->hasOptedIn = true;
            $this->rankingOptIn = (bool) $profile->ranking_opt_in;
            $gameService = app(GamificationService::class);
            $this->loadGamificationData($gameService, $profile);
        }
        $this->updateEnergyState();
    }

    public function optInRanking()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;
        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if (!$profile || !$profile->is_active) return;

        $profile->update(['ranking_opt_in' => true]);
        $this->rankingOptIn = true;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Willkommen in der Halle der Legenden!']);
    }

    public function setRankingTab(string $tab): void
    {
        $this->rankingTab = in_array($tab, ['classic', 'funkenflug'], true) ? $tab : 'classic';
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
        $gameService->syncDynamicTitles($profile);
        $this->loadGamificationData($gameService, $profile);
        $this->updateEnergyState();
        $this->dispatch('gamification-mode-changed');
        $this->dispatch('sparks-awarded');
        $this->dispatch('energy-updated');

        return $this->redirect(route('customer.games'));
    }

    #[On('sparks-awarded')]
    public function refreshSparksState()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;
        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile && $profile->is_active) {
            $this->loadGamificationData(app(GamificationService::class), $profile);
        }
    }

    #[On('energy-updated')]
    public function updateEnergyState()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile && $profile->is_active) {
            $profile = app(GamificationService::class)->refillEnergyIfDue($profile, $this->maxEnergy);
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
            $this->globalHighscoreFF = CustomerGamification::where('is_active', true)->max('funkenflug_highscore') ?? 0;
        }
    }

    public function render()
    {
        if (!in_array((string) $this->rankingTab, ['classic', 'funkenflug'], true)) {
            $this->rankingTab = 'classic';
        }

        $rankings = collect();
        if ($this->hasOptedIn && $this->rankingOptIn) {
            $query = CustomerGamification::with('customer')
                ->has('customer')
                ->where('is_active', true)
                ->where('ranking_opt_in', true);

            if ($this->rankingTab === 'funkenflug') {
                $query->where('funkenflug_highscore', '>', 0)
                      ->orderBy('funkenflug_highscore', 'desc');
            } else {
                $query->orderBy('level', 'desc')
                      ->orderBy('funken_total_earned', 'desc');
            }

            $rankings = $query->take(50)->get();
        }

        return view('livewire.customer.gamification.game-games-component', [
            'rankings' => $rankings,
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

        $clampedLevel = max(1, min(10, (int) $level));
        $filename = $map[$clampedLevel] ?? 'funki_lvl_1_rags.png';

        return asset('shop/customer/gamification/models/images/original/' . $filename);
    }

    public function getActiveTitleName(CustomerGamification $profile): string
    {
        $activeKey = $profile->active_title;
        $titlesConfig = GameConfig::getTitles();

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
        $skins = GameConfig::getShipSkins();
        $activeKey = $profile->active_ship_skin ?: 'default';
        return $skins[$activeKey] ?? $skins['default'];
    }

    public function getHallAuraForProfile(CustomerGamification $profile): array
    {
        $auras = GameConfig::getHallAuras();
        $activeKey = $profile->active_hall_aura ?: 'default';
        return $auras[$activeKey] ?? $auras['default'];
    }

    public function getHeroMottoForProfile(CustomerGamification $profile): string
    {
        $mottos = GameConfig::getHeroMottos();
        $key = $profile->hero_motto_key ?: 'default';
        return $mottos[$key] ?? $mottos['default'];
    }

    public function getHeroMottoDetailForProfile(CustomerGamification $profile): array
    {
        $details = GameConfig::getHeroMottosDetails();
        $key = $profile->hero_motto_key ?: 'default';
        return $details[$key] ?? $details['default'];
    }

    public function getPrestigeBadgeForLevel(int $level): ?array
    {
        $milestones = GameConfig::getPrestigeMilestones();
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
        $titlesConfig = GameConfig::getTitles();
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
        $mottoDetail = $this->getHeroMottoDetailForProfile($profile);
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
            'ship_category_label' => $ship['category_label'] ?? 'Gleiter & Jäger',
            'ship_perk' => $ship['perk_summary'] ?? 'Standard-Konfiguration',
            'aura_key' => $profile->active_hall_aura ?: 'default',
            'aura_name' => $aura['name'] ?? 'Klassischer Eintrag',
            'aura_icon' => $aura['icon'] ?? '✨',
            'aura_badge' => $aura['badge'] ?? null,
            'aura_row_class' => $aura['row_class'] ?? '',
            'aura_badge_class' => $aura['badge_class'] ?? '',
            'motto' => $this->getHeroMottoForProfile($profile),
            'motto_title' => $mottoDetail['title'] ?? 'Funken-Wächter',
            'motto_buff' => $mottoDetail['buff_label'] ?? '+10% Magnet-Reichweite',
            'prestige_name' => $prestige['name'] ?? ($profile->level >= 10 ? 'Seelengott' : 'Aufstrebender Funke'),
            'prestige_icon' => $prestige['icon'] ?? ($profile->level >= 10 ? '⚡' : '✨'),
            'funken_total' => number_format((int) $profile->funken_total_earned, 0, ',', '.'),
            'highscore' => number_format((int) $profile->funkenflug_highscore, 0, ',', '.'),
            'trophies' => $this->getUnlockedTrophiesForProfile($profile),
        ];
    }

    public function selectTitle($titleKey, GamificationService $gameService)
    {
        if ($titleKey !== null && !is_string($titleKey)) {
            return;
        }

        $user = Auth::guard('customer')->user();
        if (!$user || !$gameService->isGameModeActive($user)) return;

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
        if (!$profile || !$profile->is_active) {
            return;
        }
        $gameService->refillEnergyIfDue($profile, $this->maxEnergy);

        $gameService->syncUnlockedRewards($profile);
        $profile->refresh();

        $this->level = $profile->level ?? 1;
        $this->balance = $profile->funken_balance;
        $this->energyBalance = $profile->energy_balance ?? 5;
        $this->leftHandedMode = $profile->left_handed_mode ?? false;

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

        $this->prestigeMilestones = GameConfig::getPrestigeMilestones();
        $hangar = $gameService->resolveHangarData($profile);
        $this->shipSkins = $hangar['skins'];
        $this->unlockedSkins = $hangar['unlocked_skins'];
        $this->activeShipSkinKey = $hangar['active_skin_key'];
        $this->activeShipSkin = $hangar['active_skin'];
        $this->hallAuras = $hangar['auras'];
        $this->unlockedAuras = $hangar['unlocked_auras'];
        $this->activeHallAuraKey = $hangar['active_aura_key'];
        $this->heroMottos = $hangar['mottos'];
        $this->heroMottosDetails = $hangar['mottos_details'] ?? GameConfig::getHeroMottosDetails();
        $this->activeMottoKey = $hangar['active_motto_key'];
        $this->activeMottoDetail = $hangar['active_motto_detail'] ?? ($this->heroMottosDetails[$this->activeMottoKey] ?? []);
        $this->isVipBuyer = $hangar['is_vip_buyer'];
        $this->battleshipUnlock = $hangar['battleship_unlock'] ?? [];
        $this->spaceStationsPlaceholder = $hangar['space_stations'] ?? GameConfig::getSpaceStationsPlaceholder();
    }

    public function buyOrEquipSkin(string $skinKey, GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if (!$user || !$gameService->isGameModeActive($user)) return;

        $res = $gameService->buyOrEquipShipSkin($user, $skinKey);
        $profile = $gameService->getProfile($user);
        $this->loadGamificationData($gameService, $profile);

        if ($res['success']) {
            $this->dispatch('sparks-awarded');
            $this->dispatch('ship-skin-changed', skin: $this->activeShipSkin);
            $this->dispatch('notify', ['type' => 'success', 'message' => $res['message']]);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $res['message']]);
        }
    }

    public function buyOrEquipAura(string $auraKey, GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if (!$user || !$gameService->isGameModeActive($user)) return;

        $res = $gameService->buyOrEquipHallAura($user, $auraKey);
        $profile = $gameService->getProfile($user);
        $this->loadGamificationData($gameService, $profile);

        if ($res['success']) {
            $this->dispatch('sparks-awarded');
            $this->dispatch('notify', ['type' => 'success', 'message' => $res['message']]);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $res['message']]);
        }
    }

    public function selectHeroMotto(string $mottoKey, GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if (!$user || !$gameService->isGameModeActive($user)) return;

        $res = $gameService->selectHeroMotto($user, $mottoKey);
        $profile = $gameService->getProfile($user);
        $this->loadGamificationData($gameService, $profile);

        if ($res['success']) {
            $this->dispatch('hero-motto-changed', motto: $this->activeMottoDetail);
            $this->dispatch('notify', ['type' => 'success', 'message' => $res['message']]);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $res['message']]);
        }
    }

    public function upgrade(GamificationService $gameService)
    {
        $user = Auth::guard('customer')->user();
        if (!$user || !$gameService->isGameModeActive($user)) return;
        $result = $gameService->upgradeLevel($user);

        if ($result['success']) {
            $profile = $gameService->getProfile($user);
            $this->loadGamificationData($gameService, $profile);

            $this->dispatch('funki-level-up', level: $result['new_level'], reward: $result['reward'], newModelPath: $this->modelPath, newImagePath: $this->imagePath);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Level Up!']);
        }
    }

    public function consumeEnergy(): bool
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            \Log::warning('consumeEnergy: SystemUser not found in customer guard');
            return false;
        }

        $throttleKey = 'game_consume_energy_' . $user->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Zu viele Anfragen. Bitte kurz warten.']);
            return false;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 3);

        $consumed = \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            $profile = CustomerGamification::where('customer_id', $user->id)->lockForUpdate()->first();
            if (!$profile || !$profile->is_active) {
                return false;
            }

            $gameService = app(GamificationService::class);
            $gameService->refillEnergyIfDue($profile, $this->maxEnergy);

            if ($profile->energy_balance <= 0) {
                return false;
            }

            if ($profile->energy_balance >= $this->maxEnergy || !$profile->last_energy_refill_at) {
                $profile->last_energy_refill_at = now();
            }
            $profile->energy_balance -= 1;
            $profile->save();

            $gameService->incrementTitleProgress($profile, 'spieler');
            return true;
        });

        if ($consumed) {
            // Aktive Spiel-Sitzung serverseitig registrieren (Anti-Cheat & Anti-Replay)
            session([
                'active_game_session' => [
                    'user_id' => $user->id,
                    'started_at' => now()->timestamp,
                    'token' => \Illuminate\Support\Str::random(32),
                ]
            ]);

            $this->updateEnergyState();
            $this->dispatch('energy-updated');
            return true;
        }

        $this->dispatch('notify', ['type' => 'error', 'message' => 'Nicht genug Seelen-Energie!']);
        return false;
    }

    public function rewardGameSparks($amount)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        // Anti-Cheat: Nur mit registrierter Spielsitzung belohnen
        $gameSession = session('active_game_session');
        if (!$gameSession || empty($gameSession['started_at'])) {
            \Log::warning("Manipulationsversuch: rewardGameSparks ohne aktive Spielsitzung (User: {$user->id})");
            SystemSecurityService::logEvent('security:game_cheat_attempt', 'warning', 'Gamification: rewardGameSparks ohne aktive Spielsitzung aufgerufen.', ['user_id' => $user->id, 'amount' => $amount]);
            return;
        }

        if (($gameSession['user_id'] ?? null) !== $user->id) {
            \Log::warning("Manipulationsversuch: Ungültige User-ID in Spielsitzung (User: {$user->id})");
            SystemSecurityService::logEvent('security:game_cheat_attempt', 'error', 'Gamification: Session-User-Mismatch bei rewardGameSparks.', ['user_id' => $user->id]);
            session()->forget('active_game_session');
            return;
        }

        $elapsed = now()->timestamp - (int)$gameSession['started_at'];
        // Mindestspieldauer für Kristall-Kollaps prüfen
        if ($elapsed < 5) {
            \Log::warning("Manipulationsversuch: rewardGameSparks zu schnell eingereicht ({$elapsed}s) von User {$user->id}");
            SystemSecurityService::logEvent('security:game_cheat_attempt', 'warning', "Gamification: Kristall-Kollaps unplausibel schnell beendet ({$elapsed}s).", ['user_id' => $user->id, 'elapsed' => $elapsed]);
            session()->forget('active_game_session');
            return;
        }

        // Sitzung sofort invalidieren (Einmal-Gültigkeit)
        session()->forget('active_game_session');

        // Plausibilitätsprüfung für Punkte/Funken
        $safeAmount = max(0, min(intval($amount), 50));

        if ($safeAmount > 0) {
            $awarded = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $safeAmount) {
                $profile = CustomerGamification::where('customer_id', $user->id)->lockForUpdate()->first();
                if (!$profile || !$profile->is_active) {
                    return false;
                }
                $profile->funken_balance += $safeAmount;
                $profile->funken_total_earned += $safeAmount;
                $progress = is_array($profile->titles_progress) ? $profile->titles_progress : [];
                $progress['funkenkoenig'] = (int) $profile->funken_total_earned;
                $profile->titles_progress = $progress;
                $profile->save();
                return true;
            });

            if ($awarded) {
                $freshProfile = CustomerGamification::where('customer_id', $user->id)->first();
                if ($freshProfile) {
                    $this->balance = $freshProfile->funken_balance;
                }
                $this->dispatch('sparks-awarded');
            }
        }
    }

    public function saveGameRecord($distance, $funkenCollected)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        // Anti-Cheat: Nur mit registrierter Spielsitzung speichern
        $gameSession = session('active_game_session');
        if (!$gameSession || empty($gameSession['started_at'])) {
            \Log::warning("Manipulationsversuch: saveGameRecord ohne aktive Spielsitzung (User: {$user->id})");
            SystemSecurityService::logEvent('security:game_cheat_attempt', 'warning', 'Gamification: saveGameRecord ohne aktive Spielsitzung aufgerufen.', ['user_id' => $user->id, 'distance' => $distance, 'funken' => $funkenCollected]);
            return;
        }

        if (($gameSession['user_id'] ?? null) !== $user->id) {
            \Log::warning("Manipulationsversuch: Ungültige User-ID in Spielsitzung (User: {$user->id})");
            SystemSecurityService::logEvent('security:game_cheat_attempt', 'error', 'Gamification: Session-User-Mismatch bei saveGameRecord.', ['user_id' => $user->id]);
            session()->forget('active_game_session');
            return;
        }

        $elapsed = now()->timestamp - (int)$gameSession['started_at'];
        if ($elapsed < 2) {
            \Log::warning("Manipulationsversuch: saveGameRecord zu schnell eingereicht ({$elapsed}s) von User {$user->id}");
            SystemSecurityService::logEvent('security:game_cheat_attempt', 'warning', "Gamification: Funkenflug unplausibel schnell beendet ({$elapsed}s).", ['user_id' => $user->id, 'elapsed' => $elapsed]);
            session()->forget('active_game_session');
            return;
        }

        // Sitzung sofort invalidieren (Einmal-Gültigkeit)
        session()->forget('active_game_session');

        // Physikalische Plausibilitätsprüfung für Funkenflug-Distanz (inkl. Skin-Bonus bis +40m pro Funke)
        $rawDistance = max(0, intval($distance));
        $maxPossibleDistance = max(140, $elapsed * 85);
        $safeDistance = min($rawDistance, $maxPossibleDistance);
        $safeDistance = min($safeDistance, 25000);

        // Plausibilitätsprüfung für gesammelte Funken
        $rawSparks = max(0, intval($funkenCollected));
        $maxPossibleSparks = (int) ceil($safeDistance / 15) + 8;
        $safeSparks = min($rawSparks, $maxPossibleSparks, 150);

        $profile = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $safeSparks, $safeDistance) {
            $lockedProfile = CustomerGamification::where('customer_id', $user->id)->lockForUpdate()->first();
            if (!$lockedProfile || !$lockedProfile->is_active) {
                return null;
            }
            if ($safeSparks > 0) {
                $lockedProfile->funken_balance += $safeSparks;
                $lockedProfile->funken_total_earned += $safeSparks;
                $progress = is_array($lockedProfile->titles_progress) ? $lockedProfile->titles_progress : [];
                $progress['funkenkoenig'] = (int) $lockedProfile->funken_total_earned;
                $lockedProfile->titles_progress = $progress;
            }

            if ($safeDistance > ($lockedProfile->funkenflug_highscore ?? 0)) {
                $lockedProfile->funkenflug_highscore = $safeDistance;
            }

            $lockedProfile->save();
            return $lockedProfile;
        });

        if ($profile) {
            if ($safeSparks > 0) {
                $this->balance = $profile->funken_balance;
                $this->dispatch('sparks-awarded');
            }

            $this->personalHighscoreFF = $profile->funkenflug_highscore;
            $this->globalHighscoreFF = max($this->globalHighscoreFF, $safeDistance);
        }
    }

    public function updatedLeftHandedMode($value)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile && $profile->is_active) {
            $profile->left_handed_mode = (bool) $value;
            $profile->save();
        }
    }
}

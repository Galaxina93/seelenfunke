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
            $gameService = app(GamificationService::class);
            $this->loadGamificationData($gameService, $profile);
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
            $this->balance = $profile->funken_balance;
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
        return view('livewire.customer.gamification.game-games-component');
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

        // Physikalische Plausibilitätsprüfung für Funkenflug-Distanz
        $rawDistance = max(0, intval($distance));
        $maxPossibleDistance = max(100, $elapsed * 65);
        $safeDistance = min($rawDistance, $maxPossibleDistance);
        $safeDistance = min($safeDistance, 10000);

        // Plausibilitätsprüfung für gesammelte Funken
        $rawSparks = max(0, intval($funkenCollected));
        $maxPossibleSparks = (int) ceil($safeDistance / 20) + 5;
        $safeSparks = min($rawSparks, $maxPossibleSparks, 100);

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
        if ($profile) {
            $profile->left_handed_mode = (bool) $value;
            $profile->save();
        }
    }
}

<?php

namespace App\Livewire\Customer\Gamification;

use App\Models\Customer\CustomerGamification;
use App\Models\Marketing\MarketingVoucher;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\GameConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Carbon\Carbon;

#[Layout('components.layouts.customer_layout')]
class GameGamesComponent extends Component
{
    public $currentEnergy = 0;
    public $timeUntilNextEnergy = '';
    public $personalHighscoreFF = 0;
    public $globalHighscoreFF = 0;
    public $hasOptedIn = false;

    public $currentRankName = 'Funken-Novize';
    public $level = 1;

    public $modelPath;
    public $imagePath;
    public $titlesData = [];

    public $balance = 0;
    public $energyBalance = 5;
    public $maxEnergy = 5;
    public $progressPercentage = 0;
    public $missingSparks = 0;
    public $upgradeCost = 0;
    public $canUpgrade = false;
    public $isMaxLevel = false;

    public $activeTitleKey = null;

    public $unlockedCoupons = [];
    public $milestonesConfig = [];
    public $leftHandedMode = false;

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if ($user) {
            $profile = CustomerGamification::where('customer_id', $user->id)->first();
            if ($profile && $profile->is_active) {
                $this->hasOptedIn = true;
                $gameService = app(GamificationService::class);
                $this->loadGamificationData($gameService, $profile);
            }
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
                'active_title' => 'apprentice'
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

    #[On('energy-updated')]
    public function updateEnergyState()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile) {
            $this->currentEnergy = $profile->energy_balance;

            // Format time until next energy if balancing is enabled (assuming typical cron setup)
            // Just a placeholder for the frontend if energy is 0
            if ($this->currentEnergy <= 0) {
                // Next refill is exactly 3 hours after the last refill timestamp.
                $lastRefill = $profile->last_energy_refill_at ? \Carbon\Carbon::parse($profile->last_energy_refill_at) : now();
                $nextRefill = $lastRefill->copy()->addHours(3);
                $diff = now()->diffInMinutes($nextRefill, false);
                $this->timeUntilNextEnergy = $diff > 0 ? (int)$diff : 0;
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
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = $gameService->getProfile($user);
        $titlesData = $gameService->evaluateTitles($profile);

        $isUnlocked = false;

        if ($titleKey === null || $titleKey === 'apprentice') {
            $isUnlocked = true;
        } elseif ($titleKey === 'mega_title') {
            $isUnlocked = ($titlesData['mega_title']['rank'] ?? 0) > 0;
        } elseif (isset($titlesData['titles'][$titleKey])) {
            $tier = $titlesData['titles'][$titleKey]['tier'] ?? 'grau';
            $isUnlocked = ($tier !== 'grau');
        }

        if (!$isUnlocked) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Dieser Titel ist noch nicht freigeschaltet!']);
            return;
        }

        $profile->update(['active_title' => $titleKey]);

        $this->loadGamificationData($gameService, $profile);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Titel erfolgreich ausgerüstet!']);
    }

    public function loadGamificationData(GamificationService $gameService, $profile)
    {
        $now = Carbon::now();
        $lastRefill = $profile->last_energy_refill_at ? Carbon::parse($profile->last_energy_refill_at) : null;
        if (!$lastRefill || $lastRefill->diffInHours($now) >= 3) {
            $profile->energy_balance = $this->maxEnergy;
            $profile->last_energy_refill_at = $now;
            $profile->save();
        }

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
                    $isUsed = ($dbVoucher->usage_limit !== null && $dbVoucher->used_count >= $dbVoucher->usage_limit) || !$dbVoucher->is_active;
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
        $this->activeTitleKey = $profile->active_title;

        if ($this->activeTitleKey === 'mega_title') {
            $this->currentRankName = $this->titlesData['mega_title']['name'] ?? 'Ein Funke im Wind';
        } elseif ($this->activeTitleKey && isset($this->titlesData['titles'][$this->activeTitleKey])) {
            $tier = $this->titlesData['titles'][$this->activeTitleKey]['tier'];
            if ($tier !== 'grau') {
                $this->currentRankName = $this->titlesData['titles'][$this->activeTitleKey]['tier_name'];
            } else {
                $this->currentRankName = $this->getRankName($this->level);
            }
        } else {
            $this->currentRankName = $this->getRankName($this->level);
        }
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

        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        if ($profile && $profile->energy_balance > 0) {
            $profile->energy_balance -= 1;
            $profile->save();

            $gameService = app(GamificationService::class);
            $gameService->incrementTitleProgress($profile, 'spieler');

            // Aktive Spiel-Sitzung serverseitig registrieren (Anti-Cheat & Anti-Replay)
            session([
                'active_game_session' => [
                    'user_id' => $user->id,
                    'started_at' => now()->timestamp,
                    'token' => \Illuminate\Support\Str::random(32),
                ]
            ]);

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
            return;
        }

        if (($gameSession['user_id'] ?? null) !== $user->id) {
            \Log::warning("Manipulationsversuch: Ungültige User-ID in Spielsitzung (User: {$user->id})");
            session()->forget('active_game_session');
            return;
        }

        $elapsed = now()->timestamp - (int)$gameSession['started_at'];
        // Mindestspieldauer für Kristall-Kollaps prüfen
        if ($elapsed < 5) {
            \Log::warning("Manipulationsversuch: rewardGameSparks zu schnell eingereicht ({$elapsed}s) von User {$user->id}");
            session()->forget('active_game_session');
            return;
        }

        // Sitzung sofort invalidieren (Einmal-Gültigkeit)
        session()->forget('active_game_session');

        // Plausibilitätsprüfung für Punkte/Funken
        $safeAmount = max(0, min(intval($amount), 50));

        if ($safeAmount > 0) {
            $profile = CustomerGamification::where('customer_id', $user->id)->first();

            if ($profile) {
                $profile->funken_balance += $safeAmount;
                $profile->funken_total_earned += $safeAmount;
                $profile->save();

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
            return;
        }

        if (($gameSession['user_id'] ?? null) !== $user->id) {
            \Log::warning("Manipulationsversuch: Ungültige User-ID in Spielsitzung (User: {$user->id})");
            session()->forget('active_game_session');
            return;
        }

        $elapsed = now()->timestamp - (int)$gameSession['started_at'];
        if ($elapsed < 2) {
            \Log::warning("Manipulationsversuch: saveGameRecord zu schnell eingereicht ({$elapsed}s) von User {$user->id}");
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

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile) {
            if ($safeSparks > 0) {
                $profile->funken_balance += $safeSparks;
                $profile->funken_total_earned += $safeSparks;
            }

            if ($safeDistance > ($profile->funkenflug_highscore ?? 0)) {
                $profile->funkenflug_highscore = $safeDistance;
            }

            $profile->save();

            if ($safeSparks > 0) {
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

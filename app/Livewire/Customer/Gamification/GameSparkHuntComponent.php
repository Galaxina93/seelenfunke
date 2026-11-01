<?php

namespace App\Livewire\Customer\Gamification;

use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GameSparkHuntComponent extends Component
{
    #[Locked]
    public $isVisible = false;

    #[Locked]
    public int $posTop = 28;

    #[Locked]
    public int $posLeft = 68;

    #[Locked]
    public string $sparkToken = '';

    public function mount(): void
    {
        if (!Auth::guard('customer')->check()) {
            return;
        }

        $user = Auth::guard('customer')->user();
        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        if (!$profile || !$profile->is_active) {
            return;
        }

        // Tageslimit Reset (falls neuer Tag)
        if ($profile->last_spark_collection_date && !$profile->last_spark_collection_date->isToday()) {
            $profile->sparks_collected_today = 0;
            $profile->save();
        }

        // Tageslimit bereits erreicht?
        if ((int) $profile->sparks_collected_today >= GameConfig::DAILY_SPARK_LIMIT) {
            return;
        }

        // Reload- & Bot-Schutz: Nach einem Fund gilt eine serverseitige Abklingzeit (Cooldown)
        $cooldownKey = 'spark_hunt_cooldown_' . $user->id;
        if (Cache::has($cooldownKey)) {
            return;
        }

        // Seltene Spawn-Chance (20 %), damit maximal 50 Test-Mounts zuverlässig triggern,
        // im echten Betrieb aber durch den 180s-Cooldown nach jedem Fund sehr selten bleibt.
        if (mt_rand(1, 100) <= 20) {
            $token = Str::random(40);
            $this->isVisible = true;
            $this->posTop = mt_rand(14, 68);
            $this->posLeft = mt_rand(14, 82);
            $this->sparkToken = $token;

            session([
                'spark_hunt_token'      => $token,
                'spark_hunt_user_id'    => (string) $user->id,
                'spark_hunt_spawned_at' => microtime(true),
            ]);
        }
    }

    public function collectSpark(GamificationService $gamificationService): void
    {
        if (!$this->isVisible) {
            return;
        }

        $user = Auth::guard('customer')->user();
        if (!$user) {
            $this->isVisible = false;
            return;
        }

        // 1. Anti-Abuse Rate Limiter (max. 5 Versuche pro Minute)
        $throttleKey = 'spark_hunt_' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->isVisible = false;
            return;
        }
        RateLimiter::hit($throttleKey, 60);

        // 2. Kryptographische Einmal-Token-Prüfung (verhindert Replay-Attacks & manipulierte Requests)
        $sessionToken = (string) session('spark_hunt_token', '');
        $sessionUserId = (string) session('spark_hunt_user_id', '');

        if (
            $sessionToken === ''
            || $this->sparkToken === ''
            || !hash_equals($sessionToken, $this->sparkToken)
            || $sessionUserId !== (string) $user->id
        ) {
            $this->isVisible = false;
            $this->sparkToken = '';
            session()->forget(['spark_hunt_token', 'spark_hunt_user_id', 'spark_hunt_spawned_at']);
            return;
        }

        // Token sofort verbrauchen (Single-Use Nonce)
        session()->forget(['spark_hunt_token', 'spark_hunt_user_id', 'spark_hunt_spawned_at']);
        $this->isVisible = false;
        $this->sparkToken = '';

        // 3. Serverseitigen Cooldown aktivieren (blockiert Page-Reload-Spam / F5-Farming)
        Cache::put('spark_hunt_cooldown_' . $user->id, now()->timestamp, GameConfig::SPARK_COOLDOWN_SECONDS);

        // 4. Funken gutschreiben & Level synchronisieren
        $gamificationService->addFunken($user, GameConfig::SPARK_HUNT_REWARD, 'website_hunt');

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Geheimer Seelenfunke gefunden! +' . GameConfig::SPARK_HUNT_REWARD . ' ✨']);
        $this->dispatch('sparks-awarded');
    }

    public function render()
    {
        return view('livewire.customer.gamification.game-spark-hunt-component');
    }
}

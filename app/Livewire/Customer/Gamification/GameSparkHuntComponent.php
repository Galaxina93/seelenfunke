<?php

namespace App\Livewire\Customer\Gamification;

use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

    public function mount()
    {
        // Nur wenn eingeloggt
        if (Auth::guard('customer')->check()) {
            $user = Auth::guard('customer')->user();
            $profile = CustomerGamification::where('customer_id', $user->id)->first();

            // Nur wenn Modus aktiv
            if ($profile && $profile->is_active) {

                // Tageslimit Reset (falls neuer Tag)
                if ($profile->last_spark_collection_date && !$profile->last_spark_collection_date->isToday()) {
                    $profile->sparks_collected_today = 0;
                    $profile->save();
                }

                // Darf noch gesammelt werden?
                if ($profile->sparks_collected_today < GameConfig::DAILY_SPARK_LIMIT) {

                    // 30% Chance, dass ein Funke auf dieser Seite spawnt
                    if (mt_rand(1, 100) <= 30) {
                        $this->isVisible = true;
                        $this->posTop = mt_rand(12, 68);
                        $this->posLeft = mt_rand(12, 82);
                        session(['spark_hunt_token' => $user->id . '_' . now()->timestamp]);
                    }
                }
            }
        }
    }

    public function collectSpark(GamificationService $gamificationService)
    {
        if (!$this->isVisible) {
            return;
        }

        $user = Auth::guard('customer')->user();
        if ($user) {
            // Anti-Abuse Rate Limiter
            $throttleKey = 'spark_hunt_' . $user->id;
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                return;
            }
            RateLimiter::hit($throttleKey, 60);

            session()->forget('spark_hunt_token');

            $this->isVisible = false;
            $gamificationService->addFunken($user, 1, 'website_hunt');

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Geheimer Seelenfunke gefunden! +1 ✨']);
            $this->dispatch('sparks-awarded');
        }
    }

    public function render()
    {
        return view('livewire.customer.gamification.game-spark-hunt-component');
    }
}

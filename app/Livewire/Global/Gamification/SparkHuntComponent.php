<?php

namespace App\Livewire\Global\Gamification;

use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SparkHuntComponent extends Component
{
    public $isVisible = false;

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
                    }
                }
            }
        }
    }

    public function collectSpark(GamificationService $gamificationService)
    {
        if (!$this->isVisible) return;

        $user = Auth::guard('customer')->user();
        if ($user) {
            $gamificationService->addFunken($user, 1, 'website_hunt');
            $this->isVisible = false;

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Geheimer Funke gefunden! +1 ✨']);
            $this->dispatch('sparks-awarded');
        }
    }

    public function render()
    {
        return view('livewire.global.gamification.spark-hunt-component');
    }
}

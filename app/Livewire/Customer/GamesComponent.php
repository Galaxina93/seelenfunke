<?php

namespace App\Livewire\Customer;

use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.customer_layout')]
class GamesComponent extends Component
{
    public function render()
    {
        return view('livewire.customer.games-component');
    }

    /**
     * Keine Parameter-Injektion mehr, sondern sicheres Auflösen über app()
     */
    public function consumeEnergy()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return false;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        if ($profile && $profile->energy_balance > 0) {
            // 1. Energie abziehen
            $profile->energy_balance -= 1;
            $profile->save();

            // 2. Den Titel für "Spiele gespielt" hochzählen
            $gameService = app(GamificationService::class);
            $gameService->incrementTitleProgress($profile, 'spieler');

            // 3. Events abfeuern (für HUD Updates)
            $this->dispatch('energy-updated');

            return true;
        }

        $this->dispatch('notify', ['type' => 'error', 'message' => 'Nicht genug Seelen-Energie!']);
        return false;
    }

    public function rewardGameSparks($amount)
    {
        $safeAmount = min(intval($amount), 50);

        if ($safeAmount > 0) {
            $user = Auth::guard('customer')->user();
            $profile = CustomerGamification::where('customer_id', $user->id)->first();

            if ($profile) {
                // Funken gutschreiben
                $profile->funken_balance += $safeAmount;
                $profile->funken_total_earned += $safeAmount;
                $profile->save();

                $this->dispatch('sparks-awarded');
            }
        }
    }
}

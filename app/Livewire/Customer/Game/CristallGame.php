<?php

namespace App\Livewire\Customer\Game;

use App\Models\Customer\CustomerGamification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CristallGame extends Component
{
    public int $level;
    public int $nextVoucherLevel;
    public int $energyBalance;
    public int $maxEnergy;

    // Wir übergeben die aktuellen Stats vom Dashboard an das Spiel
    public function mount($level = 1, $nextVoucherLevel = 5, $energyBalance = 5, $maxEnergy = 5)
    {
        $this->level = $level;
        $this->nextVoucherLevel = $nextVoucherLevel;
        $this->energyBalance = $energyBalance;
        $this->maxEnergy = $maxEnergy;
    }

    public function consumeEnergy()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return false;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        if ($profile && $profile->energy_balance > 0) {
            $profile->energy_balance -= 1;
            $profile->save();

            $this->energyBalance = $profile->energy_balance;

            // Dem Dashboard mitteilen, dass Energie verbraucht wurde (für die Header-Anzeige)
            $this->dispatch('energy-updated');

            return true;
        }

        $this->dispatch('notify', ['type' => 'error', 'message' => 'Nicht genug Seelen-Energie!']);
        return false;
    }

    public function rewardGameSparks($amount)
    {
        $safeAmount = min(intval($amount), 50); // Max 50 Funken pro Spiel

        if ($safeAmount > 0) {
            $user = Auth::guard('customer')->user();
            $profile = CustomerGamification::where('customer_id', $user->id)->first();

            if ($profile) {
                $profile->funken_balance += $safeAmount;
                $profile->funken_total_earned += $safeAmount;
                $profile->save();

                // Dem Dashboard mitteilen, dass Funken gesammelt wurden -> HUD Update
                $this->dispatch('sparks-awarded');
            }
        }
    }

    public function render()
    {
        return view('livewire.customer.game.cristall-game');
    }
}

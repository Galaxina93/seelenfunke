<?php

namespace App\Livewire\Customer;

use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

#[Layout('components.layouts.customer_layout')]
class GamesComponent extends Component
{
    public $currentEnergy = 0;
    public $timeUntilNextEnergy = '';
    public $personalHighscoreFF = 0;
    public $globalHighscoreFF = 0;

    public function mount()
    {
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
                // Determine next top of the hour or however system replenishes
                $now = now();
                $nextHour = now()->addHour()->startOfHour();
                $diff = $now->diffInMinutes($nextHour);
                $this->timeUntilNextEnergy = $diff;
            }
            // Fetch highscores for Funkenflug
            $this->personalHighscoreFF = $profile->funkenflug_highscore ?? 0;
            $this->globalHighscoreFF = CustomerGamification::max('funkenflug_highscore') ?? 0;
        }
    }

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
        if (!$user) {
            \Log::warning('consumeEnergy: User not found in customer guard');
            return false;
        }

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

            \Log::info('consumeEnergy: Energy successfully consumed. Remaining: ' . $profile->energy_balance);
            return true;
        }

        \Log::warning('consumeEnergy: Profile missing or energy is 0', [
            'user_id' => $user->id,
            'profile_found' => !!$profile,
            'energy_balance' => $profile ? $profile->energy_balance : 'N/A'
        ]);

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

    public function saveGameRecord($distance, $funkenCollected)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile) {
            $safeAmount = min(intval($funkenCollected), 500); // Sicherheitslimit
            
            if ($safeAmount > 0) {
                $profile->funken_balance += $safeAmount;
                $profile->funken_total_earned += $safeAmount;
            }

            // Highscore für Funkenflug basierend auf Distanz aktualisieren
            $dist = intval($distance);
            if ($dist > ($profile->funkenflug_highscore ?? 0)) {
                $profile->funkenflug_highscore = $dist;
            }
            
            $profile->save();
            
            if ($safeAmount > 0) {
                $this->dispatch('sparks-awarded');
            }

            // Sync Frontend State
            $this->personalHighscoreFF = $profile->funkenflug_highscore;
            $this->globalHighscoreFF = max($this->globalHighscoreFF, $dist);
        }
    }
}

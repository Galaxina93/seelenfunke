<?php

namespace App\Livewire\Customer;

use App\Models\Customer\CustomerGamification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.customer_layout')]
class GlobalRankingComponent extends Component
{
    public $hasOptedIn = false;

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $profile = CustomerGamification::where('customer_id', $user->id)->first();
        if ($profile) {
            $this->hasOptedIn = $profile->ranking_opt_in;
        }
    }

    public function optIn()
    {
        $user = Auth::guard('customer')->user();
        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        if ($profile) {
            $profile->update(['ranking_opt_in' => true]);
            $this->hasOptedIn = true;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Willkommen in der Halle der Legenden!']);
        }
    }

    public function render()
    {
        $rankings = collect();

        // Rangliste nur laden, wenn der Kunde eingewilligt hat (Performance & Datenschutz)
        if ($this->hasOptedIn) {
            $rankings = CustomerGamification::with('customer')
                ->where('is_active', true)
                ->where('ranking_opt_in', true)
                ->orderBy('level', 'desc')
                ->orderBy('funken_total_earned', 'desc')
                ->take(50)
                ->get();
        }

        return view('livewire.customer.global-ranking-component', [
            'rankings' => $rankings
        ]);
    }
}

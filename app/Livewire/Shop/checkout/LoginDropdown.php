<?php

namespace App\Livewire\Shop\checkout;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LoginDropdown extends Component
{
    public function logout()
    {
        // Guard dynamisch aus dem User-Model holen
        $guard = (new \App\Models\User)->getGuard();

        Auth::guard($guard)->logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('home');
    }

    public function render()
    {
        // Guard dynamisch ermitteln
        $guard = (new \App\Models\User)->getGuard();

        // User basierend auf dem ermittelten Guard laden
        $user = Auth::guard($guard)->user();

        return view('livewire.auth.login-dropdown', [
            'user' => $user,
            'guard' => $guard, // Guard an die View übergeben für dynamische Routen
        ]);
    }
}

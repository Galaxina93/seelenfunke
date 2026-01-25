<?php

namespace App\Livewire\Global\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProfileDropdown extends Component
{
    public function render()
    {
        $guard = (new \App\Models\User)->getGuard();
        $user = Auth::guard($guard)->user();

        return view('livewire.profile.profile-dropdown', compact('guard', 'user'));
    }

    public function logout()
    {
        $guard = (new \App\Models\User)->getGuard();
        Auth::guard($guard)->logout();
        return redirect('/');
    }

}

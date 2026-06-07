<?php

namespace App\Livewire\Customer;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.guest')]
class CustomerForcePasswordChange extends Component
{
    public string $password = '';
    public string $passwordConfirm = '';

    public function mount()
    {
        // Safety check: if user is not logged in or doesn't need to change password, redirect.
        if (!Auth::guard('customer')->check()) {
            return redirect()->route('login');
        }

        if (!Auth::guard('customer')->user()->needs_password_change) {
            return redirect()->route('customer.dashboard');
        }
    }

    public function render()
    {
        return view('livewire.customer.customer-force-password-change');
    }

    public function submit()
    {
        $this->validate([
            'password' => 'required|min:8',
            'passwordConfirm' => 'required',
        ], [
            'password.required' => 'Bitte gib ein Passwort ein.',
            'password.min' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
            'passwordConfirm.required' => 'Bitte bestätige dein Passwort.',
        ]);

        if ($this->password !== $this->passwordConfirm) {
            $this->addError('passwordConfirm', 'Die Passwörter stimmen nicht überein.');
            return;
        }

        $customer = Auth::guard('customer')->user();
        
        $customer->update([
            'password' => Hash::make($this->password),
            'needs_password_change' => false,
            'temporary_password' => null,
        ]);

        session()->forget('force_password_change_customer_id');

        session()->flash('success', 'Dein Passwort wurde erfolgreich aktualisiert.');

        return redirect()->route('customer.dashboard');
    }

    public function logout()
    {
        Auth::guard('customer')->logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('login');
    }
}

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
        if (!Auth::guard('customer')->check()) {
            abort(403);
        }

        $customer = Auth::guard('customer')->user();
        if (!$customer || !$customer->needs_password_change) {
            return redirect()->route('customer.dashboard');
        }

        $this->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
            'passwordConfirm' => 'required|string|same:password',
        ], [
            'password.required' => 'Bitte gib ein Passwort ein.',
            'password.min' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
            'password.regex' => 'Das Passwort muss mindestens einen Großbuchstaben, einen Kleinbuchstaben und eine Zahl enthalten.',
            'passwordConfirm.required' => 'Bitte bestätige dein Passwort.',
            'passwordConfirm.same' => 'Die Passwörter stimmen nicht überein.',
        ]);

        $customer->update([
            'password' => Hash::make($this->password),
            'needs_password_change' => false,
            'temporary_password' => null,
        ]);
        $customer->setRememberToken(\Illuminate\Support\Str::random(60));
        $customer->save();

        try {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $customer->id)
                ->where('id', '!=', session()->getId())
                ->delete();
        } catch (\Throwable $e) {
            // Ignore if session driver is not database
        }

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

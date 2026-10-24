<?php

namespace App\Livewire\Auth;

use App\Models\System\SystemPasswordResetToken;
use App\Services\System\SystemSecurityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AuthPasswordReset extends Component
{
    public string $email = '';
    public string $password = '';
    public string $passwordConfirm = '';
    #[Locked]
    public string $guard = '';
    #[Locked]
    public string $token = '';

    public function mount($token)
    {
        $this->token = (string) $token;
        $segment = request()->segment(1);
        if (in_array($segment, ['admin', 'employee', 'customer'], true)) {
            $this->guard = $segment;
        }
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.auth.auth-password-reset');
    }

    public function submit()
    {
        $ip = request()->ip();
        $throttleKey = 'password_reset_submit_' . $ip;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            session()->flash('error', "Zu viele Versuche. Bitte warte {$seconds} Sekunden.");
            return;
        }

        $this->validate([
            'email' => 'required|email|max:255',
            'password' => ['required', 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
            'passwordConfirm' => 'required',
        ], [
            'password.regex' => 'Das Passwort muss mindestens einen Großbuchstaben, einen Kleinbuchstaben und eine Zahl enthalten.',
        ]);

        if ($this->password != $this->passwordConfirm)
        {
            session()->flash('error', 'Die Passwörter stimmen nicht überein.');
            return;
        }

        $segment = request()->segment(1);
        $expectedGuard = in_array($this->guard, ['admin', 'employee', 'customer'], true)
            ? $this->guard
            : (in_array($segment, ['admin', 'employee', 'customer'], true) ? $segment : null);

        $tokenQuery = SystemPasswordResetToken::where('email', $this->email);
        if ($expectedGuard) {
            $tokenQuery->where('guard', $expectedGuard);
        }
        $passwordResetToken = $tokenQuery->first();

        // Überprüfen Sie, ob der Datensatz gefunden wurde
        if (!$passwordResetToken || !Hash::check($this->token, $passwordResetToken->token))
        {
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 300);
            SystemSecurityService::logEvent(
                'security:password_reset_failed',
                'Ungültiger Passwort-Reset-Token',
                "Fehlgeschlagener Passwort-Reset für '{$this->email}' von IP {$ip}.",
                'warning',
                ['ip_address' => $ip, 'email' => $this->email]
            );
            session()->flash('error', 'Ungültiger Passwort-Reset-Token.');
            return;
        }

        // Überprüfen Sie, ob der Datensatz vorhanden ist und ob das created_at älter als 60 Minuten ist
        if (Carbon::parse($passwordResetToken->created_at)->addMinutes(60)->isPast()) {
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 300);
            session()->flash('error', 'Der Link ist abgelaufen.');
            return;
        }

        $this->guard = $passwordResetToken->guard;

        $userModel = (new \App\Models\System\SystemUser)->getUserModelByGuard($this->guard);
        $user = $userModel::where('email', $this->email)->first();

        if (!$user) {
            session()->flash('error', 'Es ist ein Problem aufgetreten.');
            return;
        }

        // Aktualisieren des Passworts des Benutzers
        $user->password = Hash::make($this->password);
        if ($this->guard === 'customer') {
            $user->needs_password_change = false;
            $user->temporary_password = null;
        }
        $user->setRememberToken(\Illuminate\Support\Str::random(60));
        $user->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        // Alle bestehenden Sessions dieses Benutzers invalidieren
        if (config('session.driver') === 'database') {
            \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        // Löschen des Passwort-Reset-Tokens
        SystemPasswordResetToken::where('email', $this->email)->where('guard', $this->guard)->delete();

        \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

        // Zurück zum Login
        return redirect()->route('login');
    }

}

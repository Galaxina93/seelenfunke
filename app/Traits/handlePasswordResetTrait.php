<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\System\SystemPasswordResetToken;

trait handlePasswordResetTrait
{

    public function sendLink(): void
    {
        $this->validate([
            'email' => 'required|email',
        ]);

        $throttleKey = 'password_reset_request_' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            session()->flash('error', "Zu viele Anfragen. Bitte warte {$seconds} Sekunden, bevor du es erneut versuchst.");
            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 300);

        $guardsToCheck = ['admin', 'employee', 'customer'];
        $foundGuard = null;
        $user = null;

        foreach ($guardsToCheck as $g) {
            $userModel = (new \App\Models\System\SystemUser)->getUserModelByGuard($g);
            $candidate = $userModel::where('email', $this->email)->first();
            if ($candidate) {
                $foundGuard = $g;
                $user = $candidate;
                break;
            }
        }

        if (!$user) {
            // Einheitliche Meldung zur Verhinderung von User-Enumeration
            $this->email = "";
            session()->flash('status', 'Falls ein Konto mit dieser E-Mail-Adresse existiert, haben wir einen Link zum Zurücksetzen des Passworts zugesandt.');
            return;
        }

        $this->guard = $foundGuard;

        // Prüfe ob schon ein gültiger Reset Token vorhanden ist
        $passwordResetToken = SystemPasswordResetToken::where('email', $this->email)
            ->where('guard', $this->guard)
            ->first();

        // Sollte der PassworResetToken existieren aber die Zeit von 2 Minuten ist noch nicht vergangen, dann return
        // (Einheitliche Statusmeldung verhindert User-Enumeration)
        if ($passwordResetToken != null && !Carbon::parse($passwordResetToken->created_at)->addMinutes(2)->isPast()) {
            $this->email = "";
            session()->flash('status', 'Falls ein Konto mit dieser E-Mail-Adresse existiert, haben wir einen Link zum Zurücksetzen des Passworts zugesandt.');
            return;
        }

        $token = $this->generateToken();
        $this->updatePasswordResetToken($this->email, $this->guard, $token);

        $emailData = [
            'to' => $this->email,
            'subject' => 'Passwort vergessen',
            'viewTemplate' => 'global.mails.forgot-password',
            'reset_link' => url('/' . $this->guard . '/password-reset/' . $token), // Passwort-Reset-Link
        ];

        $this->sendMail($emailData);

        $this->email = "";

        session()->flash('status', 'Falls ein Konto mit dieser E-Mail-Adresse existiert, haben wir einen Link zum Zurücksetzen des Passworts zugesandt.');

    }
    protected function generateToken(): string
    {
        return Str::random(60);
    }
    protected function updatePasswordResetToken($email, $guard, $token): void
    {
        // Füge einen neuen Token in die password_reset_tokens-Tabelle ein oder aktualisiere den vorhandenen Datensatz
        DB::table('system_password_reset_tokens')->updateOrInsert(
            [
                'email' => $email,
                'guard' => $guard,
            ],
            [
                'token' => bcrypt($token),
                'created_at' => Carbon::now(),
            ]
        );
    }

    protected function resetPassword()
    {
        $throttleKey = 'password_reset_exec_' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            session()->flash('error', "Zu viele Versuche. Bitte warte {$seconds} Sekunden.");
            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 300);

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
        $expectedGuard = in_array($this->guard ?? '', ['admin', 'employee', 'customer'], true)
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
            session()->flash('error', 'Ungültiger Passwort-Reset-Token.');
            return;
        }

        // Überprüfen Sie, ob der Datensatz vorhanden ist und ob das created_at älter als 60 Minuten ist
        if (Carbon::parse($passwordResetToken->created_at)->addMinutes(60)->isPast()) {
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
        $user->setRememberToken(Str::random(60));
        $user->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        // Löschen des Passwort-Reset-Tokens
        SystemPasswordResetToken::where('email', $this->email)->where('guard', $this->guard)->delete();

        // Zurück zum Login
        return redirect()->route('login');
    }

}








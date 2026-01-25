<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\PasswordResetToken;

trait handlePasswordResetTrait
{

    public function sendLink(): void
    {
        // Prüfe ob schon ein gültiger Reset Token vorhanden ist
        $passwordResetToken = PasswordResetToken::where('email', $this->email)
            ->where('guard', $this->guard)
            ->first();

        // Sollte der PassworResetToken existieren aber die Zeit von 2 Minuten ist noch nicht vergangen, dann return
        if ($passwordResetToken != null && !Carbon::parse($passwordResetToken->created_at)->addMinutes(2)->isPast()) {
            session()->flash('error', 'Bitte warte, bis du es erneut versuchst!');
            return;
        }

        $userModel = (new \App\Models\User)->getUserModelByGuard($this->guard);
        $user = $userModel::where('email', $this->email)->first();

        if (!$user) {
            session()->flash('error', 'Es ist ein Problem aufgetreten.');
            return;
        }

        $this->validate([
            'email' => 'required|email',
        ]);

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

        session()->flash('status', 'Wir haben Ihnen den Link zum Zurücksetzen des Passworts per E-Mail zugesandt!');

    }
    public function generateToken(): string
    {
        return Str::random(60);
    }
    public function updatePasswordResetToken($email, $guard, $token): void
    {
        // Füge einen neuen Token in die password_reset_tokens-Tabelle ein oder aktualisiere den vorhandenen Datensatz
        DB::table('password_reset_tokens')->updateOrInsert(
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

    public function resetPassword()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
            'passwordConfirm' => 'required',
        ]);

        if ($this->password != $this->passwordConfirm)
        {
            session()->flash('error', 'Die Passwörter stimmen nicht überein.');
            return;
        }

        $passwordResetToken = PasswordResetToken::where('email', $this->email)->first();

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

        $userModel = (new \App\Models\User)->getUserModelByGuard($this->guard);
        $user = $userModel::where('email', $this->email)->first();

        if (!$user) {
            session()->flash('error', 'Es ist ein Problem aufgetreten.');
            return;
        }

        // Aktualisieren des Passworts des Benutzers
        $user->password = Hash::make($this->password);
        $user->save();

        // Löschen des Passwort-Reset-Tokens
        PasswordResetToken::where('email', $this->email)->where('guard', $this->guard)->delete();

        // Zurück zum Login
        return redirect()->route('login');
    }

}








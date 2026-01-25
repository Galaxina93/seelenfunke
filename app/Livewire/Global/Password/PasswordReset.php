<?php

namespace App\Livewire\Global\Password;

use App\Models\PasswordResetToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class PasswordReset extends Component
{
    public string $email;
    public string $password;
    public string $passwordConfirm;
    public string $guard;
    public string $token;

    public function mount($token)
    {
        $this->token = $token;
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.password.password-reset');
    }

    public function submit()
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

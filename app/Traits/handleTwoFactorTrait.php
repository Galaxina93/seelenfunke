<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException;
use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;

trait handleTwoFactorTrait
{

    // Inputfield from blade
    public $code;

    /**
     * @throws IncompatibleWithGoogleAuthenticatorException
     * @throws SecretKeyTooShortException
     * @throws InvalidCharactersException
     */

    // Frontend
    public function twoFactorVerify()
    {
        $this->validate([
            'code' => 'required',
        ]);

        $userId = session('2fa_user_id');
        $guard = session('guard');

        if (!$userId || !$guard) {
            session()->flash('error', 'Authentifizierung fehlgeschlagen. Bitte erneut anmelden.');
            return;
        }

        $userModel = (new \App\Models\System\SystemUser)->getUserModelByGuard($guard);
        $user = $userModel::find($userId);

        if (!$user) {
            session()->flash('error', 'Benutzer nicht gefunden. Bitte erneut anmelden.');
            return;
        }

        // Rate-Limiting für 2FA-Verifikation (Schutz vor 6-stelligen Brute-Force-Angriffen)
        $throttleKey = '2fa_verify_' . $user->id . '_' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            session()->flash('error', "Zu viele Fehlversuche bei der 2FA-Eingabe. Bitte warte {$seconds} Sekunden.");
            return;
        }

        // 2FA prüfen
        $valid = $this->twoFactorCheckCode($user->profile->two_factor_secret, $this->code);

        if (!$valid) {
            $recoveryCodes = json_decode(decrypt($user->profile->two_factor_recovery_codes), true);
            $valid = in_array($this->code, $recoveryCodes);

            if ($valid) {
                $recoveryCodes = array_diff($recoveryCodes, [$this->code]);
                $user->profile->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
                $user->profile->save();
            }
        }

        if ($valid) {
            \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

            Auth::guard($guard)->login($user);
            session()->regenerate();

            session()->forget('2fa_user_id');
            session()->forget('guard');

            // Berechtigungen in der Session hinterlegen (analog zu AuthLogin)
            $permissions = [];
            if (method_exists($user, 'roles')) {
                $permissions = $user->roles->flatMap(fn($role) => $role->permissions)->pluck('name', 'name')->all();
            }
            session(['permissions' => $permissions]);

            if ($guard === 'customer' && $user->needs_password_change) {
                session(['force_password_change_customer_id' => $user->id]);
                return redirect(route('customer.password-change-force'));
            }

            return redirect(route($guard . '.dashboard'));
        }

        \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 300);

        if (class_exists(\App\Models\System\SystemLoginAttempt::class)) {
            \App\Models\System\SystemLoginAttempt::create([
                'email' => $user->email,
                'ip_address' => request()->ip(),
                'success' => false,
                'attempted_at' => now(),
            ]);
        }

        session()->flash('error', 'Der eingegebene Code ist ungültig. Bitte versuchen Sie es erneut.');
    }
    public function twoFactorCheckCode($secretKey, $code): bool|int
    {
        $google2fa = new Google2FA();
        return $google2fa->verifyKey($secretKey, $code);
    }

}








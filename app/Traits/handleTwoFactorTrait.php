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

        $userModel = (new \App\Models\User)->getUserModelByGuard($guard);
        $user = $userModel::find($userId);

        if (!$user) {
            session()->flash('error', 'Benutzer nicht gefunden. Bitte erneut anmelden.');
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
            Auth::guard($guard)->login($user);

            session()->forget('2fa_user_id');
            session()->forget('guard');

            return redirect(route($guard . '.dashboard'));
        }

        session()->flash('error', 'Der eingegebene Code ist ungültig. Bitte versuchen Sie es erneut.');
    }
    public function twoFactorCheckCode($secretKey, $code): bool|int
    {
        $google2fa = new Google2FA();
        return $google2fa->verifyKey($secretKey, $code);
    }

}








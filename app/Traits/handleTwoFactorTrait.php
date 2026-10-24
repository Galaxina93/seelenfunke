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
            'code' => 'required|string|max:64',
        ]);

        $inputCode = trim((string) $this->code);

        $userId = session('2fa_user_id');
        $guard = session('guard');

        if (!$userId || !$guard) {
            session()->flash('error', 'Authentifizierung fehlgeschlagen. Bitte erneut anmelden.');
            return;
        }

        $userModel = (new \App\Models\System\SystemUser)->getUserModelByGuard($guard);
        $user = $userModel::find($userId);

        if (!$user || !$user->profile || (method_exists($user, 'trashed') && $user->trashed())) {
            session()->flash('error', 'Benutzer nicht gefunden. Bitte erneut anmelden.');
            return;
        }

        // Rate-Limiting für 2FA-Verifikation (Schutz vor 6-stelligen Brute-Force-Angriffen)
        $throttleKey = '2fa_verify_' . $user->id . '_' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            \App\Services\System\SystemSecurityService::logEvent(
                'security:2fa_rate_limit',
                '2FA Brute-Force Schutz ausgelöst',
                "Zu viele fehlerhafte 2FA-Versuche für '{$user->email}' ({$guard}) von IP " . request()->ip(),
                'error',
                ['target_email' => $user->email, 'guard' => $guard]
            );
            session()->flash('error', "Zu viele Fehlversuche bei der 2FA-Eingabe. Bitte warte {$seconds} Sekunden.");
            return;
        }

        // 2FA prüfen (strikt typisiert)
        $valid = false;
        if (!empty($user->profile->two_factor_secret)) {
            try {
                $valid = (bool) $this->twoFactorCheckCode((string) $user->profile->two_factor_secret, $inputCode);
            } catch (\Throwable $e) {
                $valid = false;
            }
        }

        if (!$valid && !empty($user->profile->two_factor_recovery_codes)) {
            try {
                $recoveryCodes = json_decode(decrypt($user->profile->two_factor_recovery_codes), true);
                if (is_array($recoveryCodes)) {
                    foreach ($recoveryCodes as $idx => $savedCode) {
                        if (is_string($savedCode) && $savedCode !== '' && hash_equals($savedCode, $inputCode)) {
                            $valid = true;
                            unset($recoveryCodes[$idx]);
                            $user->profile->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
                            $user->profile->save();
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $valid = false;
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

        \App\Services\System\SystemSecurityService::recordLoginAttempt($user->email, request()->ip(), false, '2fa');
        \App\Services\System\SystemSecurityService::logEvent(
            'security:2fa_failed',
            'Fehlgeschlagene 2FA-Verifikation',
            "Ungültiger 2FA-Code für '{$user->email}' ({$guard}) von IP " . request()->ip(),
            'warning',
            ['target_email' => $user->email, 'guard' => $guard]
        );

        session()->flash('error', 'Der eingegebene Code ist ungültig. Bitte versuchen Sie es erneut.');
    }
    public function twoFactorCheckCode($secretKey, $code): bool|int
    {
        $google2fa = new Google2FA();
        return $google2fa->verifyKey($secretKey, $code);
    }

}








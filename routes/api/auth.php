<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::post('/login', function (Request $request) {
    if (\App\Models\System\SystemBlockedIp::isBlocked($request->ip())) {
        return response()->json(['message' => 'Deine IP-Adresse wurde aufgrund verdächtiger Aktivitäten gesperrt.'], 403);
    }

    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Wir prüfen nacheinander alle Guards
    $guards = ['admin', 'employee', 'customer'];

    foreach ($guards as $guard) {
        if (Auth::guard($guard)->validate($credentials)) {
            $userModelClass = (new \App\Models\System\SystemUser)->getUserModelByGuard($guard);
            $user = $userModelClass::withTrashed()->where('email', $credentials['email'])->first();

            if (!$user || (method_exists($user, 'trashed') && $user->trashed())) {
                \App\Services\System\SystemSecurityService::recordLoginAttempt($credentials['email'], $request->ip(), false, 'api');
                return response()->json(['message' => 'Dieser Account wurde deaktiviert.'], 403);
            }

            if ($guard === 'customer' && method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()) {
                \App\Services\System\SystemSecurityService::recordLoginAttempt($credentials['email'], $request->ip(), false, 'api');
                return response()->json(['message' => 'Bitte bestätige zuerst deine E-Mail-Adresse.'], 403);
            }

            if ($guard === 'customer' && !empty($user->needs_password_change)) {
                \App\Services\System\SystemSecurityService::recordLoginAttempt($credentials['email'], $request->ip(), false, 'api_password_change_required');
                return response()->json([
                    'status' => 'password_change_required',
                    'message' => 'Bitte ändere zuerst dein temporäres Passwort über das Web-Portal.',
                ], 403);
            }

            // 2FA-Prüfung auch für API-Logins erzwingen
            if ($user->profile && $user->profile->two_factor_is_active) {
                $twoFactorCode = trim((string) $request->input('two_factor_code', $request->input('code', '')));
                if ($twoFactorCode === '') {
                    return response()->json([
                        'status' => 'two_factor_required',
                        'message' => 'Zwei-Faktor-Authentifizierung erforderlich.',
                    ], 403);
                }

                $valid2fa = false;
                if (!empty($user->profile->two_factor_secret)) {
                    try {
                        $google2fa = new \PragmaRX\Google2FA\Google2FA();
                        $valid2fa = (bool) $google2fa->verifyKey((string) $user->profile->two_factor_secret, $twoFactorCode);
                    } catch (\Throwable $e) {
                        $valid2fa = false;
                    }
                }

                if (!$valid2fa && !empty($user->profile->two_factor_recovery_codes)) {
                    try {
                        $recoveryCodes = json_decode(decrypt($user->profile->two_factor_recovery_codes), true);
                        if (is_array($recoveryCodes)) {
                            foreach ($recoveryCodes as $idx => $savedCode) {
                                if (is_string($savedCode) && $savedCode !== '' && hash_equals($savedCode, $twoFactorCode)) {
                                    $valid2fa = true;
                                    unset($recoveryCodes[$idx]);
                                    $user->profile->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
                                    $user->profile->save();
                                    break;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        $valid2fa = false;
                    }
                }

                if (!$valid2fa) {
                    \App\Services\System\SystemSecurityService::recordLoginAttempt($credentials['email'], $request->ip(), false, 'api_2fa');
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:api_2fa_failed',
                        'API 2FA-Verifikation fehlgeschlagen',
                        "Ungültiger 2FA-Code beim API-Login für '{$user->email}' ({$guard}) von IP " . $request->ip(),
                        'warning',
                        ['target_email' => $user->email, 'guard' => $guard]
                    );
                    return response()->json(['message' => 'Ungültiger 2FA-Code.'], 401);
                }
            }

            \App\Services\System\SystemSecurityService::recordLoginAttempt($credentials['email'], $request->ip(), true, 'api');

            // Optional: Alte Tokens aufräumen
            $user->tokens()->delete();

            // Token erstellen
            $token = $user->createToken('FunkiApp-' . $guard)->plainTextToken;

            return response()->json([
                'status' => 'success',
                'token' => $token,
                'user_type' => $guard,
                'user' => $user
            ]);
        }
    }

    \App\Services\System\SystemSecurityService::recordLoginAttempt($credentials['email'], $request->ip(), false, 'api');

    return response()->json(['message' => 'Zugangsdaten ungültig.'], 401);
})->middleware('throttle:5,1');

Route::post('/password/email', function (Request $request) {
    if (\App\Models\System\SystemBlockedIp::isBlocked($request->ip())) {
        return response()->json(['message' => 'Deine IP-Adresse wurde aufgrund verdächtiger Aktivitäten gesperrt.'], 403);
    }

    $request->validate([
        'email' => 'required|email',
    ]);

    $email = $request->email;
    $guardsToCheck = ['admin', 'employee', 'customer'];
    $foundGuard = null;
    $user = null;

    foreach ($guardsToCheck as $g) {
        $userModel = (new \App\Models\System\SystemUser)->getUserModelByGuard($g);
        $candidate = $userModel::where('email', $email)->first();
        if ($candidate) {
            $foundGuard = $g;
            $user = $candidate;
            break;
        }
    }

    if (!$user) {
        // Einheitliche Erfolgsmeldung zur Verhinderung von User-Enumeration
        return response()->json([
            'status' => 'success',
            'message' => 'Falls ein Konto mit dieser E-Mail-Adresse existiert, haben wir einen Link zum Zurücksetzen des Passworts zugesandt.'
        ]);
    }

    $passwordResetToken = \App\Models\System\SystemPasswordResetToken::where('email', $email)
        ->where('guard', $foundGuard)
        ->first();

    if ($passwordResetToken != null && !\Carbon\Carbon::parse($passwordResetToken->created_at)->addMinutes(2)->isPast()) {
        return response()->json([
            'status' => 'success',
            'message' => 'Falls ein Konto mit dieser E-Mail-Adresse existiert, haben wir einen Link zum Zurücksetzen des Passworts zugesandt.'
        ]);
    }

    $token = \Illuminate\Support\Str::random(60);
    \Illuminate\Support\Facades\DB::table('system_password_reset_tokens')->updateOrInsert(
        [
            'email' => $email,
            'guard' => $foundGuard,
        ],
        [
            'token' => bcrypt($token),
            'created_at' => \Carbon\Carbon::now(),
        ]
    );

    $emailData = [
        'to' => $email,
        'subject' => 'Passwort vergessen',
        'viewTemplate' => 'global.mails.forgot-password',
        'reset_link' => url('/' . $foundGuard . '/password-reset/' . $token),
    ];

    \Illuminate\Support\Facades\Notification::route('mail', $email)
        ->notify(new \App\Notifications\globalNotification($emailData));

    return response()->json([
        'status' => 'success',
        'message' => 'Falls ein Konto mit dieser E-Mail-Adresse existiert, haben wir einen Link zum Zurücksetzen des Passworts zugesandt.'
    ]);
})->middleware('throttle:5,1');


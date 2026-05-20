<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Wir prüfen nacheinander alle Guards
    $guards = ['admin', 'employee', 'customer'];

    foreach ($guards as $guard) {
        if (Auth::guard($guard)->attempt($credentials)) {
            $user = Auth::guard($guard)->user();

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

    return response()->json(['message' => 'Zugangsdaten ungültig.'], 401);
});

Route::post('/password/email', function (Request $request) {
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
        return response()->json(['message' => 'Diese E-Mail-Adresse ist uns nicht bekannt.'], 404);
    }

    $passwordResetToken = \App\Models\System\SystemPasswordResetToken::where('email', $email)
        ->where('guard', $foundGuard)
        ->first();

    if ($passwordResetToken != null && !\Carbon\Carbon::parse($passwordResetToken->created_at)->addMinutes(2)->isPast()) {
        return response()->json(['message' => 'Bitte warte, bis du es erneut versuchst.'], 429);
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

    return response()->json(['status' => 'success', 'message' => 'Link zum Zurücksetzen gesendet.']);
});


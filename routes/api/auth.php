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

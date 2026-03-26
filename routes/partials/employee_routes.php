<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:employee'])->group(function () {



});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/employee/password-reset/{token}', function ($token) {
        return view('auth.password-reset', ['token' => $token]);
    });
});

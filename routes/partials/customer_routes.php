<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:customer'])->group(function () {

    // Dashboard
    Route::get('/customer/dashboard', function () {
        return view('backend.customer.pages.dashboard');
    })->name('customer.dashboard');

    // Projekte
    Route::get('/customer/projects', function () {
        return view('backend.customer.pages.projects');
    })->name('customer.projects');

    // Profile
    Route::get('/customer/profile', function () {
        return view('backend.customer.pages.profile');
    })->name('customer.profile');

});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/customer/password-reset/{token}', function ($token) {
        return view('global/pages/password/password-reset', ['token' => $token]);
    });
});

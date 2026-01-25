<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:employee'])->group(function () {

    // Dashboard
    Route::get('/employee/dashboard', function () {
        return view('backend.employee.pages.dashboard');
    })->name('employee.dashboard');

    // Projekte
    Route::get('/employee/projects', function () {
        return view('backend.employee.pages.projects');
    })->name('employee.projects');

    // Profile
    Route::get('/employee/profile', function () {
        return view('backend.employee.pages.profile');
    })->name('employee.profile');

});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/employee/password-reset/{token}', function ($token) {
        return view('global/pages/password/password-reset', ['token' => $token]);
    });
});

<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:admin'])->group(function () {

    // Dashboard
    Route::get('/admin/dashboard', function () {
    return view('backend.admin.pages.dashboard');
    })->name('admin.dashboard');

    // Benutzerverwaltung
    Route::get('/admin/user-management', function () {
        return view('backend.admin.pages.user-management');
    })->name('admin.user-management');

    // Benutzerverwaltung
    Route::get('/admin/right-management', function () {
        return view('backend.admin.pages.right-management');
    })->name('admin.right-management');

    // Profile
    Route::get('/admin/profile', function () {
    return view('backend.admin.pages.profile');
    })->name('admin.profile');

    // Shop
    Route::get('/admin/products', function () {
        return view('backend.admin.pages.products');
    })->name('admin.products');
    Route::get('/admin/voucher', function () {
        return view('backend.admin.pages.voucher');
    })->name('admin.voucher');
    Route::get('/admin/newsletter', function () {
        return view('backend.admin.pages.newsletter');
    })->name('admin.newsletter');

});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/admin/password-reset/{token}', function ($token) {
        return view('global/pages/password/password-reset', ['token' => $token]);
    });
});


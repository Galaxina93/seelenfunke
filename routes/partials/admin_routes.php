<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:admin'])->group(function () {

    // Dashboard
    Route::get('/admin/dashboard', function () {
    return view('backend.admin.pages.dashboard');
    })->name('admin.dashboard');

    // Funki
    Route::get('/admin/funki', function () {
        return view('backend.admin.pages.funki');
    })->name('admin.funki');
    Route::get('/admin/funki-routine', function () {
        return view('backend.admin.pages.funki-routine');
    })->name('admin.funki-routine');
    Route::get('/admin/funki-todos', function () {
        return view('backend.admin.pages.funki-todos');
    })->name('admin.funki-todos');
    Route::get('/admin/funki-kalender', function () {
        return view('backend.admin.pages.funki-kalender');
    })->name('admin.funki-kalender');

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

    Route::get('/admin/invoices', function () {
        return view('backend.admin.pages.invoices');
    })->name('admin.invoices');

    Route::get('/admin/orders', function () {
        return view('backend.admin.pages.orders');
    })->name('admin.orders');

    Route::get('/admin/quote-requests', function () {
        return view('backend.admin.pages.quote-requests');
    })->name('admin.quote-requests');

    Route::get('/admin/shipping', function () {
        return view('backend.admin.pages.shipping');
    })->name('admin.shipping');

    Route::get('/admin/financial-evaluation', function () {
        return view('backend.admin.pages.financial-evaluation');
    })->name('admin.financial-evaluation');

    Route::get('/admin/financial-categories-special-editions', function () {
        return view('backend.admin.pages.financial-categories-special-editions');
    })->name('admin.financial-categories-special-editions');

    Route::get('/admin/financial-contracts-groups', function () {
        return view('backend.admin.pages.financial-contracts-groups');
    })->name('admin.financial-contracts-groups');


    Route::get('/admin/configuration', function () {
        return view('backend.admin.pages.configuration');
    })->name('admin.configuration');

    Route::get('/admin/blog', function () {
        return view('backend.admin.pages.blog');
    })->name('admin.blog');

});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/admin/password-reset/{token}', function ($token) {
        return view('global/pages/password/password-reset', ['token' => $token]);
    });
});


<?php

use App\Livewire\Customer\OrderDetail;
use App\Livewire\Customer\Orders;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:customer'])->group(function () {

    // Dashboard
    Route::get('/customer/dashboard', function () {
        return view('backend.customer.pages.dashboard');
    })->name('customer.dashboard');

    // Profile
    Route::get('/customer/profile', function () {
        return view('backend.customer.pages.profile');
    })->name('customer.profile');

    // 1. Übersicht & Opt-In (Hier landet man nach dem Login)
    Route::get('/dashboard', \App\Livewire\Customer\DashboardComponent::class)->name('customer.dashboard');

    // 2. Der Funki Shop
    Route::get('/funki-shop', \App\Livewire\Customer\FunkiShopComponent::class)->name('customer.funki-shop');

    // 3. Spiele Bereich
    Route::get('/games', \App\Livewire\Customer\GamesComponent::class)->name('customer.games');

    // 4. Bestellungen
    Route::get('/orders', \App\Livewire\Customer\OrdersComponent::class)->name('customer.orders');


});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/customer/password-reset/{token}', function ($token) {
        return view('global/pages/password/password-reset', ['token' => $token]);
    });
});

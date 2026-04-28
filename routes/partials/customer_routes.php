<?php


use Illuminate\Support\Facades\Route;

Route::middleware(['auth:customer'])->group(function () {

    // Login-Redirect Fallback (Verhindert eine komplett leere Seite bei alten Logins/Links)
    Route::redirect('/customer/dashboard', '/dashboard', 301);

    // Profile
    Route::get('/customer/profile', function () {
        return view('backend.customer.pages.profile');
    })->name('customer.profile');

    // 1. Dashboard Redirect auf Bestellungen
    Route::redirect('/dashboard', '/orders', 301)->name('customer.dashboard');

    // 1b. Spielen (Ehemalige Zentrale)
    Route::get('/play', \App\Livewire\Customer\CustomerDashboardComponent::class)->name('customer.play');

    // 2. Bestellungen
    Route::get('/orders', \App\Livewire\Customer\CustomerOrdersComponent::class)->name('customer.orders');

    // 3. Rechnungs-Archiv (Invoices)
    Route::get('/invoices', \App\Livewire\Customer\CustomerInvoicesComponent::class)->name('customer.invoices');

    // 4. Spiele Bereich
    Route::get('/games', \App\Livewire\Customer\Gamification\GameGamesComponent::class)->name('customer.games');

    // 4. NEU: Globale Rangliste
    Route::get('/ranking', \App\Livewire\Customer\Gamification\GameGlobalRankingComponent::class)->name('customer.ranking');

    // NEU: Support & Tickets
    Route::get('/support', \App\Livewire\Customer\CustomerTicketsComponent::class)->name('customer.support');
});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/customer/password-reset/{token}', function ($token) {
        return view('auth.password-reset', ['token' => $token]);
    });
});

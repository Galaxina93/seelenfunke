<?php


use Illuminate\Support\Facades\Route;

Route::middleware(['auth:customer'])->group(function () {
    Route::get('/customer/password-change-force', \App\Livewire\Customer\CustomerForcePasswordChange::class)->name('customer.password-change-force');

    Route::middleware([\App\Http\Middleware\EnforcePasswordChange::class])->group(function () {
        // Profile (öffnet Dashboard inkl. Profil-Modal)
        Route::get('/customer/profile', \App\Livewire\Customer\CustomerDashboardComponent::class)->name('customer.profile');
        Route::redirect('/profile', '/customer/profile');

        // 1. Dashboard (Zentrale)
        Route::get('/customer/dashboard', \App\Livewire\Customer\CustomerDashboardComponent::class)->name('customer.dashboard');
        Route::redirect('/dashboard', '/customer/dashboard');

        // 2. Bestellungen
        Route::get('/orders', \App\Livewire\Customer\CustomerOrdersComponent::class)->name('customer.orders');

        // 3. Rechnungs-Archiv (Invoices)
        Route::get('/invoices', \App\Livewire\Customer\CustomerInvoicesComponent::class)->name('customer.invoices');

        // 4. Support & Tickets
        Route::get('/support', \App\Livewire\Customer\CustomerTicketsComponent::class)->name('customer.support');

        // 5. Dein Funki & Prämien (Eigene übersichtliche Seite statt überladenem Modal)
        Route::get('/funki', \App\Livewire\Customer\CustomerFunkiComponent::class)->name('customer.funki');
        Route::redirect('/customer/funki', '/funki');

        // 6. Seelenfunken Audio-Einstellungen (BGM-Lautstärke, Mute & "Musik laufen lassen" über alle Seiten hinweg)
        Route::post('/customer/gamification/audio-settings', function (\Illuminate\Http\Request $request, \App\Services\Gamification\GamificationService $gameService) {
            $user = \Illuminate\Support\Facades\Auth::guard('customer')->user();
            if (!$user) {
                return response()->json(['success' => false], 401);
            }

            $validated = $request->validate([
                'volume'       => ['required', 'integer', 'min:0', 'max:100'],
                'muted'        => ['required', 'boolean'],
                'keep_playing' => ['nullable', 'boolean'],
            ]);

            $res = $gameService->updateAudioSettings(
                $user,
                (int) $validated['volume'],
                (bool) $validated['muted'],
                array_key_exists('keep_playing', $validated) ? (bool) $validated['keep_playing'] : null
            );

            return response()->json($res);
        })->middleware('throttle:60,1')->name('customer.gamification.audio-settings');

    });
});

// NEU: Gutschein PDF Download (globaler Download, berechtigt im Controller)
Route::get('/customer/gift-voucher/{voucher}/download', [\App\Http\Controllers\Customer\GiftVoucherDownloadController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('customer.gift-voucher.download');

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/customer/password-reset/{token}', function ($token) {
        return view('auth.password-reset', ['token' => $token]);
    });
});

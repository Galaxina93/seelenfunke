<?php

use App\Http\Controllers\Api\AgenticCommerceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Stripe Agentic Commerce Protocol (ACP v1) Routes
|--------------------------------------------------------------------------
|
| Gesicherte PCI-DSS SAQ-A Schnittstelle (/api/acp/v1/*) für externe
| Einkaufs-Agenten auf Basis von Stripe Shared Payment Tokens (spt_*).
|
*/

Route::prefix('acp/v1')
    ->middleware('throttle:30,1')
    ->group(function () {
        Route::get('/products', [AgenticCommerceController::class, 'listProducts'])
            ->name('api.acp.v1.products');

        Route::post('/checkout/sessions', [AgenticCommerceController::class, 'createCheckoutSession'])
            ->name('api.acp.v1.checkout.sessions.create');

        Route::post('/checkout/sessions/{sessionId}/complete', [AgenticCommerceController::class, 'completeCheckoutSession'])
            ->where('sessionId', '[A-Za-z0-9_-]{8,80}')
            ->name('api.acp.v1.checkout.sessions.complete');
    });

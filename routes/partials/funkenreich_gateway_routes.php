<?php

use App\Http\Controllers\Frontend\FunkenreichGatewayController;
use App\Http\Middleware\MarketingTrackingMiddleware;
use App\Http\Middleware\TrackVisitor;
use App\Http\Middleware\UserLastActivity;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/funkenreich/enter', [FunkenreichGatewayController::class, 'enter'])
    ->middleware('throttle:30,1')
    ->name('funkenreich.enter');

Route::get('/api/funkenreich/access-status', [FunkenreichGatewayController::class, 'accessStatus'])
    ->withoutMiddleware([
        VerifyCsrfToken::class,
        TrackVisitor::class,
        MarketingTrackingMiddleware::class,
        UserLastActivity::class,
    ])
    ->middleware('throttle:120,1')
    ->name('funkenreich.access-status');

<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Hier werden die verschiedenen API-Bereiche modular geladen.
| Dies hält die Hauptdatei schlank und wartbar.
|
*/

// --- 1. Authentifizierung (Ungeschützt) ---
require __DIR__ . '/api/auth.php';

// --- Lokale KI API ---
require __DIR__ . '/api/ai.php';

// --- Frontend Error Tracking ---
Route::post('/log/frontend-error', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->validate([
            'message' => 'required|string',
            'source' => 'nullable|string',
            'lineno' => 'nullable|integer',
            'colno' => 'nullable|integer',
            'url' => 'nullable|string',
            'userAgent' => 'nullable|string',
            'type' => 'nullable|string',
            'stack' => 'nullable|string'
        ]);

        \App\Models\System\SystemLog::create([
            'type' => 'frontend_error',
            'action_id' => 'js_error_' . uniqid(),
            'title' => 'Frontend ' . ($data['type'] === 'promise_rejection' ? 'Promise Error' : 'JS Error'),
            'message' => \Illuminate\Support\Str::limit($data['message'], 250),
            'status' => 'error',
            'payload' => $data,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 500);
    }
});

// --- Telegram Webhooks ---
Route::post('/telegram/webhook/{telegram_token}', [\App\Http\Controllers\Api\TelegramAgentController::class, 'handleWebhook'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// --- Twilio Webhooks ---
Route::post('/twilio/outbound', [\App\Http\Controllers\Api\TwilioCallController::class, 'outbound'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/twilio/inbound', [\App\Http\Controllers\Api\TwilioCallController::class, 'inbound'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/twilio/call-log', [\App\Http\Controllers\Api\TwilioCallController::class, 'callLog'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// --- Flight Data Proxy (CORS Fix) ---
Route::get('/flights', function (\Illuminate\Http\Request $request) {
    try {
        $lamin = $request->query('lamin');
        $lomin = $request->query('lomin');
        $lamax = $request->query('lamax');
        $lomax = $request->query('lomax');
        
        $url = "https://opensky-network.org/api/states/all?lamin={$lamin}&lomin={$lomin}&lamax={$lamax}&lomax={$lomax}";
        $response = \Illuminate\Support\Facades\Http::get($url);
        
        return response()->json($response->json());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Flight API Proxy failed'], 500);
    }
});

// --- 2. Geschützte API-Routen (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {

    // Basis User- & Geräte-Funktionen
    require __DIR__ . '/api/core.php';

    // Finanz-Manager
    require __DIR__ . '/api/finance.php';

    // Tagesroutine
    require __DIR__ . '/api/routine.php';

    // Tasks (Aufgaben)
    require __DIR__ . '/api/tasks.php';

    // Calender & Termine
    require __DIR__ . '/api/calendar.php';

});

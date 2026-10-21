<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;

/*
|--------------------------------------------------------------------------
| AI Remote Control API Routes
|--------------------------------------------------------------------------
|
| Diese Routen sind für lokale LLMs (z.B. Ollama) oder externe Skripte
| gedacht, die mit dem Laravelsystem interagieren wollen (Tool Calling).
| 
| HINWEIS: Hier sollte später ein Middleware-Schutz (z.B. Token) 
| eingebaut werden.
|
*/

Route::prefix('ai')->group(function () {
    // Endpunkt zur Verifizierung von WebSockets-Tokens (nur für internen Node-Server)
    Route::post('/verify-token', [AIController::class, 'verifyToken'])->middleware('throttle:60,1');

    // Endpunkt zum Persistieren von Live-Transkripten (für internen Node-Server)
    Route::post('/save-live-transcript', [AIController::class, 'saveLiveTranscript'])->middleware('throttle:120,1');

    // Admin & Mitarbeiter geschützte KI-Endpunkte
    Route::group(['middleware' => ['web', 'auth:sanctum,web,admin', function ($request, $next) {
        $user = $request->user() ?: auth()->guard('admin')->user() ?: auth()->guard('employee')->user();
        $isAdminOrEmployee = ($user instanceof \App\Models\Admin\Admin)
            || ($user instanceof \App\Models\Employee\Employee)
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();

        if (!$isAdminOrEmployee) {
            abort(403, 'Zugriff verweigert. Nur Administratoren und Mitarbeiter.');
        }

        return $next($request);
    }]], function () {
        // Gibt das JSON-Schema für die Ollama Function-Calling Tools zurück
        Route::get('/schema', [AIController::class, 'schema']);
        
        // Führt eine der registrierten Tools aus
        Route::post('/execute', [AIController::class, 'execute']);

        // Speichert einen Kamera-Snapshot auf dem Dateisystem
        Route::post('/camera/snapshot', [AIController::class, 'saveCameraSnapshot']);

        // Endpunkt für das Frontend (nimmt Prompt entgegen und schickt es an Ollama)
        Route::post('/chat', [AIController::class, 'chat']);

        // Endpunkt zum Abrufen der Chat-Historie
        Route::get('/history', [AIController::class, 'history']);

        // Endpunkt für den Multimodal Live API Mode (WebSockets)
        Route::get('/live-credentials', [AIController::class, 'liveCredentials']);
    });
});

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

    // Admin & interne aktive Mitarbeiter geschützte KI-Endpunkte
    Route::group(['middleware' => ['web', 'auth:sanctum,web,admin,employee', function ($request, $next) {
        $user = $request->user() ?: auth()->guard('admin')->user() ?: auth()->guard('employee')->user();
        $isAdminOrEmployee = ($user instanceof \App\Models\Admin\Admin)
            || ($user instanceof \App\Models\Employee\Employee && $user->isInternalEmployee() && $user->isAccountActive())
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();

        if (!$isAdminOrEmployee) {
            abort(403, 'Zugriff verweigert. Nur Administratoren und interne Mitarbeiter.');
        }

        // CSRF- & Cross-Origin-Schutz für Cookie-basierte (nicht-Bearer) schreibende Anfragen
        if (!$request->isMethodSafe() && !$request->bearerToken()) {
            $secFetchSite = strtolower((string) $request->header('Sec-Fetch-Site', ''));
            if ($secFetchSite === 'cross-site') {
                \App\Services\System\SystemSecurityService::logEvent(
                    'security:ai_csrf_blocked',
                    'Cross-Site KI-API Aufruf blockiert',
                    'Cross-Site Request (Sec-Fetch-Site: cross-site) auf ' . $request->path() . ' abgewehrt.',
                    'error'
                );
                abort(403, 'Cross-site request blocked.');
            }

            $origin = (string) $request->header('Origin', '');
            if ($origin !== '') {
                $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
                $requestHost = strtolower((string) $request->getHost());
                if ($originHost === '' || $originHost !== $requestHost) {
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:ai_csrf_blocked',
                        'Cross-Origin KI-API Aufruf blockiert',
                        "Abweichender Origin-Header ({$origin}) auf " . $request->path() . " abgewehrt.",
                        'error'
                    );
                    abort(403, 'Cross-origin request blocked.');
                }
            }

            if (!app()->runningUnitTests() && $request->hasSession()) {
                $sessionToken = (string) $request->session()->token();
                $providedToken = (string) ($request->header('X-CSRF-TOKEN') ?: $request->input('_token', ''));
                if ($sessionToken === '' || $providedToken === '' || !hash_equals($sessionToken, $providedToken)) {
                    abort(419, 'CSRF token mismatch.');
                }
            }
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

        // Leichtgewichtiger Health- & Latenz-Ping für das Live-AI Seiten-Widget
        Route::get('/bridge-ping', [AIController::class, 'bridgePing']);

        // Geschützter Abruf der System-Brain-Map (Architektur-Graph) nur für Admins & Mitarbeiter
        Route::get('/system-brain-map', function () {
            $jsonPath = storage_path('app/private/system-brain-map.json');
            if (!\Illuminate\Support\Facades\File::exists($jsonPath)) {
                $jsonPath = storage_path('app/public/system-brain-map.json');
            }
            if (!\Illuminate\Support\Facades\File::exists($jsonPath)) {
                return response()->json(['error' => 'Brain Map not generated yet'], 404);
            }
            return response()->file($jsonPath, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        });
    });
});

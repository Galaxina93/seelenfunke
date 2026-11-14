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

        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && !$user->tokenCan('*') && !$user->tokenCan('ai:funkira')) {
            abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
        }
        if ($request->bearerToken()) {
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken((string) $request->bearerToken());
            if ($pat && $pat->expires_at && $pat->expires_at->isPast()) {
                abort(401, 'Token abgelaufen.');
            }
            if ($pat && !$pat->can('*') && !$pat->can('ai:funkira')) {
                abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
            }
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

        // Speichert einen Kamera-Snapshot auf dem Dateisystem (privater Workspace-Speicher)
        Route::post('/camera/snapshot', [AIController::class, 'saveCameraSnapshot']);
        Route::post('/camera-snapshot', [AIController::class, 'saveCameraSnapshot']);

        // Endpunkt für das Frontend (nimmt Prompt entgegen und schickt es an Ollama)
        Route::post('/chat', [AIController::class, 'chat']);

        // Endpunkt zum Abrufen der Chat-Historie
        Route::get('/history', [AIController::class, 'history']);

        // Endpunkt für den Multimodal Live API Mode (WebSockets)
        Route::get('/live-credentials', [AIController::class, 'liveCredentials']);

        // Leichtgewichtiger Health- & Latenz-Ping für das Live-AI Seiten-Widget
        Route::get('/bridge-ping', [AIController::class, 'bridgePing']);

        // Detaillierte Fehler- & Auto-Heal-Protokollierung des Live-AI Web-Clients
        Route::post('/client-error-log', [AIController::class, 'clientErrorLog'])->middleware('throttle:30,1');

        // Geschützter Abruf der System-Brain-Map (Architektur-Graph inkl. Live-SystemLog-Fehlerkerne in Rot)
        Route::get('/system-brain-map', function () {
            $jsonPath = storage_path('app/private/system-brain-map.json');
            if (!\Illuminate\Support\Facades\File::exists($jsonPath)) {
                $jsonPath = storage_path('app/public/system-brain-map.json');
            }
            if (!\Illuminate\Support\Facades\File::exists($jsonPath)) {
                return response()->json(['error' => 'Brain Map not generated yet'], 404);
            }

            $rawJson = \Illuminate\Support\Facades\File::get($jsonPath);
            $decoded = json_decode($rawJson, true);
            if (!is_array($decoded)) {
                return response()->file($jsonPath, [
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                ]);
            }

            $enriched = app(\App\Services\System\SystemBrainDiagnosticsService::class)
                ->enrichBrainGraphJson($decoded);

            return response()->json($enriched, 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        });

        // Live-Abruf offener SystemLog-Fehlerkerne & zusammenhängender Dateien für das Projekt-Gehirn
        Route::get('/system-brain-errors', function () {
            $overlay = app(\App\Services\System\SystemBrainDiagnosticsService::class)
                ->getActiveErrorOverlay();

            return response()->json([
                'status' => 'success',
                'has_errors' => $overlay['has_errors'],
                'open_errors_count' => $overlay['open_errors_count'],
                'open_warnings_count' => $overlay['open_warnings_count'],
                'error_core_count' => $overlay['error_core_count'],
                'neighbor_count' => $overlay['neighbor_count'],
                'errors' => $overlay['api_errors_list'],
                'neighbors' => $overlay['neighbors'],
                'unmapped_logs' => $overlay['unmapped_logs'],
            ], 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        });

        // Aktive Gesamt-Systemprüfung ("System prüfen") aus dem 3D-Projekt-Gehirn (nur für Admins & Mitarbeiter)
        Route::post('/system-brain-check', function () {
            $report = app(\App\Services\System\SystemBrainDiagnosticsService::class)
                ->runFullSystemCheck(true);

            return response()->json([
                'status' => 'success',
                'report' => $report,
                'errors' => $report['overlay']['api_errors_list'] ?? [],
                'neighbors' => $report['overlay']['neighbors'] ?? [],
            ], 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        })->middleware('throttle:10,1');
    });
});

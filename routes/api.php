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
            'page_title' => 'nullable|string',
            'viewport' => 'nullable|string',
            'userAgent' => 'nullable|string',
            'type' => 'nullable|string',
            'status_code' => 'nullable|integer',
            'stack' => 'nullable|string'
        ]);

        $errType = $data['type'] ?? 'js_error';
        $title = match ($errType) {
            'promise_rejection' => 'Frontend Promise Error',
            'livewire_request_error' => 'Frontend Livewire HTTP-' . ($data['status_code'] ?? 'Error'),
            default => 'Frontend JS Error',
        };

        $diagnosis = [
            'category' => 'frontend_' . $errType,
            'probable_cause' => $errType === 'livewire_request_error'
                ? 'Livewire-Hintergrundanfrage fehlgeschlagen (HTTP ' . ($data['status_code'] ?? '?') . '). Mögliche Ursachen: abgelaufenes CSRF-Token (419), Server-Exception (500) oder Verbindungsabbruch.'
                : 'Clientseitiger JavaScript-/Alpine.js-Laufzeitfehler im Browser auf URL ' . ($data['url'] ?? 'unbekannt') . '.',
            'recommended_action' => 'Prüfe URL, Skript-Quelle (source/lineno) und den Browser-Stack-Trace im Payload.',
        ];

        $payload = array_merge($data, [
            'file' => $data['source'] ?? null,
            'line' => $data['lineno'] ?? null,
            'client_ip' => $request->ip(),
            'diagnosis' => $diagnosis,
            'server_environment' => class_exists(\App\Services\System\SystemErrorLogger::class)
                ? \App\Services\System\SystemErrorLogger::buildServerEnvironment()
                : ['php_version' => PHP_VERSION, 'app_env' => config('app.env')],
        ]);

        \App\Models\System\SystemLog::create([
            'type' => 'frontend_error',
            'action_id' => 'frontend:' . $errType,
            'title' => $title,
            'message' => \Illuminate\Support\Str::limit($data['message'], 1500),
            'status' => 'error',
            'payload' => $payload,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 500);
    }
})->middleware('throttle:20,1');

// --- WebSocket Bridge Error & Status Tracking ---
Route::post('/log/websocket', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->validate([
            'type' => 'required|string', // 'crash', 'error', 'info', 'warning'
            'message' => 'required|string',
            'action_id' => 'nullable|string',
            'payload' => 'nullable|array'
        ]);

        $payload = $data['payload'] ?? [];
        $payload['server_environment'] = [
            'php_version' => PHP_VERSION,
            'app_env' => config('app.env'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_connection' => config('queue.default'),
        ];

        $mappedStatus = in_array($data['type'], ['crash', 'error'], true)
            ? 'error'
            : ($data['type'] === 'warning' ? 'warning' : 'success');

        $actionId = $data['action_id'] ?? ('ws_' . uniqid());

        \App\Models\System\SystemLog::create([
            'type' => 'websocket',
            'action_id' => $actionId,
            'title' => 'WebSocket Bridge ' . ucfirst($data['type']),
            'message' => \Illuminate\Support\Str::limit($data['message'], 1000),
            'status' => $mappedStatus,
            'payload' => $payload,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        // --- E-Mail Benachrichtigung an Admin bei Fehlern/Crashes oder abnormalen Abbrüchen (mit Throttling gegen Mail-Bombing) ---
        $isError = in_array($data['type'], ['crash', 'error'], true);
        $isAbnormalDisconnect = ($data['type'] === 'warning' && (!isset($payload['code']) || !in_array((int)$payload['code'], [1000, 1001, 1005, 1006], true)));

        if ($isError || $isAbnormalDisconnect) {
            $mailThrottleKey = 'ws_error_mail_throttle_' . md5(($data['message'] ?? 'ws_error') . '_' . ($payload['code'] ?? ''));
            $shouldSendMail = true;
            try {
                if (\Illuminate\Support\Facades\Cache::has($mailThrottleKey)) {
                    $shouldSendMail = false;
                } else {
                    \Illuminate\Support\Facades\Cache::put($mailThrottleKey, true, now()->addMinutes(15));
                }
            } catch (\Throwable $cacheEx) {
                $shouldSendMail = true;
            }

            if ($shouldSendMail) {
                try {
                    $recipient = 'kontakt@mein-seelenfunke.de';
                    if (function_exists('shop_setting')) {
                        $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: $recipient;
                    }

                    $subject = "⚠️ WEBSOCKET BRÜCKEN-FEHLER: " . ucfirst($data['type']);
                    if ($isAbnormalDisconnect && isset($payload['code'])) {
                        $subject = "⚠️ WEBSOCKET VERBINDUNGSABBRUCH: Code " . $payload['code'];
                    }

                    $body = "Hallo Admin,\n\n";
                    $body .= "Es wurde ein technisches Problem auf der WebSocket-Audiobrücke festgestellt.\n\n";
                    $body .= "--- Details ---\n";
                    $body .= "Typ: " . strtoupper($data['type']) . "\n";
                    $body .= "Action-ID: " . $actionId . "\n";
                    $body .= "Zeit: " . now()->format('d.m.Y H:i:s') . " Uhr\n";
                    $body .= "Nachricht: " . $data['message'] . "\n\n";

                    if (!empty($payload['diagnosis']) && is_array($payload['diagnosis'])) {
                        $diag = $payload['diagnosis'];
                        $body .= "--- Automatische Ursachen-Analyse (Diagnose) ---\n";
                        $body .= "Kategorie: " . ($diag['category'] ?? 'unbekannt') . "\n";
                        $body .= "Wahrscheinliche Ursache: " . ($diag['probable_cause'] ?? 'Keine Angabe') . "\n";
                        $body .= "Empfohlene Maßnahme: " . ($diag['recommended_action'] ?? 'Systemlogs prüfen') . "\n\n";
                    }

                    if (!empty($payload['session_telemetry']) && is_array($payload['session_telemetry'])) {
                        $tel = $payload['session_telemetry'];
                        $norms = $tel['normalizations_applied'] ?? [];
                        $body .= "--- Sitzungs- & Client-Telemetrie ---\n";
                        $body .= "Agent: " . ($tel['agent_name'] ?? 'Funkira') . " (ID: " . ($tel['agent_id'] ?? '-') . ")\n";
                        $body .= "User-ID: " . ($tel['user_id'] ?? 'Gast') . " | Chat-Session: " . ($tel['chat_session_id'] ?? '-') . "\n";
                        $body .= "Client-Typ: " . ($tel['client_type'] ?? 'Unbekannt') . " | IP: " . ($tel['client_ip'] ?? '-') . "\n";
                        $body .= "User-Agent: " . ($tel['user_agent'] ?? '-') . "\n";
                        $body .= "Modell (angefordert -> aktiv): " . ($tel['requested_model'] ?? '-') . " -> " . ($tel['effective_model'] ?? '-') . "\n";
                        $body .= "Google Setup abgeschlossen: " . (!empty($tel['is_google_setup_complete']) ? 'Ja' : 'Nein') . "\n";
                        $body .= "Sitzungsdauer: " . ($tel['session_duration_sec'] ?? 0) . " Sek.\n";
                        $body .= "Pakete (Client-Audio -> Google-Msgs / Audio): " . ($tel['client_audio_packets_sent'] ?? 0) . " -> " . ($tel['google_messages_received'] ?? 0) . " / " . ($tel['google_audio_chunks_received'] ?? 0) . "\n";
                        $body .= "Tool-Calls: " . ($tel['tool_calls_count'] ?? 0) . " (Letztes Tool: " . ($tel['last_tool_call'] ?? 'keins') . ")\n";
                        $body .= "Schutz-Normalisierungen: Modell-Rewrite=" . (!empty($norms['model_rewritten']) ? 'Ja' : 'Nein')
                            . ", MediaChunks-Konvertiert=" . ($norms['media_chunks_converted'] ?? 0)
                            . ", Leere-Interrupts-Blockiert=" . ($norms['empty_interrupts_dropped'] ?? 0) . "\n\n";
                    }

                    if (!empty($payload['recent_events']) && is_array($payload['recent_events'])) {
                        $body .= "--- Letzte Ereignisse vor dem Ausfall (Chronologie) ---\n";
                        foreach ($payload['recent_events'] as $ev) {
                            $tMs = $ev['t_ms'] ?? 0;
                            $evName = $ev['event'] ?? 'event';
                            $evDetail = !empty($ev['detail']) ? " ({$ev['detail']})" : '';
                            $body .= sprintf("[+%dms] %s%s\n", $tMs, $evName, $evDetail);
                        }
                        $body .= "\n";
                    }

                    $env = $payload['server_environment'] ?? [];
                    $body .= "--- Server- & Bridge-Umgebung ---\n";
                    $body .= "Bridge-Version: " . ($payload['bridge_version'] ?? 'v1.0') . " (Uptime: " . ($payload['bridge_uptime_sec'] ?? '-') . "s, RAM: " . ($payload['memory_rss_mb'] ?? '-') . " MB)\n";
                    $body .= "Laravel Env: " . ($env['app_env'] ?? '-') . " | PHP: " . ($env['php_version'] ?? PHP_VERSION) . "\n";
                    $body .= "Treiber (Cache / Session / Queue): " . ($env['cache_driver'] ?? '-') . " / " . ($env['session_driver'] ?? '-') . " / " . ($env['queue_connection'] ?? '-') . "\n\n";

                    if (!empty($payload)) {
                        $body .= "--- Systemdaten (Payload) ---\n";
                        $body .= json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    }

                    $body .= "Bitte prüfe die Systemlogs und starte ggf. die Node.js-Audiobrücke neu.\n\n";
                    $body .= "Herzliche Grüße,\nFunkira ✨ (System-Überwachung)";

                    \Illuminate\Support\Facades\Mail::to($recipient)->send(
                        new \App\Services\AI\Mails\AiAgentMessageMail($subject, $body, 'Funkira', [], 'generic')
                    );
                } catch (\Exception $mailEx) {
                    \Illuminate\Support\Facades\Log::error("Failed to send WebSocket error mail: " . $mailEx->getMessage());
                }
            }
        }

        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'error' => $e->getMessage()], 500);
    }
})->middleware('throttle:10,1');

// --- Telegram Webhooks ---
Route::post('/telegram/webhook/{telegram_token}', [\App\Http\Controllers\Api\TelegramAgentController::class, 'handleWebhook'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:60,1');

// --- Twilio Webhooks ---
Route::post('/twilio/outbound', [\App\Http\Controllers\Api\TwilioCallController::class, 'outbound'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:60,1');
Route::post('/twilio/inbound', [\App\Http\Controllers\Api\TwilioCallController::class, 'inbound'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:60,1');
Route::post('/twilio/call-log', [\App\Http\Controllers\Api\TwilioCallController::class, 'callLog'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:30,1');

// --- Flight Data Proxy (CORS Fix) ---
Route::get('/flights', function (\Illuminate\Http\Request $request) {
    try {
        $url = "https://opensky-network.org/api/states/all";
        $queryParams = array_filter($request->only(['lamin', 'lomin', 'lamax', 'lomax']), function($value) {
            return !is_null($value) && $value !== '';
        });
        
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        $response = \Illuminate\Support\Facades\Http::get($url);
        
        return response()->json($response->json());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Flight API Proxy failed'], 500);
    }
})->middleware('throttle:30,1');

// --- Secure File View for Mobile App (allows query parameter token authentication) ---
Route::get('/funki/financials/receipt', function (\Illuminate\Http\Request $request) {
    $tokenString = $request->query('token');
    if ($tokenString) {
        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
        if ($token && $token->tokenable) {
            $user = $token->tokenable;
            $request->setUserResolver(fn() => $user);
        }
    }

    $user = $request->user() ?: $request->user('sanctum');
    if (!$user) {
        abort(401, 'Unauthorized');
    }

    if (!$user instanceof \App\Models\Admin\Admin) {
        abort(403, 'Nur Administratoren dürfen Buchhaltungsbelege einsehen.');
    }

    $path = $request->query('path');
    if (!$path || str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
        abort(404, 'Path missing or invalid');
    }

    $fullPath = null;
    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
    } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
    }

    if (!$fullPath || !file_exists($fullPath)) {
        abort(404, 'File not found');
    }

    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
    $safeInlineMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $disposition = in_array($mime, $safeInlineMimes, true) ? 'inline' : 'attachment';

    return response()->file($fullPath, [
        'Content-Type' => $mime,
        'Content-Disposition' => $disposition . '; filename="' . basename($fullPath) . '"',
        'X-Content-Type-Options' => 'nosniff',
    ]);
});

// --- Secure Laser SVG Download for Mobile App ---
Route::get('/funki/shop/order-items/{itemId}/laser-file/{side}', function (\Illuminate\Http\Request $request, $itemId, $side, \App\Services\Export\FileDownloadService $downloadService) {
    $tokenString = $request->query('token');
    if ($tokenString) {
        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
        if ($token && $token->tokenable) {
            $user = $token->tokenable;
            $request->setUserResolver(fn() => $user);
        }
    }

    $user = $request->user() ?: $request->user('sanctum');
    if (!$user) {
        abort(401, 'Unauthorized');
    }

    if (!$user instanceof \App\Models\Admin\Admin) {
        abort(403, 'Nur Admins dürfen Bestellungen verwalten.');
    }

    return $downloadService->downloadLaserSvg($itemId, $side);
});

// --- 2. Geschützte API-Routen (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {

    // Basis User- & Geräte-Funktionen (Für alle authentifizierten Benutzer: Admin, Mitarbeiter, Kunde)
    require __DIR__ . '/api/core.php';

    // Admin Back-Office (Ausschließlich für Administratoren zugänglich)
    Route::group(['middleware' => [function ($request, $next) {
        if (!$request->user() instanceof \App\Models\Admin\Admin) {
            abort(403, 'Zugriff verweigert. Nur Administratoren dürfen auf das Back-Office zugreifen.');
        }
        return $next($request);
    }]], function () {

        // Finanz-Manager
        require __DIR__ . '/api/finance.php';

        // Tagesroutine
        require __DIR__ . '/api/routine.php';

        // Tasks (Aufgaben)
        require __DIR__ . '/api/tasks.php';

        // E-Mails
        require __DIR__ . '/api/emails.php';

        // Kontakte
        require __DIR__ . '/api/contacts.php';

        // Einkaufsliste
        require __DIR__ . '/api/shopping.php';

        // Calender & Termine
        require __DIR__ . '/api/calendar.php';

        // Shop Orders (Bestellungen)
        require __DIR__ . '/api/orders.php';

        // AI Chat
        Route::get('/ai/agents', function() {
            return response()->json([
                'success' => true,
                'data' => \App\Models\Ai\AiAgent::where('is_active', true)->orderBy('name')->get()
            ]);
        });
    });

});

// --- First-Party Produkt-Telemetrie Beacon (DSGVO-konform, kein CSRF nötig) ---
Route::post('/product-telemetry/beacon', function (\Illuminate\Http\Request $request) {
    try {
        $source = $request->input('source', 'configurator');
        $productId = $request->input('product_id');
        $step = $request->input('step');
        $meta = $request->input('meta', []);

        if ($source === 'calculator') {
            \App\Services\ProductTrackingService::trackCalculatorAbandoned(
                is_numeric($step) ? (int) $step : 0,
                is_array($meta) ? $meta : []
            );
        } else {
            \App\Services\ProductTrackingService::trackConfiguratorAbandoned(
                $productId,
                is_array($meta) ? $meta : []
            );
        }

        return response()->json(['success' => true]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false], 200);
    }
});



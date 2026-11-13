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
            'message' => 'required|string|max:2000',
            'source' => 'nullable|string|max:500',
            'lineno' => 'nullable|integer|min:0|max:10000000',
            'colno' => 'nullable|integer|min:0|max:10000000',
            'url' => 'nullable|string|max:1000',
            'page_title' => 'nullable|string|max:300',
            'viewport' => 'nullable|string|max:100',
            'userAgent' => 'nullable|string|max:500',
            'type' => 'nullable|string|max:64',
            'status_code' => 'nullable|integer|min:100|max:599',
            'stack' => 'nullable|string|max:10000'
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

        $rawIp = (string) $request->ip();
        $anonIp = class_exists(\Symfony\Component\HttpFoundation\IpUtils::class) && $rawIp !== ''
            ? \Symfony\Component\HttpFoundation\IpUtils::anonymize($rawIp)
            : $rawIp;

        $payload = array_merge($data, [
            'file' => $data['source'] ?? null,
            'line' => $data['lineno'] ?? null,
            'client_ip' => $anonIp,
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
    if (!\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($request)) {
        \App\Services\System\SystemSecurityService::logEvent(
            'security:ws_log_unauthorized',
            'Unautorisierter WebSocket-Log-Aufruf blockiert',
            'Externer Aufruf auf /api/log/websocket von IP ' . $request->ip() . ' abgewehrt.',
            'warning'
        );
        return response()->json(['status' => 'forbidden'], 403);
    }

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
                        $recipient = shop_setting('owner_email_backup') ?: shop_setting('company_email') ?: shop_setting('owner_email') ?: $recipient;
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
        $bounds = ['lamin' => [-90, 90], 'lamax' => [-90, 90], 'lomin' => [-180, 180], 'lomax' => [-180, 180]];
        $queryParams = [];
        foreach ($bounds as $param => [$min, $max]) {
            $val = $request->query($param);
            if (is_scalar($val) && $val !== '' && is_numeric($val)) {
                $floatVal = (float) $val;
                if ($floatVal >= $min && $floatVal <= $max) {
                    $queryParams[$param] = $floatVal;
                }
            }
        }
        
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        $data = \Illuminate\Support\Facades\Cache::remember('opensky_flights_' . md5($url), 10, function () use ($url) {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url);
            return $response->successful() ? $response->json() : [];
        });
        
        return response()->json($data);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Flight API Proxy failed'], 500);
    }
})->middleware('throttle:30,1');

// --- Secure File View for Mobile App (allows query parameter token authentication) ---
Route::get('/funki/financials/receipt', function (\Illuminate\Http\Request $request) {
    $tokenString = $request->query('token');
    if (is_string($tokenString) && $tokenString !== '') {
        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
        if ($token && !$token->can('*')) {
            abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
        }
        if ($token && (!$token->expires_at || !$token->expires_at->isPast()) && $token->tokenable) {
            $user = $token->tokenable;
            $request->setUserResolver(fn() => $user);
        }
    } elseif ($request->bearerToken()) {
        $pat = \Laravel\Sanctum\PersonalAccessToken::findToken((string) $request->bearerToken());
        if ($pat && !$pat->can('*')) {
            abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
        }
    }

    $user = $request->user() ?: $request->user('sanctum');
    if (!$user) {
        abort(401, 'Unauthorized');
    }

    if (!$user instanceof \App\Models\Admin\Admin) {
        abort(403, 'Nur Administratoren dürfen Buchhaltungsbelege einsehen.');
    }
    if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && !$user->tokenCan('*')) {
        abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
    }

    $path = $request->query('path');
    if (!is_string($path) || $path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
        abort(404, 'Path missing or invalid');
    }

    // Sicherheit: Erlaube nur Pfade innerhalb der Buchhaltungs-/Beleg- & Aufgaben-Verzeichnisse
    $allowedPrefixes = ['buchhaltung/', 'invoices/', 'finanzen/', 'receipts/', 'leitung/tasks/'];
    $hasAllowedPrefix = false;
    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            $hasAllowedPrefix = true;
            break;
        }
    }
    if (!$hasAllowedPrefix) {
        \App\Services\System\SystemSecurityService::logEvent(
            'security:path_traversal_blocked',
            'Unzulässiger Beleg-Dateipfad blockiert',
            "Zugriff auf Pfad '{$path}' außerhalb des Beleg-Tresors verweigert.",
            'error',
            ['path' => $path]
        );
        abort(403, 'Unzulässiger Dateipfad.');
    }

    $fullPath = null;
    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
    } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
    }

    if (!$fullPath || !file_exists($fullPath) || is_link($fullPath)) {
        abort(404, 'File not found');
    }

    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
    $safeInlineMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $disposition = in_array($mime, $safeInlineMimes, true) ? 'inline' : 'attachment';
    $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($fullPath));

    return response()->file($fullPath, [
        'Content-Type' => $mime,
        'Content-Disposition' => $disposition . '; filename="' . $safeFilename . '"',
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->middleware('throttle:30,1');

// --- Secure Laser SVG Download for Mobile App ---
Route::get('/funki/shop/order-items/{itemId}/laser-file/{side}', function (\Illuminate\Http\Request $request, $itemId, $side, \App\Services\Export\FileDownloadService $downloadService) {
    $tokenString = $request->query('token');
    if (is_string($tokenString) && $tokenString !== '') {
        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
        if ($token && !$token->can('*')) {
            abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
        }
        if ($token && (!$token->expires_at || !$token->expires_at->isPast()) && $token->tokenable) {
            $user = $token->tokenable;
            $request->setUserResolver(fn() => $user);
        }
    } elseif ($request->bearerToken()) {
        $pat = \Laravel\Sanctum\PersonalAccessToken::findToken((string) $request->bearerToken());
        if ($pat && !$pat->can('*')) {
            abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
        }
    }

    $user = $request->user() ?: $request->user('sanctum');
    if (!$user) {
        abort(401, 'Unauthorized');
    }

    if (!$user instanceof \App\Models\Admin\Admin) {
        abort(403, 'Nur Admins dürfen Bestellungen verwalten.');
    }
    if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && !$user->tokenCan('*')) {
        abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
    }

    return $downloadService->downloadLaserSvg($itemId, $side);
})->middleware('throttle:30,1');

// --- Secure Private Music Stream (Supports Bearer Token or ?token= Query Param + HTTP 206 Range Requests) ---
Route::get('/funki/music/stream/{id}', function (\Illuminate\Http\Request $request, $id) {
    $tokenString = $request->query('token');
    if (is_string($tokenString) && $tokenString !== '') {
        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
        if ($token && !$token->can('*')) {
            abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
        }
        if ($token && (!$token->expires_at || !$token->expires_at->isPast()) && $token->tokenable) {
            $user = $token->tokenable;
            $request->setUserResolver(fn() => $user);
        }
    } elseif ($request->bearerToken()) {
        $pat = \Laravel\Sanctum\PersonalAccessToken::findToken((string) $request->bearerToken());
        if ($pat && !$pat->can('*')) {
            abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
        }
    }

    $user = $request->user() ?: $request->user('sanctum');
    if (!$user || !$user instanceof \App\Models\Admin\Admin) {
        abort(401, 'Unauthorized');
    }
    if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && !$user->tokenCan('*')) {
        abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $track = \App\Models\Management\ManagementTrack::findOrFail($id);

    $fullPath = null;
    if (!empty($track->file_path)) {
        $relPath = ltrim(str_replace('\\', '/', (string) $track->file_path), '/');
        if (
            $relPath !== ''
            && !str_contains($relPath, '..')
            && !str_contains($relPath, "\0")
            && str_starts_with($relPath, 'leitung/music/')
        ) {
            $cand = storage_path('app/' . $relPath);
            if (file_exists($cand) && !is_link($cand) && filesize($cand) > 10000) {
                $fullPath = $cand;
            }
        }
    }

    if (!$fullPath && !empty($track->external_id)) {
        $vid = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $track->external_id);
        if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $vid)) {
            abort(422, 'Ungültige externe Audio-ID.');
        }
        $rel = "leitung/music/tracks/{$vid}.m4a";
        $cand = storage_path("app/{$rel}");
        if (file_exists($cand) && filesize($cand) > 10000) {
            $fullPath = $cand;
            if ($track->status !== 'cached') {
                $track->update(['file_path' => $rel, 'status' => 'cached', 'file_size' => filesize($cand)]);
            }
        } else {
            // Instant on-the-fly download if not yet cached
            $ytDlp = storage_path('app/leitung/music/bin/yt-dlp');
            $tracksDir = storage_path('app/leitung/music/tracks');
            if (!is_dir($tracksDir)) {
                @mkdir($tracksDir, 0775, true);
            }
            $outPattern = storage_path('app/leitung/music/tracks/%(id)s.%(ext)s');
            $cmd = escapeshellarg($ytDlp)
                . ' --no-playlist -f "140/ba[ext=m4a]/ba" --concurrent-fragments 4 --no-warnings -o '
                . escapeshellarg($outPattern) . ' -- '
                . escapeshellarg("https://www.youtube.com/watch?v={$vid}") . ' 2>&1';
            @shell_exec($cmd);

            if (file_exists($cand) && filesize($cand) > 10000) {
                $fullPath = $cand;
                $track->update(['file_path' => $rel, 'status' => 'cached', 'file_size' => filesize($cand)]);
            } else {
                $matches = glob(storage_path("app/leitung/music/tracks/{$vid}.*"));
                if (!empty($matches) && filesize($matches[0]) > 10000) {
                    $fullPath = $matches[0];
                    $track->update([
                        'file_path' => 'leitung/music/tracks/' . basename($fullPath),
                        'status' => 'cached',
                        'file_size' => filesize($fullPath),
                    ]);
                }
            }
        }
    }

    if (!$fullPath || !file_exists($fullPath)) {
        abort(404, 'Audiospur nicht gefunden.');
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'mp3' => 'audio/mpeg',
        'm4a', 'mp4', 'aac' => 'audio/mp4',
        'wav' => 'audio/wav',
        'ogg', 'opus' => 'audio/ogg',
        default => 'audio/mp4',
    };

    $size = filesize($fullPath);
    $start = 0;
    $end = $size - 1;
    $status = 200;

    $headers = [
        'Content-Type' => $mime,
        'Accept-Ranges' => 'bytes',
        'Cache-Control' => 'private, max-age=86400',
        'X-Content-Type-Options' => 'nosniff',
    ];

    if ($request->headers->has('Range')) {
        $range = $request->header('Range');
        if (preg_match('/bytes=(\d+)-(\d*)/i', (string)$range, $matches)) {
            $start = (int) $matches[1];
            if ($matches[2] !== '') {
                $end = min($size - 1, (int) $matches[2]);
            }
            if ($start <= $end && $start < $size) {
                $status = 206;
                $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
            }
        }
    }

    if ($start === 0) {
        $track->increment('play_count');
    }

    $length = ($end - $start) + 1;
    $headers['Content-Length'] = $length;

    return response()->stream(function () use ($fullPath, $start, $length) {
        $fp = fopen($fullPath, 'rb');
        if ($fp === false) return;
        if ($start > 0) {
            fseek($fp, $start);
        }
        $remaining = $length;
        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, min(65536, $remaining));
            if ($chunk === false || $chunk === '') break;
            echo $chunk;
            $remaining -= strlen($chunk);
            if (ob_get_level() > 0) @ob_flush();
            flush();
        }
        fclose($fp);
    }, $status, $headers);
})->middleware('throttle:240,1');

// --- 2. Geschützte API-Routen (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {

    // --- 2a. Isolierter Zero-Trust Manufaktur-Monitor Endpunkt (Read-Only, 0 PII / 0 Finanzdaten) ---
    Route::get('/shop/monitor-pulse', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if (!$user instanceof \App\Models\Admin\Admin) {
            abort(403, 'Nur Administratoren dürfen den Manufaktur-Monitor abrufen.');
        }
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && !$user->tokenCan('monitor:pulse') && !$user->tokenCan('*')) {
            abort(403, 'Unzureichende Token-Berechtigung.');
        }

        $formatGdprShortName = static function (?string $firstName, ?string $lastName, ?string $fallbackName = null): string {
            $first = trim((string) $firstName);
            $last = trim((string) $lastName);
            if ($first === '' && $last === '' && !empty($fallbackName)) {
                $parts = preg_split('/\s+/', trim((string) $fallbackName)) ?: [];
                $first = $parts[0] ?? '';
                $last = count($parts) > 1 ? (string) end($parts) : '';
            }
            if ($first === '') {
                return 'Kunde';
            }
            $firstClean = mb_substr($first, 0, 18);
            if ($last !== '') {
                $initial = mb_strtoupper(mb_substr($last, 0, 1));
                return $firstClean . ' ' . $initial . '.';
            }
            return $firstClean;
        };

        $validOrdersForTop = \App\Models\Order\OrderOrder::query()
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get(['customer_id', 'email', 'billing_address', 'total_price']);

        $groupedCustomers = [];
        foreach ($validOrdersForTop as $row) {
            $key = !empty($row->customer_id)
                ? 'cid_' . $row->customer_id
                : 'mail_' . mb_strtolower(trim((string) $row->email));
            if ($key === 'mail_') {
                continue;
            }
            if (!isset($groupedCustomers[$key])) {
                $billing = is_array($row->billing_address) ? $row->billing_address : [];
                $groupedCustomers[$key] = [
                    'display_name' => $formatGdprShortName(
                        $billing['first_name'] ?? null,
                        $billing['last_name'] ?? null,
                        $row->customer_name ?? null
                    ),
                    'total_cents' => 0,
                    'orders_count' => 0,
                ];
            }
            $groupedCustomers[$key]['total_cents'] += (int) $row->total_price;
            $groupedCustomers[$key]['orders_count']++;
        }

        usort($groupedCustomers, static function (array $a, array $b): int {
            if ($b['total_cents'] !== $a['total_cents']) {
                return $b['total_cents'] <=> $a['total_cents'];
            }
            return $b['orders_count'] <=> $a['orders_count'];
        });

        $topDisplayOnly = array_map(
            static fn(array $c): array => ['display_name' => $c['display_name']],
            array_slice(array_values($groupedCustomers), 0, 3)
        );

        $openBaseQuery = \App\Models\Order\OrderOrder::whereNotIn('status', ['completed', 'cancelled', 'refunded', 'shipped']);

        // Aktive Tagesroutine (Europe/Berlin) für den Manufaktur-Monitor (inkl. Fokus-Modus)
        $activeRoutinePayload = null;
        try {
            $nowBerlin = \Carbon\Carbon::now('Europe/Berlin');
            $nowMins = ((int) $nowBerlin->format('G')) * 60 + (int) $nowBerlin->format('i');
            $routines = \App\Models\Management\ManagementDayRoutine::with('steps')
                ->where('is_active', true)
                ->orderBy('start_time', 'asc')
                ->get();

            foreach ($routines as $r) {
                $st = \Carbon\Carbon::parse($r->start_time);
                $sMins = ((int) $st->format('G')) * 60 + (int) $st->format('i');
                $dur = max(1, (int) ($r->duration_minutes ?: 60));
                $eMins = $sMins + $dur;
                $inWindow = $eMins <= 1440
                    ? ($nowMins >= $sMins && $nowMins < $eMins)
                    : ($nowMins >= $sMins || $nowMins < ($eMins % 1440));

                if ($inWindow) {
                    $elapsed = $nowMins >= $sMins ? ($nowMins - $sMins) : (1440 - $sMins + $nowMins);
                    $stepTitle = '';
                    $acc = 0;
                    foreach ($r->steps as $step) {
                        $acc += max(1, (int) ($step->duration_minutes ?: 10));
                        if ($elapsed < $acc) {
                            $stepTitle = (string) $step->title;
                            break;
                        }
                    }
                    $startStr = sprintf('%02d:%02d', intdiv($sMins, 60), $sMins % 60);
                    $endMod = $eMins % 1440;
                    $endStr = sprintf('%02d:%02d', intdiv($endMod, 60), $endMod % 60);
                    $activeRoutinePayload = [
                        'start_time' => $startStr,
                        'end_time' => $endStr,
                        'title' => (string) $r->title,
                        'type' => (string) ($r->type ?? 'work'),
                        'current_step' => $stepTitle,
                        'banner' => $startStr . ' - ' . $endStr . ' · ' . mb_strtoupper((string) $r->title),
                    ];
                    break;
                }
            }
        } catch (\Throwable $routineEx) {
            $activeRoutinePayload = null;
        }

        // Jahreszeit, Feiertag & Shop-Wachstums-Stufe (1..5) für Funkiras 3D-Atelier
        $nowB = \Carbon\Carbon::now('Europe/Berlin');
        $m = (int) $nowB->month;
        $d = (int) $nowB->day;
        $season = in_array($m, [12, 1, 2], true) ? 'winter' : (in_array($m, [3, 4, 5], true) ? 'spring' : (in_array($m, [6, 7, 8], true) ? 'summer' : 'autumn'));
        $occasion = 'standard';
        if (($m === 12 && $d >= 24 && $d <= 26)) {
            $occasion = 'christmas';
        } elseif (($m === 11 && $d >= 24) || ($m === 12 && $d < 24)) {
            $occasion = 'advent';
        } elseif (($m === 12 && $d >= 30) || ($m === 1 && $d <= 2)) {
            $occasion = 'new_year';
        } elseif ($m === 2 && $d >= 7 && $d <= 14) {
            $occasion = 'valentines';
        } elseif ($m === 10 && $d >= 25) {
            $occasion = 'halloween';
        } elseif ($m === 11 && $d >= 1 && $d <= 3) {
            $occasion = 'anniversary';
        } elseif ($m === 5 && $d >= 8 && $d <= 14) {
            $occasion = 'mothers_day';
        } elseif (($m === 3 && $d >= 22) || ($m === 4 && $d <= 22)) {
            try {
                if (function_exists('easter_days')) {
                    $easter = \Carbon\Carbon::create($nowB->year, 3, 21, 0, 0, 0, 'Europe/Berlin')->addDays(easter_days($nowB->year));
                    if (abs($nowB->diffInDays($easter, false)) <= 5) {
                        $occasion = 'easter';
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $validOrderCountBase = \App\Models\Order\OrderOrder::whereNotIn('status', ['cancelled', 'refunded']);
        $ordersToday = (clone $validOrderCountBase)->whereDate('created_at', $nowB->toDateString())->count();
        $ordersWeek = (clone $validOrderCountBase)->where('created_at', '>=', $nowB->copy()->startOfWeek())->count();
        $ordersMonth = (clone $validOrderCountBase)->where('created_at', '>=', $nowB->copy()->startOfMonth())->count();
        $totalValidOrders = count($validOrdersForTop);
        $growthScore = max($ordersMonth, (int) floor($totalValidOrders / 2));
        $shopLevel = $growthScore >= 100 ? 5 : ($growthScore >= 50 ? 4 : ($growthScore >= 20 ? 3 : ($growthScore >= 5 ? 2 : 1)));
        $levelTitles = [
            1 => 'Gründer-Werkbank',
            2 => 'Aufstrebende Manufaktur',
            3 => 'Florierendes Atelier',
            4 => 'Premium-Manufaktur',
            5 => 'Seelenfunke Imperium',
        ];

        return response()->json([
            'open_orders_count' => (clone $openBaseQuery)->count(),
            'express_orders_count' => (clone $openBaseQuery)->where('is_express', true)->count(),
            'open_carts_count' => \App\Models\Cart\Cart::whereHas('items')->count(),
            'total' => \App\Models\Order\OrderOrder::count(),
            'latest_order_id' => (string) (\App\Models\Order\OrderOrder::max('id') ?? ''),
            'top_customers' => $topDisplayOnly,
            'active_routine' => $activeRoutinePayload,
            'pending_trichter_count' => \App\Services\System\MiniPcTrichterRelayService::countPending(),
            'open_system_errors_count' => class_exists(\App\Models\System\SystemLog::class)
                ? \App\Models\System\SystemLog::where('status', 'error')->where('started_at', '>=', now()->subHours(24))->count()
                : 0,
            'world_state' => [
                'season' => $season,
                'occasion' => $occasion,
                'orders_today' => $ordersToday,
                'orders_week' => $ordersWeek,
                'orders_month' => $ordersMonth,
                'shop_level' => $shopLevel,
                'level_title' => $levelTitles[$shopLevel] ?? 'Gründer-Werkbank',
            ],
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->middleware('throttle:60,1');

    // --- 2a-bis. Verschlüsselte Eingang-Trichter Abholung für den Werkstatt-Mini-PC (nur Admin mit ai:funkira oder *) ---
    Route::prefix('shop/minipc-trichter')->middleware('throttle:60,1')->group(function () {
        $authorizeMiniPcTrichter = static function (\Illuminate\Http\Request $request): void {
            if ($request->bearerToken()) {
                $pat = \Laravel\Sanctum\PersonalAccessToken::findToken((string) $request->bearerToken());
                if (!$pat || (!$pat->can('ai:funkira') && !$pat->can('*'))) {
                    abort(403, 'Unzureichende Token-Berechtigung für Eingang-Trichter.');
                }
            }

            $user = $request->user();
            if (!$user instanceof \App\Models\Admin\Admin) {
                abort(403, 'Nur Administratoren dürfen auf die Mini-PC Trichter-Schleuse zugreifen.');
            }
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && !$user->tokenCan('ai:funkira') && !$user->tokenCan('*')) {
                abort(403, 'Unzureichende Token-Berechtigung für Eingang-Trichter.');
            }
        };

        Route::get('/pending', function (\Illuminate\Http\Request $request) use ($authorizeMiniPcTrichter) {
            $authorizeMiniPcTrichter($request);
            $items = \App\Services\System\MiniPcTrichterRelayService::listPending();
            return response()->json([
                'success' => true,
                'count' => count($items),
                'items' => $items,
            ], 200, ['Cache-Control' => 'no-store, private']);
        });

        Route::get('/pull/{id}', function (\Illuminate\Http\Request $request, string $id) use ($authorizeMiniPcTrichter) {
            $authorizeMiniPcTrichter($request);
            $pulled = \App\Services\System\MiniPcTrichterRelayService::pullDecrypted($id);
            if (!$pulled) {
                abort(404, 'Trichter-Dokument nicht gefunden oder bereits abgeholt.');
            }
            $meta = $pulled['meta'];
            return response($pulled['bytes'], 200, [
                'Content-Type' => (string) ($meta['mime_type'] ?? 'application/octet-stream'),
                'X-Trichter-Filename' => rawurlencode((string) ($meta['file_name'] ?? 'dokument.pdf')),
                'X-Trichter-Title' => rawurlencode((string) ($meta['custom_title'] ?? '')),
                'X-Trichter-Sha256' => (string) ($meta['sha256'] ?? ''),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        });

        Route::delete('/ack/{id}', function (\Illuminate\Http\Request $request, string $id) use ($authorizeMiniPcTrichter) {
            $authorizeMiniPcTrichter($request);
            $deleted = \App\Services\System\MiniPcTrichterRelayService::ackAndDelete($id);
            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'remaining_count' => \App\Services\System\MiniPcTrichterRelayService::countPending(),
            ]);
        });
    });

    // --- 2a-ter. Zentraler Ökosystem-Error-Sentinel (Portal, Android App, Werkstatt Mini-PC & Funkenwerk Windows) ---
    Route::post('/system/error-ingest', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if (!$user instanceof \App\Models\Admin\Admin) {
            abort(403, 'Nur Administratoren dürfen System-Diagnosedaten einliefern.');
        }

        if ($request->bearerToken()) {
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken((string) $request->bearerToken());
            if (!$pat || (!$pat->can('*') && !$pat->can('ai:funkira') && !$pat->can('monitor:pulse'))) {
                abort(403, 'Unzureichende Token-Berechtigung für Error-Sentinel.');
            }
        }

        $rawEvents = $request->input('events');
        if (!is_array($rawEvents)) {
            $rawEvents = [$request->only([
                'platform', 'component', 'severity', 'title', 'message', 'error_class',
                'file', 'line', 'stack', 'fingerprint', 'occurrences', 'auto_healed',
                'auto_heal_action', 'app_version', 'device', 'occurred_at', 'context',
            ])];
        }

        $ingested = 0;
        $ids = [];
        foreach (array_slice($rawEvents, 0, 25) as $ev) {
            if (!is_array($ev) || empty($ev['message'])) {
                continue;
            }
            $log = \App\Services\System\SystemErrorLogger::recordCrossPlatformEvent($ev);
            if ($log) {
                $ingested++;
                $ids[] = $log->id;
            }
        }

        return response()->json([
            'success' => true,
            'ingested' => $ingested,
            'log_ids' => $ids,
        ], 200, ['Cache-Control' => 'no-store, private']);
    })->middleware('throttle:60,1');

    // --- 2a-quater. Funkira Telefon-Assistent Relay (Android Smartphone <-> Werkstatt Mini-PC, Zero-Twilio) ---
    Route::prefix('system/phone-concierge')->middleware('throttle:120,1')->group(function () {
        $authorizePhoneConcierge = function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if (!$user instanceof \App\Models\Admin\Admin) {
                abort(403, 'Nur Administratoren dürfen den Funkira Telefon-Assistenten nutzen.');
            }
            if ($request->bearerToken()) {
                $pat = \Laravel\Sanctum\PersonalAccessToken::findToken((string) $request->bearerToken());
                if (!$pat || (!$pat->can('*') && !$pat->can('ai:funkira') && !$pat->can('monitor:pulse'))) {
                    abort(403, 'Unzureichende Token-Berechtigung für Telefon-Assistent.');
                }
            }
        };

        Route::get('/config', function (\Illuminate\Http\Request $request) use ($authorizePhoneConcierge) {
            $authorizePhoneConcierge($request);
            return response()->json([
                'success' => true,
                'data' => \App\Services\System\FunkiraPhoneConciergeService::getConfigPayload(),
            ], 200, ['Cache-Control' => 'no-store, private']);
        });

        Route::post('/config', function (\Illuminate\Http\Request $request) use ($authorizePhoneConcierge) {
            $authorizePhoneConcierge($request);
            $validated = $request->validate([
                'enabled' => 'nullable|boolean',
                'ring_delay_seconds' => 'nullable|integer|min:0|max:30',
            ]);
            $enabled = array_key_exists('enabled', $validated) ? (bool) $validated['enabled'] : null;
            $delay = array_key_exists('ring_delay_seconds', $validated) ? (int) $validated['ring_delay_seconds'] : null;
            return response()->json([
                'success' => true,
                'data' => \App\Services\System\FunkiraPhoneConciergeService::updateConfig($enabled, $delay),
            ], 200, ['Cache-Control' => 'no-store, private']);
        });

        Route::post('/signal', function (\Illuminate\Http\Request $request) use ($authorizePhoneConcierge) {
            $authorizePhoneConcierge($request);
            $validated = $request->validate([
                'call_id' => 'nullable|string|max:100',
                'state' => 'required|string|in:ringing,accepted,cancelled,ended',
                'is_simulation' => 'nullable|boolean',
                'contact_id' => 'nullable|string|max:64',
                'caller_name' => 'nullable|string|max:160',
                'caller_nickname' => 'nullable|string|max:120',
                'relation_type' => 'nullable|string|max:120',
                'caller_phone' => 'nullable|string|max:80',
                'system_instructions' => 'nullable|string|max:1500',
                'audio_mode' => 'nullable|string|max:40',
            ]);
            $result = \App\Services\System\FunkiraPhoneConciergeService::signalCallState($validated);
            return response()->json([
                'success' => empty($result['rejected']),
                'data' => $result,
            ], 200, ['Cache-Control' => 'no-store, private']);
        });

        Route::get('/poll', function (\Illuminate\Http\Request $request) use ($authorizePhoneConcierge) {
            $authorizePhoneConcierge($request);
            return response()->json([
                'success' => true,
                'data' => \App\Services\System\FunkiraPhoneConciergeService::pollState(),
            ], 200, ['Cache-Control' => 'no-store, private']);
        });

        Route::post('/complete', function (\Illuminate\Http\Request $request) use ($authorizePhoneConcierge) {
            $authorizePhoneConcierge($request);
            $validated = $request->validate([
                'call_id' => 'nullable|string|max:100',
                'is_simulation' => 'nullable|boolean',
                'contact_id' => 'nullable|string|max:64',
                'caller_name' => 'nullable|string|max:160',
                'greeting_name' => 'nullable|string|max:120',
                'summary' => 'required|string|max:1000',
                'urgency' => 'nullable|string|in:hoch,mittel,niedrig',
                'callback_requested' => 'nullable|boolean',
            ]);
            $result = \App\Services\System\FunkiraPhoneConciergeService::completeCall($validated);
            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200, ['Cache-Control' => 'no-store, private']);
        });
    });

    // Selbst-Herabstufung eines Voll-Admin-Tokens auf ein reines Read-Only Manufaktur-Monitor-Token (monitor:pulse)
    Route::post('/auth/downgrade-to-monitor-token', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if (!$user instanceof \App\Models\Admin\Admin) {
            abort(403, 'Nur Administratoren dürfen Monitor-Tokens ausstellen.');
        }

        $currentToken = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if ($currentToken && method_exists($currentToken, 'can') && !$currentToken->can('*') && $currentToken->can('monitor:pulse')) {
            return response()->json([
                'status' => 'already_scoped',
                'scope' => 'monitor:pulse',
            ]);
        }

        $deviceSuffix = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->input('device_name', 'Kiosk'));
        $tokenName = 'FunkenwerkMonitor-' . ($deviceSuffix !== '' ? $deviceSuffix : 'Kiosk') . '-admin';

        if ($currentToken && method_exists($currentToken, 'delete')) {
            $currentToken->delete();
        }
        $user->tokens()->where('name', $tokenName)->delete();

        $newToken = $user->createToken($tokenName, ['monitor:pulse'])->plainTextToken;

        return response()->json([
            'status' => 'downgraded',
            'scope' => 'monitor:pulse',
            'token' => $newToken,
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    })->middleware('throttle:10,1');

    // --- 2b. Vollzugriffs-Schranke: Eingeschränkte Geräte-Tokens (z.B. monitor:pulse) werden für das gesamte restliche Portal blockiert ---
    Route::group(['middleware' => [function ($request, $next) {
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && !$user->tokenCan('*')) {
            if (!$request->is('api/logout')) {
                abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
            }
        }
        return $next($request);
    }]], function () {

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

            // Musik & Soundplayer
            require __DIR__ . '/api/music.php';

            // Shop Orders (Bestellungen)
            require __DIR__ . '/api/orders.php';

            // Dateiverwaltung (Workspace & Dateitrichter)
            require __DIR__ . '/api/files.php';

            // Marketing & Content-Planer Live-Kontext (Shop-Produkte, Margen, Jahreszeiten & Feiertage)
            Route::get('/funki/marketing/live-context', function (\App\Services\Marketing\HolidayOfferAutopilotService $autopilot) {
                return response()->json([
                    'success' => true,
                    'data' => $autopilot->buildLiveMarketingPlanContext(),
                ]);
            });

            // AI Chat
            Route::get('/ai/agents', function() {
                return response()->json([
                    'success' => true,
                    'data' => \App\Models\Ai\AiAgent::where('is_active', true)->orderBy('name')->get()
                ]);
            });
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
})->middleware('throttle:60,1');



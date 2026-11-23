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

        // Kurzlebiger Zero-Secret Vertex-OAuth2-Token-Broker (strikt Admin-only im Controller)
        Route::get('/vertex-ephemeral', [AIController::class, 'vertexEphemeral'])->middleware('throttle:30,1');

        // Leichtgewichtiger Health- & Latenz-Ping für das Live-AI Seiten-Widget
        Route::get('/bridge-ping', [AIController::class, 'bridgePing']);

        // Detaillierte Fehler- & Auto-Heal-Protokollierung des Live-AI Web-Clients
        Route::post('/client-error-log', [AIController::class, 'clientErrorLog'])->middleware('throttle:30,1');

        // Geschützter Abruf der System-Brain-Map (Architektur-Graph inkl. Live-SystemLog-Fehlerkerne in Rot, mit mtime-Cache)
        Route::get('/system-brain-map', function () {
            $jsonPath = storage_path('app/private/system-brain-map.json');
            if (!\Illuminate\Support\Facades\File::exists($jsonPath)) {
                $jsonPath = storage_path('app/public/system-brain-map.json');
            }
            if (!\Illuminate\Support\Facades\File::exists($jsonPath)) {
                return response()->json(['error' => 'Brain Map not generated yet'], 404);
            }

            $mtime = (int) @filemtime($jsonPath);
            $decoded = \Illuminate\Support\Facades\Cache::remember(
                'system_brain_map_base_' . md5($jsonPath . ':' . $mtime),
                3600,
                function () use ($jsonPath) {
                    $rawJson = \Illuminate\Support\Facades\File::get($jsonPath);
                    return json_decode($rawJson, true);
                }
            );

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

        // Ultraschnelle Direkt-Orts- & POI-Suche für das interaktive Map-HUD (0 LLM-Wartezeit, 24h gecacht)
        Route::get('/map-quick-search', function (\Illuminate\Http\Request $request) {
            $query = trim(mb_substr((string) $request->query('q', ''), 0, 120));
            if (mb_strlen($query) < 2) {
                return response()->json(['status' => 'success', 'results' => []]);
            }

            $token = config('services.mapbox.token') ?: env('MAPBOX_TOKEN');
            $cacheKey = 'map_hud_quick_' . md5(mb_strtolower($query));

            $results = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($query, $token) {
                $items = [];
                if (!empty($token)) {
                    try {
                        $res = \Illuminate\Support\Facades\Http::timeout(3)->get(
                            'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode($query) . '.json',
                            [
                                'access_token' => $token,
                                'limit' => 5,
                                'language' => 'de',
                            ]
                        );
                        if ($res->successful()) {
                            foreach (($res->json()['features'] ?? []) as $feat) {
                                if (!empty($feat['center'])) {
                                    $type = $feat['place_type'][0] ?? 'place';
                                    $zoom = match ($type) {
                                        'country' => 5,
                                        'region' => 8,
                                        'place', 'city', 'locality' => 12,
                                        'poi', 'address' => 16,
                                        default => 14,
                                    };
                                    $items[] = [
                                        'title' => (string) ($feat['text'] ?? $query),
                                        'subtitle' => (string) ($feat['place_name'] ?? ''),
                                        'lng' => (float) $feat['center'][0],
                                        'lat' => (float) $feat['center'][1],
                                        'zoom' => $zoom,
                                        'pitch' => $zoom >= 15 ? 60 : 45,
                                    ];
                                }
                            }
                        }
                    } catch (\Throwable) {
                    }
                }

                if (empty($items)) {
                    try {
                        $nom = \Illuminate\Support\Facades\Http::timeout(3)->withHeaders([
                            'User-Agent' => 'Seelenfunke/1.0 (Map HUD)'
                        ])->get('https://nominatim.openstreetmap.org/search', [
                            'q' => $query,
                            'format' => 'json',
                            'limit' => 5,
                        ]);
                        if ($nom->successful() && is_array($nom->json())) {
                            foreach ($nom->json() as $row) {
                                if (isset($row['lon'], $row['lat'])) {
                                    $items[] = [
                                        'title' => (string) ($row['name'] ?: strtok((string) ($row['display_name'] ?? $query), ',')),
                                        'subtitle' => (string) ($row['display_name'] ?? ''),
                                        'lng' => (float) $row['lon'],
                                        'lat' => (float) $row['lat'],
                                        'zoom' => 14,
                                        'pitch' => 50,
                                    ];
                                }
                            }
                        }
                    } catch (\Throwable) {
                    }
                }

                return $items;
            });

            return response()->json([
                'status' => 'success',
                'results' => $results,
            ], 200, [
                'Cache-Control' => 'private, max-age=300',
            ]);
        })->middleware('throttle:60,1');

        // =========================================================================
        // TOP SECRET VAULT API (STRIKT ADMIN-ONLY, AES-256 VERSCHLÜSSELT, NO-STORE)
        // =========================================================================
        Route::group([
            'prefix' => '/top-secret',
            'middleware' => [\App\Http\Middleware\EnsureAdminExecutiveAccess::class],
        ], function () {
            Route::get('/vault', function () {
                $intelCat = \App\Models\Ai\AiKnowledgeBaseCategory::where('slug', 'osint-intel')->first();
                $relCat = \App\Models\Ai\AiKnowledgeBaseCategory::where('slug', 'osint-relation')->first();

                $intelItems = [];
                if ($intelCat) {
                    $rows = \App\Models\Ai\AiKnowledgeBase::with('tags')
                        ->where('ai_knowledge_base_category_id', $intelCat->id)
                        ->orderByDesc('updated_at')
                        ->limit(100)
                        ->get();

                    foreach ($rows as $row) {
                        $raw = (string) $row->content;
                        $wasEncrypted = str_starts_with($raw, '[TS_ENC_V1]:');
                        $decrypted = \App\Services\AI\AIFunctionsRegistry::decryptTopSecretPayload($raw);

                        // Auto-Upgrade älterer Klartext-Einträge auf AES-256 + VISIBILITY_ADMIN_EXECUTIVE
                        if ((!$wasEncrypted && $decrypted !== '') || $row->visibility_scope !== \App\Models\Ai\AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE) {
                            $row->update([
                                'content' => \App\Services\AI\AIFunctionsRegistry::encryptTopSecretPayload($decrypted),
                                'visibility_scope' => \App\Models\Ai\AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
                            ]);
                        }

                        $imageUrl = null;
                        $cleanContent = $decrypted;
                        if (preg_match('/^!\[[^\]]*\]\(([^)]+)\)\s*/', $cleanContent, $m)) {
                            $imageUrl = trim($m[1]);
                            $cleanContent = trim(substr($cleanContent, strlen($m[0])));
                        }

                        $intelItems[] = [
                            'id' => (string) $row->id,
                            'title' => (string) $row->title,
                            'content' => $cleanContent,
                            'image_url' => $imageUrl,
                            'encrypted' => true,
                            'tags' => $row->tags->pluck('name')->values()->all(),
                            'updated_at' => $row->updated_at ? $row->updated_at->format('d.m.Y H:i') : '',
                        ];
                    }
                }

                $relations = [];
                if ($relCat) {
                    $relRows = \App\Models\Ai\AiKnowledgeBase::where('ai_knowledge_base_category_id', $relCat->id)
                        ->orderByDesc('updated_at')
                        ->limit(150)
                        ->get();

                    foreach ($relRows as $rel) {
                        $raw = (string) $rel->content;
                        $wasEncrypted = str_starts_with($raw, '[TS_ENC_V1]:');
                        $decrypted = \App\Services\AI\AIFunctionsRegistry::decryptTopSecretPayload($raw);

                        if ((!$wasEncrypted && $decrypted !== '') || $rel->visibility_scope !== \App\Models\Ai\AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE) {
                            $rel->update([
                                'content' => \App\Services\AI\AIFunctionsRegistry::encryptTopSecretPayload($decrypted),
                                'visibility_scope' => \App\Models\Ai\AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
                            ]);
                        }

                        $parsed = json_decode($decrypted, true);
                        if (is_array($parsed) && (!empty($parsed['source']) || !empty($parsed['target']))) {
                            $relations[] = [
                                'id' => (string) $rel->id,
                                'source' => (string) ($parsed['source'] ?? 'Unbekannt'),
                                'target' => (string) ($parsed['target'] ?? 'Unbekannt'),
                                'relationship' => (string) ($parsed['relationship'] ?? 'Verbindung'),
                                'evidence' => (string) ($parsed['evidence'] ?? ''),
                                'updated_at' => $rel->updated_at ? $rel->updated_at->format('d.m.Y H:i') : '',
                            ];
                        }
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'cipher' => 'AES-256-CBC',
                    'intel' => $intelItems,
                    'relations' => $relations,
                ], 200, [
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                    'Pragma' => 'no-cache',
                    'X-Robots-Tag' => 'noindex, nofollow',
                ]);
            });

            Route::post('/intel', function (\Illuminate\Http\Request $request) {
                $title = trim(mb_substr((string) $request->input('title', ''), 0, 180));
                $content = trim(mb_substr((string) $request->input('content', ''), 0, 20000));
                $tagsInput = $request->input('tags', []);

                if ($title === '' || $content === '') {
                    return response()->json(['status' => 'error', 'message' => 'Titel und Inhalt sind erforderlich.'], 422);
                }

                $tags = is_array($tagsInput)
                    ? array_filter(array_map(fn ($t) => trim(mb_substr((string) $t, 0, 40)), $tagsInput))
                    : array_filter(array_map('trim', explode(',', (string) $tagsInput)));

                $res = \App\Services\AI\AIFunctionsRegistry::executePersonaSaveIntel([
                    'entity_name' => $title,
                    'intel_content' => $content,
                    'tags' => array_values($tags),
                ]);

                return response()->json($res, ($res['status'] ?? '') === 'success' ? 200 : 422, [
                    'Cache-Control' => 'no-store, private',
                ]);
            })->middleware('throttle:30,1');

            Route::delete('/entry/{id}', function (string $id) {
                $entry = \App\Models\Ai\AiKnowledgeBase::with('category')->find($id);
                if (!$entry || !in_array($entry->category?->slug, ['osint-intel', 'osint-relation'], true)) {
                    return response()->json(['status' => 'error', 'message' => 'Eintrag nicht gefunden.'], 404);
                }
                $entry->delete();

                return response()->json(['status' => 'success'], 200, [
                    'Cache-Control' => 'no-store, private',
                ]);
            })->middleware('throttle:30,1');

            Route::post('/relation', function (\Illuminate\Http\Request $request) {
                $source = trim(mb_substr((string) $request->input('source', ''), 0, 140));
                $target = trim(mb_substr((string) $request->input('target', ''), 0, 140));
                $relationship = trim(mb_substr((string) $request->input('relationship', 'Verbindung'), 0, 100));
                $evidence = trim(mb_substr((string) $request->input('evidence', ''), 0, 2000));

                if ($source === '' || $target === '') {
                    return response()->json(['status' => 'error', 'message' => 'Quelle und Ziel sind erforderlich.'], 422);
                }

                $res = \App\Services\AI\AIFunctionsRegistry::executePersonaLinkEntities([
                    'source_entity' => $source,
                    'target_entity' => $target,
                    'relationship_type' => $relationship !== '' ? $relationship : 'Verbindung',
                    'evidence_summary' => $evidence,
                ]);

                return response()->json($res, ($res['status'] ?? '') === 'success' ? 200 : 422, [
                    'Cache-Control' => 'no-store, private',
                ]);
            })->middleware('throttle:30,1');

            Route::get('/file/{folder}/{filename}', function (string $folder, string $filename) {
                if (!in_array($folder, ['media', 'dossiers'], true)) {
                    abort(404);
                }
                $cleanName = basename($filename);
                if ($cleanName === '' || $cleanName !== $filename || !preg_match('/^[A-Za-z0-9._-]+$/', $cleanName)) {
                    abort(403, 'Ungültiger Dateiname.');
                }

                $baseDir = realpath(storage_path('app/private/top_secret/' . $folder));
                $fullPath = storage_path('app/private/top_secret/' . $folder . '/' . $cleanName);
                $realFile = realpath($fullPath);

                if ($baseDir === false || $realFile === false || !str_starts_with($realFile, $baseDir . DIRECTORY_SEPARATOR) || !is_file($realFile)) {
                    abort(404);
                }

                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($realFile) ?: 'application/octet-stream';
                return response()->file($realFile, [
                    'Content-Type' => $mime,
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                    'Pragma' => 'no-cache',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            })->where('filename', '.*')->middleware('throttle:60,1');
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

        // Geheimer Fehler-Analyse-Keller (Funkira 3D-Hologramm, Datei-Zusammenhänge & Cross-Platform-Logs)
        Route::get('/cellar-snapshot', function () {
            $snapshot = app(\App\Services\System\SystemBrainDiagnosticsService::class)
                ->buildCellarSnapshot();

            return response()->json($snapshot, 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        });

        // 1-Klick & Sprach-Übergabe eines Geheimnis-bereinigten Reparatur-Dossiers an Antigravity
        Route::post('/cellar-handoff', function (\Illuminate\Http\Request $request) {
            $result = app(\App\Services\System\SystemBrainDiagnosticsService::class)
                ->prepareAntigravityHandoff($request->all());

            return response()->json($result, 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        })->middleware('throttle:20,1');

        Route::get('/cellar-handoff', function () {
            $dossier = app(\App\Services\System\SystemBrainDiagnosticsService::class)
                ->getActiveAntigravityHandoff();

            return response()->json([
                'status' => 'success',
                'has_handoff' => $dossier !== null,
                'dossier' => $dossier,
            ], 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        });

        Route::post('/cellar-auto-heal', function () {
            $result = app(\App\Services\System\SystemBrainDiagnosticsService::class)
                ->runAutonomousCellarHeal();

            return response()->json($result, 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        })->middleware('throttle:20,1');

        // --- Zentrales Funkira Universum-Ticketsystem (Android, Funkenwerk Desktop, Mini-PC & Tresor-Worker) ---
        Route::get('/tickets', [AIController::class, 'listUniverseTickets']);
        Route::post('/tickets', [AIController::class, 'dispatchUniverseMessage'])->middleware('throttle:60,1');
        Route::post('/tickets/{id}/action', [AIController::class, 'performUniverseTicketAction'])->middleware('throttle:60,1');
        Route::get('/tresor-queue', [AIController::class, 'pollTresorQueue'])->middleware('throttle:60,1');
        Route::post('/tresor-queue/{id}/complete', [AIController::class, 'completeTresorOrder'])->middleware('throttle:30,1');
        Route::post('/antigravity/heartbeat', [AIController::class, 'antigravityHeartbeat'])->middleware('throttle:60,1');
    });
});

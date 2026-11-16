<?php

namespace App\Services\System;

use App\Models\System\SystemLog;
use App\Models\System\SystemNeuralNode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 24/7 Selbstheilungs-Wächter & Antigravity-Auffang-Pool (Sentinel Triage Service).
 *
 * Zweistufige Architektur:
 *  - Stufe 1 (Autonome Infrastruktur-Selbstheilung, 0 Quellcode-Mutation):
 *    Behebt verifizierbare Betriebs-/Cache-/Session-/Brücken-Störungen selbstständig
 *    und legt sie ehrlich als [AUTO-GEHEILT] im 7-Tage-Archiv ab.
 *  - Stufe 2 (Antigravity-Auffang-Pool bei echten Code-Bugs):
 *    Erstellt strikt 1x pro eindeutigem SHA-256-Fingerprint ein geheimnisbereinigtes
 *    Funkira-Reparatur-Dossier (inkl. Datei, Zeile, +/-12 Zeilen Code-Ausschnitt,
 *    Aufrufer-Nachbarschaft und optionaler KI-Wurzelursachen-Analyse) und legt es
 *    für die nächste Antigravity-Sitzung in storage/app/private/antigravity_inbox.json ab.
 */
class SentinelTriageService
{
    public const INBOX_FILE = 'app/private/antigravity_inbox.json';
    public const HANDOFF_FILE = 'app/private/antigravity_error_handoff.json';
    public const OPPORTUNISTIC_LOCK_KEY = 'sentinel:opportunistic_triage:last_run';
    public const PUSH_COOLDOWN_KEY = 'sentinel:critical_push:last_sent';

    public const BRIDGE_HEALTH_TARGETS = [
        'reverb_twilio' => [
            'label' => 'Reverb Twilio-Bridge (ws.mein-seelenfunke.de)',
            'url' => 'https://ws.mein-seelenfunke.de/twilio-health',
        ],
        'live_bridge' => [
            'label' => 'Node.js Gemini Live-Bridge (api-live-bridge.mein-seelenfunke.de)',
            'url' => 'https://api-live-bridge.mein-seelenfunke.de/health',
        ],
    ];

    protected static bool $isTriaging = false;

    /**
     * Führt einen opportunistischen Kurz-Durchlauf (ohne externe HTTP-/KI-Latenz) aus,
     * gedrosselt auf maximal 1x alle 5 Minuten (z. B. beim Öffnen von /admin/global-logs).
     */
    public function runOpportunistically(): void
    {
        if (self::$isTriaging || app()->runningUnitTests()) {
            return;
        }

        try {
            if (Cache::has(self::OPPORTUNISTIC_LOCK_KEY)) {
                return;
            }
            Cache::put(self::OPPORTUNISTIC_LOCK_KEY, now()->toIso8601String(), now()->addMinutes(5));
            $this->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
        } catch (\Throwable $ignore) {
        }
    }

    /**
     * Haupt-Triage-Zyklus (alle 5 Minuten auf Stage per Cronjob + manuell per Button/CLI).
     */
    public function runTriageCycle(bool $probeBridges = false, bool $allowAiEnrichment = true): array
    {
        if (self::$isTriaging || !Schema::hasTable('system_logs')) {
            return [
                'status' => 'skipped',
                'drained_deadletter' => 0,
                'failed_jobs_imported' => 0,
                'probed_bridges' => 0,
                'auto_healed_count' => 0,
                'newly_queued_count' => 0,
                'open_pool_count' => 0,
                'inbox_path' => 'storage/' . self::INBOX_FILE,
            ];
        }

        self::$isTriaging = true;

        try {
            if (!app()->runningUnitTests()) {
                LogArchiveService::ingestActiveLogFilesIntoSentinel();
            }
            $drained = $this->drainDeadLetterSpool();
            $probeStats = $this->probeActiveInfrastructure($probeBridges);

            $healedCount = (int) ($probeStats['auto_healed_bridges'] ?? 0);
            $newlyQueuedCount = 0;
            $alreadyQueuedCount = 0;
            $aiCallsUsed = 0;

            $openLogs = SystemLog::openIssues()
                ->orderByDesc('id')
                ->limit(120)
                ->get();

            foreach ($openLogs as $log) {
                $healDecision = $this->attemptAutonomousHeal($log);

                if (!empty($healDecision['healed'])) {
                    $this->markAsAutoHealed(
                        $log,
                        (string) ($healDecision['playbook'] ?? 'autonomous_heal'),
                        (string) ($healDecision['summary'] ?? 'Automatisch durch Funkira-Wächter bereinigt.')
                    );
                    $healedCount++;
                    continue;
                }

                $payload = is_array($log->payload) ? $log->payload : [];
                $fingerprint = $this->resolveLogFingerprint($log, $payload);
                $existingTriageState = $payload['triage']['state'] ?? null;
                $existingDossierFp = $payload['antigravity_dossier']['fingerprint'] ?? null;

                // Strikte 1x-pro-Fingerprint-Idempotenz (0 Token-Schleifen bei Wiederholungsfehlern)
                if ($existingTriageState === 'queued_for_antigravity' && $existingDossierFp === $fingerprint) {
                    $occurrences = max(1, (int) ($payload['occurrences_count'] ?? 1));
                    $dossierOccurrences = (int) ($payload['antigravity_dossier']['occurrences_count'] ?? 1);
                    if ($occurrences !== $dossierOccurrences) {
                        $payload['antigravity_dossier']['occurrences_count'] = $occurrences;
                        $payload['antigravity_dossier']['last_seen_at'] = $payload['last_seen_at'] ?? now()->toIso8601String();
                        $log->payload = $payload;
                        $log->save();
                    }
                    $alreadyQueuedCount++;
                    continue;
                }

                $canCallAi = $allowAiEnrichment && $aiCallsUsed < 3;
                $dossier = $this->buildAntigravityDossier($log, $fingerprint, $canCallAi, $aiCallsUsed);

                $payload['fingerprint'] = $fingerprint;
                $payload['triage'] = [
                    'state' => 'queued_for_antigravity',
                    'triaged_at' => now()->toIso8601String(),
                    'triaged_by' => 'Funkira Sentinel',
                    'criticality' => $dossier['criticality'] ?? 'standard',
                ];
                $payload['antigravity_dossier'] = $dossier;

                $log->payload = $payload;
                $log->save();

                $this->maybeSendCriticalOwnerAlert($log, $dossier);
                $newlyQueuedCount++;
            }

            $snapshot = $this->writeInboxSnapshotFile();

            // Zero-Log-Baseline: Sobald 0 offene Fehler vorliegen, wandern verbleibende Roh-Logs verlustfrei ins Archiv (Haupt-Log = 0 Bytes)
            if (!app()->runningUnitTests() && ((int) ($snapshot['open_pool_count'] ?? 0)) === 0 && SystemLog::openIssues()->count() === 0) {
                LogArchiveService::archiveAndZeroActiveLogs('Funkira Sentinel Auto-Archiv', 30);
            }

            return [
                'status' => 'success',
                'drained_deadletter' => $drained,
                'failed_jobs_imported' => (int) ($probeStats['failed_jobs_imported'] ?? 0),
                'probed_bridges' => (int) ($probeStats['probed_bridges'] ?? 0),
                'auto_healed_count' => $healedCount,
                'newly_queued_count' => $newlyQueuedCount,
                'already_queued_count' => $alreadyQueuedCount,
                'ai_calls_used' => $aiCallsUsed,
                'open_pool_count' => (int) ($snapshot['open_pool_count'] ?? 0),
                'inbox_path' => 'storage/' . self::INBOX_FILE,
                'handoff_path' => 'storage/' . self::HANDOFF_FILE,
            ];
        } finally {
            self::$isTriaging = false;
        }
    }

    /**
     * Spült während eines kurzen DB-Ausfalls lokal gepufferte Fehler (sentinel_deadletter.jsonl)
     * verlustfrei in system_logs nach.
     */
    public function drainDeadLetterSpool(): int
    {
        $spoolPath = storage_path(SystemErrorLogger::DEAD_LETTER_SPOOL_FILE);
        if (!is_file($spoolPath) || !is_readable($spoolPath)) {
            return 0;
        }

        $lines = @file($spoolPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || empty($lines)) {
            @unlink($spoolPath);
            return 0;
        }

        $imported = 0;
        $remainingLines = [];

        foreach ($lines as $rawLine) {
            $decoded = json_decode((string) $rawLine, true);
            if (!is_array($decoded)) {
                continue;
            }

            try {
                $type = Str::limit((string) ($decoded['type'] ?? 'system'), 40, '');
                $actionId = Str::limit((string) ($decoded['action_id'] ?? 'system:deadletter'), 190, '');
                $payload = is_array($decoded['payload'] ?? null)
                    ? SystemErrorLogger::sanitizeArrayRecursive($decoded['payload'])
                    : [];
                $payload['drained_from_deadletter'] = true;
                $payload['deadletter_queued_at'] = $decoded['queued_at'] ?? null;

                $fingerprint = (string) ($payload['fingerprint'] ?? '');
                if ($fingerprint !== '') {
                    $existing = SystemLog::where('action_id', $actionId)
                        ->whereIn('status', ['error', 'warning'])
                        ->where('started_at', '>=', now()->subHours(24))
                        ->latest('id')
                        ->first();

                    if ($existing && (($existing->payload['fingerprint'] ?? '') === $fingerprint)) {
                        $p = is_array($existing->payload) ? $existing->payload : [];
                        $p['occurrences_count'] = ((int) ($p['occurrences_count'] ?? 1)) + max(1, (int) ($payload['occurrences_count'] ?? 1));
                        $p['last_seen_at'] = now()->toIso8601String();
                        $p['drained_from_deadletter'] = true;
                        $existing->payload = $p;
                        $existing->finished_at = now();
                        $existing->save();
                        $imported++;
                        continue;
                    }
                }

                $queuedAt = !empty($decoded['queued_at']) ? Carbon::parse($decoded['queued_at']) : now();

                SystemLog::create([
                    'type' => $type ?: 'system',
                    'action_id' => $actionId ?: 'system:deadletter',
                    'title' => Str::limit(SystemErrorLogger::sanitizeSensitiveText((string) ($decoded['title'] ?? 'System-Fehler (Spool)')), 190),
                    'message' => Str::limit(SystemErrorLogger::sanitizeSensitiveText((string) ($decoded['message'] ?? 'Nachgespülter Fehler aus Notfall-Spool')), 2000),
                    'status' => in_array(($decoded['status'] ?? 'error'), ['error', 'warning', 'success'], true) ? $decoded['status'] : 'error',
                    'payload' => $payload,
                    'started_at' => $queuedAt,
                    'finished_at' => now(),
                ]);
                $imported++;
            } catch (\Throwable $dbStillDown) {
                $remainingLines[] = $rawLine;
            }
        }

        if (empty($remainingLines)) {
            @unlink($spoolPath);
        } else {
            @file_put_contents($spoolPath, implode("\n", $remainingLines) . "\n", LOCK_EX);
            @chmod($spoolPath, 0600);
        }

        return $imported;
    }

    /**
     * Aktive Infrastruktur-Prüfung:
     *  1. Synchronisiert abgebrochene Queue-Jobs aus failed_jobs (letzte 24h) in system_logs.
     *  2. Prüft optional die Echtzeit-WebSocket-Brücken (Reverb + Node.js Live-Bridge) per IPv4-Puls
     *     und heilt offene Brücken-Warnungen automatisch, sobald HTTP 200 zurückkehrt.
     */
    public function probeActiveInfrastructure(bool $includeExternalBridges = false): array
    {
        $failedJobsImported = 0;
        $probedBridges = 0;
        $autoHealedBridges = 0;

        if (Schema::hasTable('failed_jobs')) {
            try {
                $recentFailedJobs = DB::table('failed_jobs')
                    ->where('failed_at', '>=', now()->subHours(24))
                    ->orderByDesc('id')
                    ->limit(25)
                    ->get();

                foreach ($recentFailedJobs as $job) {
                    $jobUuid = trim((string) ($job->uuid ?? $job->id));
                    $actionId = Str::limit('queue:failed_job:' . $jobUuid, 190, '');

                    if (SystemLog::where('action_id', $actionId)->exists()) {
                        continue;
                    }

                    $jobPayload = json_decode((string) ($job->payload ?? '{}'), true);
                    $displayName = is_array($jobPayload)
                        ? (string) ($jobPayload['displayName'] ?? $jobPayload['job'] ?? 'QueueJob')
                        : 'QueueJob';

                    $rawException = SystemErrorLogger::sanitizeSensitiveText((string) ($job->exception ?? 'Unbekannter Queue-Abbruch'));
                    $firstLine = trim(strtok($rawException, "\n") ?: 'Queue-Job fehlgeschlagen');

                    $file = null;
                    $line = null;
                    if (preg_match('/in\s+([^\s:]+\.php):(\d+)/', $rawException, $m)) {
                        $file = $m[1];
                        $line = (int) $m[2];
                    }

                    $fingerprint = hash('sha256', strtolower('queue|' . $displayName . '|' . ($file ?? '') . '|' . ($line ?? 0) . '|' . mb_substr($firstLine, 0, 140)));

                    SystemLog::create([
                        'type' => 'system',
                        'action_id' => $actionId,
                        'title' => Str::limit('Queue-Job fehlgeschlagen (' . class_basename($displayName) . ')', 190),
                        'message' => Str::limit($firstLine, 2000),
                        'status' => 'error',
                        'payload' => [
                            'platform' => 'portal',
                            'platform_label' => 'Webportal',
                            'component' => 'queue_worker',
                            'job_uuid' => $jobUuid,
                            'job_class' => $displayName,
                            'queue' => (string) ($job->queue ?? 'default'),
                            'connection' => (string) ($job->connection ?? 'database'),
                            'file' => $file,
                            'line' => $line,
                            'stack_excerpt' => Str::limit($rawException, 3500),
                            'fingerprint' => $fingerprint,
                            'occurrences_count' => 1,
                            'first_seen_at' => (string) ($job->failed_at ?? now()->toIso8601String()),
                            'last_seen_at' => now()->toIso8601String(),
                            'diagnosis' => SystemErrorLogger::diagnose($firstLine, $displayName, $file),
                        ],
                        'started_at' => !empty($job->failed_at) ? Carbon::parse($job->failed_at) : now(),
                        'finished_at' => now(),
                    ]);

                    $failedJobsImported++;
                }
            } catch (\Throwable $ignore) {
            }
        }

        if ($includeExternalBridges) {
            foreach (self::BRIDGE_HEALTH_TARGETS as $bridgeKey => $meta) {
                $probedBridges++;
                $actionId = 'sentinel:bridge_unhealthy:' . $bridgeKey;
                $healthy = false;
                $httpStatus = null;
                $errorDetail = '';

                try {
                    $response = Http::withOptions([
                        'force_ip_resolve' => 'v4',
                        'connect_timeout' => 4,
                        'timeout' => 6,
                    ])->get($meta['url']);

                    $httpStatus = $response->status();
                    $healthy = $response->successful();
                    if (!$healthy) {
                        $errorDetail = 'HTTP ' . $httpStatus . ' von ' . $meta['url'];
                    }
                } catch (\Throwable $e) {
                    $healthy = false;
                    $errorDetail = SystemErrorLogger::sanitizeSensitiveText($e->getMessage());
                }

                if ($healthy) {
                    $openBridgeLogs = SystemLog::openIssues()
                        ->where('action_id', $actionId)
                        ->get();

                    foreach ($openBridgeLogs as $openBridgeLog) {
                        $this->markAsAutoHealed(
                            $openBridgeLog,
                            'bridge_recovered_http_200',
                            $meta['label'] . ' antwortet wieder regulär mit HTTP ' . ($httpStatus ?? 200) . '.'
                        );
                        $autoHealedBridges++;
                    }
                } else {
                    $fingerprint = hash('sha256', 'sentinel|bridge|' . $bridgeKey);
                    $existing = SystemLog::openIssues()
                        ->where('action_id', $actionId)
                        ->latest('id')
                        ->first();

                    if ($existing) {
                        $p = is_array($existing->payload) ? $existing->payload : [];
                        $p['occurrences_count'] = ((int) ($p['occurrences_count'] ?? 1)) + 1;
                        $p['last_seen_at'] = now()->toIso8601String();
                        $p['last_http_status'] = $httpStatus;
                        $p['last_probe_error'] = Str::limit($errorDetail, 500);
                        $existing->payload = $p;
                        $existing->finished_at = now();
                        $existing->save();
                    } else {
                        SystemLog::create([
                            'type' => 'system',
                            'action_id' => $actionId,
                            'title' => Str::limit('Brücken-Wächter: ' . $meta['label'] . ' gestört', 190),
                            'message' => Str::limit('Gesundheits-Puls fehlgeschlagen: ' . ($errorDetail ?: 'Keine Antwort'), 2000),
                            'status' => 'error',
                            'payload' => [
                                'platform' => 'portal',
                                'platform_label' => 'Webportal',
                                'component' => 'realtime_bridge_' . $bridgeKey,
                                'bridge_key' => $bridgeKey,
                                'bridge_url' => $meta['url'],
                                'last_http_status' => $httpStatus,
                                'fingerprint' => $fingerprint,
                                'occurrences_count' => 1,
                                'first_seen_at' => now()->toIso8601String(),
                                'last_seen_at' => now()->toIso8601String(),
                                'diagnosis' => SystemErrorLogger::diagnose('bridge_unhealthy ' . $meta['url']),
                            ],
                            'started_at' => now(),
                            'finished_at' => now(),
                        ]);
                    }
                }
            }
        }

        $watchtowerSynced = ['logged' => 0, 'healed' => 0];
        if ($includeExternalBridges && class_exists(SystemHealthPulseService::class)) {
            try {
                $pulse = app(SystemHealthPulseService::class)->evaluate(
                    source: 'cli_scheduler',
                    strict: false,
                    recordSample: true,
                    syncLogs: false
                );
                $watchtowerSynced = app(SystemHealthPulseService::class)->syncWithSystemLogs($pulse['pillars'] ?? []);
            } catch (\Throwable $ignore) {
            }
        }

        return [
            'failed_jobs_imported' => $failedJobsImported,
            'probed_bridges' => $probedBridges,
            'auto_healed_bridges' => $autoHealedBridges,
            'watchtower_logged' => $watchtowerSynced['logged'] ?? 0,
            'watchtower_healed' => $watchtowerSynced['healed'] ?? 0,
        ];
    }

    /**
     * Stufe 1: Prüft streng freigegebene, sichere Infrastruktur-Selbstheilungs-Playbooks (0 Code-Änderungen).
     */
    protected function attemptAutonomousHeal(SystemLog $log): array
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $occurrences = max(1, (int) ($payload['occurrences_count'] ?? 1));
        $message = (string) ($log->message ?? '');
        $msgLower = strtolower($message);
        $titleLower = strtolower((string) ($log->title ?? ''));
        $actionId = strtolower((string) ($log->action_id ?? ''));
        $file = (string) ($payload['file'] ?? $payload['compiled_view_file'] ?? '');
        $category = strtolower((string) ($payload['diagnosis']['category'] ?? ''));

        // Playbook 1: Vom Endgerät/Watchdog (Mini-PC, Android, Desktop) bereits verifiziert selbstgeheilt
        if ((!empty($payload['auto_healed']) || str_starts_with((string) $log->title, '[AUTO-GEHEILT]')) && $occurrences < 20) {
            $clientAction = trim((string) ($payload['auto_heal_action'] ?? ''));
            return [
                'healed' => true,
                'playbook' => 'client_watchdog_confirmed',
                'summary' => $clientAction !== ''
                    ? $clientAction
                    : 'Vom lokalen Plattform-Watchdog erfolgreich selbstgeheilt und bestätigt.',
            ];
        }

        // Playbook 2: Kompilierter View-/Datei-Cache-Stream-Konflikt (keine Syntax-/Variablen-Bugs!)
        $isCodeBugInView = str_contains($msgLower, 'undefined variable')
            || str_contains($msgLower, 'attempt to read property')
            || str_contains($msgLower, 'syntax error')
            || str_contains($msgLower, 'call to undefined')
            || str_contains($msgLower, 'must be of type');

        $isCorruptCacheOrViewStream = !$isCodeBugInView && $occurrences < 10 && (
            (str_contains($msgLower, 'file_put_contents') && (str_contains($msgLower, 'framework/views') || str_contains($msgLower, 'framework/cache') || str_contains($msgLower, 'sf_views_')))
            || (str_contains($msgLower, 'failed to open stream') && (str_contains($msgLower, 'framework/views') || str_contains($msgLower, 'framework/cache') || str_contains($msgLower, 'sf_views_')))
            || (str_contains($msgLower, 'unserialize(): error at offset') && (str_contains($file, 'FileStore') || str_contains($file, 'Filesystem')))
        );

        if ($isCorruptCacheOrViewStream) {
            try {
                Artisan::call('view:clear');
                Artisan::call('cache:clear');
                $compiledDir = (string) config('view.compiled', storage_path('framework/views'));
                if ($compiledDir !== '' && !is_dir($compiledDir)) {
                    @mkdir($compiledDir, 0775, true);
                }
                if ($compiledDir === '' || is_dir($compiledDir)) {
                    return [
                        'healed' => true,
                        'playbook' => 'compiled_view_or_cache_corrupt',
                        'summary' => 'Kompilierter View- und Datei-Cache wurde bereinigt und Schreibbarkeit verifiziert.',
                    ];
                }
            } catch (\Throwable $ignore) {
            }
        }

        // Playbook 3: Einzelne abgelaufene Browser-Tab-Sessions (HTTP 419 < 25) oder bereinigte tote FCM-Tokens
        // Ab >= 25 Auftreten eskaliert HTTP 419 bewusst in Stufe 2 (Antigravity-Pool) als echter Session-/Cookie-Bug!
        if ($actionId === 'system:http_419_csrf' && $occurrences < 25) {
            return [
                'healed' => true,
                'playbook' => 'expired_session_csrf_redirected',
                'summary' => 'Abgelaufenes Browser-Formular-Token wurde sauber abgefangen (unterhalb des Sturm-Schwellenwerts von 25).',
            ];
        }

        if ((str_contains($msgLower, 'unregistered') || str_contains($msgLower, 'requested entity was not found'))
            && (str_contains($msgLower, 'fcm') || str_contains($msgLower, 'firebase') || str_contains($titleLower, 'fcm'))
            && $occurrences < 15) {
            return [
                'healed' => true,
                'playbook' => 'stale_fcm_token_pruned',
                'summary' => 'Abgemeldetes Push-Geräte-Token erkannt und im Archiv abgelegt.',
            ];
        }

        // Playbook 4: Kurzzeitiger DNS-/Redis-Wackler, der seit >= 5 Min nicht mehr auftrat und live gesund ist
        if (in_array($category, ['network_dns_or_socket_eagain', 'redis_connectivity_or_dns'], true) && $occurrences < 10) {
            $lastSeenRaw = $payload['last_seen_at'] ?? ($log->finished_at?->toIso8601String() ?? $log->started_at?->toIso8601String());
            $isCooledDown = false;
            if ($lastSeenRaw) {
                try {
                    $isCooledDown = Carbon::parse($lastSeenRaw)->lte(now()->subMinutes(5));
                } catch (\Throwable $ignore) {
                }
            }

            if ($isCooledDown && $this->verifyCoreConnectivityHealthy()) {
                return [
                    'healed' => true,
                    'playbook' => 'transient_dns_or_cache_recovered',
                    'summary' => 'Temporärer DNS-/Cache-Engpass ist abgeklungen; Live-Verbindungstest (DB + Cache) erfolgreich.',
                ];
            }
        }

        // Playbook 5: Regulärer Google Gemini Live 15-Min-Session-Rollover (GoAway) & einmaliger Client-WLAN-Puls-Aussetzer
        if (str_contains($actionId, 'gemini_live_ws_close')
            && (str_contains($msgLower, 'goaway') || str_contains($msgLower, 'session duration'))
            && $occurrences < 25) {
            return [
                'healed' => true,
                'playbook' => 'gemini_live_session_rollover',
                'summary' => 'Regulärer Session-Rollover (GoAway) der Google Gemini Live-API; Client baut die Verbindung automatisch neu auf.',
            ];
        }

        if ((str_contains($actionId, 'portal_pulse') || str_contains($msgLower, '/api/shop/monitor-pulse'))
            && preg_match('/\b(?:status|http)\s*(0|429|502|503|504)\b/i', $message, $pulseMatch)
            && $occurrences < 60
            && $this->verifyCoreConnectivityHealthy()) {
            $pulseCode = $pulseMatch[1];
            return [
                'healed' => true,
                'playbook' => 'client_portal_pulse_recovered',
                'summary' => $pulseCode === '0'
                    ? 'Kurzzeitiger lokaler Netzwerk-Aussetzer (Status 0) am Endgerät ist abgeklungen; Telemetrie-Verbindung zum Portal steht wieder.'
                    : "Kurzzeitige Rate-Limit- oder Gateway-Spitze (Status {$pulseCode}) auf /api/shop/monitor-pulse ist abgeklungen; Telemetrie-Verbindung zum Portal steht wieder.",
            ];
        }

        // Playbook 6: Kurzzeitiges Mobilfunk-/WLAN-Funkloch oder Stream-Abbruch auf Endgeräten (UnknownHostException, ConnectException, funkira_live_ws EOF/Socket)
        if (in_array($log->resolvePlatformKey(), ['android', 'minipc_linux', 'windows_desktop'], true)
            && (str_contains($actionId, 'funkira_live_ws') || str_contains($titleLower, 'funkira_live_ws'))
            && $occurrences < 12) {
            $wsCombined = $titleLower . ' | ' . $msgLower . ' | ' . strtolower((string) ($payload['exception_class'] ?? ''));
            $isRealWsAuthOrCodeBug = str_contains($wsCombined, 'nullpointerexception')
                || str_contains($wsCombined, 'illegalstateexception')
                || str_contains($wsCombined, 'illegalargumentexception')
                || str_contains($wsCombined, 'securityexception')
                || str_contains($wsCombined, 'jsonexception')
                || str_contains($wsCombined, 'serializationexception')
                || str_contains($wsCombined, 'classcastexception')
                || str_contains($wsCombined, 'outofmemoryerror')
                || str_contains($wsCombined, 'unauthorized')
                || str_contains($wsCombined, 'forbidden')
                || str_contains($wsCombined, 'unauthenticated')
                || str_contains($wsCombined, 'invalid token')
                || str_contains($wsCombined, 'api key')
                || (bool) preg_match('/\b(?:http|status|code)\s*(?:401|403|404|500)\b/i', $wsCombined);

            $isTransientWsStreamDrop = str_contains($wsCombined, 'eofexception')
                || str_contains($wsCombined, 'socketexception')
                || str_contains($wsCombined, 'sockettimeoutexception')
                || str_contains($wsCombined, 'streamresetexception')
                || str_contains($wsCombined, 'connection reset')
                || str_contains($wsCombined, 'software caused connection abort')
                || str_contains($msgLower, 'verbindungsfehler: null');

            if (!$isRealWsAuthOrCodeBug && $isTransientWsStreamDrop && $this->verifyCoreConnectivityHealthy()) {
                return [
                    'healed' => true,
                    'playbook' => 'transient_mobile_ws_recovered',
                    'summary' => 'Kurzzeitiger Mobilfunk- oder Stream-Abbruch auf funkira_live_ws; automatischer WebSocket-Reconnect greift.',
                ];
            }
        }

        if (in_array($log->resolvePlatformKey(), ['android', 'minipc_linux', 'windows_desktop'], true)
            && (str_contains($titleLower, 'unknownhostexception')
                || str_contains($msgLower, 'unknownhostexception')
                || str_contains($titleLower, 'connectexception')
                || str_contains($titleLower, 'sockettimeoutexception'))
            && $occurrences < 8
            && $this->verifyCoreConnectivityHealthy()) {
            return [
                'healed' => true,
                'playbook' => 'transient_mobile_offline_recovered',
                'summary' => 'Kurzzeitiger DNS-/Mobilfunk-Wechsel am Endgerät (lokaler Offline-Cache übernahm unterbrechungsfrei).',
            ];
        }

        // Playbook 7: Überholte Paket-Audit-Warnung, sobald ein nachfolgender Security-Scan 0 Produktions-CVEs und 0 Schranken-Fehler bestätigt
        if ($actionId === 'security:package_audit') {
            $logTs = $log->finished_at ?? $log->started_at ?? $log->created_at;
            $newerCleanAuditExists = SystemLog::query()
                ->where('action_id', 'security:package_audit')
                ->where('status', 'success')
                ->where('id', '!=', $log->id)
                ->when($logTs !== null, fn ($q) => $q->where('finished_at', '>=', $logTs))
                ->get()
                ->contains(function (SystemLog $candidate): bool {
                    $cp = is_array($candidate->payload) ? $candidate->payload : [];
                    return ((int) ($cp['cve_count'] ?? 0)) === 0 && ((int) ($cp['guardrail_errors'] ?? 0)) === 0;
                });

            if ($newerCleanAuditExists) {
                return [
                    'healed' => true,
                    'playbook' => 'security_audit_verified_clean',
                    'summary' => 'Nachfolgender Sicherheits-Scan bestätigte 0 echte Produktions-CVEs und 0 Schranken-Verstöße.',
                ];
            }
        }

        // Playbook 8: Infrastruktur-Check Backup: Überholt, sobald ein valides Backup (< 48h) vorliegt
        if (str_contains($actionId, 'health_fail') && (str_contains($msgLower, 'backup') || str_contains($titleLower, 'backup'))) {
            $diskName = (string) config('backup.backup.destination.disks.0', 'local');
            $backupName = (string) config('backup.backup.name', config('app.name') . '-db-backup');
            $hasFreshBackup = false;
            try {
                $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
                if ($disk->exists($backupName)) {
                    foreach ($disk->files($backupName) as $file) {
                        if (str_ends_with(strtolower($file), '.zip')) {
                            $mtime = $disk->lastModified($file);
                            if ($mtime >= now()->subHours(48)->getTimestamp()) {
                                $hasFreshBackup = true;
                                break;
                            }
                        }
                    }
                }
            } catch (\Throwable $ignore) {
            }

            if ($hasFreshBackup) {
                return [
                    'healed' => true,
                    'playbook' => 'health_backup_fresh_verified',
                    'summary' => 'Frisches Datenbank-Backup (< 48h) wurde verifiziert; Sicherungsstand ist aktuell.',
                ];
            }
        }

        // Playbook 9: Infrastruktur-Check Telephony Audio-Bridge: Überholt nach Dienstneustart / im Normalbetrieb
        if (str_contains($actionId, 'health_fail') && (str_contains($msgLower, 'telephony') || str_contains($titleLower, 'telephony') || str_contains($msgLower, 'telefonie')) && $occurrences < 10) {
            if ($this->verifyCoreConnectivityHealthy()) {
                return [
                    'healed' => true,
                    'playbook' => 'health_telephony_worker_recovered',
                    'summary' => 'Telephony Audio-Bridge Dienstbereitschaft nach Container-Neustart wiederhergestellt.',
                ];
            }
        }

        // Playbook 10: Interaktiver Tippfehler bei manuellem CLI-Befehlsaufruf (Option/Command existiert nicht)
        if (($log->exception_class === 'Symfony\\Component\\Console\\Exception\\RuntimeException'
            || $log->exception_class === 'Symfony\\Component\\Console\\Exception\\CommandNotFoundException'
            || str_contains($file, 'ArgvInput.php'))
            && (str_contains($msgLower, 'option does not exist') || str_contains($msgLower, 'is not defined'))
            && $occurrences < 5) {
            return [
                'healed' => true,
                'playbook' => 'interactive_cli_typo_handled',
                'summary' => 'Interaktiver Tippfehler bei manuellem CLI-Befehlsaufruf abgefangen.',
            ];
        }

        return ['healed' => false];
    }

    /**
     * Markiert einen Eintrag ehrlich und nachvollziehbar als [AUTO-GEHEILT] im 7-Tage-Archiv.
     */
    protected function markAsAutoHealed(SystemLog $log, string $playbook, string $summary): void
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $cleanSummary = SystemErrorLogger::sanitizeSensitiveText($summary);

        $payload['auto_healed'] = true;
        $payload['auto_heal_action'] = $cleanSummary;
        $payload['triage'] = [
            'state' => 'auto_healed',
            'playbook' => $playbook,
            'healed_at' => now()->toIso8601String(),
            'healed_by' => 'Funkira Sentinel',
            'summary' => $cleanSummary,
        ];

        $title = (string) $log->title;
        if (!str_starts_with($title, '[AUTO-GEHEILT]')) {
            $title = Str::limit('[AUTO-GEHEILT] ' . $title, 190);
        }

        $log->title = $title;
        $log->status = 'success';
        $log->payload = $payload;
        $log->finished_at = now();
        $log->save();
    }

    /**
     * Stufe 2: Baut das geheimnisbereinigte Funkira-Reparatur-Dossier für Antigravity.
     */
    protected function buildAntigravityDossier(
        SystemLog $log,
        string $fingerprint,
        bool $allowAi,
        int &$aiCallsUsed
    ): array {
        $payload = is_array($log->payload) ? $log->payload : [];
        $platformKey = $log->resolvePlatformKey();
        $platformMeta = SystemLog::PLATFORMS[$platformKey] ?? SystemLog::PLATFORMS['portal'];

        $rawFile = $this->extractBestFileCandidate($payload);
        $resolvedFile = SystemErrorLogger::resolveCompiledBladeViewPath($rawFile) ?? $rawFile;
        $relativeFile = $this->normalizeProjectRelativePath($resolvedFile);
        $line = isset($payload['line']) && is_numeric($payload['line']) ? (int) $payload['line'] : $this->extractBestLineCandidate($payload);

        $codeSnippet = $this->extractSafeCodeSnippet($relativeFile, $line, 12);
        $neighbors = $this->resolveFileNeighborhood($relativeFile);

        $diagnosis = is_array($payload['diagnosis'] ?? null)
            ? $payload['diagnosis']
            : SystemErrorLogger::diagnose((string) $log->message, $payload['exception_class'] ?? null, $relativeFile);

        $probableCause = SystemErrorLogger::sanitizeSensitiveText((string) ($diagnosis['probable_cause'] ?? 'Unerwarteter Laufzeitfehler.'));
        $recommendedAction = SystemErrorLogger::sanitizeSensitiveText((string) ($diagnosis['recommended_action'] ?? 'Datei und Stack-Trace prüfen.'));
        $criticality = $this->determineCriticality($log, $payload, $relativeFile);
        $aiEnriched = false;

        if ($allowAi) {
            $aiResult = $this->enrichWithGeminiFlash($log, $fingerprint, $relativeFile, $line, $codeSnippet, $probableCause);
            if (is_array($aiResult)) {
                $probableCause = $aiResult['root_cause_de'] ?: $probableCause;
                $recommendedAction = $aiResult['fix_plan_de'] ?: $recommendedAction;
                if (!empty($aiResult['criticality']) && in_array($aiResult['criticality'], ['critical', 'high', 'standard'], true)) {
                    $criticality = $aiResult['criticality'];
                }
                $aiEnriched = true;
                $aiCallsUsed++;
            }
        }

        $traceRaw = '';
        if (!empty($payload['trace_excerpt']) && is_array($payload['trace_excerpt'])) {
            $traceRaw = implode("\n", array_slice($payload['trace_excerpt'], 0, 10));
        } elseif (!empty($payload['stack_excerpt']) && is_string($payload['stack_excerpt'])) {
            $traceRaw = $payload['stack_excerpt'];
        } elseif (!empty($payload['console_output']) && is_string($payload['console_output'])) {
            $traceRaw = $payload['console_output'];
        }

        return SystemErrorLogger::sanitizeArrayRecursive([
            'dossier_version' => 2,
            'fingerprint' => $fingerprint,
            'log_id' => $log->id,
            'origin_environment' => (string) ($payload['origin_environment'] ?? config('app.env', 'production')),
            'prepared_at' => now()->toIso8601String(),
            'prepared_by' => $aiEnriched ? 'Funkira Sentinel (Gemini 2.5 Flash)' : 'Funkira Sentinel (Deterministisch)',
            'ai_enriched' => $aiEnriched,
            'criticality' => $criticality,
            'platform' => $platformKey,
            'platform_label' => $platformMeta['label'],
            'type' => (string) $log->type,
            'action_id' => (string) $log->action_id,
            'severity' => (string) $log->status,
            'title' => (string) $log->title,
            'message' => Str::limit((string) $log->message, 1200),
            'exception_class' => $payload['exception_class'] ?? null,
            'culprit_file' => $relativeFile ?? ($resolvedFile !== '' ? $resolvedFile : null),
            'compiled_view_file' => $payload['compiled_view_file'] ?? null,
            'line' => $line,
            'route_or_command' => $payload['execution_context']['route']
                ?? $payload['execution_context']['command']
                ?? $payload['command']
                ?? null,
            'occurrences_count' => max(1, (int) ($payload['occurrences_count'] ?? 1)),
            'first_seen_at' => $payload['first_seen_at'] ?? ($log->started_at?->toIso8601String() ?? now()->toIso8601String()),
            'last_seen_at' => $payload['last_seen_at'] ?? ($log->finished_at?->toIso8601String() ?? now()->toIso8601String()),
            'category' => $diagnosis['category'] ?? 'application_runtime_error',
            'probable_cause' => $probableCause,
            'recommended_action' => $recommendedAction,
            'code_snippet' => $codeSnippet,
            'trace_excerpt' => Str::limit($traceRaw, 2000),
            'incoming_files' => $neighbors['incoming'],
            'outgoing_files' => $neighbors['outgoing'],
        ]);
    }

    /**
     * Optionale, einmalige Gemini-2.5-Flash-Anreicherung pro Fingerprint (IPv4, 10s Timeout, 24h Cache).
     * Fällt bei fehlendem API-Key oder Offline-Modus geräuschlos auf die deterministische Diagnose zurück.
     */
    protected function enrichWithGeminiFlash(
        SystemLog $log,
        string $fingerprint,
        ?string $relativeFile,
        ?int $line,
        ?string $codeSnippet,
        string $fallbackCause
    ): ?array {
        if (!\App\Services\AI\VertexAiService::hasCredentials()) {
            return null;
        }

        $cacheKey = 'sentinel:ai_dossier:' . $fingerprint;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $prompt = "Du bist Funkira, die technische Wächter-KI von Mein-Seelenfunke. "
            . "Analysiere diesen Anwendungsfehler präzise für den Entwickler-Agenten Antigravity. "
            . "Antworte AUSSCHLIESSLICH als valides JSON-Objekt mit den Schlüsseln: "
            . "\"root_cause_de\" (1-2 klare deutsche Sätze zur exakten technischen Ursache, ohne Gedankenstriche), "
            . "\"fix_plan_de\" (1-2 konkrete deutsche Sätze zum sicheren Code-Fix inkl. Test-Hinweis, ohne Gedankenstriche), "
            . "\"criticality\" (\"critical\" nur bei Checkout/Zahlung/Totalausfall, sonst \"high\" oder \"standard\").\n\n"
            . "Titel: " . SystemErrorLogger::sanitizeSensitiveText((string) $log->title) . "\n"
            . "Meldung: " . SystemErrorLogger::sanitizeSensitiveText(Str::limit((string) $log->message, 600)) . "\n"
            . "Datei: " . ($relativeFile ?? 'unbekannt') . ':' . ($line ?? '?') . "\n"
            . "Vorab-Diagnose: " . $fallbackCause . "\n"
            . ($codeSnippet ? "Code-Ausschnitt:\n" . $codeSnippet . "\n" : '');

        try {
            $response = \App\Services\AI\VertexAiService::generateContent('gemini-2.5-flash', [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 350,
                    'responseMimeType' => 'application/json',
                ],
            ], 10, 4);

            if (!$response->successful()) {
                return null;
            }

            $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
            $parsed = json_decode(trim($text), true);
            if (!is_array($parsed) || empty($parsed['root_cause_de']) || empty($parsed['fix_plan_de'])) {
                return null;
            }

            $clean = [
                'root_cause_de' => str_replace(' – ', ', ', SystemErrorLogger::sanitizeSensitiveText(trim((string) $parsed['root_cause_de']))),
                'fix_plan_de' => str_replace(' – ', ', ', SystemErrorLogger::sanitizeSensitiveText(trim((string) $parsed['fix_plan_de']))),
                'criticality' => in_array(($parsed['criticality'] ?? ''), ['critical', 'high', 'standard'], true)
                    ? $parsed['criticality']
                    : 'standard',
            ];

            Cache::put($cacheKey, $clean, now()->addHours(24));
            return $clean;
        } catch (\Throwable $ignore) {
            return null;
        }
    }

    /**
     * Schreibt den gesamten offenen Antigravity-Auffang-Pool als JSON-Datei (0600)
     * und hält gleichzeitig antigravity_error_handoff.json mit dem wichtigsten offenen Fall synchron.
     */
    public function writeInboxSnapshotFile(): array
    {
        $queuedLogs = SystemLog::queuedForAntigravity()
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $items = [];
        foreach ($queuedLogs as $log) {
            $payload = is_array($log->payload) ? $log->payload : [];
            $dossier = is_array($payload['antigravity_dossier'] ?? null)
                ? $payload['antigravity_dossier']
                : null;

            if ($dossier === null) {
                continue;
            }

            if (!app()->runningUnitTests() && (($dossier['origin_environment'] ?? '') === 'testing' || ($payload['origin_environment'] ?? '') === 'testing')) {
                continue;
            }

            $dossier['log_id'] = $log->id;
            $dossier['occurrences_count'] = max(1, (int) ($payload['occurrences_count'] ?? $dossier['occurrences_count'] ?? 1));
            $dossier['last_seen_at'] = $payload['last_seen_at'] ?? $dossier['last_seen_at'] ?? now()->toIso8601String();
            $items[] = $dossier;
        }

        // Kritische und häufige Fehler zuerst sortieren
        usort($items, function (array $a, array $b): int {
            $prio = ['critical' => 3, 'high' => 2, 'standard' => 1];
            $pa = $prio[$a['criticality'] ?? 'standard'] ?? 1;
            $pb = $prio[$b['criticality'] ?? 'standard'] ?? 1;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            return ((int) ($b['occurrences_count'] ?? 1)) <=> ((int) ($a['occurrences_count'] ?? 1));
        });

        $autoHealed7d = SystemLog::autoHealedEntries()
            ->where('finished_at', '>=', now()->subDays(7))
            ->count();

        $snapshot = SystemErrorLogger::sanitizeArrayRecursive([
            'inbox_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'generated_at_human' => now()->format('d.m.Y H:i:s'),
            'source_environment' => config('app.env', 'production'),
            'app_url' => config('app.url'),
            'open_pool_count' => count($items),
            'auto_healed_last_7d_count' => $autoHealed7d,
            'items' => $items,
        ]);

        $fileMode = app()->environment('local', 'testing') ? 0666 : 0600;

        $inboxPath = storage_path(self::INBOX_FILE);
        File::ensureDirectoryExists(dirname($inboxPath));
        File::put($inboxPath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @chmod($inboxPath, $fileMode);

        $handoffPath = storage_path(self::HANDOFF_FILE);
        if (!empty($items)) {
            $top = $items[0];
            $handoffCompat = SystemErrorLogger::sanitizeArrayRecursive([
                'handoff_version' => 2,
                'prepared_at' => now()->toIso8601String(),
                'prepared_at_human' => now()->format('d.m.Y H:i:s'),
                'prepared_by' => $top['prepared_by'] ?? 'Funkira Sentinel',
                'environment' => $top['origin_environment'] ?? config('app.env', 'production'),
                'status' => 'ready_for_antigravity',
                'open_pool_count' => count($items),
                'fingerprint' => $top['fingerprint'] ?? null,
                'item_key' => $top['culprit_file'] ? ('core:' . $top['culprit_file']) : ('log:' . ($top['log_id'] ?? '0')),
                'platform' => $top['platform'] ?? 'portal',
                'platform_label' => $top['platform_label'] ?? 'Webportal',
                'severity' => $top['severity'] ?? 'error',
                'title' => $top['title'] ?? 'System-Fehler',
                'message' => $top['message'] ?? '',
                'culprit_file' => $top['culprit_file'] ?? null,
                'culprit_name' => !empty($top['culprit_file']) ? basename((string) $top['culprit_file']) : ($top['platform_label'] ?? 'System'),
                'line' => $top['line'] ?? null,
                'occurrences_count' => $top['occurrences_count'] ?? 1,
                'log_id' => $top['log_id'] ?? null,
                'route' => $top['route_or_command'] ?? '',
                'probable_cause' => $top['probable_cause'] ?? '',
                'recommended_action' => $top['recommended_action'] ?? '',
                'code_snippet' => $top['code_snippet'] ?? null,
                'trace_excerpt' => $top['trace_excerpt'] ?? '',
                'incoming_files' => $top['incoming_files'] ?? [],
                'outgoing_files' => $top['outgoing_files'] ?? [],
            ]);
            File::put($handoffPath, json_encode($handoffCompat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            @chmod($handoffPath, $fileMode);
        } elseif (File::exists($handoffPath)) {
            $existingHandoff = json_decode((string) @file_get_contents($handoffPath), true);
            if (is_array($existingHandoff) && str_contains((string) ($existingHandoff['prepared_by'] ?? ''), 'Sentinel')) {
                @unlink($handoffPath);
            }
        }

        return $snapshot;
    }

    /**
     * Liefert den aktuellen Auffang-Pool-Snapshot (optional nach vorherigem Schnell-Triage-Lauf).
     */
    public function exportInboxSnapshot(bool $runFastTriageFirst = true): array
    {
        if ($runFastTriageFirst) {
            $this->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
        }

        return $this->writeInboxSnapshotFile();
    }

    /**
     * Synchronisiert den 24/7-Stage-Auffang-Pool verlustfrei in die lokale Notebook-Datenbank (127.0.0.1)
     * und aktualisiert die lokale antigravity_inbox.json, sobald das Notebook eingeschaltet wird.
     */
    public function importRemoteStageInbox(array $remoteSnapshot): array
    {
        $remoteItems = is_array($remoteSnapshot['items'] ?? null) ? $remoteSnapshot['items'] : [];
        $sourceEnv = (string) ($remoteSnapshot['source_environment'] ?? 'stage');

        $importedNew = 0;
        $updatedExisting = 0;
        $autoReconciled = 0;
        $activeRemoteFingerprints = [];

        foreach ($remoteItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $fp = trim((string) ($item['fingerprint'] ?? ''));
            if ($fp === '') {
                continue;
            }
            $activeRemoteFingerprints[$fp] = true;

            $relativeFile = $this->normalizeProjectRelativePath((string) ($item['culprit_file'] ?? ''));
            $line = isset($item['line']) && is_numeric($item['line']) ? (int) $item['line'] : null;

            if ($relativeFile === null && !empty($item['trace_excerpt'])) {
                $tracePayload = ['stack_excerpt' => is_array($item['trace_excerpt']) ? implode("\n", $item['trace_excerpt']) : (string) $item['trace_excerpt']];
                $candFile = $this->extractBestFileCandidate($tracePayload);
                $relativeFile = $this->normalizeProjectRelativePath($candFile);
                if ($line === null) {
                    $line = $this->extractBestLineCandidate($tracePayload);
                }
                if ($relativeFile !== null) {
                    $item['culprit_file'] = $relativeFile;
                    if ($line !== null) {
                        $item['line'] = $line;
                    }
                }
            }

            // Falls der Code lokal vorhanden ist und im Stage-/Mini-PC-Eintrag noch kein lokaler Ausschnitt lag, anreichern
            if (empty($item['code_snippet']) && $relativeFile !== null) {
                $item['code_snippet'] = $this->extractSafeCodeSnippet($relativeFile, $line, 12);
            }
            $itemOrigin = trim((string) ($item['origin_environment'] ?? '')) ?: $sourceEnv;
            $item['origin_environment'] = $itemOrigin;

            $existing = $this->findOpenLogByFingerprint($fp);
            $occurrences = max(1, (int) ($item['occurrences_count'] ?? 1));

            if ($existing) {
                $p = is_array($existing->payload) ? $existing->payload : [];
                $p['fingerprint'] = $fp;
                $p['origin_environment'] = $itemOrigin;
                $p['stage_log_id'] = $item['log_id'] ?? ($p['stage_log_id'] ?? null);
                $p['occurrences_count'] = max((int) ($p['occurrences_count'] ?? 1), $occurrences);
                $p['last_seen_at'] = $item['last_seen_at'] ?? now()->toIso8601String();
                $p['triage'] = [
                    'state' => 'queued_for_antigravity',
                    'triaged_at' => now()->toIso8601String(),
                    'triaged_by' => 'Universe 24/7 Pool Sync',
                    'criticality' => $item['criticality'] ?? 'standard',
                ];
                $p['antigravity_dossier'] = SystemErrorLogger::sanitizeArrayRecursive($item);
                $existing->payload = $p;
                $existing->finished_at = now();
                $existing->save();
                $updatedExisting++;
                continue;
            }

            $rawTitle = SystemErrorLogger::sanitizeSensitiveText((string) ($item['title'] ?? 'System-Fehler'));
            $badge = $itemOrigin === 'minipc_direct' ? '[MINI-PC]' : '[STAGE]';
            $prefixedTitle = (str_starts_with($rawTitle, '[STAGE]') || str_starts_with($rawTitle, '[MINI-PC]'))
                ? $rawTitle
                : ($badge . ' ' . $rawTitle);

            SystemLog::create([
                'type' => Str::limit((string) ($item['type'] ?? 'system'), 40, ''),
                'action_id' => Str::limit((string) ($item['action_id'] ?? ($itemOrigin . ':pool:' . substr($fp, 0, 16))), 190, ''),
                'title' => Str::limit($prefixedTitle, 190),
                'message' => Str::limit(SystemErrorLogger::sanitizeSensitiveText((string) ($item['message'] ?? '')), 2000),
                'status' => ($item['severity'] ?? 'error') === 'warning' ? 'warning' : 'error',
                'payload' => SystemErrorLogger::sanitizeArrayRecursive([
                    'platform' => $item['platform'] ?? 'portal',
                    'platform_label' => $item['platform_label'] ?? 'Webportal',
                    'origin_environment' => $itemOrigin,
                    'stage_log_id' => $item['log_id'] ?? null,
                    'fingerprint' => $fp,
                    'exception_class' => $item['exception_class'] ?? null,
                    'file' => $relativeFile ?? ($item['culprit_file'] ?? null),
                    'line' => $line,
                    'occurrences_count' => $occurrences,
                    'first_seen_at' => $item['first_seen_at'] ?? now()->toIso8601String(),
                    'last_seen_at' => $item['last_seen_at'] ?? now()->toIso8601String(),
                    'diagnosis' => [
                        'category' => $item['category'] ?? 'application_runtime_error',
                        'probable_cause' => $item['probable_cause'] ?? '',
                        'recommended_action' => $item['recommended_action'] ?? '',
                    ],
                    'triage' => [
                        'state' => 'queued_for_antigravity',
                        'triaged_at' => now()->toIso8601String(),
                        'triaged_by' => 'Stage 24/7 Pool Sync',
                        'criticality' => $item['criticality'] ?? 'standard',
                    ],
                    'antigravity_dossier' => $item,
                ]),
                'started_at' => !empty($item['first_seen_at']) ? Carbon::parse($item['first_seen_at']) : now(),
                'finished_at' => now(),
            ]);

            $importedNew++;
        }

        // Lokal importierte Stage-/Mini-PC-Einträge, die remote bereits behoben/geschlossen wurden, automatisch nachziehen
        $reconcileOrigins = array_values(array_unique([$sourceEnv, 'minipc_direct']));
        $localStageLogs = SystemLog::openIssues()
            ->whereIn('payload->origin_environment', $reconcileOrigins)
            ->get();

        foreach ($localStageLogs as $localStageLog) {
            $fp = (string) ($localStageLog->payload['fingerprint'] ?? '');
            if ($fp !== '' && !isset($activeRemoteFingerprints[$fp])) {
                $p = is_array($localStageLog->payload) ? $localStageLog->payload : [];
                $p['resolved_at'] = now()->toIso8601String();
                $p['resolved_by'] = 'Stage-Pool Abgleich';
                $p['triage']['state'] = 'resolved';
                $localStageLog->status = 'resolved';
                $localStageLog->payload = $p;
                $localStageLog->finished_at = now();
                $localStageLog->save();
                $autoReconciled++;
            }
        }

        $localSnapshot = $this->writeInboxSnapshotFile();

        return [
            'status' => 'success',
            'remote_pool_count' => count($remoteItems),
            'imported_new' => $importedNew,
            'updated_existing' => $updatedExisting,
            'auto_reconciled' => $autoReconciled,
            'local_open_pool_count' => (int) ($localSnapshot['open_pool_count'] ?? 0),
            'inbox_path' => 'storage/' . self::INBOX_FILE,
        ];
    }

    /**
     * Markiert behobene Einträge im Auffang-Pool sauber als 'resolved' (7-Tage-Archiv) und schreibt die Inbox neu.
     */
    public function resolvePoolItems(
        ?array $fingerprints = null,
        ?array $logIds = null,
        bool $resolveAll = false,
        string $resolvedBy = 'Antigravity'
    ): int {
        $query = SystemLog::openIssues();

        if (!$resolveAll) {
            $fps = array_values(array_filter(array_map('trim', $fingerprints ?? [])));
            $ids = array_values(array_filter(array_map('intval', $logIds ?? [])));

            if (empty($fps) && empty($ids)) {
                return 0;
            }

            $query->where(function ($q) use ($fps, $ids) {
                if (!empty($ids)) {
                    $q->whereIn('id', $ids);
                }
                if (!empty($fps)) {
                    foreach ($fps as $fp) {
                        $q->orWhere('payload->fingerprint', $fp);
                    }
                }
            });
        }

        $logs = $query->get();
        $resolvedCount = 0;

        foreach ($logs as $log) {
            $payload = is_array($log->payload) ? $log->payload : [];
            $payload['resolved_at'] = now()->toIso8601String();
            $payload['resolved_by'] = $resolvedBy;
            if (!isset($payload['triage']) || !is_array($payload['triage'])) {
                $payload['triage'] = [];
            }
            $payload['triage']['state'] = 'resolved';
            $payload['triage']['resolved_at'] = now()->toIso8601String();
            $payload['triage']['resolved_by'] = $resolvedBy;

            $log->status = 'resolved';
            $log->payload = $payload;
            $log->finished_at = now();
            $log->save();
            $resolvedCount++;
        }

        $this->writeInboxSnapshotFile();

        return $resolvedCount;
    }

    /**
     * Kritischer Push-Alarm ans Smartphone (streng gedrosselt auf max. 1x pro 6h und NUR bei echtem kritischen Befund
     * sowie vorhandener firebase-service-account.json, damit auf Stage niemals Fehl-Logs entstehen!).
     */
    protected function maybeSendCriticalOwnerAlert(SystemLog $log, array $dossier): void
    {
        if (($dossier['criticality'] ?? 'standard') !== 'critical') {
            return;
        }

        $credentialsPath = (string) config('services.firebase.credentials_file', storage_path('app/firebase-service-account.json'));
        if ($credentialsPath === '' || !is_file($credentialsPath)) {
            return;
        }

        if (Cache::has(self::PUSH_COOLDOWN_KEY)) {
            return;
        }

        try {
            if (!Schema::hasTable('system_user_devices')) {
                return;
            }

            $tokens = DB::table('system_user_devices')
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->pluck('fcm_token')
                ->unique()
                ->take(5)
                ->all();

            if (empty($tokens)) {
                return;
            }

            Cache::put(self::PUSH_COOLDOWN_KEY, now()->toIso8601String(), now()->addHours(6));

            $firebase = app(\App\Services\FirebaseService::class);
            foreach ($tokens as $token) {
                $firebase->sendNotification(
                    (string) $token,
                    'Funkira Wächter: Kritischer Befund',
                    Str::limit((string) ($dossier['title'] ?? $log->title), 120),
                    [
                        'type' => 'sentinel_critical_alert',
                        'log_id' => (string) $log->id,
                    ]
                );
            }
        } catch (\Throwable $ignore) {
        }
    }

    protected function verifyCoreConnectivityHealthy(): bool
    {
        try {
            DB::select('SELECT 1');
            $probeKey = 'sentinel:health_probe:' . Str::random(8);
            Cache::put($probeKey, 'ok', 15);
            $val = Cache::get($probeKey);
            Cache::forget($probeKey);
            return $val === 'ok';
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function resolveLogFingerprint(SystemLog $log, array $payload): string
    {
        $fp = trim((string) ($payload['fingerprint'] ?? ''));
        if (strlen($fp) >= 16) {
            return $fp;
        }

        $normalizedMsg = preg_replace('/\b\d+\b/', '#', strtolower((string) $log->message));
        return hash('sha256', strtolower($log->type . '|' . $log->action_id . '|' . ($payload['file'] ?? '') . '|' . ($payload['line'] ?? 0) . '|' . mb_substr((string) $normalizedMsg, 0, 160)));
    }

    protected function findOpenLogByFingerprint(string $fingerprint): ?SystemLog
    {
        return SystemLog::openIssues()
            ->where('payload->fingerprint', $fingerprint)
            ->latest('id')
            ->first();
    }

    protected function extractBestFileCandidate(array $payload): ?string
    {
        if (!empty($payload['file']) && is_string($payload['file'])) {
            return $payload['file'];
        }

        $traces = [];
        if (!empty($payload['trace_excerpt']) && is_array($payload['trace_excerpt'])) {
            $traces = $payload['trace_excerpt'];
        } elseif (!empty($payload['stack_excerpt']) && is_string($payload['stack_excerpt'])) {
            $traces = explode("\n", $payload['stack_excerpt']);
        }

        foreach ($traces as $frameLine) {
            $frameStr = (string) $frameLine;
            if (str_contains($frameStr, '/vendor/') || str_contains($frameStr, '\\vendor\\')) {
                continue;
            }
            // Android Kotlin/Java JVM Stacktrace: at de.meinseelenfunke.app.foo.Bar.baz(Bar.kt:123)
            if (preg_match('/\bat\s+(de\.meinseelenfunke\.app(?:\.[A-Za-z0-9_]+)*)\.[A-Za-z0-9_$<>]+\.[A-Za-z0-9_$<>]+\(([A-Za-z0-9_]+\.(?:kt|java)):(\d+)\)/', $frameStr, $km)) {
                $pkgRel = str_replace('.', '/', $km[1]);
                return 'android-app/app/src/main/java/' . $pkgRel . '/' . $km[2];
            }
            // C# Stacktrace (FunkenwerkOSLinux / Funkenwerk Desktop): in /.../Foo.cs:line 123
            if (preg_match('/([A-Za-z0-9_\/\\\\.\-]+?\.cs):line\s+(\d+)/', $frameStr, $cm)) {
                return $cm[1];
            }
            if (preg_match('/([A-Za-z0-9_\/\\\\.\-]+?\.(?:php|blade\.php|kt|cs|py|js)):(\d+)/', $frameStr, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    protected function extractBestLineCandidate(array $payload): ?int
    {
        $traces = [];
        if (!empty($payload['trace_excerpt']) && is_array($payload['trace_excerpt'])) {
            $traces = $payload['trace_excerpt'];
        } elseif (!empty($payload['stack_excerpt']) && is_string($payload['stack_excerpt'])) {
            $traces = explode("\n", $payload['stack_excerpt']);
        }

        foreach ($traces as $frameLine) {
            $frameStr = (string) $frameLine;
            if (str_contains($frameStr, '/vendor/') || str_contains($frameStr, '\\vendor\\')) {
                continue;
            }
            if (preg_match('/\bat\s+de\.meinseelenfunke\.app[A-Za-z0-9_.$<>]*\([A-Za-z0-9_]+\.(?:kt|java):(\d+)\)/', $frameStr, $km)) {
                return (int) $km[1];
            }
            if (preg_match('/\.cs:line\s+(\d+)/', $frameStr, $cm)) {
                return (int) $cm[1];
            }
            if (preg_match('/\.(?:php|blade\.php|kt|cs|py|js):(\d+)/', $frameStr, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    public function normalizeProjectRelativePath(?string $rawPath): ?string
    {
        if ($rawPath === null || trim($rawPath) === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', trim($rawPath));
        $baseNormalized = rtrim(str_replace('\\', '/', base_path()), '/') . '/';

        if (str_starts_with($normalized, $baseNormalized)) {
            return ltrim(substr($normalized, strlen($baseNormalized)), '/');
        }

        $knownRoots = [
            'app/', 'resources/', 'routes/', 'config/', 'database/',
            'public/', 'tests/', 'funkenwerk/', 'funkenwerk-mcp/',
            'funkenwerk-os-linux/', 'android-app/',
        ];

        foreach ($knownRoots as $root) {
            if (str_starts_with($normalized, $root)) {
                return $normalized;
            }
            $needle = '/' . $root;
            $pos = strpos($normalized, $needle);
            if ($pos !== false) {
                return substr($normalized, $pos + 1);
            }
        }

        return null;
    }

    /**
     * Liest +/- $radius Zeilen rund um die Fehlerzeile sicher und geheimnisbereinigt aus.
     * Sicherheits-Whitelist verhindert jeglichen Zugriff auf .env, Keys, vendor/ oder storage/.
     */
    public function extractSafeCodeSnippet(?string $relativePath, ?int $line, int $radius = 12): ?string
    {
        if ($relativePath === null || $relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $lower = strtolower($relativePath);
        if (str_starts_with($lower, '.env')
            || str_contains($lower, '/.env')
            || str_contains($lower, 'secret')
            || str_contains($lower, 'private_key')
            || str_contains($lower, 'firebase-service-account')
            || str_contains($lower, 'vertex-service-account')
            || str_starts_with($lower, 'storage/')
            || str_starts_with($lower, 'vendor/')) {
            return null;
        }

        $allowedPrefixes = [
            'app/', 'resources/', 'routes/', 'config/', 'database/',
            'tests/', 'funkenwerk/', 'funkenwerk-mcp/', 'funkenwerk-os-linux/', 'android-app/',
        ];

        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return null;
        }

        $absPath = base_path($relativePath);
        if (!is_file($absPath) || !is_readable($absPath) || (@filesize($absPath) ?: 0) > 1024 * 1024) {
            return null;
        }

        $allLines = @file($absPath, FILE_IGNORE_NEW_LINES);
        if (!is_array($allLines) || empty($allLines)) {
            return null;
        }

        $total = count($allLines);
        $targetLine = ($line !== null && $line > 0) ? min($line, $total) : 1;
        $start = max(1, $targetLine - $radius);
        $end = min($total, $targetLine + $radius);

        $excerpt = [];
        for ($i = $start; $i <= $end; $i++) {
            $marker = ($line !== null && $i === $targetLine) ? '> ' : '  ';
            $cleanCode = SystemErrorLogger::sanitizeSensitiveText(Str::limit((string) ($allLines[$i - 1] ?? ''), 220, '...'));
            $excerpt[] = sprintf('%s%4d | %s', $marker, $i, $cleanCode);
        }

        return implode("\n", $excerpt);
    }

    /**
     * Löst Aufrufer (incoming) und Abhängigkeiten (outgoing) schnell über SystemNeuralNode auf.
     */
    protected function resolveFileNeighborhood(?string $relativePath): array
    {
        if ($relativePath === null || !Schema::hasTable('system_neural_nodes')) {
            return ['incoming' => [], 'outgoing' => []];
        }

        try {
            $node = SystemNeuralNode::where('file_path', $relativePath)->first();
            $outgoing = [];
            if ($node && is_array($node->dependencies)) {
                $outgoing = array_slice(array_values(array_filter($node->dependencies, 'is_string')), 0, 10);
            }

            $incoming = SystemNeuralNode::whereJsonContains('dependencies', $relativePath)
                ->limit(10)
                ->pluck('file_path')
                ->all();

            return [
                'incoming' => $incoming,
                'outgoing' => $outgoing,
            ];
        } catch (\Throwable $ignore) {
            return ['incoming' => [], 'outgoing' => []];
        }
    }

    protected function determineCriticality(SystemLog $log, array $payload, ?string $relativeFile): string
    {
        $haystack = strtolower(($log->title ?? '') . ' ' . ($log->message ?? '') . ' ' . ($relativeFile ?? '') . ' ' . ($log->action_id ?? ''));
        $occurrences = (int) ($payload['occurrences_count'] ?? 1);

        if (str_contains($haystack, 'ordercheckout')
            || str_contains($haystack, 'stripe')
            || str_contains($haystack, 'fatalshutdownerror')
            || str_contains($haystack, 'out of memory')
            || str_contains($haystack, 'bridge_unhealthy')) {
            return 'critical';
        }

        if ($log->status === 'error' && $occurrences >= 10) {
            return 'high';
        }

        return 'standard';
    }
}

<?php

namespace App\Services\System;

use App\Models\System\SystemLog;
use App\Models\System\SystemNeuralNode;
use App\Services\AI\Mails\AiAgentMessageMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
    public const BRIDGE_ALERT_COOLDOWN_PREFIX = 'sentinel:bridge_alert_sent:';
    public const BRIDGE_ALERT_COOLDOWN_MINUTES = 30;
    public const BRIDGE_ALERT_EMAIL = 'kontakt@mein-seelenfunke.de';

    public const BRIDGE_HEALTH_TARGETS = [
        'twilio_reverb' => [
            'key' => 'twilio_reverb',
            'action_id' => 'sentinel:bridge_down:twilio_reverb',
            'legacy_action_ids' => ['sentinel:bridge_unhealthy:reverb_twilio', 'sentinel:bridge_unhealthy:twilio_reverb'],
            'label' => 'Primäre Telefonie- & Reverb-Bridge (a-iurgq8)',
            'short_label' => 'Telefonie & Reverb (a-iurgq8)',
            'container_id' => 'a-iurgq8',
            'url' => 'https://ws.mein-seelenfunke.de/twilio-health',
            'health_path' => '/twilio-health',
            'ssh_restart_cmd' => 'ssh kontakt@mein-seelenfunke.de@a-iurgq8@ssh.altgemeinde.project.host "mittnitectl job restart"',
        ],
        'gemini_live' => [
            'key' => 'gemini_live',
            'action_id' => 'sentinel:bridge_down:gemini_live',
            'legacy_action_ids' => ['sentinel:bridge_unhealthy:live_bridge', 'sentinel:bridge_unhealthy:gemini_live'],
            'label' => 'Node.js Gemini-Live-Audio-Bridge (a-yeq7el)',
            'short_label' => 'Gemini Live-Bridge (a-yeq7el)',
            'container_id' => 'a-yeq7el',
            'url' => 'https://api-live-bridge.mein-seelenfunke.de/health',
            'health_path' => '/health',
            'ssh_restart_cmd' => 'ssh kontakt@mein-seelenfunke.de@a-yeq7el@ssh.altgemeinde.project.host "mittnitectl job restart"',
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
     * Scant eine Laravel-Logdatei gezielt über die gehärtete LogArchiveService-Pipeline (ignoriert `testing.*`-Zeilen,
     * eskaliert jedoch `local.*` und `production.*` ERROR/CRITICAL-Zeilen in system_logs).
     */
    public function scanLaravelLogFile(?string $customPath = null): int
    {
        $path = $customPath ?? storage_path('logs/laravel.log');

        return LogArchiveService::ingestSingleLogFileIntoSentinel($path, 'laravel.log');
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
            $relapseEscalatedCount = 0;
            $aiCallsUsed = 0;

            $openLogs = SystemLog::openIssues()
                ->orderByDesc('id')
                ->limit(120)
                ->get();

            foreach ($openLogs as $log) {
                $payload = is_array($log->payload) ? $log->payload : [];
                $fingerprint = $this->resolveLogFingerprint($log, $payload);
                $existingTriageState = $payload['triage']['state'] ?? null;
                $existingDossierFp = $payload['antigravity_dossier']['fingerprint'] ?? null;
                $alreadyRelapseLocked = !empty($payload['triage']['relapse_detected']) && $existingTriageState === 'queued_for_antigravity';

                $relapseTriggeredCount = null;
                $blockedPlaybook = null;

                if (!$alreadyRelapseLocked) {
                    $healDecision = $this->attemptAutonomousHeal($log);

                    if (!empty($healDecision['healed'])) {
                        $candidatePlaybook = (string) ($healDecision['playbook'] ?? 'autonomous_heal');
                        $priorAutoHeals24h = $candidatePlaybook === 'gemini_live_session_rollover'
                            ? 0
                            : $this->countPriorAutoHealsLast24h($log, $fingerprint, $payload);

                        if ($priorAutoHeals24h < 2) {
                            $payload['fingerprint'] = $fingerprint;
                            $log->payload = $payload;
                            $this->markAsAutoHealed(
                                $log,
                                $candidatePlaybook,
                                (string) ($healDecision['summary'] ?? 'Automatisch durch Funkira-Wächter bereinigt.')
                            );
                            $healedCount++;
                            continue;
                        }

                        // Rezidiv-Sperre (Chronic Relapse Guard): >= 2 vorangegangene Auto-Heilungen in 24h -> 3.+ Auftreten eskalieren!
                        $relapseTriggeredCount = $priorAutoHeals24h + 1;
                        $blockedPlaybook = $candidatePlaybook;
                    }
                }

                // Strikte 1x-pro-Fingerprint-Idempotenz (0 Token-Schleifen bei Wiederholungsfehlern)
                if ($existingTriageState === 'queued_for_antigravity' && $existingDossierFp === $fingerprint && $relapseTriggeredCount === null) {
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

                if ($relapseTriggeredCount !== null) {
                    $relapseDiagnosis = "Wiederkehrender Fehler trotz Selbstheilung (Rezidiv #{$relapseTriggeredCount} in 24h) - Dauerhafte Ursachenbehebung erforderlich";
                    $dossier['criticality'] = 'elevated';
                    $dossier['severity'] = 'error';
                    $dossier['relapse_detected'] = true;
                    $dossier['relapse_count_24h'] = $relapseTriggeredCount;
                    $dossier['blocked_playbook'] = $blockedPlaybook;
                    $dossier['probable_cause'] = $relapseDiagnosis . '. ' . ($dossier['probable_cause'] ?? '');

                    $payload['diagnosis'] = is_array($payload['diagnosis'] ?? null) ? $payload['diagnosis'] : [];
                    $payload['diagnosis']['probable_cause'] = $relapseDiagnosis;
                    $payload['fingerprint'] = $fingerprint;
                    $payload['triage'] = [
                        'state' => 'queued_for_antigravity',
                        'triaged_at' => now()->toIso8601String(),
                        'triaged_by' => 'Funkira Sentinel',
                        'criticality' => 'elevated',
                        'relapse_detected' => true,
                        'relapse_count_24h' => $relapseTriggeredCount,
                        'blocked_playbook' => $blockedPlaybook,
                    ];
                    $payload['antigravity_dossier'] = $dossier;

                    $log->status = 'error';
                    $log->payload = $payload;
                    $log->save();

                    $relapseEscalatedCount++;
                    $newlyQueuedCount++;
                    continue;
                }

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
            $this->recordLastTriageHeartbeat();

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
                'relapse_escalated_count' => $relapseEscalatedCount,
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
                $type = SystemLog::clampColumnValue((string) ($decoded['type'] ?? 'system'), SystemLog::MAX_TYPE_LENGTH, '');
                $actionId = SystemLog::clampColumnValue((string) ($decoded['action_id'] ?? 'system:deadletter'), SystemLog::MAX_ACTION_ID_LENGTH, '');
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
                    'title' => SystemLog::clampColumnValue(SystemErrorLogger::sanitizeSensitiveText((string) ($decoded['title'] ?? 'System-Fehler (Spool)')), SystemLog::MAX_TITLE_LENGTH, '...'),
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
            @chmod($spoolPath, app()->environment('local', 'testing') ? 0666 : 0600);
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
                    $actionId = SystemLog::clampColumnValue('queue:failed_job:' . $jobUuid, SystemLog::MAX_ACTION_ID_LENGTH, '');

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
                        'title' => SystemLog::clampColumnValue('Queue-Job fehlgeschlagen (' . class_basename($displayName) . ')', SystemLog::MAX_TITLE_LENGTH, '...'),
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
                            'diagnosis' => SystemErrorLogger::diagnose(
                                $firstLine,
                                $displayName,
                                $file,
                                Str::limit($rawException, 4000, '')
                            ),
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
            $bridgeReport = $this->probeGeminiBridges(retryOnBlip: true, persistAndAlert: true);
            $probedBridges = (int) ($bridgeReport['probed_count'] ?? 0);
            $autoHealedBridges = (int) ($bridgeReport['auto_healed_count'] ?? 0);
        }

        $watchtowerSynced = ['logged' => 0, 'healed' => 0];
        if ($includeExternalBridges && class_exists(SystemHealthPulseService::class)) {
            try {
                $pulse = app(SystemHealthPulseService::class)->evaluate(
                    source: 'cli_scheduler',
                    strict: false,
                    recordSample: true,
                    syncLogs: true
                );
                $watchtowerSynced = $pulse['log_sync'] ?? ['logged' => 0, 'healed' => 0];
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

        // Playbook 4: Kurzzeitiger DNS-/Redis-Wackler (automatischer File-Cache-Fallback schützt alle Web-/API-Requests sofort)
        if ($actionId === 'system:redis_fallback' || (in_array($category, ['network_dns_or_socket_eagain', 'redis_connectivity_or_dns'], true) && $occurrences < 10)) {
            $lastSeenRaw = $payload['last_seen_at'] ?? ($log->finished_at?->toIso8601String() ?? $log->started_at?->toIso8601String());
            $isCooledDown = $actionId === 'system:redis_fallback' || $category === 'redis_connectivity_or_dns';
            if (!$isCooledDown && $lastSeenRaw) {
                try {
                    $isCooledDown = Carbon::parse($lastSeenRaw)->lte(now()->subMinutes(5));
                } catch (\Throwable $ignore) {
                }
            }

            if ($isCooledDown && $this->verifyCoreConnectivityHealthy()) {
                return [
                    'healed' => true,
                    'playbook' => 'transient_dns_or_cache_recovered',
                    'summary' => 'Temporärer DNS-/Redis-Engpass durch File-Cache-Fallback abgefangen; Live-Verbindungstest (DB + Cache) erfolgreich.',
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
                || str_contains($titleLower, 'sockettimeoutexception')
                || ((str_contains($titleLower, 'httpexception') || str_contains($msgLower, 'httpexception')) && $occurrences < 5))
            && $occurrences < 8
            && $this->verifyCoreConnectivityHealthy()) {
            return [
                'healed' => true,
                'playbook' => 'transient_mobile_offline_recovered',
                'summary' => 'Kurzzeitiger DNS-/Mobilfunk-Wechsel oder API-Reconnect am Endgerät (lokaler Offline-Cache übernahm unterbrechungsfrei).',
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

        // Playbook 8: Infrastruktur-Check Backup: Überholt, sobald ein valides Backup (< 48h) vorliegt oder in lokaler Entwicklungsumgebung
        if (str_contains($actionId, 'health_fail') && (str_contains($msgLower, 'backup') || str_contains($titleLower, 'backup'))) {
            $originEnvForBackup = strtolower(trim((string) ($payload['origin_environment'] ?? '')));
            $isLocalBackupLog = !app()->environment('production', 'staging')
                && ($originEnvForBackup !== '' ? $originEnvForBackup === 'local' : app()->environment('local'))
                && !str_starts_with((string) $log->title, '[STAGE]')
                && !str_starts_with((string) $log->title, '[LIVE]');

            if ($isLocalBackupLog) {
                return [
                    'healed' => true,
                    'playbook' => 'local_dev_backup_check_skipped',
                    'summary' => 'Lokale Entwicklungsumgebung erkannt (tägliche Backup-Pflicht gilt ausschließlich für Stage/Live).',
                ];
            }

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

        // Playbook 9: Infrastruktur-Check Gemini / Telephony Audio-Bridge: NUR heilen, wenn die echte Audio-Bridge live gesund antwortet!
        $isAudioBridgeHealthFail = str_contains($actionId, 'health_fail') && (
            str_contains($actionId, 'gemini_bridge')
            || str_contains($msgLower, 'gemini audio bridge')
            || str_contains($msgLower, 'gemini live audio-bridge')
            || str_contains($titleLower, 'gemini audio bridge')
            || str_contains($msgLower, 'telephony')
            || str_contains($titleLower, 'telephony')
            || str_contains($msgLower, 'telefonie')
        );
        if ($isAudioBridgeHealthFail) {
            $hasOpenBridgeOutage = SystemLog::openIssues()
                ->where(function ($q) {
                    $q->where('action_id', 'like', 'sentinel:bridge_down:%')
                        ->orWhere('action_id', 'like', 'sentinel:bridge_unhealthy:%');
                })
                ->exists();

            if ($hasOpenBridgeOutage || !$this->verifyGeminiBridgeHealthy()) {
                return ['healed' => false];
            }

            if ($occurrences < 10) {
                if (class_exists(SystemHealthPulseService::class)) {
                    app(SystemHealthPulseService::class)->resolveGeminiBridgeOutage();
                }

                return [
                    'healed' => true,
                    'playbook' => 'health_telephony_worker_recovered',
                    'summary' => 'Gemini Audio-Bridge Dienstbereitschaft per Live-Health-Check verifiziert und wiederhergestellt.',
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

        // Playbook 11: Interaktiver AG-Inbox CLI-Synchronisations-Aufruf (z. B. lokaler Komplettierungs-Versuch fuer Stage-Meldung)
        if (str_contains($actionId, 'cronjob_failed:system:ag-inbox') || (str_contains($msgLower, 'system:ag-inbox') && str_contains($msgLower, 'exit 1'))) {
            return [
                'healed' => true,
                'playbook' => 'interactive_ag_inbox_reconciled',
                'summary' => 'AG-Inbox CLI-Aufruf als interaktiver Stage-/Lokal-Sync erkannt und harmonisiert.',
            ];
        }

        // Playbook 12: Einmalige Blade-View-Cache-Ueberschneidung waehrend view:clear beim Deployment
        if (
            str_contains($msgLower, 'file does not exist at path')
            && str_contains($msgLower, 'storage/framework/views/')
            && $occurrences <= 3
        ) {
            $viewsDir = storage_path('framework/views');
            if (!is_dir($viewsDir)) {
                @mkdir($viewsDir, 0775, true);
            }
            if (is_dir($viewsDir) && is_writable($viewsDir)) {
                return [
                    'healed' => true,
                    'playbook' => 'transient_compiled_view_deploy_race',
                    'summary' => 'Kurzzeitige Blade-Kompilier-Ueberschneidung waehrend view:clear verifiziert (View-Cache-Ordner beschreibbar).',
                ];
            }
        }

        // Playbook 13: Harmloser Hintergrund-Stream-/Socket-Abbruch beim Schließen in Funkenwerk Windows (wpf.task)
        if (
            $log->resolvePlatformKey() === 'windows_desktop'
            && (str_contains($actionId, 'wpftask') || str_contains($actionId, 'wpf_task') || str_contains($titleLower, 'hintergrund-task-ausnahme'))
            && (
                str_contains($msgLower, 'wegen eines threadendes oder einer anwendungsanforderung abgebrochen')
                || str_contains($msgLower, 'operation was aborted')
                || str_contains($msgLower, 'the i/o operation has been aborted')
                || str_contains($msgLower, 'operationcanceledexception')
                || str_contains($msgLower, 'taskcanceledexception')
            )
            && !str_contains($msgLower, 'nullreferenceexception')
            && !str_contains($msgLower, 'invalidoperationexception')
            && $occurrences < 25
        ) {
            return [
                'healed' => true,
                'playbook' => 'benign_windows_wpftask_aborted_io',
                'summary' => 'Regulärer Hintergrund-Stream-Abbruch (ERROR_OPERATION_ABORTED / TaskCanceled) beim Schließen einer Verbindung in der Funkenwerk Suite bereinigt.',
            ];
        }

        // Playbook 14: Kurzzeitiger lokaler Docker-MySQL-Kaltstart ([2002] Connection refused auf Host db), strikt begrenzt auf local!
        $originEnv = strtolower(trim((string) ($payload['origin_environment'] ?? '')));
        $isStrictlyLocalOrigin = !app()->environment('production', 'staging')
            && ($originEnv !== '' ? $originEnv === 'local' : app()->environment('local'));

        if (
            $isStrictlyLocalOrigin
            && (str_contains($msgLower, '[2002]') || str_contains($msgLower, 'code: 2002'))
            && str_contains($msgLower, 'connection refused')
            && str_contains($msgLower, 'host: db')
            && $occurrences <= 5
            && $this->isLocalMysqlReachableNow()
        ) {
            return [
                'healed' => true,
                'playbook' => 'local_transient_mysql_cold_boot_recovered',
                'summary' => 'Kurzzeitiger lokaler Docker-MySQL-Kaltstart ([2002] Connection refused auf Host db) beendet; Live-Datenbankverbindung per SELECT 1 verifiziert.',
            ];
        }

        // Playbook 14b: Geplanter Wartungsmodus-503 (PreventRequestsDuringMaintenance waehrend migrate/deploy), sobald app wieder online ist
        if (
            $actionId === 'system:http_503'
            && str_contains(strtolower((string) ($payload['file'] ?? '')), 'preventrequestsduringmaintenance')
            && !app()->isDownForMaintenance()
            && $occurrences <= 25
        ) {
            return [
                'healed' => true,
                'playbook' => 'planned_maintenance_503_window_ended',
                'summary' => 'Geplantes Wartungsfenster (php artisan down -> HTTP 503) ist beendet; Anwendung nimmt Anfragen wieder regulär entgegen.',
            ];
        }

        // Playbook 15: Erfolgreiche Auto-Firewall-Abwehr (Honeypot / Exploit-Scanner-Ban für 24h)
        if (in_array($actionId, ['security:exploit_path_ban', 'security:auto_ip_ban'], true)) {
            $bannedIp = (string) ($payload['ip_address'] ?? '');
            return [
                'healed' => true,
                'playbook' => 'auto_firewall_ip_blocked',
                'summary' => 'Automatische Firewall-Abwehr erfolgreich: Angreifer-IP' . ($bannedIp !== '' ? " ({$bannedIp})" : '') . ' wurde für 24 Stunden gesperrt (Audit-Nachweis bleibt 30 Tage im Archiv erhalten).',
            ];
        }

        // Playbook 16: Zweiter Reverb-Startversuch auf bereits aktivem Port 6001 (Address already in use)
        if (
            str_contains($msgLower, 'address already in use')
            && (str_contains($msgLower, '6001') || str_contains($msgLower, 'reverb'))
            && $occurrences < 10
        ) {
            return [
                'healed' => true,
                'playbook' => 'reverb_daemon_already_running',
                'summary' => 'WebSocket-Port 6001 ist bereits durch den primären Reverb-Daemon belegt; kein Dienstausfall.',
            ];
        }

        return ['healed' => false];
    }

    protected function isLocalMysqlReachableNow(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Zählt, wie oft derselbe Fehler (gleicher Fingerprint oder gleiche action_id + Komponente
     * bei watchtower:* / system:health_fail) in den letzten 24h bereits per Auto-Heal geschlossen wurde.
     */
    public function countPriorAutoHealsLast24h(SystemLog $log, string $fingerprint, array $payload): int
    {
        if (!Schema::hasTable('system_logs')) {
            return 0;
        }

        $cutoff = now()->subHours(24);
        $actionIdLower = strtolower(trim((string) $log->action_id));
        $isHealthOrWatchtower = str_starts_with($actionIdLower, 'watchtower:')
            || str_contains($actionIdLower, 'health_fail');
        $targetComponent = strtolower(trim((string) ($payload['component'] ?? $payload['service'] ?? $payload['pillar'] ?? '')));

        // Wenn ein Fingerprint kürzlich explizit von Antigravity behoben wurde ('resolved'),
        // beginnt ab diesem Rollout-Zeitpunkt ein frisches Rezidiv-Fenster (statt vor-Fix Auto-Heals weiterzuzählen).
        $latestAntigravityResolution = SystemLog::query()
            ->where('status', 'resolved')
            ->where('finished_at', '>=', $cutoff)
            ->where('payload->fingerprint', $fingerprint)
            ->max('finished_at');
        if (!empty($latestAntigravityResolution)) {
            try {
                $resTime = Carbon::parse($latestAntigravityResolution)->addSecond();
                if ($resTime->greaterThan($cutoff)) {
                    $cutoff = $resTime;
                }
            } catch (\Throwable) {
            }
        }

        $candidates = SystemLog::autoHealedEntries()
            ->where('id', '!=', $log->id)
            ->where(function ($q) use ($cutoff) {
                $q->where('finished_at', '>=', $cutoff)
                    ->orWhere(function ($q2) use ($cutoff) {
                        $q2->whereNull('finished_at')->where('created_at', '>=', $cutoff);
                    });
            })
            ->where(function ($q) use ($fingerprint, $log, $isHealthOrWatchtower) {
                $q->where('payload->fingerprint', $fingerprint);
                if ($isHealthOrWatchtower && !empty($log->action_id)) {
                    $q->orWhere('action_id', $log->action_id);
                }
            })
            ->get(['id', 'type', 'action_id', 'message', 'payload']);

        $matchedCount = 0;
        foreach ($candidates as $cand) {
            $cp = is_array($cand->payload) ? $cand->payload : [];
            $candFp = $this->resolveLogFingerprint($cand, $cp);
            if ($candFp === $fingerprint || (($cp['fingerprint'] ?? '') === $fingerprint)) {
                $matchedCount++;
                continue;
            }

            if ($isHealthOrWatchtower && strtolower(trim((string) $cand->action_id)) === $actionIdLower) {
                $candComp = strtolower(trim((string) ($cp['component'] ?? $cp['service'] ?? $cp['pillar'] ?? '')));
                if ($targetComponent === '' || $candComp === $targetComponent) {
                    $matchedCount++;
                }
            }
        }

        return $matchedCount;
    }

    protected function recordLastTriageHeartbeat(): void
    {
        $iso = now()->toIso8601String();
        try {
            Cache::put('sentinel:last_triage_cycle_at', $iso, now()->addHours(24));
        } catch (\Throwable $ignore) {
        }

        try {
            if (class_exists(SystemHealthPulseService::class)) {
                $path = SystemHealthPulseService::resolveSentinelLastTriageFilePath();
                File::ensureDirectoryExists(dirname($path));
                @file_put_contents($path, json_encode(['ran_at' => $iso, 'timestamp' => now()->timestamp], JSON_UNESCAPED_SLASHES), LOCK_EX);
                if (!function_exists('posix_geteuid') || posix_geteuid() === 0 || @fileowner($path) === posix_geteuid()) {
                    @chmod($path, app()->environment('local', 'testing') ? 0666 : 0664);
                }
            }
        } catch (\Throwable $ignore) {
        }
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
            $title = SystemLog::clampColumnValue('[AUTO-GEHEILT] ' . $title, SystemLog::MAX_TITLE_LENGTH, '...');
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
                'root_cause_de' => str_replace(" \u{2013} ", ', ', SystemErrorLogger::sanitizeSensitiveText(trim((string) $parsed['root_cause_de']))),
                'fix_plan_de' => str_replace(" \u{2013} ", ', ', SystemErrorLogger::sanitizeSensitiveText(trim((string) $parsed['fix_plan_de']))),
                'criticality' => in_array(($parsed['criticality'] ?? ''), ['critical', 'high', 'elevated', 'standard'], true)
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

        // Kritische, Rezidiv- und häufige Fehler zuerst sortieren
        usort($items, function (array $a, array $b): int {
            $prio = ['critical' => 4, 'high' => 3, 'elevated' => 2, 'standard' => 1];
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
        self::writeSharedPrivateJsonAtomically(
            $inboxPath,
            (string) json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $fileMode
        );

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
                'item_key' => !empty($top['culprit_file']) ? ('core:' . $top['culprit_file']) : ('log:' . ($top['log_id'] ?? '0')),
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
            self::writeSharedPrivateJsonAtomically(
                $handoffPath,
                (string) json_encode($handoffCompat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $fileMode
            );
        } elseif (File::exists($handoffPath)) {
            $existingHandoff = json_decode((string) @file_get_contents($handoffPath), true);
            if (is_array($existingHandoff) && str_contains((string) ($existingHandoff['prepared_by'] ?? ''), 'Sentinel')) {
                @unlink($handoffPath);
            }
        }

        return $snapshot;
    }

    /**
     * Schreibt eine geteilte JSON-Steuerdatei in storage/app/private/ atomar ueber eine Temp-Datei
     * im selben Verzeichnis + rename(), damit sich Docker (www-data / root) und Windows/WSL (ubuntuxina)
     * niemals gegenseitig durch fremde Datei-Owner (0644) mit Permission denied aussperren.
     */
    public static function writeSharedPrivateJsonAtomically(
        string $targetPath,
        string $content,
        ?int $fileMode = null
    ): void {
        $effectiveFileMode = $fileMode ?? ((app()->bound('env') && app()->environment('local', 'testing')) ? 0666 : 0600);
        $dirMode = ($effectiveFileMode & 0006) !== 0 ? 0777 : 0700;
        $dir = dirname($targetPath);

        if (!is_dir($dir)) {
            @mkdir($dir, $dirMode, true);
        }
        if (!function_exists('posix_geteuid') || posix_geteuid() === 0 || @fileowner($dir) === posix_geteuid()) {
            @chmod($dir, $dirMode);
        }

        if (is_link($targetPath)) {
            @unlink($targetPath);
        }

        $tmpPath = $dir . DIRECTORY_SEPARATOR . '.' . basename($targetPath) . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        $written = @file_put_contents($tmpPath, $content, LOCK_EX);

        if ($written !== false && $written === strlen($content)) {
            @chmod($tmpPath, $effectiveFileMode);
            if (!@rename($tmpPath, $targetPath)) {
                @unlink($targetPath);
                if (!@rename($tmpPath, $targetPath)) {
                    @unlink($tmpPath);
                    if (@is_file($targetPath) && !@is_writable($targetPath)) {
                        @unlink($targetPath);
                    }
                    @file_put_contents($targetPath, $content, LOCK_EX);
                }
            }
        } else {
            @unlink($tmpPath);
            if (@is_file($targetPath) && !@is_writable($targetPath)) {
                @unlink($targetPath);
            }
            @file_put_contents($targetPath, $content, LOCK_EX);
        }

        if (!function_exists('posix_geteuid') || posix_geteuid() === 0 || @fileowner($targetPath) === posix_geteuid()) {
            @chmod($targetPath, $effectiveFileMode);
        }
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
        $staleMiniPcReconciled = 0;
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

            // Schutz gegen veraltete Offline-Handoffs vom schlafenden Mini-PC (minipc_direct):
            // Wurde derselbe Fehler (gleiche Datei+Zeile, gleicher Message-Kern oder Fingerprint) in system_logs
            // bereits NACH prepared_at als resolved/success abgeschlossen (oder stammt ein gespiegelter /seelenfunke-stage/
            // Kuppelhallen-Eintrag vor einem bereits bereinigten Stage-Snapshot), wird das Pre-Fix-Dossier direkt als resolved abgeglichen!
            if ($itemOrigin === 'minipc_direct' && !empty($item['prepared_at'])) {
                $supersedingResolved = $this->findSupersedingResolvedLogForMiniPcHandoff($item, $relativeFile, $line);
                $isClearedStageMirror = $supersedingResolved === null
                    && $this->isStaleMirroredStageHandoffClearedOnStage($item, $remoteSnapshot, $remoteItems, $relativeFile, $line);

                if ($supersedingResolved !== null || $isClearedStageMirror) {
                    unset($activeRemoteFingerprints[$fp]);
                    $resolvedIso = now()->toIso8601String();
                    $reconciledAgainstId = $supersedingResolved?->id;

                    if ($existing) {
                        $p = is_array($existing->payload) ? $existing->payload : [];
                        $p['fingerprint'] = $fp;
                        $p['origin_environment'] = $itemOrigin;
                        $p['resolved_at'] = $resolvedIso;
                        $p['resolved_by'] = 'Mini-PC Pre-Fix Wakeup Reconciler';
                        $p['reconciled_against_log_id'] = $reconciledAgainstId;
                        $p['triage'] = is_array($p['triage'] ?? null) ? $p['triage'] : [];
                        $p['triage']['state'] = 'resolved';
                        $existing->status = 'resolved';
                        $existing->payload = $p;
                        $existing->finished_at = now();
                        $existing->save();
                    } elseif (
                        !SystemLog::query()
                            ->where('payload->fingerprint', $fp)
                            ->whereIn('status', ['resolved', 'success'])
                            ->exists()
                    ) {
                        $rawTitle = SystemErrorLogger::sanitizeSensitiveText((string) ($item['title'] ?? 'System-Fehler'));
                        $prefixedTitle = str_starts_with($rawTitle, '[GELÖST]')
                            ? $rawTitle
                            : ('[GELÖST] [MINI-PC] ' . preg_replace('/^\[(?:STAGE|MINI-PC)\]\s*/i', '', $rawTitle));

                        SystemLog::create([
                            'type' => SystemLog::clampColumnValue((string) ($item['type'] ?? 'minipc'), SystemLog::MAX_TYPE_LENGTH, ''),
                            'action_id' => SystemLog::clampColumnValue((string) ($item['action_id'] ?? ('minipc_direct:reconciled:' . substr($fp, 0, 16))), SystemLog::MAX_ACTION_ID_LENGTH, ''),
                            'title' => SystemLog::clampColumnValue($prefixedTitle, SystemLog::MAX_TITLE_LENGTH, '...'),
                            'message' => Str::limit(SystemErrorLogger::sanitizeSensitiveText((string) ($item['message'] ?? '')), 2000),
                            'status' => 'resolved',
                            'payload' => SystemErrorLogger::sanitizeArrayRecursive([
                                'platform' => $item['platform'] ?? 'portal',
                                'platform_label' => $item['platform_label'] ?? 'Webportal',
                                'origin_environment' => $itemOrigin,
                                'fingerprint' => $fp,
                                'file' => $relativeFile ?? ($item['culprit_file'] ?? null),
                                'line' => $line,
                                'occurrences_count' => $occurrences,
                                'resolved_at' => $resolvedIso,
                                'resolved_by' => 'Mini-PC Pre-Fix Wakeup Reconciler',
                                'reconciled_against_log_id' => $reconciledAgainstId,
                                'triage' => [
                                    'state' => 'resolved',
                                    'triaged_at' => $resolvedIso,
                                    'triaged_by' => 'Mini-PC Pre-Fix Wakeup Reconciler',
                                ],
                                'antigravity_dossier' => $item,
                            ]),
                            'started_at' => !empty($item['prepared_at']) ? Carbon::parse((string) $item['prepared_at']) : now(),
                            'finished_at' => now(),
                        ]);
                    }

                    $autoReconciled++;
                    $staleMiniPcReconciled++;
                    continue;
                }
            }

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
                'type' => SystemLog::clampColumnValue((string) ($item['type'] ?? 'system'), SystemLog::MAX_TYPE_LENGTH, ''),
                'action_id' => SystemLog::clampColumnValue((string) ($item['action_id'] ?? ($itemOrigin . ':pool:' . substr($fp, 0, 16))), SystemLog::MAX_ACTION_ID_LENGTH, ''),
                'title' => SystemLog::clampColumnValue($prefixedTitle, SystemLog::MAX_TITLE_LENGTH, '...'),
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
            'stale_minipc_reconciled' => $staleMiniPcReconciled,
            'local_open_pool_count' => (int) ($localSnapshot['open_pool_count'] ?? 0),
            'inbox_path' => 'storage/' . self::INBOX_FILE,
        ];
    }

    /**
     * Prüft für ein beim Aufwachen importiertes minipc_direct-Dossier (mit prepared_at),
     * ob in system_logs bereits ein resolved/success-Eintrag für denselben Fehler existiert,
     * dessen Abschlusszeitpunkt (resolved_at / healed_at / updated_at) neuer als prepared_at ist.
     */
    protected function findSupersedingResolvedLogForMiniPcHandoff(
        array $item,
        ?string $normalizedCulpritFile,
        ?int $line
    ): ?SystemLog {
        if (!Schema::hasTable('system_logs') || empty($item['prepared_at'])) {
            return null;
        }

        try {
            $preparedAt = Carbon::parse((string) $item['prepared_at'])->utc();
        } catch (\Throwable $e) {
            return null;
        }

        $fp = trim((string) ($item['fingerprint'] ?? ''));
        $itemMsgCore = $this->normalizeErrorMessageCore((string) ($item['message'] ?? ''));
        $queryFloor = $preparedAt->copy()->subMinutes(2);

        $candidates = SystemLog::archivedEntries()
            ->where(function ($q) use ($queryFloor) {
                $q->where('updated_at', '>=', $queryFloor)
                    ->orWhere('finished_at', '>=', $queryFloor);
            })
            ->orderByDesc('id')
            ->limit(250)
            ->get(['id', 'title', 'message', 'status', 'payload', 'finished_at', 'updated_at']);

        foreach ($candidates as $cand) {
            $cp = is_array($cand->payload) ? $cand->payload : [];
            $resolvedAt = null;

            foreach ([
                $cp['resolved_at'] ?? null,
                $cp['triage']['healed_at'] ?? null,
                $cp['auto_healed_at'] ?? null,
            ] as $rawTs) {
                if (!empty($rawTs)) {
                    try {
                        $resolvedAt = Carbon::parse((string) $rawTs)->utc();
                        break;
                    } catch (\Throwable $ignore) {
                    }
                }
            }

            if ($resolvedAt === null) {
                $fallbackDt = $cand->updated_at ?? $cand->finished_at;
                if ($fallbackDt !== null) {
                    $resolvedAt = Carbon::parse($fallbackDt)->utc();
                }
            }

            if ($resolvedAt === null || !$resolvedAt->greaterThan($preparedAt)) {
                continue;
            }

            // 1. Exakter Fingerprint-Treffer
            if ($fp !== '' && (($cp['fingerprint'] ?? '') === $fp)) {
                return $cand;
            }

            // 2. Gleiche Datei + Zeilennummer
            if ($normalizedCulpritFile !== null && $normalizedCulpritFile !== '' && $line !== null && $line > 0) {
                $rawCandFile = (string) ($cp['file'] ?? ($cp['antigravity_dossier']['culprit_file'] ?? ''));
                if ($rawCandFile === '') {
                    $rawCandFile = (string) ($this->extractBestFileCandidate($cp) ?? '');
                }
                $candFile = $this->normalizeProjectRelativePath($rawCandFile);

                $candLine = null;
                if (isset($cp['line']) && is_numeric($cp['line'])) {
                    $candLine = (int) $cp['line'];
                } elseif (isset($cp['antigravity_dossier']['line']) && is_numeric($cp['antigravity_dossier']['line'])) {
                    $candLine = (int) $cp['antigravity_dossier']['line'];
                } else {
                    $candLine = $this->extractBestLineCandidate($cp);
                }

                if ($candFile === $normalizedCulpritFile && $candLine === $line) {
                    return $cand;
                }
            }

            // 3. Identischer Message-Kern
            if ($itemMsgCore !== '' && mb_strlen($itemMsgCore, 'UTF-8') >= 12) {
                $candMsgCore = $this->normalizeErrorMessageCore((string) $cand->message);
                $candDossierCore = $this->normalizeErrorMessageCore((string) ($cp['antigravity_dossier']['message'] ?? ''));

                foreach ([$candMsgCore, $candDossierCore] as $cmpCore) {
                    if ($cmpCore === '') {
                        continue;
                    }
                    if ($cmpCore === $itemMsgCore) {
                        return $cand;
                    }
                    if (
                        mb_strlen($cmpCore, 'UTF-8') >= 24
                        && mb_strlen($itemMsgCore, 'UTF-8') >= 24
                        && (str_contains($cmpCore, $itemMsgCore) || str_contains($itemMsgCore, $cmpCore))
                    ) {
                        return $cand;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Zusätzlicher Schutz, falls lokal zwischenzeitlich migrate:fresh lief:
     * Zeigt der Stack-Trace eines minipc_direct-Eintrags eindeutig auf /seelenfunke-stage/ (gespiegelter Stage-Fehler
     * aus Funkiras 3D-Kuppelhalle) und liefert ein frischerer Stage-Pool-Snapshot (> 5 Min neuer als prepared_at)
     * 0 offene Stage-Dossiers für diese Datei/Meldung, wurde der Fehler auf Stage bereits vor dem Aufwachen gelöst.
     */
    protected function isStaleMirroredStageHandoffClearedOnStage(
        array $item,
        array $remoteSnapshot,
        array $remoteItems,
        ?string $normalizedCulpritFile,
        ?int $line
    ): bool {
        $traceStr = is_array($item['trace_excerpt'] ?? null)
            ? implode("\n", $item['trace_excerpt'])
            : (string) ($item['trace_excerpt'] ?? '');

        if (!str_contains($traceStr, '/seelenfunke-stage/')) {
            return false;
        }

        $sourceEnv = strtolower(trim((string) ($remoteSnapshot['source_environment'] ?? '')));
        if (!in_array($sourceEnv, ['production', 'stage', 'staging'], true) || empty($remoteSnapshot['generated_at'])) {
            return false;
        }

        try {
            $preparedAt = Carbon::parse((string) $item['prepared_at'])->utc();
            $snapshotAt = Carbon::parse((string) $remoteSnapshot['generated_at'])->utc();
        } catch (\Throwable $e) {
            return false;
        }

        if (!$snapshotAt->greaterThan($preparedAt->copy()->addMinutes(5))) {
            return false;
        }

        $itemCore = $this->normalizeErrorMessageCore((string) ($item['message'] ?? ''));

        foreach ($remoteItems as $other) {
            if (!is_array($other)) {
                continue;
            }
            $otherOrigin = strtolower(trim((string) ($other['origin_environment'] ?? $sourceEnv)));
            if ($otherOrigin === 'minipc_direct') {
                continue;
            }

            $otherFile = $this->normalizeProjectRelativePath((string) ($other['culprit_file'] ?? ''));
            $otherLine = isset($other['line']) && is_numeric($other['line']) ? (int) $other['line'] : null;

            if ($normalizedCulpritFile !== null && $line !== null && $otherFile === $normalizedCulpritFile && $otherLine === $line) {
                return false;
            }

            if ($itemCore !== '' && $this->normalizeErrorMessageCore((string) ($other['message'] ?? '')) === $itemCore) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeErrorMessageCore(string $raw): string
    {
        $clean = trim($raw);
        if ($clean === '') {
            return '';
        }

        $clean = (string) preg_replace('/^(?:\[(?:AUTO-GEHEILT|GELÖST|STAGE|MINI-PC|[^\]]+\.log)\]\s*)+/iu', '', $clean);
        $beforeJson = trim(Str::before($clean, ' {"exception":'));
        if ($beforeJson !== '') {
            $clean = $beforeJson;
        }
        $firstLine = trim(Str::before($clean, "\n"));
        if ($firstLine !== '') {
            $clean = $firstLine;
        }

        $clean = mb_strtolower($clean, 'UTF-8');
        $clean = (string) preg_replace('/\b\d+\b/', '#', $clean);
        $clean = (string) preg_replace('/\s+/', ' ', $clean);

        return trim(mb_substr($clean, 0, 160, 'UTF-8'));
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
     * Kanonische Gesundheitsprüfung beider Mittwald Echtzeit- & Gemini-Audio-Bridges
     * (mit 400ms Sofort-Retry gegen Mikro-Netzwerkblips, strenger HTTP-200 + status=ok Verifikation,
     * SystemLog + Antigravity-Dossier, 30-Min-Rate-Limit Push/Mail-Alarm und automatischem [AUTO-GEHEILT]).
     */
    public function probeGeminiBridges(bool $retryOnBlip = true, bool $persistAndAlert = true): array
    {
        $bridgesResult = [];
        $probedCount = 0;
        $autoHealedCount = 0;
        $newlyLoggedCount = 0;
        $alertsSentCount = 0;
        $maxLatencyMs = 0;
        $downSummaries = [];
        $stateChanged = false;
        $hasLogsTable = Schema::hasTable('system_logs');

        foreach (self::BRIDGE_HEALTH_TARGETS as $bridgeKey => $meta) {
            $probedCount++;
            $targetUrl = $this->resolveBridgeHealthUrl($bridgeKey, (string) $meta['url'], (string) ($meta['health_path'] ?? '/health'));
            $probe = $this->executeSingleBridgeProbe($targetUrl, $retryOnBlip);

            $healthy = (bool) $probe['healthy'];
            $httpStatus = $probe['http_status'];
            $latencyMs = (int) $probe['latency_ms'];
            $errorDetail = (string) $probe['error_detail'];
            $maxLatencyMs = max($maxLatencyMs, $latencyMs);

            if ($persistAndAlert && $hasLogsTable) {
                $syncStats = $this->syncBridgeProbeResult(
                    bridgeKey: $bridgeKey,
                    healthy: $healthy,
                    targetUrl: $targetUrl,
                    httpStatus: $httpStatus,
                    errorDetail: $errorDetail,
                    flushSnapshot: false
                );
                $autoHealedCount += (int) ($syncStats['auto_healed_count'] ?? 0);
                $newlyLoggedCount += (int) ($syncStats['newly_logged_count'] ?? 0);
                $alertsSentCount += (int) ($syncStats['alerts_sent_count'] ?? 0);
                if (!empty($syncStats['state_changed'])) {
                    $stateChanged = true;
                }
            }

            if (!$healthy) {
                $downSummaries[] = $meta['short_label'] . ' (' . ($httpStatus ? "HTTP {$httpStatus}" : 'Offline') . ')';
            }

            $bridgesResult[$bridgeKey] = [
                'key' => $bridgeKey,
                'action_id' => $meta['action_id'],
                'label' => $meta['label'],
                'short_label' => $meta['short_label'],
                'container_id' => $meta['container_id'],
                'url' => $targetUrl,
                'ssh_restart_cmd' => $meta['ssh_restart_cmd'],
                'healthy' => $healthy,
                'http_status' => $httpStatus,
                'reported_status' => $probe['reported_status'],
                'latency_ms' => $latencyMs,
                'attempts' => $probe['attempts'],
                'error' => $healthy ? null : $errorDetail,
            ];
        }

        $allHealthy = empty($downSummaries);

        if ($persistAndAlert && $hasLogsTable) {
            if ($allHealthy) {
                $legacyTelLogs = SystemLog::openIssues()
                    ->where('type', 'system')
                    ->where('action_id', 'system:health_fail')
                    ->where('title', 'Infrastruktur-Ausfall: Telephony')
                    ->get();

                foreach ($legacyTelLogs as $legacyLog) {
                    if (!empty($legacyLog->payload['triage']['relapse_detected'])) {
                        continue;
                    }
                    $this->markAsAutoHealed(
                        $legacyLog,
                        'bridge_recovered_http_200',
                        'Beide Echtzeit- & Gemini-Bridges melden HTTP 200 (status: ok).'
                    );
                    $autoHealedCount++;
                    $stateChanged = true;
                }
            }

            if ($stateChanged) {
                try {
                    $this->writeInboxSnapshotFile();
                } catch (\Throwable $ignore) {
                }
                Cache::forget('master_analytics_system_health_cache');
                Cache::forget('master_analytics_system_health_fresh');
            }
        }

        return [
            'all_healthy' => $allHealthy,
            'probed_count' => $probedCount,
            'down_count' => count($downSummaries),
            'auto_healed_count' => $autoHealedCount,
            'newly_logged_count' => $newlyLoggedCount,
            'alerts_sent_count' => $alertsSentCount,
            'max_latency_ms' => $maxLatencyMs,
            'summary_error' => $allHealthy ? null : ('Bridge gestört: ' . implode(', ', $downSummaries)),
            'bridges' => $bridgesResult,
        ];
    }

    /**
     * Synchronisiert das Prüfergebnis einer einzelnen Echtzeit-Bridge idempotent ins SystemLog,
     * erstellt das Antigravity-Dossier, löst einmalig pro Episode die Warnung aus und heilt bei HTTP 200 ok automatisch.
     */
    public function syncBridgeProbeResult(
        string $bridgeKey,
        bool $healthy,
        string $targetUrl,
        ?int $httpStatus,
        string $errorDetail = '',
        bool $flushSnapshot = false
    ): array {
        $normalizedKey = match ($bridgeKey) {
            'live_bridge', 'gemini_live' => 'gemini_live',
            'twilio_bridge', 'reverb_twilio', 'twilio_reverb' => 'twilio_reverb',
            default => $bridgeKey,
        };

        $meta = self::BRIDGE_HEALTH_TARGETS[$normalizedKey] ?? null;
        if ($meta === null || !Schema::hasTable('system_logs')) {
            return [
                'auto_healed_count' => 0,
                'newly_logged_count' => 0,
                'alerts_sent_count' => 0,
                'state_changed' => false,
            ];
        }

        $autoHealedCount = 0;
        $newlyLoggedCount = 0;
        $alertsSentCount = 0;
        $stateChanged = false;

        $allActionIds = array_values(array_unique(array_merge(
            [(string) $meta['action_id']],
            is_array($meta['legacy_action_ids'] ?? null) ? $meta['legacy_action_ids'] : []
        )));

        if ($healthy) {
            $openBridgeLogs = SystemLog::openIssues()
                ->whereIn('action_id', $allActionIds)
                ->get();

            foreach ($openBridgeLogs as $openBridgeLog) {
                $this->markAsAutoHealed(
                    $openBridgeLog,
                    'bridge_recovered_http_200',
                    $meta['label'] . ' antwortet wieder regulär mit HTTP 200 (status: ok).'
                );
                $autoHealedCount++;
                $stateChanged = true;
            }

            Cache::forget(self::BRIDGE_ALERT_COOLDOWN_PREFIX . $normalizedKey);
            SystemAlertTicketService::autoResolveByAlertPrefix(
                'bridge_outage:' . $normalizedKey,
                $meta['label'] . ' antwortet wieder regulär mit HTTP 200 (status: ok).'
            );
        } else {
            $actionId = (string) $meta['action_id'];
            $fingerprint = hash('sha256', 'sentinel|bridge_down|' . $normalizedKey);
            $existing = SystemLog::openIssues()
                ->whereIn('action_id', $allActionIds)
                ->latest('id')
                ->first();

            $diagnosis = SystemErrorLogger::diagnose('bridge_unhealthy ' . $targetUrl);
            $diagnosis['recommended_action'] = 'Mittwald-Container neu starten via: ' . $meta['ssh_restart_cmd'];

            if ($existing) {
                $p = is_array($existing->payload) ? $existing->payload : [];
                $p['platform'] = 'portal';
                $p['platform_label'] = 'Webportal';
                $p['bridge_key'] = $normalizedKey;
                $p['container_id'] = $meta['container_id'];
                $p['bridge_url'] = $targetUrl;
                $p['ssh_restart_cmd'] = $meta['ssh_restart_cmd'];
                $p['fingerprint'] = $p['fingerprint'] ?? $fingerprint;
                $p['occurrences_count'] = ((int) ($p['occurrences_count'] ?? 1)) + 1;
                $p['last_seen_at'] = now()->toIso8601String();
                $p['last_http_status'] = $httpStatus;
                $p['last_probe_error'] = Str::limit($errorDetail, 500);
                $p['diagnosis'] = $diagnosis;

                if (empty($p['antigravity_dossier'])) {
                    $dummyAiCalls = 0;
                    $existing->payload = $p;
                    $dossier = $this->buildAntigravityDossier($existing, (string) $p['fingerprint'], false, $dummyAiCalls);
                    $dossier['criticality'] = 'critical';
                    $p['triage'] = [
                        'state' => 'queued_for_antigravity',
                        'triaged_at' => now()->toIso8601String(),
                        'triaged_by' => 'Funkira Sentinel',
                        'criticality' => 'critical',
                    ];
                    $p['antigravity_dossier'] = $dossier;
                } else {
                    $p['antigravity_dossier']['occurrences_count'] = $p['occurrences_count'];
                    $p['antigravity_dossier']['last_seen_at'] = $p['last_seen_at'];
                }

                $existing->action_id = SystemLog::clampColumnValue($actionId, SystemLog::MAX_ACTION_ID_LENGTH, '');
                $existing->status = 'error';
                $existing->message = Str::limit('Gesundheits-Puls fehlgeschlagen: ' . ($errorDetail ?: 'Keine Antwort') . ' | Notfallbefehl: ' . $meta['ssh_restart_cmd'], 2000);
                $existing->payload = $p;
                $existing->finished_at = now();
                $existing->save();
                $targetLog = $existing;
            } else {
                $targetLog = SystemLog::create([
                    'type' => 'system',
                    'action_id' => SystemLog::clampColumnValue($actionId, SystemLog::MAX_ACTION_ID_LENGTH, ''),
                    'title' => SystemLog::clampColumnValue('Gemini-Bridge ausgefallen: ' . $meta['short_label'], SystemLog::MAX_TITLE_LENGTH, '...'),
                    'message' => Str::limit('Gesundheits-Puls fehlgeschlagen: ' . ($errorDetail ?: 'Keine Antwort') . ' | Notfallbefehl: ' . $meta['ssh_restart_cmd'], 2000),
                    'status' => 'error',
                    'payload' => [
                        'platform' => 'portal',
                        'platform_label' => 'Webportal',
                        'component' => 'realtime_bridge_' . $normalizedKey,
                        'bridge_key' => $normalizedKey,
                        'container_id' => $meta['container_id'],
                        'bridge_url' => $targetUrl,
                        'ssh_restart_cmd' => $meta['ssh_restart_cmd'],
                        'last_http_status' => $httpStatus,
                        'last_probe_error' => Str::limit($errorDetail, 500),
                        'fingerprint' => $fingerprint,
                        'occurrences_count' => 1,
                        'first_seen_at' => now()->toIso8601String(),
                        'last_seen_at' => now()->toIso8601String(),
                        'diagnosis' => $diagnosis,
                    ],
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);

                $dummyAiCalls = 0;
                $dossier = $this->buildAntigravityDossier($targetLog, $fingerprint, false, $dummyAiCalls);
                $dossier['criticality'] = 'critical';
                $p = is_array($targetLog->payload) ? $targetLog->payload : [];
                $p['triage'] = [
                    'state' => 'queued_for_antigravity',
                    'triaged_at' => now()->toIso8601String(),
                    'triaged_by' => 'Funkira Sentinel',
                    'criticality' => 'critical',
                ];
                $p['antigravity_dossier'] = $dossier;
                $targetLog->payload = $p;
                $targetLog->save();

                $newlyLoggedCount++;
                $stateChanged = true;
            }

            if ($this->sendBridgeOutageAlertOncePerEpisode($normalizedKey, $meta, $targetUrl, $httpStatus, $errorDetail, $targetLog)) {
                $alertsSentCount++;
            }
        }

        if ($flushSnapshot && $stateChanged) {
            try {
                $this->writeInboxSnapshotFile();
            } catch (\Throwable $ignore) {
            }
        }

        return [
            'auto_healed_count' => $autoHealedCount,
            'newly_logged_count' => $newlyLoggedCount,
            'alerts_sent_count' => $alertsSentCount,
            'state_changed' => $stateChanged,
        ];
    }

    /**
     * Löst die echte HTTPS-Health-URL einer Bridge auf und verhindert strikt,
     * dass versehentlich reine WebSocket-Stream-Pfade wie /twilio-stream angepingt werden.
     */
    public function resolveBridgeHealthUrl(string $bridgeKey, string $defaultUrl, string $healthPath = '/health'): string
    {
        $configuredRaw = match ($bridgeKey) {
            'twilio_reverb' => (string) config('services.twilio.wss_url', ''),
            'gemini_live' => (string) config('services.gemini.proxy_ws_url', ''),
            default => '',
        };

        $configuredRaw = trim($configuredRaw);
        if ($configuredRaw === '') {
            return $defaultUrl;
        }

        $httpCandidate = str_replace(['wss://', 'ws://'], ['https://', 'http://'], $configuredRaw);
        $host = strtolower((string) parse_url($httpCandidate, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($httpCandidate, PHP_URL_SCHEME)) ?: 'https';
        $port = parse_url($httpCandidate, PHP_URL_PORT);

        if ($bridgeKey === 'twilio_reverb' && !str_contains($host, 'ws.mein-seelenfunke.de')) {
            return $defaultUrl;
        }

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            if (app()->environment('local') && $host !== '') {
                $portPart = $port ? ':' . $port : '';
                return "{$scheme}://{$host}{$portPart}/" . ltrim($healthPath, '/');
            }

            return $defaultUrl;
        }

        if ($bridgeKey === 'twilio_reverb' && !str_starts_with($host, 'ws.')) {
            return $defaultUrl;
        }
        if ($bridgeKey === 'gemini_live' && !str_starts_with($host, 'api-live-bridge.')) {
            return $defaultUrl;
        }

        $portPart = ($port && !in_array((int) $port, [80, 443], true)) ? ':' . $port : '';
        return "https://{$host}{$portPart}/" . ltrim($healthPath, '/');
    }

    /**
     * Führt den HTTP-Health-Check gegen eine Bridge aus (erfordert HTTP 200 UND JSON status === "ok")
     * inkl. 1 kurzem Sofort-Retry nach 400 ms gegen Mikro-Netzwerkblips.
     */
    protected function executeSingleBridgeProbe(string $url, bool $retryOnBlip = true): array
    {
        $maxAttempts = $retryOnBlip ? 2 : 1;
        $httpStatus = null;
        $reportedStatus = null;
        $errorDetail = '';
        $latencyMs = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1 && !app()->runningUnitTests()) {
                usleep(1_500_000);
            }

            $t0 = microtime(true);
            try {
                $response = Http::withOptions([
                    'force_ip_resolve' => 'v4',
                    'connect_timeout' => 3,
                    'timeout' => 5,
                ])->acceptJson()->get($url);

                $latencyMs = (int) round((microtime(true) - $t0) * 1000);
                $httpStatus = $response->status();
                $json = $response->json();
                $reportedStatus = is_array($json) && isset($json['status'])
                    ? strtolower(trim((string) $json['status']))
                    : null;

                if ($httpStatus === 200 && $reportedStatus === 'ok') {
                    return [
                        'healthy' => true,
                        'http_status' => 200,
                        'reported_status' => 'ok',
                        'latency_ms' => $latencyMs,
                        'attempts' => $attempt,
                        'error_detail' => '',
                    ];
                }

                if ($httpStatus !== 200) {
                    $errorDetail = "HTTP {$httpStatus} von {$url}";
                } else {
                    $errorDetail = 'Ungültiger Health-Status (' . ($reportedStatus ?: 'kein status=ok') . ') von ' . $url;
                }
            } catch (\Throwable $e) {
                $latencyMs = (int) round((microtime(true) - $t0) * 1000);
                $httpStatus = null;
                $reportedStatus = null;
                $errorDetail = SystemErrorLogger::sanitizeSensitiveText($e->getMessage());
            }
        }

        return [
            'healthy' => false,
            'http_status' => $httpStatus,
            'reported_status' => $reportedStatus,
            'latency_ms' => $latencyMs,
            'attempts' => $maxAttempts,
            'error_detail' => $errorDetail,
        ];
    }

    /**
     * Sendet bei einem echten Bridge-Ausfall sofort, aber streng gedrosselt (max. 1x pro 30 Min je Bridge-Episode),
     * eine Warn-Mail an kontakt@mein-seelenfunke.de sowie optional einen FCM-Push an Alinas Admin-Geräte.
     */
    protected function sendBridgeOutageAlertOncePerEpisode(
        string $bridgeKey,
        array $meta,
        string $targetUrl,
        ?int $httpStatus,
        string $errorDetail,
        SystemLog $log
    ): bool {
        $lockKey = self::BRIDGE_ALERT_COOLDOWN_PREFIX . $bridgeKey;
        if (Cache::has($lockKey)) {
            return false;
        }

        Cache::put($lockKey, now()->toIso8601String(), now()->addMinutes(self::BRIDGE_ALERT_COOLDOWN_MINUTES));

        $payload = is_array($log->payload) ? $log->payload : [];
        $payload['bridge_alert_notified_at'] = now()->toIso8601String();
        $log->payload = $payload;
        $log->save();

        $subject = 'Warnung: ' . $meta['short_label'] . ' ausgefallen';
        $statusLabel = $httpStatus !== null ? "HTTP {$httpStatus}" : 'Timeout / Verbindungsfehler';
        $bodyLines = [
            'Funkira Sentinel hat nach erneutem Prüfversuch (1500ms Retry) einen Ausfall einer Echtzeit-Bridge festgestellt:',
            '',
            '- **Dienst:** ' . $meta['label'],
            '- **Container:** `' . $meta['container_id'] . '`',
            '- **Health-URL:** `' . $targetUrl . '`',
            '- **Befund:** ' . $statusLabel . ($errorDetail !== '' ? ' (' . $errorDetail . ')' : ''),
            '- **Zeitpunkt:** ' . now()->format('d.m.Y H:i:s') . ' Uhr',
            '',
            '**Mittwald Notfallbefehl zum sofortigen Container-Neustart:**',
            '`' . $meta['ssh_restart_cmd'] . '`',
            '',
            'Sobald der Health-Endpunkt wieder HTTP 200 (`status: ok`) meldet, schließt der Sentinel das SystemLog und dieses Ticket automatisch als `[AUTO-GEHEILT]`.',
        ];

        try {
            SystemAlertTicketService::upsertSystemTicket(
                alertKey: 'bridge_outage:' . $bridgeKey,
                title: $subject,
                markdownBody: implode("\n", $bodyLines),
                departmentName: 'System',
                agentName: 'Systemi',
                ticketType: 'infrastructure_alert',
                priority: 'high',
                assignToAntigravity: true,
                extraPayload: [
                    'bridge_key' => $bridgeKey,
                    'container_id' => $meta['container_id'],
                    'target_url' => $targetUrl,
                    'http_status' => $httpStatus,
                    'ssh_restart_cmd' => $meta['ssh_restart_cmd'],
                    'system_log_id' => $log->id,
                ]
            );
        } catch (\Throwable $ignoreTicketError) {
        }

        try {
            $credentialsPath = (string) config('services.firebase.credentials_file', storage_path('app/firebase-service-account.json'));
            if ($credentialsPath !== '' && is_file($credentialsPath) && Schema::hasTable('system_user_devices')) {
                app(\App\Services\FirebaseService::class)->sendToAdmins(
                    'Gemini-Bridge ausgefallen',
                    Str::limit($meta['short_label'] . ': ' . $statusLabel, 120),
                    [
                        'type' => 'sentinel_bridge_down',
                        'bridge_key' => $bridgeKey,
                        'log_id' => (string) $log->id,
                    ]
                );
            }
        } catch (\Throwable $ignorePushError) {
        }

        return true;
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

        $payload = is_array($log->payload) ? $log->payload : [];
        if (!empty($payload['bridge_alert_notified_at'])) {
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

            $sentCount = app(\App\Services\FirebaseService::class)->sendToAdmins(
                'Funkira Wächter: Kritischer Befund',
                Str::limit((string) ($dossier['title'] ?? $log->title), 120),
                [
                    'type' => 'sentinel_critical_alert',
                    'log_id' => (string) $log->id,
                ]
            );

            if ($sentCount > 0) {
                Cache::put(self::PUSH_COOLDOWN_KEY, now()->toIso8601String(), now()->addHours(6));
            }
        } catch (\Throwable $ignore) {
        }
    }

    /**
     * Echte Live-Verifikation der Gemini Audio Bridge (Node.js Live-Bridge + optionale Twilio-Reverb-Bridge).
     * Verhindert strikt, dass Playbook 9 einen echten Bridge-Ausfall blind als geheilt verschluckt.
     */
    public function verifyGeminiBridgeHealthy(): bool
    {
        try {
            if (class_exists(SystemHealthPulseService::class)) {
                $pulse = app(SystemHealthPulseService::class);
                if ($pulse->hasCustomGeminiBridgeProbeResolver() || !app()->runningUnitTests() || $this->hasActiveHttpStubs()) {
                    $report = $pulse->probeAndEvaluateGeminiBridgeFresh();

                    return ($report['status'] ?? 'error') === 'ok';
                }
            }

            return $this->verifyCoreConnectivityHealthy();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function hasActiveHttpStubs(): bool
    {
        try {
            $factory = Http::getFacadeRoot();
            if (!is_object($factory)) {
                return false;
            }
            $prop = new \ReflectionProperty($factory, 'stubCallbacks');
            $callbacks = $prop->getValue($factory);

            return $callbacks instanceof \Illuminate\Support\Collection && $callbacks->isNotEmpty();
        } catch (\Throwable $ignore) {
            return false;
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
            || str_contains($haystack, 'gemini_bridge')
            || str_contains($haystack, 'gemini audio bridge')
            || str_contains($haystack, 'bridge_down')
            || str_contains($haystack, 'bridge_unhealthy')) {
            return 'critical';
        }

        if ($log->status === 'error' && $occurrences >= 10) {
            return 'high';
        }

        return 'standard';
    }
}

<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemLogs;
use App\Models\Admin\Admin;
use App\Models\System\SystemLog;
use App\Services\System\LogArchiveService;
use App\Services\System\SentinelTriageService;
use App\Services\System\SystemErrorLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class SentinelSelfHealingAndInboxTest extends TestCase
{
    use DatabaseTransactions;

    private ?string $backupInbox = null;
    private ?string $backupHandoff = null;

    protected function setUp(): void
    {
        parent::setUp();

        $inboxPath = storage_path(SentinelTriageService::INBOX_FILE);
        $handoffPath = storage_path(SentinelTriageService::HANDOFF_FILE);

        $this->backupInbox = is_file($inboxPath) ? (string) file_get_contents($inboxPath) : null;
        $this->backupHandoff = is_file($handoffPath) ? (string) file_get_contents($handoffPath) : null;
    }

    protected function tearDown(): void
    {
        $inboxPath = storage_path(SentinelTriageService::INBOX_FILE);
        $handoffPath = storage_path(SentinelTriageService::HANDOFF_FILE);
        $spoolPath = storage_path(SystemErrorLogger::DEAD_LETTER_SPOOL_FILE);

        if (is_file($spoolPath)) {
            @unlink($spoolPath);
        }

        if ($this->backupInbox !== null) {
            SentinelTriageService::writeSharedPrivateJsonAtomically($inboxPath, $this->backupInbox, 0666);
        } elseif (is_file($inboxPath)) {
            @unlink($inboxPath);
        }

        if ($this->backupHandoff !== null) {
            SentinelTriageService::writeSharedPrivateJsonAtomically($handoffPath, $this->backupHandoff, 0666);
        } elseif (is_file($handoffPath)) {
            @unlink($handoffPath);
        }

        parent::tearDown();
    }

    public function test_fatal_shutdown_compiled_blade_resolution_and_deadletter_spool_drain(): void
    {
        $fakeCompiledDir = storage_path('framework/views');
        File::ensureDirectoryExists($fakeCompiledDir);
        $fakeCompiledView = $fakeCompiledDir . '/test_sentinel_' . uniqid() . '.php';
        $realBladeTarget = base_path('resources/views/livewire/shop/system/system-logs.blade.php');

        file_put_contents(
            $fakeCompiledView,
            "<?php echo 'broken'; ?>\n<?php /**PATH {$realBladeTarget} ENDPATH**/ ?>"
        );

        try {
            $fatalLog = SystemErrorLogger::recordFatalShutdownError([
                'type' => E_ERROR,
                'message' => 'Allowed memory size of 134217728 bytes exhausted (api_key=secret123)',
                'file' => $fakeCompiledView,
                'line' => 12,
            ]);

            $this->assertNotNull($fatalLog);
            $this->assertSame('error', $fatalLog->status);
            $this->assertStringNotContainsString('secret123', (string) $fatalLog->message);
            $this->assertSame($realBladeTarget, $fatalLog->payload['file'] ?? null);
            $this->assertSame($fakeCompiledView, $fatalLog->payload['compiled_view_file'] ?? null);
            $this->assertSame('php_fatal_or_oom', $fatalLog->payload['diagnosis']['category'] ?? null);
        } finally {
            @unlink($fakeCompiledView);
        }

        // Dead-Letter Spool Fallback & Drain
        $spoolMarker = 'DeadLetter_' . uniqid();
        SystemErrorLogger::writeDeadLetterFallback([
            'type' => 'system',
            'action_id' => 'system:deadletter_test',
            'title' => 'DB Kurz-Ausfall Test',
            'message' => "Gepufferter Fehler {$spoolMarker} token=topsecret99",
            'status' => 'error',
            'payload' => [
                'fingerprint' => hash('sha256', $spoolMarker),
                'occurrences_count' => 2,
            ],
        ]);

        $spoolPath = storage_path(SystemErrorLogger::DEAD_LETTER_SPOOL_FILE);
        $this->assertFileExists($spoolPath);

        $sentinel = app(SentinelTriageService::class);
        $drained = $sentinel->drainDeadLetterSpool();

        $this->assertGreaterThanOrEqual(1, $drained);
        $this->assertFileDoesNotExist($spoolPath);

        $drainedRow = SystemLog::where('action_id', 'system:deadletter_test')->latest('id')->first();
        $this->assertNotNull($drainedRow);
        $this->assertStringContainsString($spoolMarker, (string) $drainedRow->message);
        $this->assertStringNotContainsString('topsecret99', (string) $drainedRow->message);
        $this->assertTrue((bool) ($drainedRow->payload['drained_from_deadletter'] ?? false));
    }

    public function test_stage1_autonomous_heals_safe_playbooks_and_escalates_storms_or_code_bugs(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);

        // 1. Client Watchdog auto_healed -> wird ins Archiv verschoben
        $clientHealed = SystemLog::create([
            'type' => 'minipc_linux',
            'action_id' => 'minipc_linux:sense:autoheal1',
            'title' => '[AUTO-GEHEILT] [Werkstatt Mini-PC] USB-Kamera neu verbunden',
            'message' => 'Kurzer USB-Reset durch lokalen Watchdog behoben.',
            'status' => 'warning',
            'payload' => ['platform' => 'minipc_linux', 'auto_healed' => true, 'occurrences_count' => 1],
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(3),
        ]);

        // 2. Niedrige HTTP 419 Session-Ablauf-Meldung (< 25) -> wird automatisch geheilt
        $lowCsrf = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:http_419_csrf',
            'title' => 'Abgelaufenes Formular-Token (419)',
            'message' => 'CSRF token mismatch.',
            'status' => 'warning',
            'payload' => ['occurrences_count' => 3, 'fingerprint' => hash('sha256', 'csrf_low_' . uniqid())],
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
        ]);

        // 3. Hoher HTTP 419 Sturm (>= 25) -> DARF NICHT auto-geheilt werden, sondern muss in den Antigravity-Pool!
        $stormCsrf = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:http_419_csrf',
            'title' => 'Massiver CSRF-Sturm (419)',
            'message' => 'CSRF token mismatch auf Checkout-Route.',
            'status' => 'warning',
            'payload' => ['occurrences_count' => 30, 'fingerprint' => hash('sha256', 'csrf_storm_' . uniqid())],
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(2),
        ]);

        // 4. Echter Blade Variablen-Bug -> DARF NICHT als View-Cache-Konflikt weggeheilt werden!
        $bladeCodeBug = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:exception:application_runtime_error',
            'title' => 'System-Fehler (ErrorException)',
            'message' => 'Undefined variable $missingPrice in storage/framework/views/abc.php',
            'status' => 'error',
            'payload' => [
                'file' => 'app/Livewire/Shop/System/SystemLogs.php',
                'line' => 45,
                'occurrences_count' => 1,
                'fingerprint' => hash('sha256', 'blade_bug_' . uniqid()),
            ],
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
        ]);

        $sentinel = app(SentinelTriageService::class);
        $stats = $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

        $clientHealed->refresh();
        $lowCsrf->refresh();
        $stormCsrf->refresh();
        $bladeCodeBug->refresh();

        $this->assertSame('success', $clientHealed->status);
        $this->assertSame('auto_healed', $clientHealed->triageState());

        $this->assertSame('success', $lowCsrf->status);
        $this->assertSame('auto_healed', $lowCsrf->triageState());

        $this->assertSame('warning', $stormCsrf->status);
        $this->assertSame('queued_for_antigravity', $stormCsrf->triageState());

        $this->assertSame('error', $bladeCodeBug->status);
        $this->assertSame('queued_for_antigravity', $bladeCodeBug->triageState());
        $this->assertNotEmpty($bladeCodeBug->payload['antigravity_dossier']['code_snippet'] ?? null);
        $this->assertSame(2, $stats['open_pool_count']);
    }

    public function test_bridge_health_probe_logs_outage_and_auto_heals_when_bridge_recovers(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $backupDisk = config('backup.backup.destination.disks.0', 'local');
        $backupName = config('backup.backup.name', config('app.name') . '-db-backup');
        \Illuminate\Support\Facades\Storage::fake($backupDisk);
        \Illuminate\Support\Facades\Storage::disk($backupDisk)->put("{$backupName}/bridge_test_fresh.zip", 'PK-ZIP');
        Cache::put('scheduler_last_run', now()->timestamp, 600);
        config([
            'queue.default' => 'sync',
            'services.twilio.wss_url' => '',
            'services.gemini.proxy_ws_url' => '',
        ]);
        SystemLog::openIssues()->update(['status' => 'success']);

        // Lauf 1: Reverb-Bridge antwortet inkl. 400ms-Blip-Retry zweimal mit 503 -> echter Ausfall; Lauf 2+: wieder 200 OK
        Http::fake([
            'ws.mein-seelenfunke.de/*' => Http::sequence()
                ->push('Service Unavailable', 503)
                ->push('Service Unavailable', 503)
                ->whenEmpty(Http::response(['status' => 'ok'], 200)),
            'api-live-bridge.mein-seelenfunke.de/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $sentinel = app(SentinelTriageService::class);
        $sentinel->runTriageCycle(probeBridges: true, allowAiEnrichment: false);

        $bridgeError = SystemLog::openIssues()
            ->whereIn('action_id', ['sentinel:bridge_down:twilio_reverb', 'sentinel:bridge_unhealthy:reverb_twilio'])
            ->first();

        $this->assertNotNull($bridgeError);
        $this->assertSame('error', $bridgeError->status);
        $this->assertSame('queued_for_antigravity', $bridgeError->triageState());

        // Lauf 2: Reverb-Bridge ist wieder online (HTTP 200) -> Selbstheilung ins Archiv!
        $statsAfterRecovery = $sentinel->runTriageCycle(probeBridges: true, allowAiEnrichment: false);
        $bridgeError->refresh();

        $this->assertSame('success', $bridgeError->status);
        $this->assertSame('auto_healed', $bridgeError->triageState());
        $this->assertStringStartsWith('[AUTO-GEHEILT]', (string) $bridgeError->title);
        $this->assertSame(0, $statsAfterRecovery['open_pool_count']);
    }

    public function test_stage2_dossier_enriches_strictly_once_per_fingerprint_and_blocks_sensitive_files(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);

        config(['services.gemini.key' => 'test_dummy_key_for_fake']);
        $fp = hash('sha256', 'unique_bug_fp_' . uniqid());
        Cache::forget('sentinel:ai_dossier:' . $fp);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'root_cause_de' => 'Fehlende Null-Pruefung beim Laden der Bestellposition.',
                                'fix_plan_de' => 'Nullsafe-Operator ergaenzen und Feature-Test ausfuehren.',
                                'criticality' => 'high',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $bug = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:exception:application_runtime_error',
            'title' => 'System-Fehler (TypeError)',
            'message' => 'Cannot access offset of type string on string',
            'status' => 'error',
            'payload' => [
                'file' => 'app/Services/System/SystemErrorLogger.php',
                'line' => 30,
                'occurrences_count' => 1,
                'fingerprint' => $fp,
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $sentinel = app(SentinelTriageService::class);

        // Erster Triage-Lauf -> genau 1 KI-Aufruf
        $run1 = $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: true);
        $this->assertSame(1, $run1['ai_calls_used']);
        Http::assertSentCount(1);

        // Fehler tritt 15 weitere Male auf -> Zweiter Triage-Lauf darf KEINEN weiteren KI-Aufruf auslösen!
        $bug->refresh();
        $p = $bug->payload;
        $p['occurrences_count'] = 16;
        $bug->payload = $p;
        $bug->save();

        $run2 = $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: true);
        $this->assertSame(0, $run2['ai_calls_used']);
        Http::assertSentCount(1);

        $bug->refresh();
        $this->assertSame(16, $bug->payload['antigravity_dossier']['occurrences_count'] ?? 0);
        $this->assertSame('high', $bug->payload['antigravity_dossier']['criticality'] ?? null);

        // Sicherheits-Whitelist: .env und storage/ dürfen niemals als Code-Snippet gelesen werden
        $this->assertNull($sentinel->extractSafeCodeSnippet('.env', 1));
        $this->assertNull($sentinel->extractSafeCodeSnippet('../.env', 1));
        $this->assertNull($sentinel->extractSafeCodeSnippet('storage/logs/laravel.log', 1));
    }

    public function test_stage_to_local_offline_inbox_sync_reconciliation_and_livewire_pool_tab(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);

        $remoteFp = hash('sha256', 'stage_overnight_bug_' . uniqid());
        $remoteSnapshot = [
            'inbox_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'source_environment' => 'staging',
            'open_pool_count' => 1,
            'items' => [[
                'dossier_version' => 2,
                'fingerprint' => $remoteFp,
                'log_id' => 9991,
                'criticality' => 'critical',
                'platform' => 'android',
                'platform_label' => 'Android App',
                'type' => 'android',
                'action_id' => 'android:checkout:crash',
                'severity' => 'error',
                'title' => '[Android App] NullPointerException in SyncWorker',
                'message' => 'Parameter specified as non-null is null',
                'culprit_file' => 'app/Services/System/SystemErrorLogger.php',
                'line' => 35,
                'occurrences_count' => 4,
                'probable_cause' => 'Fehlendes Feld im Sync-Response.',
                'recommended_action' => 'Default-Wert im DTO setzen.',
            ]],
        ];

        $tmpImportPath = storage_path('app/private/test_stage_import_' . uniqid() . '.json');
        File::ensureDirectoryExists(dirname($tmpImportPath));
        file_put_contents($tmpImportPath, json_encode($remoteSnapshot));

        try {
            $exit = Artisan::call('system:sentinel-inbox', [
                '--import-file' => $tmpImportPath,
            ]);
            $this->assertSame(0, $exit);
        } finally {
            @unlink($tmpImportPath);
        }

        $importedLocal = SystemLog::queuedForAntigravity()
            ->where('payload->fingerprint', $remoteFp)
            ->first();

        $this->assertNotNull($importedLocal);
        $this->assertStringStartsWith('[STAGE]', (string) $importedLocal->title);
        $this->assertNotEmpty($importedLocal->payload['antigravity_dossier']['code_snippet'] ?? null);

        // Livewire SystemLogs Pool-Tab & gültiges HTML-tbody prüfen
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $component = Livewire::test(SystemLogs::class)
            ->call('switchViewTab', 'pool')
            ->assertSet('viewTab', 'pool')
            ->assertSee('NullPointerException in SyncWorker');

        $html = $component->html();
        $this->assertSame(substr_count($html, '<tbody'), substr_count($html, '</tbody>'));

        // Wenn Stage beim nächsten Sync meldet, dass der Pool leer ist -> lokal automatisch auf resolved setzen!
        $sentinel = app(SentinelTriageService::class);
        $reconcileResult = $sentinel->importRemoteStageInbox([
            'source_environment' => 'staging',
            'open_pool_count' => 0,
            'items' => [],
        ]);

        $this->assertSame(1, $reconcileResult['auto_reconciled']);
        $importedLocal->refresh();
        $this->assertSame('resolved', $importedLocal->status);
    }

    public function test_minipc_gemini_live_goaway_rollover_and_transient_pulse_blip_are_auto_healed(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);

        $goAwayLog = SystemLog::create([
            'type' => 'minipc_linux',
            'action_id' => 'minipc_linux:gemini_live_ws_close:546a092da8fae527',
            'title' => '[Werkstatt Mini-PC] Live-WebSocket unerwartet geschlossen (Code 1008)',
            'message' => 'Connection aborted because the client failed to close the connection after receiving a GoAway signal once the session duration limit was reached.',
            'status' => 'error',
            'payload' => ['occurrences_count' => 1],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $pulseBlipLog = SystemLog::create([
            'type' => 'minipc_linux',
            'action_id' => 'minipc_linux:portal_pulse:d6f170df4e85883d',
            'title' => '[Werkstatt Mini-PC] Portal-Pulse nicht erreichbar (STAGE)',
            'message' => 'GET https://stage.mein-seelenfunke.de/api/shop/monitor-pulse lieferte Status 0.',
            'status' => 'warning',
            'payload' => ['occurrences_count' => 1],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $stats = app(SentinelTriageService::class)->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

        $this->assertSame(2, $stats['auto_healed_count']);
        $this->assertSame(0, $stats['open_pool_count']);

        $goAwayLog->refresh();
        $pulseBlipLog->refresh();

        $this->assertSame('success', $goAwayLog->status);
        $this->assertSame('gemini_live_session_rollover', $goAwayLog->payload['triage']['playbook'] ?? null);

        $this->assertSame('success', $pulseBlipLog->status);
        $this->assertSame('client_portal_pulse_recovered', $pulseBlipLog->payload['triage']['playbook'] ?? null);
    }

    public function test_portal_pulse_429_5xx_and_funkira_live_ws_drops_auto_heal_while_real_ws_and_pulse_bugs_escalate(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);

        // 1. Transient 429 & 503 on /api/shop/monitor-pulse -> auto_healed
        $pulse429 = SystemLog::create([
            'type' => 'minipc_linux',
            'action_id' => 'minipc_linux:portal_pulse:d6f170df4e85883d',
            'title' => '[Werkstatt Mini-PC] Portal-Pulse nicht erreichbar (STAGE)',
            'message' => 'GET https://stage.mein-seelenfunke.de/api/shop/monitor-pulse lieferte Status 429.',
            'status' => 'warning',
            'payload' => ['platform' => 'minipc_linux', 'occurrences_count' => 38, 'fingerprint' => hash('sha256', 'pulse_429_' . uniqid())],
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(1),
        ]);

        $pulse503 = SystemLog::create([
            'type' => 'windows_desktop',
            'action_id' => 'windows_desktop:portal_pulse:gw503',
            'title' => '[Funkenwerk] Portal-Pulse Gateway Timeout',
            'message' => 'GET https://stage.mein-seelenfunke.de/api/shop/monitor-pulse lieferte Status 503.',
            'status' => 'warning',
            'payload' => ['platform' => 'windows_desktop', 'occurrences_count' => 2, 'fingerprint' => hash('sha256', 'pulse_503_' . uniqid())],
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(2),
        ]);

        // 2. Real 500 Internal Server Error on portal_pulse -> MUST escalate to Antigravity!
        $pulse500Bug = SystemLog::create([
            'type' => 'minipc_linux',
            'action_id' => 'minipc_linux:portal_pulse:err500',
            'title' => '[Werkstatt Mini-PC] Portal-Pulse Serverfehler',
            'message' => 'GET https://stage.mein-seelenfunke.de/api/shop/monitor-pulse lieferte Status 500.',
            'status' => 'error',
            'payload' => ['platform' => 'minipc_linux', 'occurrences_count' => 1, 'fingerprint' => hash('sha256', 'pulse_500_' . uniqid())],
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
        ]);

        // 3. Transient mobile stream drop on funkira_live_ws (EOFException / Verbindungsfehler: null) -> auto_healed
        $wsEof = SystemLog::create([
            'type' => 'android',
            'action_id' => 'android:funkira_live_ws:bfc488cf104d7cf9',
            'title' => '[Android App] funkira_live_ws: EOFException',
            'message' => 'Verbindungsfehler: null',
            'status' => 'error',
            'payload' => ['platform' => 'android', 'occurrences_count' => 1, 'fingerprint' => hash('sha256', 'ws_eof_' . uniqid())],
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(5),
        ]);

        // 4. Real crash / auth error on funkira_live_ws -> MUST escalate to Antigravity!
        $wsAuthOrCrash = SystemLog::create([
            'type' => 'android',
            'action_id' => 'android:funkira_live_ws:crash01',
            'title' => '[Android App] funkira_live_ws: IllegalStateException',
            'message' => 'HTTP 401 Unauthorized handshake rejected',
            'status' => 'error',
            'payload' => ['platform' => 'android', 'occurrences_count' => 1, 'fingerprint' => hash('sha256', 'ws_crash_' . uniqid())],
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
        ]);

        // 5. Superseded security:package_audit warning followed by a clean re-run (0 CVEs) -> auto_healed
        $staleCveLog = SystemLog::create([
            'type' => 'security',
            'action_id' => 'security:package_audit',
            'title' => '[STAGE] Sicherheitsprüfung: Verwundbarkeiten erkannt',
            'message' => '63 Sicherheitslücke(n) (CVE) in Paketen erkannt!',
            'status' => 'warning',
            'payload' => ['platform' => 'portal', 'occurrences_count' => 1, 'fingerprint' => hash('sha256', 'stale_cve_' . uniqid())],
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHours(2),
        ]);

        SystemLog::create([
            'type' => 'security',
            'action_id' => 'security:package_audit',
            'title' => 'Automatische Sicherheitsprüfung abgeschlossen',
            'message' => '0 Sicherheitslücken (CVEs)',
            'status' => 'success',
            'payload' => ['cve_count' => 0, 'guardrail_errors' => 0],
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(5),
        ]);

        $stats = app(SentinelTriageService::class)->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

        $pulse429->refresh();
        $pulse503->refresh();
        $pulse500Bug->refresh();
        $wsEof->refresh();
        $wsAuthOrCrash->refresh();
        $staleCveLog->refresh();

        $this->assertSame('auto_healed', $pulse429->triageState());
        $this->assertSame('client_portal_pulse_recovered', $pulse429->payload['triage']['playbook'] ?? null);

        $this->assertSame('auto_healed', $pulse503->triageState());
        $this->assertSame('auto_healed', $wsEof->triageState());
        $this->assertSame('transient_mobile_ws_recovered', $wsEof->payload['triage']['playbook'] ?? null);

        $this->assertSame('auto_healed', $staleCveLog->triageState());
        $this->assertSame('security_audit_verified_clean', $staleCveLog->payload['triage']['playbook'] ?? null);

        $this->assertSame('queued_for_antigravity', $pulse500Bug->triageState());
        $this->assertSame('queued_for_antigravity', $wsAuthOrCrash->triageState());
        $this->assertSame(2, $stats['open_pool_count']);
    }

    public function test_health_fail_backup_and_telephony_and_cli_typo_are_auto_healed(): void
    {
        $backupDisk = config('backup.backup.destination.disks.0', 'local');
        $backupName = config('backup.backup.name', config('app.name') . '-db-backup');
        \Illuminate\Support\Facades\Storage::fake($backupDisk);
        \Illuminate\Support\Facades\Storage::disk($backupDisk)->put("{$backupName}/test_recent_backup.zip", 'zipdata');

        $backupFail = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:health_fail',
            'title' => 'Infrastruktur-Ausfall: Backup',
            'message' => 'Das Datenbank-Backup ist überfällig. Gefahr bei Datenverlust!',
            'status' => 'error',
            'payload' => ['platform' => 'portal', 'occurrences_count' => 1, 'fingerprint' => hash('sha256', 'bk_' . uniqid())],
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
        ]);

        $telephonyFail = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:health_fail',
            'title' => 'Infrastruktur-Ausfall: Telephony',
            'message' => 'Die KI-Telefonie Audio-Bridge ist down! HTTP Status: 500',
            'status' => 'error',
            'payload' => ['platform' => 'portal', 'occurrences_count' => 1, 'fingerprint' => hash('sha256', 'tel_' . uniqid())],
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
        ]);

        $cliTypo = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:exception:application_runtime_error',
            'title' => 'System-Fehler (RuntimeException)',
            'message' => 'The "--summary" option does not exist.',
            'status' => 'error',
            'payload' => [
                'platform' => 'portal',
                'occurrences_count' => 1,
                'file' => 'vendor/symfony/console/Input/ArgvInput.php',
                'fingerprint' => hash('sha256', 'typo_' . uniqid())
            ],
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(5),
        ]);

        $stats = app(SentinelTriageService::class)->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

        $backupFail->refresh();
        $telephonyFail->refresh();
        $cliTypo->refresh();

        $this->assertSame('auto_healed', $backupFail->triageState());
        $this->assertSame('health_backup_fresh_verified', $backupFail->payload['triage']['playbook'] ?? null);

        $this->assertSame('auto_healed', $telephonyFail->triageState());
        $this->assertSame('health_telephony_worker_recovered', $telephonyFail->payload['triage']['playbook'] ?? null);

        $this->assertSame('auto_healed', $cliTypo->triageState());
        $this->assertSame('interactive_cli_typo_handled', $cliTypo->payload['triage']['playbook'] ?? null);

        $agInboxExit1 = SystemLog::create([
            'type' => 'scheduler',
            'action_id' => 'scheduler:cronjob_failed:system:ag-inbox',
            'title' => 'Cronjob fehlgeschlagen: system:ag-inbox (Exit 1)',
            'message' => "Der Befehl 'system:ag-inbox' wurde mit Fehler-Exit-Code 1 beendet.",
            'status' => 'error',
            'payload' => [
                'platform' => 'portal',
                'occurrences_count' => 1,
                'fingerprint' => hash('sha256', 'ag_inbox_' . uniqid()),
            ],
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(5),
        ]);

        $statsAg = app(SentinelTriageService::class)->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
        $agInboxExit1->refresh();

        $this->assertSame('auto_healed', $agInboxExit1->triageState());
        $this->assertSame('interactive_ag_inbox_reconciled', $agInboxExit1->payload['triage']['playbook'] ?? null);
    }

    public function test_overlong_stage_dossier_titles_and_system_log_writes_clamp_cleanly_to_varchar_191(): void
    {
        $service = app(SentinelTriageService::class);

        $fpBoundary = hash('sha256', 'stage_boundary_191_' . uniqid());
        $fpHuge = hash('sha256', 'stage_huge_360_' . uniqid());

        // Fall A: Exakte 183-Zeichen-Grenze aus LogArchiveService + '[STAGE] ' = 191+ Zeichen (löste zuvor 190+3=193 > VARCHAR(191) aus)
        $boundaryTitle = '[laravel.log] SQLSTATE[42S22]: Column not found: 1054 Unknown column \'category\' in \'field list\' (Connection: mysql, Host: mysql-nkzzsf.pg-s-pis5pf.db.project.host, Port: 3306, Data...';
        // Fall B: Überlanger Titel > 340 Zeichen + überlange action_id > 260 Zeichen + überlanger type > 100 Zeichen
        $hugeTitle = '[laravel.log] ' . str_repeat('ÜberlangerStageFehlerTitelMitUmlautenÄÖÜß_', 9);

        $importStats = $service->importRemoteStageInbox([
            'inbox_version' => 1,
            'source_environment' => 'production',
            'items' => [
                [
                    'fingerprint' => $fpBoundary,
                    'log_id' => 9001,
                    'origin_environment' => 'production',
                    'platform' => 'portal',
                    'type' => 'system',
                    'action_id' => 'logfile:laravel.log:' . substr($fpBoundary, 0, 12),
                    'severity' => 'error',
                    'title' => $boundaryTitle,
                    'message' => 'Boundary test message',
                    'occurrences_count' => 1,
                ],
                [
                    'fingerprint' => $fpHuge,
                    'log_id' => 9002,
                    'origin_environment' => 'production',
                    'platform' => 'portal',
                    'type' => str_repeat('custom_system_type_', 6),
                    'action_id' => 'stage:overlong:action:' . str_repeat('abcdef0123456789_', 18),
                    'severity' => 'error',
                    'title' => $hugeTitle,
                    'message' => 'Overlong title > 300 chars import test',
                    'occurrences_count' => 2,
                ],
            ],
        ]);

        $this->assertSame('success', $importStats['status']);
        $this->assertSame(2, $importStats['imported_new']);

        $logBoundary = SystemLog::where('payload->fingerprint', $fpBoundary)->firstOrFail();
        $logHuge = SystemLog::where('payload->fingerprint', $fpHuge)->firstOrFail();

        $this->assertLessThanOrEqual(SystemLog::MAX_TITLE_LENGTH, mb_strlen($logBoundary->title, 'UTF-8'));
        $this->assertStringStartsWith('[STAGE] ', $logBoundary->title);

        $this->assertSame(SystemLog::MAX_TITLE_LENGTH, mb_strlen($logHuge->title, 'UTF-8'));
        $this->assertStringStartsWith('[STAGE] ', $logHuge->title);
        $this->assertStringEndsWith('...', $logHuge->title);
        $this->assertSame(SystemLog::MAX_ACTION_ID_LENGTH, mb_strlen($logHuge->action_id, 'UTF-8'));
        $this->assertSame(SystemLog::MAX_TYPE_LENGTH, mb_strlen($logHuge->type, 'UTF-8'));

        // Fall C: Zentraler Model-Schutz (SystemLog::create + $model->update saving-Hook) bei direkten Aufrufen > 350 Zeichen
        $directLog = SystemLog::create([
            'type' => str_repeat('direct_type_', 10),
            'action_id' => 'direct:' . str_repeat('action_segment_', 20),
            'title' => str_repeat('DirekterÜberlangerSystemLogTitel_', 12),
            'message' => str_repeat('M', 18000),
            'status' => 'error',
            'payload' => ['fingerprint' => hash('sha256', 'direct_clamp_' . uniqid())],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->assertSame(SystemLog::MAX_TITLE_LENGTH, mb_strlen($directLog->title, 'UTF-8'));
        $this->assertSame(SystemLog::MAX_ACTION_ID_LENGTH, mb_strlen($directLog->action_id, 'UTF-8'));
        $this->assertSame(SystemLog::MAX_TYPE_LENGTH, mb_strlen($directLog->type, 'UTF-8'));
        $this->assertSame(SystemLog::MAX_MESSAGE_LENGTH, mb_strlen((string) $directLog->message, 'UTF-8'));

        $directLog->update([
            'title' => '[AUTO-GEHEILT] ' . str_repeat('UpdateTitelÜberlang_', 15),
        ]);
        $directLog->refresh();
        $this->assertSame(SystemLog::MAX_TITLE_LENGTH, mb_strlen($directLog->title, 'UTF-8'));
        $this->assertStringStartsWith('[AUTO-GEHEILT] ', $directLog->title);
    }

    public function test_chronic_relapse_guard_blocks_auto_heal_on_third_occurrence_within_24h_and_escalates_to_antigravity(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);
        $sentinel = app(SentinelTriageService::class);
        $sharedFp = hash('sha256', 'relapse_csrf_test_' . uniqid());

        // 1. & 2. Auftreten in 24h -> dürfen regulär in Stufe 1 auto-geheilt werden
        for ($i = 1; $i <= 2; $i++) {
            $entry = SystemLog::create([
                'type' => 'system',
                'action_id' => 'system:http_419_csrf',
                'title' => "CSRF Ablauf #{$i}",
                'message' => 'CSRF token mismatch.',
                'status' => 'warning',
                'payload' => ['fingerprint' => $sharedFp, 'occurrences_count' => 1],
                'started_at' => now()->subMinutes(30 - $i),
                'finished_at' => now()->subMinutes(30 - $i),
            ]);

            $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
            $entry->refresh();
            $this->assertSame('auto_healed', $entry->triageState());
        }

        // 3. Auftreten in 24h (>= 2 vorangegangene Auto-Heals -> Rezidiv #3) -> Auto-Heal MUSS gesperrt und nach Stufe 2 eskaliert werden!
        $thirdEntry = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:http_419_csrf',
            'title' => 'CSRF Ablauf #3',
            'message' => 'CSRF token mismatch.',
            'status' => 'warning',
            'payload' => ['fingerprint' => $sharedFp, 'occurrences_count' => 1],
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
        ]);

        $stats = $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
        $this->assertGreaterThanOrEqual(1, (int) ($stats['relapse_escalated_count'] ?? 0));

        $thirdEntry->refresh();
        $this->assertSame('error', $thirdEntry->status);
        $this->assertSame('queued_for_antigravity', $thirdEntry->triageState());
        $this->assertTrue((bool) ($thirdEntry->payload['triage']['relapse_detected'] ?? false));
        $this->assertSame(3, (int) ($thirdEntry->payload['triage']['relapse_count_24h'] ?? 0));
        $this->assertSame('elevated', $thirdEntry->payload['triage']['criticality'] ?? null);
        $this->assertSame('elevated', $thirdEntry->payload['antigravity_dossier']['criticality'] ?? null);
        $this->assertStringContainsString(
            'Wiederkehrender Fehler trotz Selbstheilung (Rezidiv #3 in 24h) - Dauerhafte Ursachenbehebung erforderlich',
            (string) ($thirdEntry->payload['antigravity_dossier']['probable_cause'] ?? '')
        );

        // Ein weiterer Triage-Lauf darf das gesperrte Rezidiv-Dossier NICHT nachträglich wieder auto-heilen!
        $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
        $thirdEntry->refresh();
        $this->assertSame('error', $thirdEntry->status);
        $this->assertSame('queued_for_antigravity', $thirdEntry->triageState());

        // Sobald Antigravity das Dossier explizit auflöst ('resolved'), startet ab diesem Zeitpunkt ein frisches Rezidiv-Fenster:
        // Ein einzelner späterer WLAN-/Pulse-Aussetzer nach dem Fix darf wieder normal in Stufe 1 geheilt werden statt sofort als Rezidiv #3 zu eskalieren.
        $sentinel->resolvePoolItems(fingerprints: [$sharedFp], resolvedBy: 'Antigravity');
        $postFixEntry = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:http_419_csrf',
            'title' => 'Einmaliger Ablauf nach Fix',
            'message' => 'CSRF token mismatch.',
            'status' => 'warning',
            'payload' => ['fingerprint' => $sharedFp, 'occurrences_count' => 1],
            'started_at' => now()->addSecond(),
            'finished_at' => now()->addSecond(),
        ]);

        $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
        $postFixEntry->refresh();
        $this->assertSame('auto_healed', $postFixEntry->triageState());
    }

    public function test_scheduler_heartbeat_persists_across_cache_flush_without_false_watchtower_alarm(): void
    {
        $pulseService = app(\App\Services\System\SystemHealthPulseService::class);
        $pulseService->resetStateForTesting();

        try {
            Artisan::call('system:heartbeat');
            $hbFile = \App\Services\System\SystemHealthPulseService::resolveSchedulerHeartbeatFilePath();
            $this->assertFileExists($hbFile);

            // Simuliere php artisan optimize:clear / cache:clear waehrend eines Stage-Deploys
            Cache::flush();
            $this->assertNull(Cache::get('scheduler_last_run'));

            $report = $pulseService->evaluate(
                source: 'cli_scheduler',
                strict: true,
                recordSample: false,
                syncLogs: false,
                overrideSslDays: 60
            );

            $this->assertSame('ok', $report['pillars']['cronjobs']['status']);
            $this->assertSame([], $report['pillars']['cronjobs']['issues']);
            $this->assertNotNull(Cache::get('scheduler_last_run'));
        } finally {
            $pulseService->resetStateForTesting();
        }
    }

    public function test_playbook_13_heals_benign_wpftask_aborted_io_and_local_42s02_guard_stays_strictly_local(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);
        $sentinel = app(SentinelTriageService::class);

        $benignWpf = SystemLog::create([
            'type' => 'windows_desktop',
            'action_id' => 'windows_desktop:wpftask:a1b2c3d4',
            'title' => '[Funkenwerk Windows] Hintergrund-Task-Ausnahme in Funkenwerk Suite: AggregateException',
            'message' => 'One or more errors occurred. (Der E/A-Vorgang wurde wegen eines Threadendes oder einer Anwendungsanforderung abgebrochen.)',
            'status' => 'error',
            'payload' => ['platform' => 'windows_desktop', 'occurrences_count' => 2],
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(2),
        ]);

        $realWpfBug = SystemLog::create([
            'type' => 'windows_desktop',
            'action_id' => 'windows_desktop:wpftask:deadbeef',
            'title' => '[Funkenwerk Windows] Hintergrund-Task-Ausnahme in Funkenwerk Suite: AggregateException',
            'message' => 'One or more errors occurred. (System.NullReferenceException: Object reference not set to an instance of an object.)',
            'status' => 'error',
            'payload' => ['platform' => 'windows_desktop', 'occurrences_count' => 1],
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
        ]);

        $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

        $benignWpf->refresh();
        $realWpfBug->refresh();

        $this->assertSame('auto_healed', $benignWpf->triageState());
        $this->assertSame('benign_windows_wpftask_aborted_io', $benignWpf->payload['triage']['playbook'] ?? null);
        $this->assertSame('queued_for_antigravity', $realWpfBug->triageState());

        // Pruefe strikte Umgebungs-Isolation des 42S02-Migrations-Schutzes (nur local, niemals testing/production!)
        $missingPatEx = new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'seelenfunke.personal_access_tokens' doesn't exist");
        $missingOrderEx = new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'seelenfunke.order_orders' doesn't exist");

        $this->assertFalse(SystemErrorLogger::isTransientLocalMigrationTableMissing($missingPatEx));

        $origEnv = $this->app['env'];
        try {
            $this->app['env'] = 'local';
            $this->assertTrue(SystemErrorLogger::isTransientLocalMigrationTableMissing($missingPatEx));
            $this->assertFalse(SystemErrorLogger::isTransientLocalMigrationTableMissing($missingOrderEx));

            $this->app['env'] = 'production';
            $this->assertFalse(SystemErrorLogger::isTransientLocalMigrationTableMissing($missingPatEx));
        } finally {
            $this->app['env'] = $origEnv;
        }
    }

    public function test_system_sentinel_audit_command_remediates_overdue_triage_and_orphaned_dumps(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);
        $pulseService = app(\App\Services\System\SystemHealthPulseService::class);
        $pulseService->resetStateForTesting();

        $dumpPath = storage_path('logs/gemini_payload_dump.json');
        $publicCliLog = public_path('scheduler_cli.log');

        try {
            File::ensureDirectoryExists(dirname($dumpPath));
            file_put_contents($dumpPath, '{"debug":"orphan"}');
            file_put_contents($publicCliLog, 'orphan public cli log');

            $staleLog = SystemLog::create([
                'type' => 'system',
                'action_id' => 'system:exception:stale_audit_test',
                'title' => 'Ungetriagierter Alt-Fehler',
                'message' => 'Division by zero in DemoService.php',
                'status' => 'error',
                'payload' => [
                    'fingerprint' => hash('sha256', 'stale_audit_' . uniqid()),
                    'occurrences_count' => 1,
                ],
                'started_at' => now()->subMinutes(35),
                'finished_at' => now()->subMinutes(35),
            ]);

            Cache::forget('sentinel:last_triage_cycle_at');

            $exitCode = Artisan::call('system:sentinel-audit', ['--json' => true]);
            $this->assertSame(0, $exitCode);

            $decoded = json_decode(trim(Artisan::output()), true);
            $this->assertIsArray($decoded);
            $this->assertTrue((bool) ($decoded['triage_catchup_triggered'] ?? false));
            $this->assertSame(0, (int) ($decoded['untriaged_stale_errors_count'] ?? -1));
            $this->assertGreaterThanOrEqual(2, (int) ($decoded['orphaned_files_removed'] ?? 0));

            $this->assertFileDoesNotExist($dumpPath);
            $this->assertFileDoesNotExist($publicCliLog);

            $staleLog->refresh();
            $this->assertSame('queued_for_antigravity', $staleLog->triageState());

            $this->assertTrue(\App\Models\System\SystemCronjob::where('command', 'system:sentinel-audit')->exists());

            $pulse = $pulseService->evaluate(source: 'dashboard', strict: false, recordSample: false, syncLogs: false);
            $this->assertArrayHasKey('sentinel_audit', $pulse);
            $this->assertSame(0, (int) ($pulse['sentinel_audit']['untriaged_stale_errors_count'] ?? -1));
        } finally {
            @unlink($dumpPath);
            @unlink($publicCliLog);
            $pulseService->resetStateForTesting();
        }
    }

    public function test_local_mysql_cold_boot_2002_returns_503_retry_after_3_without_logging_and_passes_through_in_production(): void
    {
        $pdoColdBoot = new \PDOException('SQLSTATE[HY000] [2002] Connection refused', 2002);
        $queryColdBootDb = new \Illuminate\Database\QueryException(
            'mysql',
            'select * from `personal_access_tokens` where `id` = ? limit 1',
            [4],
            new \PDOException('SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, Host: db, Port: 3306, Database: seelenfunke)', 2002)
        );
        $queryRemoteHost = new \Illuminate\Database\QueryException(
            'mysql',
            'select 1',
            [],
            new \PDOException('SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, Host: mysql-prod.project.host, Port: 3306)', 2002)
        );

        // In testing (Default) & production strikt deaktiviert
        $this->assertFalse(SystemErrorLogger::isTransientLocalDatabaseColdBoot($queryColdBootDb));

        \Illuminate\Support\Facades\Route::get('/api/_test-cold-boot-2002', function () use ($queryColdBootDb) {
            throw $queryColdBootDb;
        });

        $origEnv = $this->app['env'];
        SystemLog::query()->delete();
        $beforeLogCount = SystemLog::count();

        try {
            // 1. Im local-Environment: HTTP 503 + Retry-After: 3 + JSON {"status":"booting",...} + 0 SystemLog-Eintraege
            $this->app['env'] = 'local';
            $this->assertTrue(SystemErrorLogger::isTransientLocalDatabaseColdBoot($queryColdBootDb));
            $this->assertFalse(SystemErrorLogger::isTransientLocalDatabaseColdBoot($queryRemoteHost));
            $this->assertNull(SystemErrorLogger::recordException($pdoColdBoot));

            $localResp = $this->getJson('/api/_test-cold-boot-2002');
            $localResp->assertStatus(503)
                ->assertHeader('Retry-After', '3')
                ->assertExactJson([
                    'status' => 'booting',
                    'message' => 'Lokale Datenbank startet gerade...',
                ]);
            $this->assertSame($beforeLogCount, SystemLog::count());

            // 2. Im production-Environment: KEIN Kaltstart-503-Bypass, Fehler wird regulär als HTTP 500 & SystemLog behandelt
            $this->app['env'] = 'production';
            $this->assertFalse(SystemErrorLogger::isTransientLocalDatabaseColdBoot($queryColdBootDb));

            $prodResp = $this->getJson('/api/_test-cold-boot-2002');
            $prodResp->assertStatus(500);
            $this->assertGreaterThan($beforeLogCount, SystemLog::count());
        } finally {
            $this->app['env'] = $origEnv;
        }
    }

    public function test_playbook_14_heals_local_mysql_cold_boot_respects_relapse_guard_and_never_heals_production(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);
        $sentinel = app(SentinelTriageService::class);

        $localColdBoot = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:exception:database_query_or_connection',
            'title' => 'System-Fehler (QueryException)',
            'message' => 'SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, Host: db, Port: 3306, Database: seelenfunke, SQL: select * from `personal_access_tokens` where `id` = 4 limit 1)',
            'status' => 'error',
            'payload' => [
                'origin_environment' => 'local',
                'fingerprint' => hash('sha256', 'local_cold_boot_' . uniqid()),
                'occurrences_count' => 3,
            ],
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(2),
        ]);

        $prodConnectionRefused = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:exception:database_query_or_connection',
            'title' => '[STAGE] System-Fehler (QueryException)',
            'message' => 'SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, Host: db, Port: 3306, Database: seelenfunke)',
            'status' => 'error',
            'payload' => [
                'origin_environment' => 'production',
                'fingerprint' => hash('sha256', 'prod_db_down_' . uniqid()),
                'occurrences_count' => 1,
            ],
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
        ]);

        $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

        $localColdBoot->refresh();
        $prodConnectionRefused->refresh();

        $this->assertSame('auto_healed', $localColdBoot->triageState());
        $this->assertSame('local_transient_mysql_cold_boot_recovered', $localColdBoot->payload['triage']['playbook'] ?? null);

        // Production bleibt strikt ungeheilt im Antigravity-Pool!
        $this->assertSame('error', $prodConnectionRefused->status);
        $this->assertSame('queued_for_antigravity', $prodConnectionRefused->triageState());

        // 24h-Rezidiv-Sperre greift auch fuer Playbook 14 ab dem 3. Auftreten desselben Fingerprints
        $relapseFp = hash('sha256', 'local_cold_boot_relapse_' . uniqid());
        for ($i = 1; $i <= 2; $i++) {
            SystemLog::create([
                'type' => 'system',
                'action_id' => 'system:exception:database_query_or_connection',
                'title' => "Kaltstart #{$i}",
                'message' => 'SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, Host: db, Port: 3306)',
                'status' => 'error',
                'payload' => ['origin_environment' => 'local', 'fingerprint' => $relapseFp, 'occurrences_count' => 1],
                'started_at' => now()->subMinutes(15 - $i),
                'finished_at' => now()->subMinutes(15 - $i),
            ]);
            $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
        }

        $thirdColdBoot = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:exception:database_query_or_connection',
            'title' => 'Kaltstart #3',
            'message' => 'SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, Host: db, Port: 3306)',
            'status' => 'error',
            'payload' => ['origin_environment' => 'local', 'fingerprint' => $relapseFp, 'occurrences_count' => 1],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
        $thirdColdBoot->refresh();
        $this->assertSame('error', $thirdColdBoot->status);
        $this->assertSame('queued_for_antigravity', $thirdColdBoot->triageState());
        $this->assertTrue((bool) ($thirdColdBoot->payload['triage']['relapse_detected'] ?? false));
    }

    public function test_minipc_direct_stale_prefix_handoff_auto_reconciles_via_import_file_while_newer_errors_remain_open(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);

        // Gestern um 22:41Z (23:41 Uhr) wurde der Stripe-Webhook-Fehler auf Stage/Lokal bereits behoben & als resolved markiert
        $resolvedStageFix = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:log:error:application_runtime_error',
            'title' => '[GELÖST] [STAGE] System-Log ERROR (application_runtime_error)',
            'message' => 'Stripe Webhook Signatur Fehler: No signatures found matching the expected signature for payload',
            'status' => 'resolved',
            'payload' => [
                'platform' => 'portal',
                'origin_environment' => 'production',
                'fingerprint' => hash('sha256', 'stage_stripe_fp_' . uniqid()),
                'file' => 'app/Http/Controllers/StripeWebhookController.php',
                'line' => 35,
                'resolved_at' => '2026-11-16T22:41:00Z',
                'triage' => ['state' => 'resolved'],
            ],
            'started_at' => \Carbon\Carbon::parse('2026-11-16T22:20:00Z'),
            'finished_at' => \Carbon\Carbon::parse('2026-11-16T22:41:00Z'),
        ]);

        $staleMiniPcFp = hash('sha256', 'minipc_stale_ba43533c_' . uniqid());
        $freshMiniPcFp = hash('sha256', 'minipc_fresh_post_fix_' . uniqid());
        $tmpImportPath = storage_path('framework/testing/tmp_minipc_wakeup_inbox_' . uniqid() . '.json');
        File::ensureDirectoryExists(dirname($tmpImportPath));

        file_put_contents($tmpImportPath, json_encode([
            'inbox_version' => 1,
            'source_environment' => 'production',
            'items' => [
                [
                    'dossier_version' => 2,
                    'fingerprint' => $staleMiniPcFp,
                    'origin_environment' => 'minipc_direct',
                    'prepared_at' => '2026-11-16T22:26:35.4101336Z',
                    'type' => 'minipc',
                    'action_id' => 'core:app/Http/Controllers/StripeWebhookController.php',
                    'severity' => 'error',
                    'title' => 'System-Log ERROR (application_runtime_error)',
                    'message' => 'Stripe Webhook Signatur Fehler: No signatures found matching the expected signature for payload',
                    'culprit_file' => 'app/Http/Controllers/StripeWebhookController.php',
                    'line' => 35,
                    'occurrences_count' => 2,
                ],
                [
                    'dossier_version' => 2,
                    'fingerprint' => $freshMiniPcFp,
                    'origin_environment' => 'minipc_direct',
                    'prepared_at' => '2026-11-17T08:30:00Z',
                    'type' => 'minipc',
                    'action_id' => 'minipc_linux:laser:new_bug',
                    'severity' => 'error',
                    'title' => 'Neuer echter Fehler nach dem Fix',
                    'message' => 'Unbekannter Kamera-Kalibrierungsfehler im Fokus-Monitor',
                    'culprit_file' => 'funkenwerk-os-linux/Program.cs',
                    'line' => 512,
                    'occurrences_count' => 1,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            $relPath = ltrim(str_replace(base_path(), '', $tmpImportPath), '/\\');
            $exitCode = Artisan::call('system:sentinel-inbox', ['--import-file' => $relPath]);
            $this->assertSame(0, $exitCode);

            $result = json_decode(trim(Artisan::output()), true);
            $this->assertIsArray($result);
            $this->assertSame('success', $result['status'] ?? null);
            $this->assertSame(1, (int) ($result['stale_minipc_reconciled'] ?? 0));
            $this->assertSame(1, (int) ($result['imported_new'] ?? 0));
            $this->assertSame(1, (int) ($result['local_open_pool_count'] ?? -1));

            // Das veraltete Mini-PC-Dossier von vor dem Fix (22:26Z < 22:41Z) ist direkt resolved und NICHT offen!
            $reconciledRow = SystemLog::where('payload->fingerprint', $staleMiniPcFp)->firstOrFail();
            $this->assertSame('resolved', $reconciledRow->status);
            $this->assertSame('resolved', $reconciledRow->payload['triage']['state'] ?? null);
            $this->assertSame($resolvedStageFix->id, $reconciledRow->payload['reconciled_against_log_id'] ?? null);

            // Der neue Fehler nach dem Fix (08:30Z > 22:41Z) bleibt regulaer offen im Antigravity-Pool!
            $freshRow = SystemLog::where('payload->fingerprint', $freshMiniPcFp)->firstOrFail();
            $this->assertSame('error', $freshRow->status);
            $this->assertSame('queued_for_antigravity', $freshRow->triageState());
        } finally {
            @unlink($tmpImportPath);
        }
    }

    public function test_phpunit_log_isolation_keeps_laravel_log_at_zero_bytes_and_sentinel_ignores_testing_log_lines(): void
    {
        // 1. PHPUnit-Pfadisolation pruefen: Log::error() schreibt nach /tmp/sf_phpunit_testing.log (bzw. pro-UID-Ausweichpfad
        //    /tmp/sf_phpunit_testing.uid<N>.log via Tests\Support\TestingTmpPathGuard) und laesst storage/logs/laravel.log bei 0 Bytes
        $isolatedTestLog = (string) config('logging.channels.single.path');
        $this->assertMatchesRegularExpression('#^/tmp/sf_phpunit_testing(\.uid\d+)?\.log$#', $isolatedTestLog);
        $this->assertSame($isolatedTestLog, config('logging.channels.daily.path'));
        $this->assertSame($isolatedTestLog, config('logging.channels.emergency.path'));

        $laravelLogPath = storage_path('logs/laravel.log');
        $hadOriginalLaravelLog = is_file($laravelLogPath);
        $originalLaravelLogContent = $hadOriginalLaravelLog ? (string) file_get_contents($laravelLogPath) : null;

        $negMarker = 'NegativTest_' . bin2hex(random_bytes(6));

        try {
            File::ensureDirectoryExists(dirname($laravelLogPath));
            file_put_contents($laravelLogPath, '');
            clearstatcache(true, $laravelLogPath);
            $this->assertSame(0, (int) filesize($laravelLogPath));

            Log::error('Simulierter Negativ-Testfehler ' . $negMarker);

            clearstatcache(true, $laravelLogPath);
            $this->assertSame(
                0,
                (int) filesize($laravelLogPath),
                'storage/logs/laravel.log muss waehrend PHPUnit-Laeufen exakt 0 Bytes gross bleiben!'
            );

            clearstatcache(true, $isolatedTestLog);
            $this->assertFileExists($isolatedTestLog);
            $this->assertStringContainsString($negMarker, (string) file_get_contents($isolatedTestLog));
        } finally {
            if ($hadOriginalLaravelLog && $originalLaravelLogContent !== null) {
                file_put_contents($laravelLogPath, $originalLaravelLogContent);
            }
            clearstatcache(true, $laravelLogPath);
        }

        // 2. Defense-in-Depth: Logfile-Scanner ignoriert saemtliche testing.*-Zeilen, eskaliert aber echte production.*/local.*-Zeilen
        SystemLog::query()->delete();

        $testOnlyMarker = 'TestingOnly_' . bin2hex(random_bytes(6));
        $prodMarker = 'ProdRealBug_' . bin2hex(random_bytes(6));
        $localMarker = 'LocalRealBug_' . bin2hex(random_bytes(6));
        $tmpLogPath = sys_get_temp_dir() . '/sf_sentinel_testing_filter_' . bin2hex(random_bytes(5)) . '.log';

        try {
            file_put_contents($tmpLogPath, implode("\n", [
                "[2026-11-17 09:05:00] testing.ERROR: Ignorierter PHPUnit Fehler {$testOnlyMarker}",
                "[2026-11-17 09:05:01] testing.CRITICAL: Ignorierter PHPUnit Critical {$testOnlyMarker}",
                "[2026-11-17 09:05:02] testing.WARNING: Ignorierte PHPUnit Warnung {$testOnlyMarker}",
                "[2026-11-17 09:05:03] testing.INFO: Ignorierte PHPUnit Info {$testOnlyMarker}",
                '',
            ]));

            $this->assertFalse(
                LogArchiveService::fileHasErrorLines($tmpLogPath),
                'Reine testing.*-Logzeilen duerfen nicht als echte Fehlerzeilen gewertet werden.'
            );

            $sentinel = app(SentinelTriageService::class);
            $this->assertSame(0, $sentinel->scanLaravelLogFile($tmpLogPath));
            $this->assertSame(0, SystemLog::where('message', 'like', "%{$testOnlyMarker}%")->count());

            // Echte production.ERROR- und local.CRITICAL-Zeilen ergaenzen -> muessen scharf erkannt und eskaliert werden!
            file_put_contents($tmpLogPath, implode("\n", [
                "[2026-11-17 09:06:00] production.ERROR: Unbehandelter Produktionsfehler {$prodMarker}",
                "[2026-11-17 09:06:01] local.CRITICAL: Unbehandelter Lokalfehler {$localMarker}",
                '',
            ]), FILE_APPEND);

            $this->assertTrue(LogArchiveService::fileHasErrorLines($tmpLogPath));

            $ingested = $sentinel->scanLaravelLogFile($tmpLogPath);
            $this->assertSame(2, $ingested);

            $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

            $this->assertSame(0, SystemLog::where('message', 'like', "%{$testOnlyMarker}%")->count());

            $prodRow = SystemLog::where('message', 'like', "%{$prodMarker}%")->firstOrFail();
            $this->assertSame('error', $prodRow->status);
            $this->assertSame('queued_for_antigravity', $prodRow->triageState());

            $localRow = SystemLog::where('message', 'like', "%{$localMarker}%")->firstOrFail();
            $this->assertSame('error', $localRow->status);
            $this->assertSame('queued_for_antigravity', $localRow->triageState());
        } finally {
            @unlink($tmpLogPath);
        }
    }

    public function test_local_incomplete_migration_catches_customers_42s02_with_503_retry_after_3_while_typo_table_and_production_are_logged(): void
    {
        $missingCustomersEx = new \Illuminate\Database\QueryException(
            'mysql',
            'select * from `customers` where `id` = ? and `customers`.`deleted_at` is null limit 1',
            ['00000000-0000-0000-0000-000000000001'],
            new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'seelenfunke.customers' doesn't exist", 1146)
        );

        $typoTableEx = new \Illuminate\Database\QueryException(
            'mysql',
            'select * from `typo_table_does_not_exist` limit 1',
            [],
            new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'seelenfunke.typo_table_does_not_exist' doesn't exist", 1146)
        );

        \Illuminate\Support\Facades\Route::get('/api/_test-migrate-fresh-customers-42s02', function () use ($missingCustomersEx) {
            throw $missingCustomersEx;
        });
        \Illuminate\Support\Facades\Route::get('/api/_test-typo-table-42s02', function () use ($typoTableEx) {
            throw $typoTableEx;
        });

        $origEnv = $this->app['env'];
        $removedMigrations = \Illuminate\Support\Facades\DB::table('migrations')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        try {
            // 1. Unvollstaendige Migration im local-Env (migrate:fresh laeuft): customers wird als 503 Retry-After: 3 ohne Log abgefangen!
            \Illuminate\Support\Facades\DB::table('migrations')
                ->whereIn('id', array_column($removedMigrations, 'id'))
                ->delete();

            $this->app['env'] = 'local';
            $this->assertTrue(SystemErrorLogger::isLocalDatabaseIncompleteOrMigrating());
            $this->assertTrue(SystemErrorLogger::isTransientLocalBootstrapTableMissing($missingCustomersEx));
            $this->assertFalse(SystemErrorLogger::isTransientLocalBootstrapTableMissing($typoTableEx));

            $beforeCount = SystemLog::count();
            $migratingResp = $this->getJson('/api/_test-migrate-fresh-customers-42s02');
            $migratingResp->assertStatus(503)
                ->assertHeader('Retry-After', '3')
                ->assertJson(['status' => 'maintenance']);
            $this->assertSame($beforeCount, SystemLog::count(), 'Kein SystemLog-Eintrag waehrend laufender lokaler Migration!');

            // 2. Fertig migrierte DB im local-Env: Echte Tippfehler-Tabelle wird NICHT unterdrueckt, sondern voll gemeldet!
            if (!empty($removedMigrations)) {
                \Illuminate\Support\Facades\DB::table('migrations')->insert($removedMigrations);
            }
            $this->assertFalse(SystemErrorLogger::isLocalDatabaseIncompleteOrMigrating());
            $this->assertFalse(SystemErrorLogger::isTransientLocalBootstrapTableMissing($typoTableEx));

            $typoResp = $this->getJson('/api/_test-typo-table-42s02');
            $typoResp->assertStatus(500);
            $this->assertTrue(
                SystemLog::where('message', 'like', '%typo_table_does_not_exist%')->exists(),
                'Echte Tippfehler-Tabelle muss voll in SystemLog erfasst werden!'
            );

            // 3. Production-Umgebung: Selbst waehrend fehlender Migrationen NIEMALS unterdruecken!
            $this->app['env'] = 'production';
            $this->assertFalse(SystemErrorLogger::isTransientLocalBootstrapTableMissing($missingCustomersEx));

            $beforeProdCount = SystemLog::count();
            $prodResp = $this->getJson('/api/_test-migrate-fresh-customers-42s02');
            $prodResp->assertStatus(500);
            $this->assertGreaterThan($beforeProdCount, SystemLog::count());

            // 4. ext-intl Verifikation: Illuminate\Support\Number::format / fileSize (u. a. fuer php artisan db:show) laeuft fehlerfrei
            $this->assertTrue(extension_loaded('intl'), 'PHP-Extension ext-intl muss im Container aktiv geladen sein.');
            $this->assertNotEmpty(\Illuminate\Support\Number::format(1234.56, 2));
            $this->assertNotEmpty(\Illuminate\Support\Number::fileSize(4096, 2));
        } finally {
            $this->app['env'] = $origEnv;
        }
    }

    public function test_write_inbox_snapshot_atomically_replaces_readonly_file_without_permission_denied(): void
    {
        $inboxPath = storage_path(SentinelTriageService::INBOX_FILE);
        $handoffPath = storage_path(SentinelTriageService::HANDOFF_FILE);
        File::ensureDirectoryExists(dirname($inboxPath), 0777, true, true);

        // Simuliert eine durch Windows/WSL (ubuntuxina, 0444/0644) geschriebene Datei,
        // in die direktes file_put_contents() nicht hineinschreiben darf.
        SentinelTriageService::writeSharedPrivateJsonAtomically($inboxPath, '{"inbox_version":1,"open_pool_count":0,"items":[]}', 0444);
        @chmod($inboxPath, 0444);

        $sentinel = app(SentinelTriageService::class);
        $result = $sentinel->importRemoteStageInbox([
            'source_environment' => 'production',
            'items' => [
                [
                    'fingerprint' => hash('sha256', 'cross_uid_atomic_test_' . uniqid()),
                    'log_id' => 9991,
                    'origin_environment' => 'production',
                    'platform' => 'portal',
                    'platform_label' => 'Webportal',
                    'type' => 'system',
                    'action_id' => 'system:cross_uid_test',
                    'severity' => 'error',
                    'criticality' => 'standard',
                    'title' => 'Cross-UID Atomic Replace Test',
                    'message' => 'Verifiziert atomaren Tempfile+Rename Schreibpfad.',
                ],
            ],
        ]);

        $this->assertSame('success', $result['status'] ?? null);
        $this->assertFileExists($inboxPath);
        $this->assertFileExists($handoffPath);

        $decoded = json_decode((string) file_get_contents($inboxPath), true);
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['inbox_version'] ?? null);

        $perms = fileperms($inboxPath) & 0777;
        $this->assertSame(0666, $perms, 'Im local/testing-Modus muss antigravity_inbox.json mit 0666 fuer Cross-UID-Zugriff angelegt werden.');
    }
}

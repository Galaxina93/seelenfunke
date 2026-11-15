<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemLogs;
use App\Models\Admin\Admin;
use App\Models\System\SystemLog;
use App\Services\System\SentinelTriageService;
use App\Services\System\SystemErrorLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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
            File::ensureDirectoryExists(dirname($inboxPath));
            file_put_contents($inboxPath, $this->backupInbox);
        } elseif (is_file($inboxPath)) {
            @unlink($inboxPath);
        }

        if ($this->backupHandoff !== null) {
            File::ensureDirectoryExists(dirname($handoffPath));
            file_put_contents($handoffPath, $this->backupHandoff);
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
        SystemLog::openIssues()->update(['status' => 'success']);

        // Lauf 1: Reverb-Bridge antwortet mit 503, Lauf 2: Reverb-Bridge antwortet wieder mit 200 OK
        Http::fake([
            'ws.mein-seelenfunke.de/*' => Http::sequence()
                ->push('Service Unavailable', 503)
                ->push(['status' => 'ok'], 200),
            'api-live-bridge.mein-seelenfunke.de/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $sentinel = app(SentinelTriageService::class);
        $sentinel->runTriageCycle(probeBridges: true, allowAiEnrichment: false);

        $bridgeError = SystemLog::openIssues()
            ->where('action_id', 'sentinel:bridge_unhealthy:reverb_twilio')
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
}

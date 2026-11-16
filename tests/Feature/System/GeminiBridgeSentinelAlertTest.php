<?php

namespace Tests\Feature\System;

use App\Models\Management\ManagementTask;
use App\Models\System\SystemLog;
use App\Services\FirebaseService;
use App\Services\System\SentinelTriageService;
use App\Services\System\SystemHealthPulseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class GeminiBridgeSentinelAlertTest extends TestCase
{
    use DatabaseTransactions;

    private ?string $backupInbox = null;
    private ?string $backupHandoff = null;

    protected function setUp(): void
    {
        parent::setUp();

        app(SystemHealthPulseService::class)->resetStateForTesting();

        $inboxPath = storage_path(SentinelTriageService::INBOX_FILE);
        $handoffPath = storage_path(SentinelTriageService::HANDOFF_FILE);

        $this->backupInbox = is_file($inboxPath) ? (string) file_get_contents($inboxPath) : null;
        $this->backupHandoff = is_file($handoffPath) ? (string) file_get_contents($handoffPath) : null;
    }

    protected function tearDown(): void
    {
        app(SystemHealthPulseService::class)->resetStateForTesting();

        $inboxPath = storage_path(SentinelTriageService::INBOX_FILE);
        $handoffPath = storage_path(SentinelTriageService::HANDOFF_FILE);

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

    private function prepareHealthyCorePillars(): void
    {
        Storage::fake('local');
        Config::set('backup.backup.destination.disks', ['local']);
        Config::set('backup.backup.name', 'seelenfunke-test-backup');
        Config::set('queue.default', 'sync');
        Storage::disk('local')->put('seelenfunke-test-backup/fresh.zip', 'PK-ZIP');
        Cache::put('scheduler_last_run', now()->timestamp, 600);
        DB::table('failed_jobs')->delete();
        SystemLog::openIssues()->update(['status' => 'success']);
    }

    public function test_gemini_bridge_outage_creates_system_log_dashboard_task_fcm_push_and_is_not_swallowed_by_sentinel(): void
    {
        $this->prepareHealthyCorePillars();

        $firebaseMock = Mockery::mock(FirebaseService::class);
        $firebaseMock->shouldReceive('sendToAdmins')
            ->once()
            ->withArgs(function (string $title, string $body, array $data): bool {
                return str_contains($title, 'Gemini Audio Bridge')
                    && ($data['type'] ?? '') === 'gemini_bridge_outage'
                    && !empty($data['task_id']);
            })
            ->andReturn(1);
        $this->app->instance(FirebaseService::class, $firebaseMock);

        SystemHealthPulseService::setGeminiBridgeProbeResolver(function (): array {
            return [
                'probed' => true,
                'healthy' => false,
                'live_bridge_url' => 'https://api-live-bridge.mein-seelenfunke.de/health',
                'live_bridge_ok' => false,
                'live_bridge_reachable' => true,
                'live_bridge_http_status' => 503,
                'live_bridge_error' => 'HTTP 503 Service Unavailable',
                'twilio_bridge_probed' => true,
                'twilio_bridge_url' => 'https://ws.mein-seelenfunke.de/twilio-health',
                'twilio_bridge_ok' => true,
                'twilio_bridge_http_status' => 200,
                'latency_ms' => 112.4,
            ];
        });

        $pulseService = app(SystemHealthPulseService::class);

        // Puls 1: Ausfall wird sofort erkannt
        $report1 = $pulseService->evaluate(
            source: 'cli_scheduler',
            strict: true,
            recordSample: true,
            syncLogs: true
        );

        // Puls 2 direkt danach: Deduplizierung in SystemLog + ManagementTask + FCM-Rate-Limit greifen
        $report2 = $pulseService->evaluate(
            source: 'cli_scheduler',
            strict: true,
            recordSample: true,
            syncLogs: true
        );

        $this->assertSame('critical', $report1['status']);
        $this->assertFalse($report1['healthy']);
        $this->assertSame('critical', $report1['gemini_bridge']['status']);
        $this->assertStringContainsString('Gemini Audio Bridge', $report1['headline']);
        $this->assertSame('critical', $report2['status']);

        // 1. SystemLog dedupliziert mit action_id = watchtower:health_fail:gemini_bridge & level = ERROR
        $openBridgeLogs = SystemLog::openIssues()
            ->where('action_id', SystemHealthPulseService::GEMINI_BRIDGE_LOG_ACTION_ID)
            ->get();

        $this->assertCount(1, $openBridgeLogs);
        $bridgeLog = $openBridgeLogs->first();
        $this->assertSame('error', $bridgeLog->status);
        $this->assertSame('ERROR', $bridgeLog->payload['level'] ?? null);
        $this->assertSame(2, (int) ($bridgeLog->payload['occurrences_count'] ?? 0));

        // 2. Idempotente dringende ManagementTask auf /admin/dashboard ohne KI-Gedankenstriche + SSH-Notfallbefehl
        $openTasks = ManagementTask::query()
            ->whereNull('parent_id')
            ->where('is_completed', false)
            ->where('title', SystemHealthPulseService::GEMINI_BRIDGE_TASK_TITLE)
            ->get();

        $this->assertCount(1, $openTasks);
        $alertTask = $openTasks->first();
        $this->assertSame('hoch', $alertTask->priority);
        $this->assertStringContainsString(SystemHealthPulseService::GEMINI_BRIDGE_RESTART_CMD, (string) $alertTask->ai_plan);
        $this->assertStringNotContainsString(' – ', (string) $alertTask->title);
        $this->assertStringNotContainsString(' — ', (string) $alertTask->title);
        $this->assertStringNotContainsString(' – ', (string) $alertTask->ai_plan);
        $this->assertStringNotContainsString(' — ', (string) $alertTask->ai_plan);
        $this->assertTrue(Cache::has(SystemHealthPulseService::GEMINI_BRIDGE_PUSH_LOCK_KEY));

        // Zusätzliches klassisches Telephony-Health-Fail-Log darf bei echtem Bridge-Ausfall EBENFALLS NICHT verschluckt werden!
        $legacyTelephonyLog = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:health_fail',
            'title' => 'Infrastruktur-Ausfall: Telephony',
            'message' => 'Die KI-Telefonie Audio-Bridge antwortet mit Timeout.',
            'status' => 'error',
            'payload' => [
                'platform' => 'portal',
                'occurrences_count' => 1,
                'fingerprint' => hash('sha256', 'tel_real_outage_' . uniqid()),
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        // 3. Sentinel-Triage darf den echten Ausfall NICHT wegheilen (healed === false -> aktive Eskalation!)
        $sentinel = app(SentinelTriageService::class);
        $this->assertFalse($sentinel->verifyGeminiBridgeHealthy());

        $triageStats = $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

        $bridgeLog->refresh();
        $legacyTelephonyLog->refresh();

        $this->assertSame('error', $bridgeLog->status);
        $this->assertSame('queued_for_antigravity', $bridgeLog->triageState());
        $this->assertSame('critical', $bridgeLog->payload['triage']['criticality'] ?? null);

        $this->assertSame('error', $legacyTelephonyLog->status);
        $this->assertSame('queued_for_antigravity', $legacyTelephonyLog->triageState());
        $this->assertGreaterThanOrEqual(2, $triageStats['open_pool_count']);
    }

    public function test_gemini_bridge_recovery_auto_heals_system_log_completes_dashboard_task_and_sentinel_confirms_recovery(): void
    {
        $this->prepareHealthyCorePillars();

        $bridgeHealthyNow = false;

        SystemHealthPulseService::setGeminiBridgeProbeResolver(function () use (&$bridgeHealthyNow): array {
            if (!$bridgeHealthyNow) {
                return [
                    'probed' => true,
                    'healthy' => false,
                    'live_bridge_ok' => false,
                    'live_bridge_http_status' => 503,
                    'live_bridge_error' => 'Connection timed out',
                ];
            }

            return [
                'probed' => true,
                'healthy' => true,
                'live_bridge_ok' => true,
                'live_bridge_http_status' => 200,
                'live_bridge_json_status' => 'ok',
                'twilio_bridge_probed' => true,
                'twilio_bridge_ok' => true,
                'twilio_bridge_http_status' => 200,
                'latency_ms' => 28.5,
            ];
        });

        $pulseService = app(SystemHealthPulseService::class);
        $sentinel = app(SentinelTriageService::class);

        // Phase 1: Ausfall erzeugt SystemLog, Dashboard-Task, Push-Lock & aktiven Sentinel-Eintrag
        $outageReport = $pulseService->evaluate(source: 'cli_scheduler', strict: true, recordSample: true, syncLogs: true);
        $this->assertSame('critical', $outageReport['status']);

        $legacyTelephonyLog = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:health_fail',
            'title' => 'Infrastruktur-Ausfall: Telephony',
            'message' => 'Die KI-Telefonie Audio-Bridge meldet HTTP 503.',
            'status' => 'error',
            'payload' => [
                'platform' => 'portal',
                'occurrences_count' => 1,
                'fingerprint' => hash('sha256', 'tel_recover_test_' . uniqid()),
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

        $bridgeLog = SystemLog::where('action_id', SystemHealthPulseService::GEMINI_BRIDGE_LOG_ACTION_ID)->latest('id')->firstOrFail();
        $alertTask = ManagementTask::where('title', SystemHealthPulseService::GEMINI_BRIDGE_TASK_TITLE)
            ->where('is_completed', false)
            ->firstOrFail();

        $this->assertSame('error', $bridgeLog->status);
        $this->assertFalse($alertTask->is_completed);

        // Phase 2: Bridge antwortet wieder 200 OK ({"status":"ok"}) -> Fehler wurde NICHT gecacht, sofortige Heilung!
        $bridgeHealthyNow = true;

        $recoveredReport = $pulseService->evaluate(source: 'cli_scheduler', strict: true, recordSample: true, syncLogs: true);

        $this->assertSame('ok', $recoveredReport['status']);
        $this->assertTrue($recoveredReport['healthy']);
        $this->assertSame('ok', $recoveredReport['gemini_bridge']['status']);

        $bridgeLog->refresh();
        $alertTask->refresh();

        $this->assertSame('success', $bridgeLog->status);
        $this->assertStringStartsWith('[AUTO-GEHEILT]', (string) $bridgeLog->title);
        $this->assertTrue((bool) ($bridgeLog->payload['auto_healed'] ?? false));
        $this->assertTrue($alertTask->is_completed);
        $this->assertFalse(Cache::has(SystemHealthPulseService::GEMINI_BRIDGE_PUSH_LOCK_KEY));

        // Sentinel bestätigt echte Live-Heilung ebenfalls für offene Telephony/Bridge-Logs
        $this->assertTrue($sentinel->verifyGeminiBridgeHealthy());
        $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);

        $legacyTelephonyLog->refresh();
        $this->assertSame('success', $legacyTelephonyLog->status);
        $this->assertSame('auto_healed', $legacyTelephonyLog->triageState());
        $this->assertSame('health_telephony_worker_recovered', $legacyTelephonyLog->payload['triage']['playbook'] ?? null);
    }

    public function test_http_probe_validates_both_node_live_bridge_and_twilio_reverb_json_status_ok(): void
    {
        Config::set('services.gemini.proxy_ws_url', 'wss://api-live-bridge.mein-seelenfunke.de/gemini-live');
        Config::set('services.twilio.wss_url', 'wss://ws.mein-seelenfunke.de/twilio-stream');

        Http::fake([
            'api-live-bridge.mein-seelenfunke.de/health' => Http::sequence()
                ->push(['status' => 'degraded'], 200)
                ->push(['status' => 'degraded'], 200)
                ->push(['status' => 'ok', 'bridge_version' => 'v1.5-vertex-live-secretary'], 200),
            'ws.mein-seelenfunke.de/twilio-health' => Http::response(['status' => 'ok', 'bridge_version' => 'v2.0-reverb-live-secretary'], 200),
        ]);

        $pulseService = app(SystemHealthPulseService::class);

        // Lauf 1: HTTP 200, aber status != "ok" -> gilt als gestört und wird NICHT gecacht
        $degradedRaw = $pulseService->getCachedOrProbeGeminiBridge(false);
        $degradedEval = $pulseService->evaluateGeminiBridge(true, $degradedRaw);

        $this->assertFalse($degradedRaw['healthy']);
        $this->assertSame('critical', $degradedEval['status']);
        $this->assertFalse(Cache::has(SystemHealthPulseService::GEMINI_BRIDGE_CACHE_KEY));

        // Lauf 2: Beide Brücken liefern HTTP 200 + {"status":"ok"} -> grün und für 60s gecacht
        $okRaw = $pulseService->getCachedOrProbeGeminiBridge(false);
        $okEval = $pulseService->evaluateGeminiBridge(true, $okRaw);

        $this->assertTrue($okRaw['healthy']);
        $this->assertSame('ok', $okEval['status']);
        $this->assertTrue($okEval['twilio_bridge_probed']);
        $this->assertTrue($okEval['twilio_bridge_ok']);
        $this->assertTrue(Cache::has(SystemHealthPulseService::GEMINI_BRIDGE_CACHE_KEY));
    }
}

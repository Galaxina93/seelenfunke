<?php

namespace Tests\Feature\System;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\System\SystemLog;
use App\Services\System\SystemHealthPulseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemHealthPulseWatchtowerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(SystemHealthPulseService::class)->resetStateForTesting();
    }

    protected function tearDown(): void
    {
        app(SystemHealthPulseService::class)->resetStateForTesting();
        parent::tearDown();
    }

    public function test_authorization_matrix_strictly_blocks_guests_non_admins_expired_tokens_and_wrong_scopes(): void
    {
        // 1. Unauthenticated guest -> 401
        $this->getJson('/api/system/health-pulse')->assertStatus(401);
        $this->postJson('/api/system/health-pulse/nas-heartbeat', ['status' => 'ok'])->assertStatus(401);
        $this->postJson('/api/system/health-pulse/issue-token')->assertStatus(401);

        // 2. Customer, Influencer, Tax Advisor & Internal Employee -> strictly 403
        $customer = Customer::factory()->create();
        $influencer = Employee::create([
            'first_name' => 'Lina',
            'last_name' => 'Creator',
            'email' => 'wt-influencer-' . Str::random(5) . '@example.com',
            'role' => 'influencer',
            'password' => bcrypt('Secret123!'),
        ]);
        $taxAdvisor = Employee::create([
            'first_name' => 'Karl',
            'last_name' => 'Kanzlei',
            'email' => 'wt-tax-' . Str::random(5) . '@example.com',
            'role' => 'tax_advisor',
            'password' => bcrypt('Secret123!'),
        ]);
        $internalEmployee = Employee::create([
            'first_name' => 'Mara',
            'last_name' => 'Werkstatt',
            'email' => 'wt-emp-' . Str::random(5) . '@example.com',
            'role' => 'internal',
            'password' => bcrypt('Secret123!'),
        ]);

        foreach ([$customer, $influencer, $taxAdvisor, $internalEmployee] as $nonAdmin) {
            $tok = $nonAdmin->createToken('NonAdminTok', ['*'])->plainTextToken;
            Auth::forgetGuards();

            $this->withHeader('Authorization', 'Bearer ' . $tok)
                ->getJson('/api/system/health-pulse')
                ->assertStatus(403);

            $this->withHeader('Authorization', 'Bearer ' . $tok)
                ->postJson('/api/system/health-pulse/nas-heartbeat', ['status' => 'ok'])
                ->assertStatus(403);

            $this->withHeader('Authorization', 'Bearer ' . $tok)
                ->postJson('/api/system/health-pulse/issue-token')
                ->assertStatus(403);
        }

        // 3. Admin with unrelated narrow scope -> 403; expired token -> 401
        $admin = Admin::factory()->create();
        $wrongScopeToken = $admin->createToken('WrongScope', ['other:scope'])->plainTextToken;
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $wrongScopeToken)
            ->getJson('/api/system/health-pulse')
            ->assertStatus(403);

        $expiredPat = $admin->createToken('ExpiredMonitor', ['monitor:pulse'], now()->subMinutes(5));
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $expiredPat->plainTextToken)
            ->getJson('/api/system/health-pulse')
            ->assertStatus(401);

        // 4. Full Admin issues a dedicated read-only Watchtower token (monitor:pulse)
        $fullAdminToken = $admin->createToken('AdminFull', ['*'])->plainTextToken;
        Auth::forgetGuards();
        $issueResp = $this->withHeader('Authorization', 'Bearer ' . $fullAdminToken)
            ->postJson('/api/system/health-pulse/issue-token', ['device_name' => 'SynologyNAS']);

        $issueResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('scope', 'monitor:pulse');

        $watchtowerToken = (string) $issueResp->json('token');
        $this->assertNotEmpty($watchtowerToken);

        // 5. Watchtower monitor:pulse token CAN read health-pulse & report nas-heartbeat,
        //    but CANNOT issue new tokens or access full back-office routes!
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $watchtowerToken)
            ->getJson('/api/system/health-pulse?source=synology_nas')
            ->assertOk()
            ->assertJsonPath('success', true);

        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $watchtowerToken)
            ->postJson('/api/system/health-pulse/nas-heartbeat', [
                'status' => 'ok',
                'summary' => 'Delta-Sicherung erfolgreich',
                'copied_count' => 4,
                'deleted_count' => 0,
                'nas_host' => '192.168.188.33',
                'source' => 'synology_nas',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $watchtowerToken)
            ->postJson('/api/system/health-pulse/issue-token')
            ->assertStatus(403);

        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $watchtowerToken)
            ->getJson('/api/funki/financials/kpis')
            ->assertStatus(403);
    }

    public function test_all_four_pillars_report_green_and_track_external_watchtower_nodes_and_90d_uptime(): void
    {
        Storage::fake('local');
        Config::set('backup.backup.destination.disks', ['local']);
        Config::set('backup.backup.name', 'seelenfunke-test-backup');
        Config::set('queue.default', 'database');

        // Fresh backup (< 26h)
        Storage::disk('local')->put('seelenfunke-test-backup/2026-11-15-db.zip', 'PK-DUMMY-ZIP-DATA');
        // Fresh scheduler & queue worker heartbeats
        Cache::put('scheduler_last_run', now()->subSeconds(25)->timestamp, 600);
        Cache::put('scheduler_last_exception', null, 600);
        Cache::put('ai-worker-heartbeat', now()->subSeconds(10)->timestamp, 60);
        Storage::disk('local')->put('system/ai_worker_heartbeat.txt', (string) now()->subSeconds(10)->timestamp);

        DB::table('failed_jobs')->delete();
        DB::table('jobs')->delete();

        $admin = Admin::factory()->create();
        $token = $admin->createToken('WatchtowerNode', ['monitor:pulse'])->plainTextToken;

        // Report healthy NAS backup from Mini-PC
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/system/health-pulse/nas-heartbeat', [
                'status' => 'ok',
                'summary' => '18 Dokumente auf Synology gesichert',
                'copied_count' => 18,
                'deleted_count' => 1,
                'nas_host' => '192.168.188.33',
                'source' => 'minipc_linux',
            ])
            ->assertOk();

        Auth::forgetGuards();
        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/system/health-pulse?source=android_watchtower&strict=1');

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.healthy', true)
            ->assertJsonPath('data.pillars.shop.status', 'ok')
            ->assertJsonPath('data.pillars.cronjobs.status', 'ok')
            ->assertJsonPath('data.pillars.queues.status', 'ok')
            ->assertJsonPath('data.pillars.backups.status', 'ok')
            ->assertJsonPath('data.pillars.backups.nas_backup.status', 'ok')
            ->assertJsonPath('data.pillars.backups.nas_backup.copied_count', 18)
            ->assertJsonPath('data.watchtower.active_nodes_count', 2);

        $this->assertEquals(100.0, (float) $resp->json('data.uptime_90d_percent'));
        $this->assertCount(30, $resp->json('data.daily_bars'));
    }

    public function test_detects_stale_cronjobs_stalled_queues_overdue_backups_and_ssl_expiry(): void
    {
        Storage::fake('local');
        Config::set('backup.backup.destination.disks', ['local']);
        Config::set('backup.backup.name', 'seelenfunke-test-backup');
        Config::set('queue.default', 'database');

        $svc = app(SystemHealthPulseService::class);

        // 1. Stale scheduler (15 min old), dead worker with stuck job (15 min old), missing backup, and 5 days SSL left
        Cache::put('scheduler_last_run', now()->subMinutes(15)->timestamp, 600);
        Cache::forget('ai-worker-heartbeat');
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{"displayName":"TestStuckJob"}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(15)->timestamp,
            'created_at' => now()->subMinutes(15)->timestamp,
        ]);

        $report = $svc->evaluate(
            source: 'synology_nas',
            strict: true,
            recordSample: true,
            syncLogs: false,
            overrideSslDays: 5
        );

        $this->assertSame('critical', $report['status']);
        $this->assertFalse($report['healthy']);
        $this->assertSame('critical', $report['pillars']['shop']['status']);
        $this->assertSame('critical', $report['pillars']['cronjobs']['status']);
        $this->assertSame('critical', $report['pillars']['queues']['status']);
        $this->assertSame('critical', $report['pillars']['backups']['status']);
    }

    public function test_deduplicates_system_log_entries_and_auto_heals_when_pillar_recovers(): void
    {
        Storage::fake('local');
        Config::set('backup.backup.destination.disks', ['local']);
        Config::set('backup.backup.name', 'seelenfunke-test-backup');
        Config::set('queue.default', 'sync');

        Storage::disk('local')->put('seelenfunke-test-backup/fresh.zip', 'ZIP');
        DB::table('failed_jobs')->delete();

        $svc = app(SystemHealthPulseService::class);

        // Trigger critical cronjob pillar twice -> MUST deduplicate into a single open SystemLog with occurrences_count = 2
        Cache::put('scheduler_last_run', now()->subMinutes(20)->timestamp, 600);

        $svc->evaluate(source: 'synology_nas', strict: true, recordSample: true, syncLogs: true);
        $svc->evaluate(source: 'synology_nas', strict: true, recordSample: true, syncLogs: true);

        $openLogs = SystemLog::openIssues()
            ->where('action_id', 'watchtower:pillar:cronjobs')
            ->get();

        $this->assertCount(1, $openLogs);
        $this->assertSame(2, (int) ($openLogs->first()->payload['occurrences_count'] ?? 0));

        // Now restore scheduler heartbeat -> next evaluation MUST auto-heal the open SystemLog!
        Cache::put('scheduler_last_run', now()->timestamp, 600);
        $recovered = $svc->evaluate(source: 'synology_nas', strict: true, recordSample: true, syncLogs: true);

        $this->assertSame('ok', $recovered['status']);
        $this->assertSame(0, SystemLog::openIssues()->where('action_id', 'watchtower:pillar:cronjobs')->count());

        $healedLog = SystemLog::findOrFail($openLogs->first()->id);
        $this->assertSame('success', $healedLog->status);
        $this->assertStringStartsWith('[AUTO-GEHEILT]', (string) $healedLog->title);
        $this->assertTrue((bool) ($healedLog->payload['auto_healed'] ?? false));
    }

    public function test_artisan_health_pulse_command_outputs_valid_json(): void
    {
        Storage::fake('local');
        Config::set('backup.backup.destination.disks', ['local']);
        Config::set('backup.backup.name', 'seelenfunke-test-backup');
        Config::set('queue.default', 'sync');
        Storage::disk('local')->put('seelenfunke-test-backup/fresh.zip', 'ZIP');
        Cache::put('scheduler_last_run', now()->timestamp, 600);
        DB::table('failed_jobs')->delete();

        $this->artisan('system:health-pulse --json')
            ->assertExitCode(0);
    }

    public function test_live_domain_monitoring_handles_placeholder_200_maintenance_503_outage_502_and_ssl_warning(): void
    {
        Storage::fake('local');
        Config::set('backup.backup.destination.disks', ['local']);
        Config::set('backup.backup.name', 'seelenfunke-test-backup');
        Config::set('queue.default', 'sync');
        Storage::disk('local')->put('seelenfunke-test-backup/fresh.zip', 'ZIP');
        Cache::put('scheduler_last_run', now()->timestamp, 600);
        DB::table('failed_jobs')->delete();

        $svc = app(SystemHealthPulseService::class);

        // 1. Mittwald-Platzhalter (HTTP 200, < 12 KB ohne Shop-Marker, www noch ohne Ingress) -> ok!
        $placeholderReport = $svc->evaluate(
            source: 'dashboard',
            strict: true,
            recordSample: false,
            syncLogs: false,
            overrideSslDays: 60,
            overrideLiveDomain: [
                'reachable' => true,
                'http_status' => 200,
                'latency_ms' => 42.5,
                'ssl_days_remaining' => 75,
                'ssl_valid_until' => now()->addDays(75)->toIso8601String(),
                'body_bytes' => 2400,
                'has_shop_markers' => false,
                'www_ingress_active' => false,
                'www_http_status' => 0,
            ]
        );
        $this->assertSame('ok', $placeholderReport['status']);
        $this->assertTrue($placeholderReport['healthy']);
        $this->assertSame('ok', $placeholderReport['live_domain']['status']);
        $this->assertSame('placeholder', $placeholderReport['live_domain']['mode']);
        $this->assertSame('pending_ingress', $placeholderReport['live_domain']['www_status']);
        $this->assertFalse($placeholderReport['live_domain']['www_ingress_active']);
        $this->assertSame(['shop', 'cronjobs', 'queues', 'backups'], array_keys($placeholderReport['pillars']));

        // 2. Aktiver Vorab-Wartungsmodus (HTTP 503) -> gilt ausdrücklich als gesund / grün (ok)!
        $maintenanceReport = $svc->evaluate(
            source: 'dashboard',
            strict: true,
            recordSample: false,
            syncLogs: false,
            overrideSslDays: 60,
            overrideLiveDomain: [
                'reachable' => true,
                'http_status' => 503,
                'latency_ms' => 38.0,
                'ssl_days_remaining' => 80,
                'body_bytes' => 4200,
                'has_shop_markers' => false,
                'www_ingress_active' => true,
                'www_http_status' => 503,
            ]
        );
        $this->assertSame('ok', $maintenanceReport['status']);
        $this->assertTrue($maintenanceReport['healthy']);
        $this->assertSame('ok', $maintenanceReport['live_domain']['status']);
        $this->assertSame('maintenance', $maintenanceReport['live_domain']['mode']);
        $this->assertSame('active', $maintenanceReport['live_domain']['www_status']);

        // 3. Live-Shop aktiv (HTTP 200 mit Livewire/Shop-Markern) + SSL < 14 Tage (10 Tage) -> warning!
        $sslWarningReport = $svc->evaluate(
            source: 'dashboard',
            strict: true,
            recordSample: false,
            syncLogs: false,
            overrideSslDays: 60,
            overrideLiveDomain: [
                'reachable' => true,
                'http_status' => 200,
                'latency_ms' => 55.2,
                'ssl_days_remaining' => 10,
                'body_bytes' => 38000,
                'has_shop_markers' => true,
            ]
        );
        $this->assertSame('warning', $sslWarningReport['status']);
        $this->assertTrue($sslWarningReport['healthy']);
        $this->assertSame('live_shop', $sslWarningReport['live_domain']['mode']);
        $this->assertSame('warning', $sslWarningReport['live_domain']['status']);
        $this->assertStringContainsString('Live-Domain', $sslWarningReport['headline']);

        // 4. Echter Ausfall HTTP 502 -> critical + SystemLog Deduplizierung & Auto-Heal bei Erholung!
        $outageReport = $svc->evaluate(
            source: 'synology_nas',
            strict: true,
            recordSample: false,
            syncLogs: true,
            overrideSslDays: 60,
            overrideLiveDomain: [
                'reachable' => true,
                'http_status' => 502,
                'latency_ms' => 190.0,
                'ssl_days_remaining' => 75,
            ]
        );
        $this->assertSame('critical', $outageReport['status']);
        $this->assertFalse($outageReport['healthy']);
        $this->assertSame('error', $outageReport['live_domain']['mode']);
        $this->assertSame('critical', $outageReport['live_domain']['status']);
        $this->assertStringContainsString('Live-Domain', $outageReport['headline']);
        $this->assertSame(1, SystemLog::openIssues()->where('action_id', 'watchtower:pillar:live_domain')->count());

        // Erholung zurück in Wartungsmodus 503 heilt den SystemLog-Eintrag automatisch!
        $healedReport = $svc->evaluate(
            source: 'synology_nas',
            strict: true,
            recordSample: false,
            syncLogs: true,
            overrideSslDays: 60,
            overrideLiveDomain: [
                'reachable' => true,
                'http_status' => 503,
                'ssl_days_remaining' => 75,
            ]
        );
        $this->assertSame('ok', $healedReport['status']);
        $this->assertSame(0, SystemLog::openIssues()->where('action_id', 'watchtower:pillar:live_domain')->count());
    }

    public function test_live_domain_probe_reports_digital_domain_modes_503_200_and_pending_ingress_without_false_main_shop_alarm(): void
    {
        Storage::fake('local');
        Config::set('backup.backup.destination.disks', ['local']);
        Config::set('backup.backup.name', 'seelenfunke-test-backup');
        Config::set('queue.default', 'sync');
        Storage::disk('local')->put('seelenfunke-test-backup/fresh.zip', 'ZIP');
        Cache::put('scheduler_last_run', now()->timestamp, 600);
        DB::table('failed_jobs')->delete();

        $svc = app(SystemHealthPulseService::class);
        $this->assertSame('digital.mein-seelenfunke.de', SystemHealthPulseService::LIVE_DIGITAL_HOST);

        $defs = $svc->monitoredSubdomainDefinitions();
        $this->assertSame('digital.mein-seelenfunke.de', $defs['digital']['host']);
        $this->assertSame(1.5, $defs['digital']['timeout']);
        $this->assertSame(1.5, $defs['digital']['connect_timeout']);

        $scenario = 'pending_ingress';
        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$scenario) {
            $url = $request->url();

            if (str_contains($url, 'https://digital.mein-seelenfunke.de')) {
                return match ($scenario) {
                    'digital_maintenance' => Http::response('Digital Wartung', 503),
                    'digital_live' => Http::response('<html><meta name="csrf-token" content="ok">Blueprints</html>', 200),
                    default => Http::response('Unconfigured', 404),
                };
            }

            if (str_contains($url, 'https://mein-seelenfunke.de')) {
                return $scenario === 'digital_maintenance'
                    ? Http::response('<html><meta name="csrf-token" content="ok">Livewire</html>', 200)
                    : Http::response('Wartung', 503);
            }

            if (str_contains($url, 'https://www.mein-seelenfunke.de')) {
                return Http::response('', 301);
            }

            if (str_contains($url, 'https://stage.mein-seelenfunke.de')) {
                return Http::response('Stage OK', 200);
            }

            return Http::response(['status' => 'ok'], 200);
        });

        // 1. DNS/Ingress noch nicht aufgeschaltet -> pending_ingress (kein Fehlalarm)
        SystemHealthPulseService::setDnsResolver(function (string $host): array {
            return $host === SystemHealthPulseService::LIVE_DIGITAL_HOST ? [] : ['185.100.10.20'];
        });

        Cache::forget(SystemHealthPulseService::LIVE_DOMAIN_CACHE_KEY);
        $pendingReport = $svc->evaluate(source: 'dashboard', strict: true, recordSample: false, syncLogs: false, overrideSslDays: 60);
        $this->assertSame('ok', $pendingReport['status']);
        $this->assertTrue($pendingReport['healthy']);
        $this->assertSame('digital.mein-seelenfunke.de', $pendingReport['live_domain']['digital_domain']);
        $this->assertSame('https://digital.mein-seelenfunke.de', $pendingReport['live_domain']['digital_url']);
        $this->assertFalse($pendingReport['live_domain']['digital_ingress_active']);
        $this->assertSame(0, $pendingReport['live_domain']['digital_http_status']);
        $this->assertSame('pending_ingress', $pendingReport['live_domain']['digital_mode']);

        // 2. Digital-Domain im Vorab-Schutz (HTTP 503) bei aktivem Haupt-Shop (HTTP 200) -> digital_mode = maintenance, Haupt-Shop bleibt grün!
        $scenario = 'digital_maintenance';
        SystemHealthPulseService::setDnsResolver(fn (): array => ['185.100.10.20']);

        Cache::forget(SystemHealthPulseService::LIVE_DOMAIN_CACHE_KEY);
        $digitalMaintReport = $svc->evaluate(source: 'dashboard', strict: true, recordSample: false, syncLogs: false, overrideSslDays: 60);
        $this->assertSame('ok', $digitalMaintReport['status']);
        $this->assertTrue($digitalMaintReport['healthy']);
        $this->assertSame([], $digitalMaintReport['live_domain']['issues']);
        $this->assertSame('live_shop', $digitalMaintReport['live_domain']['mode']);
        $this->assertTrue($digitalMaintReport['live_domain']['digital_ingress_active']);
        $this->assertSame(503, $digitalMaintReport['live_domain']['digital_http_status']);
        $this->assertSame('maintenance', $digitalMaintReport['live_domain']['digital_mode']);

        // 3. Digital-Domain öffentlich freigeschaltet (HTTP 200) -> digital_mode = live_shop
        $scenario = 'digital_live';

        Cache::forget(SystemHealthPulseService::LIVE_DOMAIN_CACHE_KEY);
        $digitalLiveReport = $svc->evaluate(source: 'dashboard', strict: true, recordSample: false, syncLogs: false, overrideSslDays: 60);
        $this->assertSame('ok', $digitalLiveReport['status']);
        $this->assertTrue($digitalLiveReport['healthy']);
        $this->assertSame('maintenance', $digitalLiveReport['live_domain']['mode']);
        $this->assertTrue($digitalLiveReport['live_domain']['digital_ingress_active']);
        $this->assertSame(200, $digitalLiveReport['live_domain']['digital_http_status']);
        $this->assertSame('live_shop', $digitalLiveReport['live_domain']['digital_mode']);
    }
}

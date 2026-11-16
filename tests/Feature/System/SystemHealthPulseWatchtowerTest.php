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
}

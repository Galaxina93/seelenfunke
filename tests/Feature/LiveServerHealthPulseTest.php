<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\System\SystemHealthPulseService;
use Database\Seeders\AiAgentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiveServerHealthPulseTest extends TestCase
{
    use RefreshDatabase;

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

    private function createSanctumAdmin(): array
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-live-pulse-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);

        $plainToken = $admin->createToken('FunkenwerkAndroidLive', ['*'])->plainTextToken;
        Auth::forgetGuards();

        return [$admin, $plainToken];
    }

    public function test_live_credentials_as_sanctum_admin_for_falcone_includes_server_status_and_health_declarations(): void
    {
        $this->seed(AiAgentSeeder::class);
        Config::set('services.gemini.key', 'test-gemini-live-key');

        $falcone = AiAgent::where('name', 'Falcone')->firstOrFail();
        [, $bearerToken] = $this->createSanctumAdmin();

        // 1. Aufruf exakt wie Android-App mit Bearer-Token (ohne Session-Cookie) und agent_id
        $resById = $this->withHeader('Authorization', 'Bearer ' . $bearerToken)
            ->getJson('/api/ai/live-credentials?agent_id=' . $falcone->id . '&client=android_native&fallback=direct');

        $resById->assertStatus(200)
            ->assertJsonPath('agent_id', $falcone->id)
            ->assertJsonPath('agent_name', 'Falcone');

        $declarations = $resById->json('tools.0.functionDeclarations');
        $this->assertIsArray($declarations);
        $declaredNames = array_column($declarations, 'name');

        $this->assertContains('system_get_server_status', $declaredNames);
        $this->assertContains('system_get_health', $declaredNames);
        $this->assertContains('system_get_error_sentinel', $declaredNames);
        $this->assertContains('master_get_next_best_actions', $declaredNames);
        $this->assertContains(FunkiraSkillPoolService::EQUIP_TOOL_NAME, $declaredNames);

        // 2. Aufruf über agent_name=Falcone funktioniert ebenfalls nahtlos
        Auth::forgetGuards();
        $resByName = $this->withHeader('Authorization', 'Bearer ' . $bearerToken)
            ->getJson('/api/ai/live-credentials?agent_name=Falcone&client=android_native');

        $resByName->assertStatus(200)
            ->assertJsonPath('agent_name', 'Falcone');

        $declaredNamesByName = array_column($resByName->json('tools.0.functionDeclarations'), 'name');
        $this->assertContains('system_get_server_status', $declaredNamesByName);
        $this->assertContains('system_get_health', $declaredNamesByName);
    }

    public function test_execute_endpoint_as_sanctum_bearer_admin_without_session_succeeds_for_server_status_and_health_with_live_domains(): void
    {
        Storage::fake('local');
        Config::set('backup.backup.destination.disks', ['local']);
        Config::set('backup.backup.name', 'seelenfunke-test-backup');
        Config::set('queue.default', 'sync');
        Storage::disk('local')->put('seelenfunke-test-backup/fresh.zip', 'PK-ZIP');
        Cache::put('scheduler_last_run', now()->timestamp, 600);
        DB::table('failed_jobs')->delete();

        $this->seed(AiAgentSeeder::class);
        $falcone = AiAgent::where('name', 'Falcone')->firstOrFail();
        [, $bearerToken] = $this->createSanctumAdmin();

        SystemHealthPulseService::setLiveDomainProbeResolver(function (): array {
            return [
                'probed' => true,
                'reachable' => true,
                'http_status' => 503,
                'latency_ms' => 34.8,
                'ssl_days_remaining' => 61,
                'ssl_valid_until' => now()->addDays(61)->toIso8601String(),
                'body_bytes' => 4100,
                'has_shop_markers' => false,
                'www_ingress_active' => true,
                'www_http_status' => 503,
            ];
        });

        // 1. Exakt wie Android FunkiraLiveViewModel -> AiRepository.executeTool():
        //    Nur Bearer-Token, KEIN Session-Cookie, KEINE session_id, KEINE agent_id (Fallback -> Falcone -> Funkira)
        Auth::forgetGuards();
        $serverStatusRes = $this->withHeader('Authorization', 'Bearer ' . $bearerToken)
            ->postJson('/api/ai/execute', [
                'function' => 'system_get_server_status',
                'args' => ['force_refresh' => true],
            ]);

        $serverStatusRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('function', 'system_get_server_status')
            ->assertJsonPath('result.status', 'success')
            ->assertJsonPath('result.healthy', true)
            ->assertJsonPath('result.live_server.domain', 'mein-seelenfunke.de')
            ->assertJsonPath('result.live_server.mode', 'maintenance')
            ->assertJsonPath('result.live_server.http_status', 503)
            ->assertJsonPath('result.live_domains.domain', 'mein-seelenfunke.de')
            ->assertJsonPath('result.live_domains.www_domain', 'www.mein-seelenfunke.de');

        // 2. system_get_health exakt wie Android (Bearer-Token ohne Session-Cookie, ohne session_id)
        Auth::forgetGuards();
        $healthRes = $this->withHeader('Authorization', 'Bearer ' . $bearerToken)
            ->postJson('/api/ai/execute', [
                'function' => 'system_get_health',
                'args' => [],
            ]);

        $healthRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('function', 'system_get_health')
            ->assertJsonPath('result.status', 'success')
            ->assertJsonPath('result.is_healthy', true)
            ->assertJsonPath('result.healthy', true)
            ->assertJsonPath('result.live_domains.domain', 'mein-seelenfunke.de')
            ->assertJsonPath('result.live_domains.mode', 'maintenance')
            ->assertJsonPath('result.live_domains.ssl_days_remaining', 61)
            ->assertJsonPath('result.live_server.domain', 'mein-seelenfunke.de')
            ->assertJsonStructure([
                'status',
                'function',
                'result' => [
                    'status',
                    'is_healthy',
                    'healthy',
                    'overall_status',
                    'headline',
                    'live_domains',
                    'live_server',
                    'stage_and_portal' => ['environment', 'uptime_90d_percent', 'pillars', 'watchtower'],
                ],
            ]);

        // 3. Expliziter Aufruf mit agent_id = Falcone & agent_name = Falcone
        Auth::forgetGuards();
        $explicitFalconeRes = $this->withHeader('Authorization', 'Bearer ' . $bearerToken)
            ->postJson('/api/ai/execute', [
                'function' => 'system_get_health',
                'agent_id' => $falcone->id,
                'args' => ['force_refresh' => true],
            ]);

        $explicitFalconeRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('result.status', 'success')
            ->assertJsonPath('result.live_domains.domain', 'mein-seelenfunke.de');

        Auth::forgetGuards();
        $byNameRes = $this->withHeader('Authorization', 'Bearer ' . $bearerToken)
            ->postJson('/api/ai/execute', [
                'function' => 'system_get_server_status',
                'agent_name' => 'Falcone',
                'args' => [],
            ]);

        $byNameRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('result.status', 'success')
            ->assertJsonPath('result.live_server.domain', 'mein-seelenfunke.de');
    }

    public function test_executive_override_migration_network_fallback_and_non_admin_isolation(): void
    {
        $this->seed(AiAgentSeeder::class);

        $falcone = AiAgent::where('name', 'Falcone')->firstOrFail();
        $funkira = AiAgent::where('name', 'Funkira')->firstOrFail();
        $funki = AiAgent::where('name', 'Funki')->firstOrFail();

        // Simuliere abweichende Spezial-Rolle auf Stage ohne jegliche Tools
        $customRole = AiRole::create([
            'name' => 'Mitgruender Leitung Custom',
            'description' => 'Abweichende Stage-Rolle ohne verknüpfte Tools',
        ]);
        $falcone->update(['ai_role_id' => $customRole->id]);

        [, $bearerToken] = $this->createSanctumAdmin();

        // Simuliere Netzwerk-Exception beim Live-Domain-Probe -> darf Health-Call NIEMALS sprengen!
        SystemHealthPulseService::setLiveDomainProbeResolver(function (): array {
            throw new \RuntimeException('Simulated DNS / TLS socket timeout');
        });

        Auth::forgetGuards();
        $res = $this->withHeader('Authorization', 'Bearer ' . $bearerToken)
            ->postJson('/api/ai/execute', [
                'function' => 'system_get_health',
                'agent_id' => $falcone->id,
                'args' => ['force_refresh' => true],
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('result.status', 'success')
            ->assertJsonPath('result.live_domains.domain', 'mein-seelenfunke.de')
            ->assertJsonPath('result.live_domains.reachable', false);

        // Migration 2026_11_16_171500 verknüpft system_get_server_status & system_get_health idempotent mit allen Executive-/Admin-Rollen
        $migration = require base_path('database/migrations/2026_11_16_171500_ensure_server_status_tools_for_all_executive_agents.php');
        $migration->applyUpgrade();

        $falconeTools = $falcone->fresh(['role.tools'])->tools->pluck('identifier')->all();
        $funkiraTools = $funkira->fresh(['role.tools'])->tools->pluck('identifier')->all();
        $funkiTools = $funki->fresh(['role.tools'])->tools->pluck('identifier')->all();

        $this->assertContains('system_get_server_status', $falconeTools);
        $this->assertContains('system_get_health', $falconeTools);
        $this->assertContains('system_get_server_status', $funkiraTools);
        $this->assertContains('system_get_health', $funkiraTools);

        // Funki (Supporter) bleibt strikt isoliert!
        $this->assertNotContains('system_get_server_status', $funkiTools);
        $this->assertNotContains('system_get_health', $funkiTools);

        // Unauthentifizierte Gäste (401/403) sowie Kunden & Mitarbeiter (403) bleiben strikt gesperrt
        $this->flushHeaders();
        Auth::forgetGuards();
        $guestStatus = $this->postJson('/api/ai/execute', ['function' => 'system_get_server_status'])->status();
        $this->assertContains($guestStatus, [401, 403]);

        $customer = Customer::factory()->create();
        $custToken = $customer->createToken('CustTok', ['*'])->plainTextToken;
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $custToken)
            ->postJson('/api/ai/execute', ['function' => 'system_get_health'])
            ->assertStatus(403);

        $employee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Intern',
            'email' => 'emp-live-pulse-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
            'role' => 'internal',
            'password' => bcrypt('Secret123!'),
        ]);
        $empToken = $employee->createToken('EmpTok', ['*'])->plainTextToken;
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $empToken)
            ->postJson('/api/ai/execute', ['function' => 'system_get_health'])
            ->assertStatus(403);
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\FunkiraSkillPoolService;
use Database\Seeders\AiAgentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FalconSupremeExecutiveBrainTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-funkira-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_executive_root_recognizes_solely_funkira_removes_falcone_and_keeps_funki_strictly_air_gapped(): void
    {
        $this->seed(AiAgentSeeder::class);

        $this->assertNull(AiAgent::where('name', 'Falcone')->first());
        $this->assertNull(AiAgent::where('name', 'Falcon')->first());
        $this->assertNull(AiAgent::where('name', 'Agenti')->first());

        $funkira = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();
        $funki = AiAgent::where('name', 'Funki')->with('role.tools')->firstOrFail();

        foreach ([$funkira, 'Funkira', 'funkira', 'FUNKIRA', ['name' => 'Funkira']] as $execCandidate) {
            $this->assertTrue(FunkiraSkillPoolService::isExecutiveRoot($execCandidate));
            $this->assertTrue(FunkiraSkillPoolService::isFunkira($execCandidate));
        }

        // Falcone, Falcon, Funki & Fachspezialisten -> strikt false
        foreach ([$funki, 'Falcone', 'Falcon', 'Funki', 'Buchi', ['name' => 'Falcone'], '', null] as $nonExec) {
            $this->assertFalse(FunkiraSkillPoolService::isExecutiveRoot($nonExec));
            $this->assertFalse(FunkiraSkillPoolService::isFunkira($nonExec));
        }

        // Strikte Air-Gap-Isolation für Kunden-Agent Funki bleibt 100 % aktiv (auch bei aktivem Admin!)
        $admin = $this->createAdmin();
        Auth::guard('admin')->login($admin);

        foreach ([
            FunkiraSkillPoolService::EQUIP_TOOL_NAME => ['pools' => ['finance']],
            'master_get_next_best_actions' => [],
            'system_get_error_sentinel' => [],
            'finance_list_fixed_costs' => [],
        ] as $toolName => $args) {
            $blocked = AIFunctionsRegistry::execute($toolName, $args, $funki);
            $this->assertTrue($blocked['error'] ?? false, "Funki must remain air-gapped from {$toolName}");
            $this->assertStringContainsString('SANDBOX VIOLATION', (string) ($blocked['message'] ?? ''));
        }
    }

    public function test_funkira_possesses_aoede_voice_smarthome_and_inactivity_directives_and_executes_executive_tools(): void
    {
        $this->seed(AiAgentSeeder::class);

        $funkira = AiAgent::where('name', 'Funkira')->with(['department', 'role.tools'])->firstOrFail();
        $this->assertFunkiraExecutiveContract($funkira);

        $admin = $this->createAdmin();
        Auth::guard('admin')->login($admin);

        $compassRes = AIFunctionsRegistry::execute('master_get_next_best_actions', [], $funkira);
        $this->assertFalse($compassRes['error'] ?? false);

        $sentinelRes = AIFunctionsRegistry::execute('system_get_error_sentinel', [], $funkira);
        $this->assertFalse($sentinelRes['error'] ?? false);

        $equipRes = AIFunctionsRegistry::execute(
            FunkiraSkillPoolService::EQUIP_TOOL_NAME,
            ['pools' => ['finance', 'orders', 'laser', 'products', 'system_global']],
            $funkira
        );
        $this->assertSame('success', $equipRes['status'] ?? null);
    }

    public function test_live_credentials_for_funkira_android_native_delivers_aoede_presence_prompt_and_blocks_unauthorized_actors(): void
    {
        $this->seed(AiAgentSeeder::class);
        config(['services.gemini.key' => 'test-gemini-live-key']);

        $funkira = AiAgent::where('name', 'Funkira')->firstOrFail();
        $uri = '/api/ai/live-credentials?agent_id=' . $funkira->id . '&client=android_native&channel=android_live_call';

        // 1. Gast -> 401
        $this->getJson($uri)->assertStatus(401);

        // 2. Kunde -> 403
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer, ['*']);
        $this->getJson($uri)->assertStatus(403);

        // 3. Interne Mitarbeiter, Influencer & Steuerberater -> 403
        foreach (['internal', 'influencer', 'tax_advisor'] as $role) {
            $emp = Employee::create([
                'first_name' => ucfirst($role),
                'last_name' => 'Test',
                'email' => $role . '-funkira-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
                'role' => $role,
                'password' => bcrypt('Secret123!'),
            ]);
            Sanctum::actingAs($emp, ['*']);
            $this->getJson($uri)->assertStatus(403);
        }

        // 4. Eingeschränkter monitor:pulse-Admin-Token -> 403
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['monitor:pulse']);
        $this->getJson($uri)->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $monitorPat = $admin->createToken('MonitorPulseOnly', ['monitor:pulse'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $monitorPat)
            ->getJson($uri)
            ->assertStatus(403);

        // 5. Voll-Admin-Token -> 200 mit Aoede, Funkira-Prompt, Orts-/Umgebungsbewusstsein & Tool-Schema
        $this->app['auth']->forgetGuards();
        $fullAdminPat = $admin->createToken('AlinaAndroidPhone', ['*'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $fullAdminPat)
            ->getJson($uri);

        $response->assertStatus(200)
            ->assertJsonPath('agent_id', $funkira->id)
            ->assertJsonPath('agent_name', 'Funkira')
            ->assertJsonPath('voice_name', 'Aoede');

        $systemInstruction = (string) $response->json('system_instruction');
        $this->assertStringContainsString('Funkira', $systemInstruction);
        $this->assertStringContainsString('falcon_smarthome_control', $systemInstruction);
        $this->assertStringContainsString('60-SEKUNDEN-INAKTIVITÄTS-NACHFRAGE-DISZIPLIN', $systemInstruction);
        $this->assertStringContainsString('master_get_next_best_actions', $systemInstruction);
        $this->assertStringContainsString('system_get_error_sentinel', $systemInstruction);
        $this->assertStringContainsString('AUSSCHLIESSLICH AUF EXPLIZITE NACHFRAGE', $systemInstruction);
        $this->assertStringContainsString('[AKTIVER KONTEXT: Ort=Funkenwerk Android-App (3D Live-Call)', $systemInstruction);

        // Legacy-Fallback: agent_name=Falcone liefert nahtlos Funkira zurück
        $fallbackRes = $this->withHeader('Authorization', 'Bearer ' . $fullAdminPat)
            ->getJson('/api/ai/live-credentials?agent_name=Falcone&client=android_native');
        $fallbackRes->assertStatus(200)
            ->assertJsonPath('agent_name', 'Funkira');

        $declarations = $response->json('tools.0.functionDeclarations');
        $this->assertIsArray($declarations);
        $this->assertNotEmpty($declarations);

        $declaredNames = array_column($declarations, 'name');
        $this->assertContains('task_get_all', $declaredNames);
        $this->assertContains('order_get_urgent_tasks', $declaredNames);
        $this->assertContains('routine_get_day_routines', $declaredNames);
        $this->assertContains('calendar_get_events', $declaredNames);
        $this->assertContains('brain_search', $declaredNames);
        $this->assertNotEmpty($response->json('direct_ws_url'));
    }

    private function assertFunkiraExecutiveContract(AiAgent $funkira): void
    {
        $this->assertSame('Funkira', $funkira->name);
        $this->assertSame('Funkira', $funkira->wake_word);
        $this->assertSame('Aoede', $funkira->tts_voice);
        $this->assertTrue($funkira->tts_enabled);
        $this->assertSame('gemini_native', $funkira->tts_provider);
        $this->assertTrue($funkira->is_active);
        $this->assertTrue($funkira->is_in_chat);
        $this->assertSame('Leitung', $funkira->department?->name);
        $this->assertSame('Teamleiter', $funkira->role?->name);

        $toolIdentifiers = $funkira->tools->pluck('identifier')->all();
        $this->assertContains(FunkiraSkillPoolService::EQUIP_TOOL_NAME, $toolIdentifiers);
        $this->assertContains('master_get_next_best_actions', $toolIdentifiers);
        $this->assertContains('system_get_server_status', $toolIdentifiers);
        $this->assertContains('system_get_error_sentinel', $toolIdentifiers);
        $this->assertContains('ticket_set_status_or_ask', $toolIdentifiers);

        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', (string) $funkira->role_description);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', (string) $funkira->system_prompt);

        foreach ([
            'Funkira',
            'Alina Steinhauer',
            'xTool F2 Ultra UV',
            'FYRST',
            '60-SEKUNDEN-INAKTIVITÄTS-NACHFRAGE-DISZIPLIN',
            'master_get_next_best_actions',
            'system_get_server_status',
            'mein-seelenfunke.de',
            'system_get_error_sentinel',
            'AUSSCHLIESSLICH AUF EXPLIZITE NACHFRAGE',
            'falcon_music_control',
            'falcon_app_navigate',
            'falcon_smarthome_control',
            'Fußleisten Lichterkette',
            'Spiegel Lichterkette',
            'falcon_camera_control',
            'take_photo',
            'falcon_send_to_antigravity',
            'falcon_check_antigravity_status',
            'execution_mode="concept"',
            'execution_mode="direct"',
            'attach_captured_photo=true',
        ] as $expectedSnippet) {
            $this->assertStringContainsString($expectedSnippet, (string) $funkira->system_prompt);
        }
    }

    public function test_system_get_server_status_executes_for_funkira_supports_force_refresh_and_enforces_role_isolation(): void
    {
        $this->seed(AiAgentSeeder::class);

        $funkira = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();
        $funki = AiAgent::where('name', 'Funki')->with('role.tools')->firstOrFail();

        $this->assertContains('system_get_server_status', FunkiraSkillPoolService::getCoreToolIdentifiers());
        $this->assertContains('system_get_server_status', FunkiraSkillPoolService::getActiveToolIdentifiers(['system_global']));
        $this->assertContains('system_get_server_status', FunkiraSkillPoolService::getActiveToolIdentifiers(['system_workspace']));

        $guestRes = AIFunctionsRegistry::executeGetServerStatus([]);
        $this->assertTrue($guestRes['error'] ?? false);

        $customer = Customer::factory()->create();
        Auth::guard('customer')->login($customer);
        $custRes = AIFunctionsRegistry::execute('system_get_server_status', [], $funkira);
        $this->assertTrue($custRes['error'] ?? false);
        Auth::guard('customer')->logout();

        foreach (['internal', 'influencer', 'tax_advisor'] as $empRole) {
            $emp = Employee::create([
                'first_name' => 'Emp',
                'last_name' => $empRole,
                'email' => $empRole . '-srv-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
                'role' => $empRole,
                'password' => bcrypt('Secret123!'),
            ]);
            Auth::guard('employee')->login($emp);
            $empRes = AIFunctionsRegistry::execute('system_get_server_status', [], $funkira);
            $this->assertTrue($empRes['error'] ?? false);
            $this->assertStringContainsString('ROLE VIOLATION', (string) ($empRes['message'] ?? ''));
            Auth::guard('employee')->logout();
        }

        $admin = $this->createAdmin();
        Auth::guard('admin')->login($admin);

        $funkiBlocked = AIFunctionsRegistry::execute('system_get_server_status', [], $funki);
        $this->assertTrue($funkiBlocked['error'] ?? false);
        $this->assertStringContainsString('SANDBOX VIOLATION', (string) ($funkiBlocked['message'] ?? ''));

        $pulseSvc = app(\App\Services\System\SystemHealthPulseService::class);
        $pulseSvc->resetStateForTesting();

        $probeCalls = 0;
        \App\Services\System\SystemHealthPulseService::setLiveDomainProbeResolver(function () use (&$probeCalls): array {
            $probeCalls++;
            return [
                'reachable' => true,
                'http_status' => $probeCalls === 1 ? 200 : 503,
                'latency_ms' => 36.4,
                'ssl_days_remaining' => 82,
                'body_bytes' => 1800,
                'has_shop_markers' => false,
                'www_ingress_active' => false,
                'www_http_status' => 0,
            ];
        });

        try {
            $first = AIFunctionsRegistry::execute('system_get_server_status', ['force_refresh' => false], $funkira);
            $this->assertSame('success', $first['status'] ?? null);
            $this->assertSame(1, $probeCalls);

            $second = AIFunctionsRegistry::execute('system_get_server_status', ['force_refresh' => false], $funkira);
            $this->assertSame(1, $probeCalls);

            $refreshed = AIFunctionsRegistry::execute('system_get_server_status', ['force_refresh' => true], $funkira);
            $this->assertSame('success', $refreshed['status'] ?? null);
            $this->assertTrue($refreshed['force_refreshed']);
            $this->assertSame(2, $probeCalls);
            $this->assertSame('maintenance', $refreshed['live_server']['mode']);
        } finally {
            $pulseSvc->resetStateForTesting();
        }
    }
}

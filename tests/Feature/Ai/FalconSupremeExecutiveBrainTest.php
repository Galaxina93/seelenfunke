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
            'email' => 'alina-falcon-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_executive_root_recognizes_falcon_and_funkira_while_keeping_funki_strictly_air_gapped(): void
    {
        $this->seed(AiAgentSeeder::class);

        $falcon = AiAgent::where('name', 'Falcon')->with('role.tools')->firstOrFail();
        $funkira = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();
        $funki = AiAgent::where('name', 'Funki')->with('role.tools')->firstOrFail();

        // String, Model-Objekt & Array (case-insensitive) für Falcon & Funkira -> true
        foreach ([$falcon, $funkira, 'Falcon', 'falcon', 'FALCON', 'Funkira', 'funkira', ['name' => 'Falcon'], ['name' => 'Funkira']] as $execCandidate) {
            $this->assertTrue(FunkiraSkillPoolService::isExecutiveRoot($execCandidate));
            $this->assertTrue(FunkiraSkillPoolService::isFunkira($execCandidate));
        }

        // Funki, Ruheständler, Null & leere Eingaben -> strikt false
        foreach ([$funki, 'Funki', 'funki', 'Buchi', ['name' => 'Funki'], '', null] as $nonExec) {
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

    public function test_falcon_possesses_orus_voice_leitung_department_core_tools_and_supreme_prompt_in_seeder_and_migration(): void
    {
        $this->seed(AiAgentSeeder::class);

        $falconFromSeeder = AiAgent::where('name', 'Falcon')->with(['department', 'role.tools'])->firstOrFail();
        $this->assertFalconExecutiveContract($falconFromSeeder);

        // Simuliere ein älteres/abweichendes Falcon-Profil und führe das Migrations-Upgrade aus
        $falconFromSeeder->update([
            'tts_voice' => 'Puck',
            'system_prompt' => 'Alter Platzhalter',
            'is_in_chat' => true,
        ]);

        $migration = require base_path('database/migrations/2026_11_16_121500_upgrade_falcon_supreme_executive_brain.php');
        $migration->applyUpgrade();

        $falconAfterMigration = AiAgent::where('name', 'Falcon')->with(['department', 'role.tools'])->firstOrFail();
        $this->assertFalconExecutiveContract($falconAfterMigration);

        // Prüfe direkte Ausführbarkeit der Core-Executive-Werkzeuge für Falcon unter Admin-Auth
        $admin = $this->createAdmin();
        Auth::guard('admin')->login($admin);

        $compassRes = AIFunctionsRegistry::execute('master_get_next_best_actions', [], $falconAfterMigration);
        $this->assertFalse($compassRes['error'] ?? false);

        $sentinelRes = AIFunctionsRegistry::execute('system_get_error_sentinel', [], $falconAfterMigration);
        $this->assertFalse($sentinelRes['error'] ?? false);

        $equipRes = AIFunctionsRegistry::execute(
            FunkiraSkillPoolService::EQUIP_TOOL_NAME,
            ['pools' => ['finance', 'orders', 'laser', 'products', 'system_global']],
            $falconAfterMigration
        );
        $this->assertSame('success', $equipRes['status'] ?? null);
        $this->assertEqualsCanonicalizing(
            ['finance', 'orders', 'laser', 'products', 'system_global'],
            $equipRes['equipped_pools'] ?? []
        );
    }

    public function test_live_credentials_for_falcon_android_native_delivers_orus_prompt_and_tools_and_blocks_unauthorized_actors(): void
    {
        $this->seed(AiAgentSeeder::class);
        config(['services.gemini.key' => 'test-gemini-live-key']);

        $falcon = AiAgent::where('name', 'Falcon')->firstOrFail();
        $uri = '/api/ai/live-credentials?agent_id=' . $falcon->id . '&client=android_native';

        // 1. Gast -> 401
        $this->getJson($uri)->assertStatus(401);

        // 2. Kunde -> 403
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer, ['*']);
        $this->getJson($uri)->assertStatus(403);

        // 3. Interne Mitarbeiter, Influencer & Steuerberater -> 403 (Falcon ist strikt Admin-exklusiv!)
        foreach (['internal', 'influencer', 'tax_advisor'] as $role) {
            $emp = Employee::create([
                'first_name' => ucfirst($role),
                'last_name' => 'Test',
                'email' => $role . '-falcon-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
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

        // 5. Voll-Admin-Token -> 200 mit Orus, Falcon-Prompt, Tool-Schema & Direct-WS-URL
        $this->app['auth']->forgetGuards();
        $fullAdminPat = $admin->createToken('AlinaAndroidPhone', ['*'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $fullAdminPat)
            ->getJson($uri);

        $response->assertStatus(200)
            ->assertJsonPath('agent_id', $falcon->id)
            ->assertJsonPath('agent_name', 'Falcon')
            ->assertJsonPath('voice_name', 'Orus');

        $systemInstruction = (string) $response->json('system_instruction');
        $this->assertStringContainsString('Falcon', $systemInstruction);
        $this->assertStringContainsString('master_get_next_best_actions', $systemInstruction);
        $this->assertStringContainsString('system_get_error_sentinel', $systemInstruction);
        $this->assertStringContainsString('AUSSCHLIESSLICH AUF EXPLIZITE NACHFRAGE', $systemInstruction);
        $this->assertStringContainsString('falcon_music_control', $systemInstruction);
        $this->assertStringContainsString('falcon_camera_control', $systemInstruction);
        $this->assertStringContainsString('falcon_send_to_antigravity', $systemInstruction);
        $this->assertStringContainsString('falcon_check_antigravity_status', $systemInstruction);

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

    private function assertFalconExecutiveContract(AiAgent $falcon): void
    {
        $this->assertSame('Falcon', $falcon->name);
        $this->assertSame('Orus', $falcon->tts_voice);
        $this->assertTrue($falcon->tts_enabled);
        $this->assertSame('gemini_native', $falcon->tts_provider);
        $this->assertTrue($falcon->is_active);
        $this->assertFalse($falcon->is_in_chat);
        $this->assertSame('amber-500', $falcon->color);
        $this->assertSame('shop/ai/images/falcon_selfie.png', $falcon->profile_picture);
        $this->assertSame('Leitung', $falcon->department?->name);
        $this->assertSame('Teamleiter', $falcon->role?->name);

        $toolIdentifiers = $falcon->tools->pluck('identifier')->all();
        $this->assertContains(FunkiraSkillPoolService::EQUIP_TOOL_NAME, $toolIdentifiers);
        $this->assertContains('master_get_next_best_actions', $toolIdentifiers);
        $this->assertContains('system_get_error_sentinel', $toolIdentifiers);

        // Keine KI-Gedankenstriche (" – ")
        $this->assertStringNotContainsString(' – ', (string) $falcon->role_description);
        $this->assertStringNotContainsString(' – ', (string) $falcon->system_prompt);

        // Alle geforderten Executive-, Smartphone-, Kamera-, Musik- & Antigravity-Direktiven im System-Prompt
        foreach ([
            'Falcon',
            'Alina Steinhauer',
            'xTool F2 Ultra UV',
            'FYRST',
            'master_get_next_best_actions',
            'system_get_error_sentinel',
            'AUSSCHLIESSLICH AUF EXPLIZITE NACHFRAGE',
            'falcon_music_control',
            'falcon_app_navigate',
            'falcon_camera_control',
            'take_photo',
            'falcon_send_to_antigravity',
            'falcon_check_antigravity_status',
            'execution_mode="concept"',
            'execution_mode="direct"',
            'attach_captured_photo=true',
        ] as $expectedSnippet) {
            $this->assertStringContainsString($expectedSnippet, (string) $falcon->system_prompt);
        }
    }
}

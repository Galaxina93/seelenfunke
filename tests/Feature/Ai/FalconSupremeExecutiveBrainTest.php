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
            'email' => 'alina-falcone-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_executive_root_recognizes_falcone_falcon_and_funkira_while_keeping_funki_strictly_air_gapped(): void
    {
        $this->seed(AiAgentSeeder::class);

        $falcone = AiAgent::where('name', 'Falcone')->with('role.tools')->firstOrFail();
        $funkira = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();
        $funki = AiAgent::where('name', 'Funki')->with('role.tools')->firstOrFail();

        // String, Model-Objekt & Array (case-insensitive + abwärtskompatibel für Falcon & Falcone) -> true
        foreach ([
            $falcone, $funkira,
            'Falcone', 'falcone', 'FALCONE',
            'Falcon', 'falcon', 'FALCON',
            'Funkira', 'funkira',
            ['name' => 'Falcone'], ['name' => 'Falcon'], ['name' => 'Funkira'],
        ] as $execCandidate) {
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

    public function test_falcone_possesses_orus_voice_smarthome_and_inactivity_directives_and_renames_legacy_falcon_without_duplicates(): void
    {
        $this->seed(AiAgentSeeder::class);

        $falconeFromSeeder = AiAgent::where('name', 'Falcone')->with(['department', 'role.tools'])->firstOrFail();
        $originalUuid = $falconeFromSeeder->id;
        $this->assertFalconeExecutiveContract($falconeFromSeeder);

        // Simuliere einen älteren 'Falcon'-Datensatz vor der Umbenennungs-Migration -> muss in-place ohne Dublette umbenannt werden!
        $falconeFromSeeder->update([
            'name' => 'Falcon',
            'wake_word' => 'Falcon',
            'tts_voice' => 'Puck',
            'system_prompt' => 'Alter Platzhalter',
            'is_in_chat' => true,
        ]);

        $migration = require base_path('database/migrations/2026_11_16_135000_rename_falcon_to_falcone_and_add_smarthome_directive.php');
        $migration->applyUpgrade();

        $this->assertSame(0, AiAgent::where('name', 'Falcon')->count());
        $this->assertSame(1, AiAgent::where('name', 'Falcone')->count());

        $falconeAfterMigration = AiAgent::where('name', 'Falcone')->with(['department', 'role.tools'])->firstOrFail();
        $this->assertSame($originalUuid, $falconeAfterMigration->id);
        $this->assertFalconeExecutiveContract($falconeAfterMigration);

        // Prüfe direkte Ausführbarkeit der Core-Executive-Werkzeuge für Falcone unter Admin-Auth
        $admin = $this->createAdmin();
        Auth::guard('admin')->login($admin);

        $compassRes = AIFunctionsRegistry::execute('master_get_next_best_actions', [], $falconeAfterMigration);
        $this->assertFalse($compassRes['error'] ?? false);

        $sentinelRes = AIFunctionsRegistry::execute('system_get_error_sentinel', [], $falconeAfterMigration);
        $this->assertFalse($sentinelRes['error'] ?? false);

        $equipRes = AIFunctionsRegistry::execute(
            FunkiraSkillPoolService::EQUIP_TOOL_NAME,
            ['pools' => ['finance', 'orders', 'laser', 'products', 'system_global']],
            $falconeAfterMigration
        );
        $this->assertSame('success', $equipRes['status'] ?? null);
    }

    public function test_live_credentials_for_falcone_android_native_delivers_orus_prompt_and_tools_and_blocks_unauthorized_actors(): void
    {
        $this->seed(AiAgentSeeder::class);
        config(['services.gemini.key' => 'test-gemini-live-key']);

        $falcone = AiAgent::where('name', 'Falcone')->firstOrFail();
        $uri = '/api/ai/live-credentials?agent_id=' . $falcone->id . '&client=android_native';

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
                'email' => $role . '-falcone-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
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

        // 5. Voll-Admin-Token -> 200 mit Orus, Falcone-Prompt, SmartHome-Direktive, Tool-Schema & Direct-WS-URL
        $this->app['auth']->forgetGuards();
        $fullAdminPat = $admin->createToken('AlinaAndroidPhone', ['*'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $fullAdminPat)
            ->getJson($uri);

        $response->assertStatus(200)
            ->assertJsonPath('agent_id', $falcone->id)
            ->assertJsonPath('agent_name', 'Falcone')
            ->assertJsonPath('voice_name', 'Orus');

        $systemInstruction = (string) $response->json('system_instruction');
        $this->assertStringContainsString('Falcone', $systemInstruction);
        $this->assertStringContainsString('F-A-L-C-O-N-E', $systemInstruction);
        $this->assertStringContainsString('falcon_smarthome_control', $systemInstruction);
        $this->assertStringContainsString('2-MINUTEN-INAKTIVITÄTS-NACHFRAGE-DISZIPLIN', $systemInstruction);
        $this->assertStringContainsString('master_get_next_best_actions', $systemInstruction);
        $this->assertStringContainsString('system_get_error_sentinel', $systemInstruction);
        $this->assertStringContainsString('AUSSCHLIESSLICH AUF EXPLIZITE NACHFRAGE', $systemInstruction);

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

    private function assertFalconeExecutiveContract(AiAgent $falcone): void
    {
        $this->assertSame('Falcone', $falcone->name);
        $this->assertSame('Hey Falcone', $falcone->wake_word);
        $this->assertSame('Orus', $falcone->tts_voice);
        $this->assertTrue($falcone->tts_enabled);
        $this->assertSame('gemini_native', $falcone->tts_provider);
        $this->assertTrue($falcone->is_active);
        $this->assertFalse($falcone->is_in_chat);
        $this->assertSame('amber-500', $falcone->color);
        $this->assertSame('shop/ai/images/falcon_selfie.png', $falcone->profile_picture);
        $this->assertSame('Leitung', $falcone->department?->name);
        $this->assertSame('Teamleiter', $falcone->role?->name);

        $toolIdentifiers = $falcone->tools->pluck('identifier')->all();
        $this->assertContains(FunkiraSkillPoolService::EQUIP_TOOL_NAME, $toolIdentifiers);
        $this->assertContains('master_get_next_best_actions', $toolIdentifiers);
        $this->assertContains('system_get_error_sentinel', $toolIdentifiers);

        // Keine KI-Gedankenstriche (" – ")
        $this->assertStringNotContainsString(' – ', (string) $falcone->role_description);
        $this->assertStringNotContainsString(' – ', (string) $falcone->system_prompt);

        // Alle geforderten Executive-, SmartHome-, Inaktivitäts-, Kamera-, Musik- & Antigravity-Direktiven
        foreach ([
            'Falcone',
            'F-A-L-C-O-N-E',
            'Alina Steinhauer',
            'xTool F2 Ultra UV',
            'FYRST',
            '2-MINUTEN-INAKTIVITÄTS-NACHFRAGE-DISZIPLIN',
            'master_get_next_best_actions',
            'system_get_error_sentinel',
            'AUSSCHLIESSLICH AUF EXPLIZITE NACHFRAGE',
            'falcon_music_control',
            'falcon_app_navigate',
            'falcon_smarthome_control',
            'turn_on',
            'turn_off',
            'toggle',
            'turn_all_on',
            'turn_all_off',
            'list_devices',
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
            $this->assertStringContainsString($expectedSnippet, (string) $falcone->system_prompt);
        }
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Customer\Customer;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\AiAgentRouter;
use App\Services\AI\AiPromptService;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\AI\GeminiAgent;
use Database\Seeders\AiAgentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InspectableSkillPoolGeminiAgent extends GeminiAgent
{
    public array $capturedPoolsDuringLoop = [];
    public bool $simulateMidLoopEquip = false;
    public bool $simulateException = false;

    protected function chatLoop(
        array &$messages,
        array &$contextData = [],
        array &$usageData = [],
        array &$eventsData = [],
        int $depth = 0,
        array &$calledTools = [],
        ?\Closure $streamCallback = null
    ): string {
        $this->capturedPoolsDuringLoop[] = $this->ephemeralEquippedPools;

        if ($this->simulateMidLoopEquip && $depth === 0) {
            $res = AIFunctionsRegistry::execute(
                FunkiraSkillPoolService::EQUIP_TOOL_NAME,
                ['pools' => ['products', 'laser']],
                $this->agent
            );
            if (!empty($res['equipped_pools'])) {
                $this->ephemeralEquippedPools = array_values(array_unique(array_merge(
                    $this->ephemeralEquippedPools,
                    $res['equipped_pools']
                )));
            }
            return $this->chatLoop($messages, $contextData, $usageData, $eventsData, $depth + 1, $calledTools, $streamCallback);
        }

        if ($this->simulateException) {
            throw new \RuntimeException('Simulated failure during inference');
        }

        return 'Alles erledigt, Alina!';
    }
}

class FunkiraSkillPoolAndFunkiIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_activates_only_funkira_and_funki_and_retires_ten_legacy_colleagues(): void
    {
        $this->seed(AiAgentSeeder::class);

        $activeNames = AiAgent::where('is_active', true)->pluck('name')->sort()->values()->all();
        $this->assertSame(['Funki', 'Funkira'], $activeNames);

        $inChatNames = AiAgent::where('is_in_chat', true)->pluck('name')->all();
        $this->assertSame(['Funkira'], $inChatNames);

        $retiredNames = AiAgent::where('is_active', false)->pluck('name')->sort()->values()->all();
        $this->assertSame([
            'Agenti', 'Bestelli', 'Buchi', 'Dr. Funki', 'Einkaufi',
            'Globi', 'Lasi', 'Marketi', 'Produkti', 'Systemi',
        ], $retiredNames);

        // Router wählt intern immer Funkira ohne jeglichen HTTP-Call und niemals Funki
        Http::fake();
        $routed = AiAgentRouter::determineRequiredAgents('Zeig mir alle offenen Rechnungen und Bestellungen');
        $funkira = AiAgent::where('name', 'Funkira')->firstOrFail();
        $this->assertSame([$funkira->id], $routed);
        Http::assertNothingSent();
    }

    public function test_funkira_equips_skill_pools_dynamically_and_snaps_back_to_core_after_request(): void
    {
        $this->seed(AiAgentSeeder::class);
        $admin = Admin::factory()->create();
        Auth::guard('admin')->login($admin);

        $funkira = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();

        // 1. Core-Werkzeuge sind schlank (< 50 statt 140+), enthalten funkira_equip_skill_pool, aber keine Spezialtools wie finance_list_fixed_costs
        $coreTools = FunkiraSkillPoolService::getActiveToolIdentifiers([]);
        $this->assertContains(FunkiraSkillPoolService::EQUIP_TOOL_NAME, $coreTools);
        $this->assertNotContains('finance_list_fixed_costs', $coreTools);
        $this->assertNotContains('supplier_get_all', $coreTools);
        $this->assertLessThan(50, count($coreTools));

        // 2. Anfrage zur Buchhaltung aktiviert in Vordistanz den Pool 'finance', rüstet im Loop 'products' & 'laser' nach und springt danach zurück auf []
        $agent = new InspectableSkillPoolGeminiAgent($funkira);
        $agent->simulateMidLoopEquip = true;

        $this->assertSame([], $agent->getEphemeralEquippedPools());
        $agent->ask([['role' => 'user', 'content' => 'Prüfe bitte unsere monatlichen Fixkosten in der Buchhaltung']]);

        $this->assertSame(['finance'], $agent->capturedPoolsDuringLoop[0]);
        $this->assertEqualsCanonicalizing(['finance', 'products', 'laser'], $agent->capturedPoolsDuringLoop[1]);
        // Snap-Back nach Request-Ende!
        $this->assertSame([], $agent->getEphemeralEquippedPools());

        // Auch supplier_get_all ist jetzt sauber registriert und im Pool 'products' enthalten
        $productsPoolTools = FunkiraSkillPoolService::getActiveToolIdentifiers(['products']);
        $this->assertContains('supplier_get_all', $productsPoolTools);
        $this->assertContains('supplier_update', $productsPoolTools);

        // 3. Snap-Back funktioniert dank try/finally selbst bei Exceptions
        $crashAgent = new InspectableSkillPoolGeminiAgent($funkira);
        $crashAgent->simulateException = true;
        try {
            $crashAgent->ask([['role' => 'user', 'content' => 'Wie laufen unsere Bestellungen und DHL Sendungen?']]);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame([], $crashAgent->getEphemeralEquippedPools());
        }
    }

    public function test_funkira_knows_the_atelier_wall_group_photo_and_all_former_colleagues(): void
    {
        $this->seed(AiAgentSeeder::class);
        $admin = Admin::factory()->create();
        Auth::guard('admin')->login($admin);

        $funkira = AiAgent::where('name', 'Funkira')->with('role')->firstOrFail();
        $prompt = AiPromptService::getRichPrompt($funkira);

        $this->assertStringContainsString('DAS GRÜNDER-TEAM · MEIN SEELENFUNKE', $prompt);
        foreach (['Buchi', 'Bestelli', 'Produkti', 'Marketi', 'Lasi', 'Dr. Funki', 'Einkaufi', 'Systemi', 'Agenti', 'Globi', 'Funki'] as $colleague) {
            $this->assertStringContainsString($colleague, $prompt);
        }

        // Externer Kunde darf niemals die internen Easter-Egg- oder Alina-Passagen erhalten
        Auth::guard('admin')->logout();
        $customer = Customer::factory()->create();
        Auth::guard('customer')->login($customer);

        $customerPrompt = AiPromptService::getRichPrompt($funkira);
        $this->assertStringNotContainsString('DAS GRÜNDER-TEAM · MEIN SEELENFUNKE', $customerPrompt);
        $this->assertStringNotContainsString('Du sprichst DIREKT mit Alina', $customerPrompt);
    }

    public function test_funki_is_air_gapped_even_when_admin_is_authenticated(): void
    {
        $this->seed(AiAgentSeeder::class);
        $admin = Admin::factory()->create();
        Auth::guard('admin')->login($admin);

        $funki = AiAgent::where('name', 'Funki')->with('role.tools')->firstOrFail();

        // 1. Prompt-Air-Gap: Funki bekommt niemals interne Alina-, Buchhaltungs- oder Brain-Anweisungen
        $funkiPrompt = AiPromptService::getRichPrompt($funki);
        $this->assertStringNotContainsString('Du sprichst DIREKT mit Alina', $funkiPrompt);
        $this->assertStringNotContainsString('[BUCHHALTUNG & FINANZEN REGEL]', $funkiPrompt);
        $this->assertStringNotContainsString('brain_search', $funkiPrompt);
        $this->assertStringNotContainsString('communication_ask_agent', $funkiPrompt);

        // 2. Registry-Air-Gap: Funki wird bei allen internen Werkzeugen hart blockiert, selbst mit aktivem Admin-Login!
        foreach ([
            FunkiraSkillPoolService::EQUIP_TOOL_NAME => ['pools' => ['finance']],
            'finance_list_fixed_costs' => [],
            'admin_get_support_kpis' => [],
            'brain_search' => ['query' => 'BKK'],
            'supplier_get_all' => [],
        ] as $forbiddenTool => $args) {
            $res = AIFunctionsRegistry::execute($forbiddenTool, $args, $funki);
            $this->assertTrue($res['error'] ?? false, "Tool {$forbiddenTool} must be blocked for Funki");
            $this->assertStringContainsString('SANDBOX VIOLATION', $res['message'] ?? '');
        }

        // 3. Kunden-Support-Werkzeuge funktionieren weiterhin für Funki
        $allowedRes = AIFunctionsRegistry::execute('support_get_delivery_times', [], $funki);
        $this->assertFalse($allowedRes['error'] ?? false);
    }

    public function test_is_funkira_helper_and_live_credentials_endpoint_succeed(): void
    {
        $this->seed(AiAgentSeeder::class);
        $funkira = AiAgent::where('name', 'Funkira')->firstOrFail();
        $funki = AiAgent::where('name', 'Funki')->firstOrFail();

        $this->assertTrue(FunkiraSkillPoolService::isFunkira($funkira));
        $this->assertTrue(FunkiraSkillPoolService::isFunkira('Funkira'));
        $this->assertTrue(FunkiraSkillPoolService::isFunkira(['name' => 'Funkira']));
        $this->assertFalse(FunkiraSkillPoolService::isFunkira($funki));
        $this->assertFalse(FunkiraSkillPoolService::isFunkira(null));

        config(['services.gemini.key' => 'test-gemini-live-key']);
        $admin = Admin::first() ?? Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->getJson('/api/ai/live-credentials?agent_id=' . $funkira->id);

        $response->assertOk()
            ->assertJsonPath('agent_name', 'Funkira');
    }
}

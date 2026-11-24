<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Customer\Customer;
use App\Models\System\SystemSetting;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\CentralBrainService;
use App\Services\AI\FunkiraSkillPoolService;
use Database\Seeders\AiKnowledgeBaseSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class FoundingAndTaxContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_founding_and_tax_context_tracks_pending_and_assigned_states_dynamically(): void
    {
        (new SystemSettingSeeder())->run();

        $pending = CentralBrainService::resolveCompanyFoundingAndTaxContext();
        $this->assertSame('2026-11-01', $pending['business_registration']['start_date']);
        $this->assertSame('01.11.2026', $pending['business_registration']['start_date_de']);
        $this->assertSame('gew / 28-10-2026 / Ce / 402', $pending['business_registration']['file_reference']);
        $this->assertSame('2319', $pending['tax_registration']['finanzamt_nr']);
        $this->assertTrue($pending['tax_numbers']['steuernummer']['is_pending']);
        $this->assertTrue($pending['tax_numbers']['ust_id']['is_pending']);
        $this->assertSame('pending', $pending['tax_numbers']['steuernummer']['status']);
        $this->assertSame('pending', $pending['tax_numbers']['ust_id']['status']);
        $this->assertNotEmpty($pending['waiting_processes']);

        // Sobald neue Steuernummer und USt-IdNr. in shop_settings hinterlegt werden, schaltet der Status dynamisch um
        SystemSetting::updateOrCreate(['key' => 'owner_tax_id'], ['value' => '19/143/99999']);
        SystemSetting::updateOrCreate(['key' => 'owner_ust_id'], ['value' => 'DE999888777']);
        Cache::forget('global_shop_settings');

        $assigned = CentralBrainService::resolveCompanyFoundingAndTaxContext();
        $this->assertFalse($assigned['tax_numbers']['steuernummer']['is_pending']);
        $this->assertFalse($assigned['tax_numbers']['ust_id']['is_pending']);
        $this->assertSame('assigned', $assigned['tax_numbers']['steuernummer']['status']);
        $this->assertSame('assigned', $assigned['tax_numbers']['ust_id']['status']);
        $this->assertSame([], $assigned['waiting_processes']);
    }

    public function test_zero_token_context_and_finance_tool_expose_founding_status_only_internally(): void
    {
        (new SystemSettingSeeder())->run();

        $dept = AiDepartment::create([
            'name' => 'Buchhaltung',
            'slug' => 'buchhaltung',
            'color' => 'emerald-500',
            'icon' => 'calculator',
            'order_index' => 1,
        ]);

        $funkira = AiAgent::create([
            'name' => 'Funkira',
            'wake_word' => 'funkira',
            'system_prompt' => 'CEO KI',
            'ai_department_id' => $dept->id,
            'is_active' => true,
        ]);

        $funki = AiAgent::create([
            'name' => 'Funki',
            'wake_word' => 'funki',
            'system_prompt' => 'Kundensupport',
            'is_active' => true,
        ]);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $instantCtx = CentralBrainService::buildZeroTokenInstantContext($dept->id, 'Wie ist der Stand unserer Steuernummer?', $funkira);
        $this->assertStringContainsString('01.11.2026', $instantCtx);
        $this->assertStringContainsString('Finanzamt Gifhorn', $instantCtx);
        $this->assertStringContainsString('NOCH AUSSTEHEND', $instantCtx);

        // Air-Gap: Öffentlicher Funki erhält niemals interne Gründungs- oder Steuerdaten
        $this->assertSame('', CentralBrainService::buildZeroTokenInstantContext($dept->id, 'Wie ist der Stand unserer Steuernummer?', $funki));

        // Skill-Pool-Erkennung muss den Finance-Pool bei Fragen zu Gewerbeanmeldung / Steuernummer / Finanzamt laden
        $detectedPools = FunkiraSkillPoolService::detectPoolsForRequest('Wann war unsere Gewerbeanmeldung und was macht das Finanzamt wegen der Steuernummer?');
        $this->assertContains('finance', $detectedPools);

        // Admin darf das dedizierte Tool ausführen
        $toolResult = AIFunctionsRegistry::execute('finance_get_tax_and_founding_status', [], $funkira);
        $this->assertSame('success', $toolResult['status']);
        $this->assertSame('01.11.2026', $toolResult['data']['business_registration']['start_date_de']);

        // Kunde / Gast / öffentlicher Funki wird strikt blockiert
        Auth::guard('admin')->logout();
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $deniedResult = AIFunctionsRegistry::execute('finance_get_tax_and_founding_status', [], $funki);
        $this->assertSame('error', $deniedResult['status']);
    }

    public function test_knowledge_seeder_anchors_founding_and_tax_dossier_idempotently(): void
    {
        $seeder = new AiKnowledgeBaseSeeder();
        $method = new ReflectionMethod($seeder, 'seedInfrastructureAndAgentBrainReports');
        $method->setAccessible(true);

        $method->invoke($seeder);
        $method->invoke($seeder);

        $entry = AiKnowledgeBase::where(
            'source_path',
            'Abschlussberichte/Buchhaltung/Stammdaten_Gewerbeanmeldung_und_Steuerstatus.md'
        )->get();

        $this->assertCount(1, $entry);
        $this->assertStringContainsString('01.11.2026', $entry->first()->content);
        $this->assertStringContainsString('ep3104hvg8fm3b1m4zrwrd3djc6ieugc', $entry->first()->content);
    }
}

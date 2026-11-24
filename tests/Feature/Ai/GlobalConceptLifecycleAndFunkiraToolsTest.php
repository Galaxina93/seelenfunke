<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiUniverseTickets;
use App\Models\Admin\Admin;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Models\Customer\Customer;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\AgentSkillBudgetGuard;
use App\Services\AI\CentralBrainService;
use App\Services\AI\ConceptLifecycleService;
use App\Services\AI\TicketDeliverableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalConceptLifecycleAndFunkiraToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_concept_lifecycle_creates_open_deduplicates_protects_and_transitions_to_verified(): void
    {
        Storage::fake('workspace');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 1. Neues Konzept über Funkira-Tool anlegen -> landet mit Status "open" unter ftype=concept
        $createRes = AIFunctionsRegistry::executeManageConcept([
            'action' => 'save',
            'title_or_identifier' => 'Backend Produktkonfigurator auf Laseransicht Logik',
            'markdown_content' => "# Laser-Studio Konzept\nUniverselle Erstellung von Laserprojekten direkt auf dem Live-Kamerabild des xTool F2 Ultra UV.",
            'department' => 'Produkte',
        ]);

        $this->assertSame('success', $createRes['status']);
        $this->assertSame('created_new', $createRes['action']);
        $this->assertSame('open', $createRes['concept']['concept_status']);
        $this->assertTrue((bool) $createRes['concept']['pinned']);

        // 2. Zweiter Aufruf zum selben Thema (mit "Konzept:"-Präfix & erweitertem Text) -> KEIN Duplikat, sondern v2!
        $updateRes = AIFunctionsRegistry::executeManageConcept([
            'action' => 'save',
            'title_or_identifier' => 'Konzept: Backend Produktkonfigurator auf Laseransicht Logik',
            'markdown_content' => "# Laser-Studio Konzept v2\nUniverselle Erstellung von Laserprojekten inkl. Vektor-QR-Codes und eigenen Material-Presets.",
            'department' => 'Produkte',
        ]);

        $this->assertSame('success', $updateRes['status']);
        $this->assertSame('updated_existing', $updateRes['action']);
        $this->assertSame(2, $updateRes['concept']['concept_version']);

        $listRes = AIFunctionsRegistry::executeManageConcept([
            'action' => 'list',
            'status' => 'open',
        ]);
        $this->assertSame(1, $listRes['count']);

        // 3. 0-Token-Sofortkontext enthält das offene Konzept für Funkira
        $instantCtx = CentralBrainService::buildZeroTokenInstantContext(null, 'Konzept');
        $this->assertStringContainsString('Backend Produktkonfigurator auf Laseransicht Logik', $instantCtx);

        // 4. Offene Konzepte dürfen auch nach > 28 Tagen NICHT wegarchiviert werden
        $msg = AiUniverseTicketMessage::findOrFail($updateRes['concept'] ? ConceptLifecycleService::findExistingConcept('Backend Produktkonfigurator')['message']->id : '');
        $atts = $msg->attachments;
        $atts[0]['pinned'] = false;
        $atts[0]['created_at'] = now()->subDays(45)->toIso8601String();
        $msg->update(['attachments' => $atts]);

        $cleanup = TicketDeliverableService::runAutomatedLifecycleCleanup(28, 180);
        $this->assertSame(0, $cleanup['archived_count']);

        // 5. Nach Umsetzung & Prüfung -> Status "completed_verified" + Sync in Wissensdatenbank
        $verifyRes = AIFunctionsRegistry::executeManageConcept([
            'action' => 'set_status',
            'title_or_identifier' => 'Backend Produktkonfigurator auf Laseransicht Logik',
            'status' => 'completed_verified',
        ]);
        $this->assertSame('success', $verifyRes['status']);
        $this->assertSame('completed_verified', $verifyRes['concept']['concept_status']);
        $this->assertTrue(AiKnowledgeBase::where('source_path', 'like', 'concept:%')->exists());

        // 6. Livewire-UI unter /admin/tickets?view=files&ftype=concept zeigt Status & erlaubt 1-Klick-Löschung
        $item = ConceptLifecycleService::listConcepts('completed_verified')[0];
        Livewire::test(AiUniverseTickets::class, ['mainView' => 'files', 'fileTypeFilter' => 'concept'])
            ->assertSee('Fertig & Geprüft')
            ->call('deleteDeliverableFile', $item['message_id'], $item['key']);

        $this->assertCount(0, ConceptLifecycleService::listConcepts());
        $this->assertFalse(AiKnowledgeBase::where('source_path', 'like', 'concept:%')->exists());
    }

    public function test_funki_and_customers_are_strictly_blocked_from_concept_management(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $denied = AIFunctionsRegistry::executeManageConcept(['action' => 'list']);
        $this->assertSame('error', $denied['status']);
        $this->assertNotContains('concept_manage', AgentSkillBudgetGuard::IMMUTABLE_FUNKI_TOOLS);
    }

    public function test_ag_inbox_attachment_without_size_bytes_does_not_crash_deliverables_or_live_credentials(): void
    {
        Storage::fake('workspace');
        config(['services.gemini.key' => 'test-gemini-live-key']);
        $this->seed(\Database\Seeders\AiCompanyStructureSeeder::class);
        $this->seed(\Database\Seeders\AiAgentSeeder::class);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $ticket = \App\Models\Ai\AiUniverseTicket::create([
            'ticket_number' => 'SF-9901',
            'title' => 'AG Attachment Test',
            'status' => \App\Models\Ai\AiUniverseTicket::STATUS_NEW,
            'priority' => 'normal',
            'ticket_type' => 'ag_direct',
            'is_business' => true,
            'source_channel' => 'android_app',
            'environment_tier' => 'TESTING',
            'last_activity_at' => now(),
        ]);

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'user',
            'sender_name' => 'Alina',
            'content' => 'Anhang ohne size_bytes',
            'attachments' => [[
                'name' => 'foto.jpg',
                'url' => '/api/funkenwerk/ag-inbox/ag_20261124_test_missing_size/attachment',
                'category' => 'image',
            ]],
        ]);

        $res = TicketDeliverableService::collectAllTicketDeliverables('all', 'all', '');
        $this->assertSame(1, $res['stats']['active_count']);
        $this->assertSame('/admin/ai/ag-attachment/ag_20261124_test_missing_size', $res['items'][0]['url']);

        $liveResp = $this->actingAs($admin, 'admin')
            ->getJson('/api/ai/live-credentials?client=android_native');

        $liveResp->assertOk()
            ->assertJsonPath('agent_name', 'Funkira')
            ->assertJsonPath('voice_name', 'Aoede');
    }
}

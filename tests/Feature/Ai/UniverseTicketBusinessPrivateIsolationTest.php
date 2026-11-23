<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiUniverseTickets;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Models\Employee\Employee;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\CentralBrainService;
use App\Services\AI\FunkiraTicketRouter;
use App\Services\AI\TicketDeliverableService;
use Database\Seeders\AiAgentSeeder;
use Database\Seeders\AiCompanyStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class UniverseTicketBusinessPrivateIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-priv-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    private function createProductionEmployee(): Employee
    {
        return Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Manufaktur',
            'email' => 'max-prod-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'role' => 'production',
            'is_active' => true,
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_zero_token_triage_separates_private_and_business_tickets_and_respects_antigravity_negation(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        // 1. Physisches Bad-/Dachfenster-Problem mit expliziter Antigravity-Verneinung -> Privat, Leitung, Funkira, KEIN Antigravity
        $bathRes = FunkiraTicketRouter::dispatchMessage(
            'Da kommt Wasser durch die Decke im Bad am Dachfenster. Nee, du musst jetzt nicht an Antigravity melden!',
            ['channel' => 'android_live_call']
        );

        /** @var AiUniverseTicket $bathTicket */
        $bathTicket = $bathRes['ticket'];
        $this->assertNotNull($bathTicket);
        $this->assertFalse($bathTicket->is_business);
        $this->assertFalse($bathTicket->assigned_to_antigravity);
        $this->assertSame('private_household', $bathTicket->ticket_type);
        $this->assertSame('Leitung', $bathTicket->department?->name);
        $this->assertSame('Funkira', $bathTicket->agent?->name);
        $this->assertStringNotContainsString('an Antigravity übergeben', $bathRes['reply']);

        // 2. Private Vorsorge / Renteninformation -> Privat, Buchhaltung, Buchi
        $pensionTriage = FunkiraTicketRouter::classifyIntent('Bitte prüfe meine aktuelle Renteninformation und die Wohngebäudeversicherung bei der Barmenia.');
        $this->assertFalse($pensionTriage['is_business']);
        $this->assertFalse($pensionTriage['assign_antigravity']);
        $this->assertSame('Buchhaltung', $pensionTriage['department_name']);
        $this->assertSame('Buchi', $pensionTriage['agent_name']);
        $this->assertSame('private_finance', $pensionTriage['ticket_type']);

        // 3. Private Gesundheit -> Privat, Leitung, Dr. Funki
        $healthTriage = FunkiraTicketRouter::classifyIntent('Ich brauche einen Folgetermin beim Zahnarzt und eine Anpassung der Medikamenten-Dosis.');
        $this->assertFalse($healthTriage['is_business']);
        $this->assertFalse($healthTriage['assign_antigravity']);
        $this->assertSame('Leitung', $healthTriage['department_name']);
        $this->assertSame('Dr. Funki', $healthTriage['agent_name']);

        // 4. Gewerblicher Laser-/Shop-Auftrag -> Gewerblich (is_business = true)
        $laserRes = FunkiraTicketRouter::dispatchMessage(
            'Bitte prüfe das xTool Laser-Gravurprofil für den Seelen-Kristall in der Manufaktur.',
            ['channel' => 'web_portal']
        );
        /** @var AiUniverseTicket $laserTicket */
        $laserTicket = $laserRes['ticket'];
        $this->assertNotNull($laserTicket);
        $this->assertTrue($laserTicket->is_business);
        $this->assertFalse($laserTicket->assigned_to_antigravity);
        $this->assertSame('Produkte', $laserTicket->department?->name);
        $this->assertSame('Lasi', $laserTicket->agent?->name);

        // 5. Echter Software-/Code-Auftrag -> Gewerblich & Antigravity
        $devTriage = FunkiraTicketRouter::classifyIntent('Bitte baue in der Livewire Ticketlogik eine neue Migration ein.');
        $this->assertTrue($devTriage['is_business']);
        $this->assertTrue($devTriage['assign_antigravity']);
    }

    public function test_ag_send_to_antigravity_blocks_physical_household_issues_and_redirects_to_private_ticket(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $res = AIFunctionsRegistry::execute('ag_send_to_antigravity', [
            'title' => 'Wasserschaden an Decke/Wand im Bad nahe Dachfenster festgestellt',
            'body' => 'Wasserfleck im privaten Badezimmer unter dem ausgebauten Dachboden.',
            'channel' => 'minipc',
            'execution_mode' => 'direct',
        ]);

        $this->assertIsArray($res);
        $this->assertSame('redirected_to_private_ticket', $res['status'] ?? null);
        $this->assertFalse((bool) ($res['sent_live'] ?? true));
        $this->assertFalse((bool) ($res['is_business'] ?? true));
        $this->assertNotEmpty($res['ticket_number'] ?? '');

        $ticket = AiUniverseTicket::where('ticket_number', $res['ticket_number'])->first();
        $this->assertNotNull($ticket);
        $this->assertFalse($ticket->is_business);
        $this->assertFalse($ticket->assigned_to_antigravity);
        $this->assertSame('Leitung', $ticket->department?->name);
    }

    public function test_private_tickets_and_deliverables_are_strictly_hidden_from_employees_and_filterable_for_admin(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();
        $employee = $this->createProductionEmployee();

        $dept = \App\Models\Ai\AiDepartment::where('name', 'Leitung')->firstOrFail();
        $funkira = \App\Models\Ai\AiAgent::where('name', 'Funkira')->firstOrFail();

        $businessTicket = AiUniverseTicket::create([
            'ticket_number' => 'SF-1001',
            'title' => 'Verpackungskartons Manufaktur prüfen',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'priority' => 'normal',
            'ticket_type' => 'management',
            'is_business' => true,
            'ai_department_id' => $dept->id,
            'assigned_agent_id' => $funkira->id,
            'source_channel' => 'web_portal',
            'environment_tier' => 'TESTING',
            'last_activity_at' => now(),
        ]);

        $privateTicket = AiUniverseTicket::create([
            'ticket_number' => 'SF-1002',
            'title' => 'Wasserfleck am Dachfenster im privaten Bad',
            'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
            'priority' => 'high',
            'ticket_type' => 'private_household',
            'is_business' => false,
            'ai_department_id' => $dept->id,
            'assigned_agent_id' => $funkira->id,
            'source_channel' => 'android_live_call',
            'environment_tier' => 'TESTING',
            'last_activity_at' => now()->addSecond(),
        ]);

        $attMeta = TicketDeliverableService::storeBytesForTicket("# Bad-Notiz\nBleistiftlinie gezogen.", 'Bad_Dachfenster_Check.md');
        $this->assertNotNull($attMeta);

        AiUniverseTicketMessage::create([
            'ticket_id' => $privateTicket->id,
            'sender_role' => 'agent',
            'sender_name' => 'Funkira',
            'channel' => 'android_live_call',
            'content' => 'Privatdokument hinterlegt.',
            'attachments' => [$attMeta],
        ]);

        // 1. Admin sieht beide Tickets, kann auf 'Privat' / 'Gewerblich' filtern und per 1-Klick umschalten
        Livewire::actingAs($admin, 'admin')
            ->test(AiUniverseTickets::class)
            ->assertSee('SF-1001')
            ->assertSee('SF-1002')
            ->call('setBusinessFilter', 'private')
            ->assertSee('SF-1002')
            ->assertDontSee('SF-1001')
            ->call('setBusinessFilter', 'business')
            ->assertSee('SF-1001')
            ->assertDontSee('SF-1002')
            ->call('setBusinessFilter', 'all')
            ->call('toggleTicketScope', $businessTicket->id);

        $this->assertFalse($businessTicket->fresh()->is_business);

        // Zurück auf Gewerblich schalten für Mitarbeiter-Isolationsprüfung
        FunkiraTicketRouter::setTicketBusinessScope($businessTicket->fresh(), true, 'web_portal');
        $this->assertTrue($businessTicket->fresh()->is_business);

        // 2. Produktions-Mitarbeiter sieht NIEMALS private Tickets oder deren Datei-Anhänge
        Livewire::actingAs($employee, 'employee')
            ->test(AiUniverseTickets::class)
            ->assertSee('SF-1001')
            ->assertDontSee('SF-1002')
            ->assertDontSee('Wasserfleck am Dachfenster')
            ->call('setBusinessFilter', 'private')
            ->assertSet('businessFilter', 'business')
            ->assertDontSee('SF-1002')
            ->call('selectTicket', $privateTicket->id)
            ->assertSet('activeTicketId', $businessTicket->id);

        $this->actingAs($employee, 'employee');
        $empDeliverables = TicketDeliverableService::collectAllTicketDeliverables('all', 'all', '');
        $this->assertCount(0, $empDeliverables['items']);

        // Mitarbeiter darf keine privaten Tickets beantworten
        $empReply = FunkiraTicketRouter::dispatchMessage('Testzugriff', ['channel' => 'web_portal'], $privateTicket->id);
        $this->assertSame('forbidden_private_ticket', $empReply['status']);
    }

    public function test_buchi_special_issue_and_brain_report_automatically_inherit_private_ticket_scope(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $dept = \App\Models\Ai\AiDepartment::where('name', 'Buchhaltung')->firstOrFail();
        $buchi = \App\Models\Ai\AiAgent::where('name', 'Buchi')->firstOrFail();

        $privateTicket = AiUniverseTicket::create([
            'ticket_number' => 'SF-2001',
            'title' => 'Dachfenster-Dichtung im privaten Bad prüfen',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'priority' => 'normal',
            'ticket_type' => 'private_finance',
            'is_business' => false,
            'ai_department_id' => $dept->id,
            'assigned_agent_id' => $buchi->id,
            'source_channel' => 'web_portal',
            'environment_tier' => 'TESTING',
            'last_activity_at' => now(),
        ]);

        // Simuliere aktiven Ticket-Kontext: Selbst wenn das LLM fälschlich is_business=true übergibt,
        // erzwingt das private Ticket automatisch is_business=false und tax_rate=null!
        app()->instance('active_universe_ticket_id', $privateTicket->id);
        try {
            $res = AIFunctionsRegistry::execute('finance_create_quick_entry_expense', [
                'title' => 'Dichtband Dachfenster Bad',
                'amount' => -24.90,
                'category' => 'Haus & Privat',
                'execution_date' => '2026-11-23',
                'is_business' => true,
                'tax_rate' => 19,
            ], $buchi);
        } finally {
            app()->forgetInstance('active_universe_ticket_id');
        }

        $this->assertSame('success', $res['status'] ?? null);
        $issue = AccountingSpecialIssue::findOrFail($res['id']);
        $this->assertFalse((bool) $issue->is_business);
        $this->assertNull($issue->tax_rate);

        // Abschlussbericht im Wissensgehirn trägt Bereich Privat und Admin-Executive-Sichtbarkeit
        $privateTicket->update([
            'status' => AiUniverseTicket::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $kb = CentralBrainService::recordTicketCompletionReport($privateTicket, 'Dachfenster abgedichtet und privat gebucht.');
        $this->assertNotNull($kb);
        $this->assertSame(AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE, $kb->visibility_scope);
        $this->assertStringContainsString('**Bereich:** Privat', (string) $kb->content);
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiCompanyStructure;
use App\Livewire\Shop\Ai\AiUniverseTickets;
use App\Livewire\Shop\Management\ManagementRoutine;
use App\Livewire\Shop\System\SystemShopConfig;
use App\Models\Admin\Admin;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Product\ProductPackagingItem;
use App\Services\AI\AiOutputQualityGate;
use App\Services\AI\FunkiraPresenceContext;
use App\Services\AI\FunkiraTicketRouter;
use App\Services\AI\ProcurementSafetyGuard;
use App\Services\AI\TicketAutoCompactService;
use App\Services\Navigation\BackendNavigationService;
use App\Services\System\ZeroTokenSelfHealer;
use Database\Seeders\AiAgentSeeder;
use Database\Seeders\AiCompanyStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class FunkiraUniverseTicketSystemTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-tickets-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_navigation_places_tickets_at_spot_two_removes_legacy_agent_group_and_relocates_cronjobs_and_health(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $nav = BackendNavigationService::getStructure();
        $topItems = $nav[0]['items'] ?? [];
        $allIds = [];
        foreach ($nav as $section) {
            foreach (($section['items'] ?? []) as $item) {
                $allIds[] = $item['id'] ?? null;
            }
        }

        $this->assertSame('Dashboard', $topItems[0]['title'] ?? null);
        $this->assertSame('/admin/dashboard', $topItems[0]['route'] ?? null);
        $this->assertSame('Tickets', $topItems[1]['title'] ?? null);
        $this->assertSame('/admin/tickets', $topItems[1]['route'] ?? null);
        $this->assertNotContains('system_ai', $allIds);

        // 1. Alle HTTP-Zugriffsprüfungen zuerst (vor Livewire::test Redirector-Swap)
        $this->get('/admin/tickets')->assertStatus(302);

        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->get('/admin/tickets')->assertStatus(302);

        $influencer = Employee::create([
            'first_name' => 'Creator',
            'last_name' => 'Partner',
            'email' => 'inf-ticket-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
            'role' => 'influencer',
            'password' => bcrypt('Secret123!'),
        ]);
        $infRes = $this->actingAs($influencer, 'employee')->get('/admin/tickets');
        $this->assertContains($infRes->getStatusCode(), [302, 403]);

        // Admin -> 200 auf /admin/tickets + 302 Redirects der Alt-URLs
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin')
            ->get('/admin/tickets')
            ->assertStatus(200)
            ->assertSee('Tickets')
            ->assertSee('Organigramm')
            ->assertSee('Wissen');

        $this->actingAs($admin, 'admin')
            ->get('/admin/ai/analytics')
            ->assertRedirect('/admin/tickets');

        $this->actingAs($admin, 'admin')
            ->get('/admin/ai/workspace')
            ->assertRedirect('/admin/tickets');

        $this->actingAs($admin, 'admin')
            ->get('/admin/organigramm')
            ->assertRedirect('/admin/tickets?view=organigramm');

        // 2. Livewire-Isolationsschutz (Customer & Influencer -> 403)
        Auth::forgetGuards();
        Livewire::actingAs($customer, 'customer')->test(AiUniverseTickets::class)->assertForbidden();
        Auth::forgetGuards();
        Livewire::actingAs($influencer, 'employee')->test(AiUniverseTickets::class)->assertForbidden();

        // Organigramm bleibt mit HEADQUARTERS & 7 Abteilungen vollständig erhalten
        Auth::forgetGuards();
        Livewire::actingAs($admin, 'admin')
            ->test(AiUniverseTickets::class)
            ->call('setMainView', 'organigramm')
            ->assertSet('mainView', 'organigramm');

        Livewire::actingAs($admin, 'admin')
            ->test(AiCompanyStructure::class)
            ->assertSee('KI Organigramm')
            ->assertSee('Headquarters')
            ->assertSee('Funkira')
            ->assertSee('Antigravity')
            ->assertDontSee('Falcone');

        $deptNames = AiDepartment::orderBy('order_index')->pluck('name')->all();
        $this->assertSame([
            'Leitung', 'Support', 'Produkte', 'Marketing', 'Bestellungen', 'Buchhaltung', 'System',
        ], $deptNames);

        // Cronjobs unter System -> Einstellungen & Gesundheit Admin-only unter Leitung -> Routine
        Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertSee('Cronjobs');

        Livewire::actingAs($admin, 'admin')
            ->test(ManagementRoutine::class)
            ->assertSee('Gesundheit')
            ->set('viewMode', 'health')
            ->assertSet('viewMode', 'health');

        $internalEmployee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Intern',
            'email' => 'intern-routine-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
            'role' => 'internal',
            'password' => bcrypt('Secret123!'),
        ]);
        Auth::forgetGuards();
        Livewire::actingAs($internalEmployee, 'employee')
            ->test(ManagementRoutine::class)
            ->assertDontSee('Gesundheit')
            ->set('viewMode', 'health')
            ->assertSet('viewMode', 'combined');
    }

    public function test_presence_context_detects_all_channels_environments_and_enforces_minipc_visitor_lock(): void
    {
        $localReq = Request::create('http://127.0.0.1/api/ai/tickets', 'POST');
        $localCtx = FunkiraPresenceContext::build([
            'channel' => 'funkenwerk_desktop',
            'environment_override' => 'LOCAL',
        ], $localReq);
        $this->assertSame('LOCAL', $localCtx['environment_tier']);
        $this->assertSame('funkenwerk_desktop', $localCtx['channel']);
        $this->assertFalse($localCtx['visitor_sandbox_locked']);

        $stageReq = Request::create('https://stage.mein-seelenfunke.de/api/ai/live-credentials', 'GET');
        $stageCtx = FunkiraPresenceContext::build([
            'channel' => 'android_live_call',
            'environment_override' => 'STAGE',
        ], $stageReq);
        $this->assertSame('STAGE', $stageCtx['environment_tier']);
        $this->assertSame('android_live_call', $stageCtx['channel']);

        $liveReq = Request::create('https://mein-seelenfunke.de/api/ai/chat', 'POST');
        $liveCtx = FunkiraPresenceContext::build([
            'channel' => 'workshop_minipc',
            'environment_override' => 'LIVE',
            'camera_alina_verified' => false,
            'voice_alina_verified' => false,
            'detected_persons' => ['Besucher'],
        ], $liveReq);
        $this->assertSame('LIVE', $liveCtx['environment_tier']);
        $this->assertSame('workshop_minipc', $liveCtx['channel']);
        $this->assertTrue($liveCtx['visitor_sandbox_locked']);

        // Mini-PC Zero-PII Besucher-Sperre blockt Ticket-Anlage in 0 Tokens
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $blockedResult = FunkiraTicketRouter::dispatchMessage(
            'Zeig mir alle Umsätze und erstelle ein Buchhaltungsticket',
            [
                'channel' => 'workshop_minipc',
                'environment_override' => 'LIVE',
                'camera_alina_verified' => false,
                'voice_alina_verified' => false,
                'detected_persons' => ['Besucher'],
            ]
        );

        $this->assertSame('visitor_locked', $blockedResult['status']);
        $this->assertSame('visitor_sandbox_0_token', $blockedResult['mode']);
        $this->assertNull($blockedResult['ticket']);
        $this->assertSame(0, AiUniverseTicket::count());
    }

    public function test_fast_lane_executes_ping_without_ticket_clutter_while_real_tasks_route_and_enforce_anti_bluff_gate(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();
        Auth::guard('admin')->login($admin);

        // 1. 0-Token Fast-Lane ("Funkira?") -> kein Ticket-Müll
        $ping = FunkiraTicketRouter::dispatchMessage('Funkira?', [
            'channel' => 'android_app',
            'environment_override' => 'TESTING',
        ]);
        $this->assertSame('fast_lane_0_token', $ping['mode']);
        $this->assertNull($ping['ticket']);
        $this->assertSame(0, AiUniverseTicket::count());

        // 2. 0-Token Anti-Bluff Gate ("No Tool = No Promise") fängt leere Arbeitsversprechen ab
        $bluffCheck = AiOutputQualityGate::inspectExecutionTruth(
            'Ja Alina, das werde ich dann mal in Arbeit nehmen und direkt für dich erledigen!',
            []
        );
        $this->assertTrue($bluffCheck['bluff_detected']);
        $this->assertTrue($bluffCheck['suggest_antigravity_handoff']);
        $this->assertStringContainsString('Antigravity', $bluffCheck['text']);

        $bluffCheck2 = AiOutputQualityGate::inspectExecutionTruth(
            'Ja Alina, ich nehme das jetzt direkt in Arbeit!',
            []
        );
        $this->assertTrue($bluffCheck2['bluff_detected']);

        // Wenn ein echtes Aktions-Tool lief, darf die Bestätigung normal durchgehen
        $truthfulCheck = AiOutputQualityGate::inspectExecutionTruth(
            'Aufgabe wurde im Dashboard angelegt.',
            ['task_create']
        );
        $this->assertFalse($truthfulCheck['bluff_detected']);

        // 3. 1-Wort-Bestätigung ("Ja") übergibt ein wartendes Capability-Gap-Ticket sofort in 0 Tokens an Antigravity
        $gapTicket = AiUniverseTicket::create([
            'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
            'title' => '3D Shader Anpassung im Konfigurator',
            'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
            'priority' => 'normal',
            'source_channel' => 'web_portal',
            'environment_tier' => 'LOCAL',
            'action_payload' => ['type' => 'capability_gap', 'missing_capability' => 'GLSL Shader Anpassung'],
            'last_activity_at' => now(),
        ]);

        FunkiraPresenceContext::markAntigravityNotebookOnline();

        $handoffRes = FunkiraTicketRouter::dispatchMessage('Ja', [
            'channel' => 'android_app',
            'environment_override' => 'LOCAL',
        ], $gapTicket->id);

        $this->assertSame('antigravity_handoff', $handoffRes['mode']);
        $this->assertTrue($gapTicket->fresh()->assigned_to_antigravity);
        $this->assertSame(AiUniverseTicket::STATUS_IN_PROGRESS, $gapTicket->fresh()->status);
        $this->assertStringContainsString('Notebook ist online', $handoffRes['reply']);
    }

    public function test_zero_token_self_healer_deduplicates_repeated_exceptions_into_single_systemi_ticket(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        for ($i = 0; $i < 10; $i++) {
            $res = ZeroTokenSelfHealer::handleSystemError(
                'Datenbank-Timeout bei DHL-Tarifabfrage',
                'RuntimeException',
                'app/Services/DhlService.php',
                142
            );
            $this->assertContains($res['action'], ['created_bundled_ticket', 'deduplicated_increment']);
        }

        $this->assertSame(1, AiUniverseTicket::count());

        $ticket = AiUniverseTicket::with(['department', 'agent'])->firstOrFail();
        $this->assertSame(10, $ticket->occurrence_count);
        $this->assertSame('System', $ticket->department?->name);
        $this->assertSame('Systemi', $ticket->agent?->name);
        $this->assertNotEmpty($ticket->error_fingerprint);
    }

    public function test_ticket_auto_compact_service_compacts_history_without_losing_chronological_ui_messages(): void
    {
        $ticket = AiUniverseTicket::create([
            'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
            'title' => 'Längere Abstimmung Verpackungsgrößen',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'source_channel' => 'web_portal',
            'environment_tier' => 'LOCAL',
            'last_activity_at' => now(),
        ]);

        for ($i = 1; $i <= 8; $i++) {
            AiUniverseTicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_role' => $i % 2 === 1 ? 'alina' : 'agent',
                'sender_name' => $i % 2 === 1 ? 'Alina' : 'Einkaufi',
                'channel' => 'web_portal',
                'content' => "Chronologische Nachricht Nummer {$i} zur Abstimmung der Versandtaschen.",
                'created_at' => now()->addSeconds($i),
            ]);
        }

        $compacted = TicketAutoCompactService::compactIfNeeded($ticket->fresh());
        $this->assertTrue($compacted);

        $freshTicket = $ticket->fresh();
        $this->assertNotEmpty($freshTicket->compact_context);
        $this->assertSame(8, $freshTicket->messages()->count());

        $promptHistory = TicketAutoCompactService::buildPromptHistory($freshTicket);
        $this->assertCount(1 + TicketAutoCompactService::KEEP_RECENT_COUNT, $promptHistory);
        $this->assertStringContainsString('AUTO-COMPACT TICKET-ZUSAMMENFASSUNG', $promptHistory[0]['content']);
    }

    public function test_procurement_safety_guard_and_tresor_queue_enforce_25_euro_auto_buy_environment_locks_and_5_percent_drift_abort(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        ProductPackagingItem::create([
            'name' => 'Papierflachbeutel Kraftpapier Braun A6',
            'category' => 'bag',
            'supplier_name' => 'Karton.eu',
            'reorder_url' => 'https://www.karton.eu/papierflachbeutel-a6',
            'pack_quantity' => 250,
            'pack_price_cents' => 1190,
            'unit_cost_cents' => 5,
            'stock_quantity' => 40,
            'min_stock_threshold' => 100,
            'is_active' => true,
        ]);

        // 1. Auf STAGE / TESTING darf KEIN Auto-Kauf ausgelöst werden -> erfordert Alinas Klick!
        $stageEval = ProcurementSafetyGuard::evaluateReorder('Papierflachbeutel', 1, 'STAGE');
        $this->assertTrue($stageEval['found']);
        $this->assertFalse($stageEval['auto_execute_allowed']);
        $this->assertLessThanOrEqual(2500, $stageEval['gross_total_cents']);

        // 2. Auf LIVE unter <= 25,00 EUR Brutto -> Auto-Freigabe & Tresor-Queue bereit!
        $liveResult = FunkiraTicketRouter::dispatchMessage('Wir brauchen noch weitere Papierflachbeutel', [
            'channel' => 'android_live_call',
            'environment_override' => 'LIVE',
        ]);

        $ticket = $liveResult['ticket'];
        $this->assertNotNull($ticket);
        $this->assertSame(AiUniverseTicket::STATUS_WAITING_FOR_TRESOR, $ticket->status);
        $this->assertSame('Buchhaltung', $ticket->department?->name);
        $this->assertSame('Einkaufi', $ticket->agent?->name);
        $this->assertTrue((bool) ($ticket->action_payload['auto_executed_under_25'] ?? false));

        // 3. Tresor-Queue API prüfen + Sicherheits-Abbruch bei > 5 % Preisabweichung im Live-Shop
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $queueRes = $this->getJson('/api/ai/tresor-queue')->assertStatus(200);
        $this->assertCount(1, $queueRes->json('orders'));
        $this->assertSame($ticket->id, $queueRes->json('orders.0.id'));

        $expectedGross = (int) ($ticket->action_payload['gross_total_cents'] ?? 0);
        $driftedPrice = $expectedGross + 500; // Deutlich > 5 % über Kalkulation

        $this->postJson("/api/ai/tresor-queue/{$ticket->id}/complete", [
            'success' => true,
            'final_gross_cents' => $driftedPrice,
            'summary' => 'Preis im Live-Warenkorb geprüft.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'aborted_price_guard');

        $this->assertSame(AiUniverseTicket::STATUS_WAITING_FOR_ALINA, $ticket->fresh()->status);

        // 4. Nach Go-Freigabe von Alina und korrektem Live-Preis -> Ticket completed + Präferenz dauerhaft gelernt!
        $this->postJson("/api/ai/tickets/{$ticket->id}/action", [
            'action' => 'approve_procurement',
        ])->assertStatus(200);

        $this->assertSame(AiUniverseTicket::STATUS_WAITING_FOR_TRESOR, $ticket->fresh()->status);

        $this->postJson("/api/ai/tresor-queue/{$ticket->id}/complete", [
            'success' => true,
            'final_gross_cents' => $expectedGross,
            'summary' => 'Bestellung KEU-2026-99412 erfolgreich ausgeführt.',
        ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $ticket->fresh()->status);
        $this->assertSame(1, AiLearnedPreference::count());

        // 5. 14-Tage-Dubletten-Sperre verhindert sofortigen Doppelkauf desselben Artikels!
        $duplicateEval = ProcurementSafetyGuard::evaluateReorder('Papierflachbeutel', 1, 'LIVE');
        $this->assertFalse($duplicateEval['auto_execute_allowed']);
        $this->assertTrue($duplicateEval['duplicate_lock_triggered']);

        // 6. Artikel über 25,00 EUR Brutto wird auf LIVE niemals automatisch gekauft!
        ProductPackagingItem::create([
            'name' => 'Westpack Schmuckboxen Großkarton Schwarz',
            'category' => 'gift_box',
            'supplier_name' => 'Westpack',
            'reorder_url' => 'https://www.westpack.com/de/schmuckboxen-schwarz',
            'pack_quantity' => 96,
            'pack_price_cents' => 8900,
            'unit_cost_cents' => 93,
            'stock_quantity' => 5,
            'min_stock_threshold' => 24,
            'is_active' => true,
        ]);

        $overLimitEval = ProcurementSafetyGuard::evaluateReorder('Westpack Schmuckboxen', 1, 'LIVE');
        $this->assertTrue($overLimitEval['found']);
        $this->assertFalse($overLimitEval['auto_execute_allowed']);
        $this->assertGreaterThan(2500, $overLimitEval['gross_total_cents']);
    }

    public function test_antigravity_direct_inbox_concepts_awaiting_go_and_dismissals_mirror_bidirectionally_into_tickets(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $isolatedRoot = storage_path('framework/testing/ag_ticket_mirror_' . Str::lower(Str::random(8)));
        \Illuminate\Support\Facades\File::makeDirectory($isolatedRoot, 0700, true, true);
        $inbox = new \App\Services\System\AntigravityDirectInboxService($isolatedRoot);
        $this->app->instance(\App\Services\System\AntigravityDirectInboxService::class, $inbox);

        try {
            // 1. Funkira erstellt ein Konzept-Ticket für Antigravity -> erscheint sofort 1:1 in ai_universe_tickets
            $created = $inbox->createItem([
                'channel' => 'leitung',
                'title' => 'Konzept für Digital-Produkt-Marktanalyse',
                'body' => 'Bitte Chancen und Risiken prüfen.',
                'execution_mode' => 'concept',
                'source' => 'android_live_call',
            ]);
            $agId = (string) $created['item']['id'];

            $mirrored = \App\Services\AI\AntigravityUniverseTicketBridge::findTicketByAgId($agId);
            $this->assertNotNull($mirrored);
            $this->assertTrue($mirrored->assigned_to_antigravity);
            $this->assertSame('Antigravity', $mirrored->agent?->name);
            $this->assertSame('Leitung', $mirrored->department?->name);

            // 2. Antigravity liefert Konzept -> Status awaiting_go spiegelt sich sofort auf waiting_for_alina ("Rückfrage / Go")
            $inbox->completeItem($agId, 'Konzept fertiggestellt. Sollen wir starten?');
            $mirrored->refresh();
            $this->assertSame(AiUniverseTicket::STATUS_WAITING_FOR_ALINA, $mirrored->status);
            $this->assertSame('ag_inbox_blockade', $mirrored->action_payload['type'] ?? null);

            // 3. Verwerfung ("dismissed") spiegelt sich sofort ins Ticket-Archiv mit Verworfen-Markierung
            $inbox->resolveBlockadeItem($agId, 'dismissed', 'Auf Alinas Wunsch verworfen', 'Alina');
            $mirrored->refresh();
            $this->assertSame(AiUniverseTicket::STATUS_ARCHIVED, $mirrored->status);
            $this->assertTrue((bool) ($mirrored->action_payload['dismissed'] ?? false));

            // 4. Zweites Konzept -> 1-Klick Go direkt aus dem Ticketsystem löst die Antigravity-Blockade bidirektional auf!
            $created2 = $inbox->createItem([
                'channel' => 'shop',
                'title' => 'Konzept Konfigurator Smart-Fit Feinschliff',
                'body' => 'Abstände optimieren.',
                'execution_mode' => 'concept',
            ]);
            $agId2 = (string) $created2['item']['id'];
            $inbox->completeItem($agId2, 'Konzept bereit. Bitte Go geben.');

            $ticket2 = \App\Services\AI\AntigravityUniverseTicketBridge::findTicketByAgId($agId2);
            $this->assertNotNull($ticket2);
            $this->assertSame(AiUniverseTicket::STATUS_WAITING_FOR_ALINA, $ticket2->status);
            $this->assertCount(1, $inbox->getPendingBlockades());

            FunkiraPresenceContext::markAntigravityNotebookOnline();
            FunkiraTicketRouter::performTicketAction($ticket2, 'approve_ag_blockade', [
                'channel' => 'web_portal',
                'note' => 'Go direkt im Ticketsystem erteilt',
            ]);

            $this->assertCount(0, $inbox->getPendingBlockades());
            $ticket2->refresh();
            $this->assertSame(AiUniverseTicket::STATUS_IN_PROGRESS, $ticket2->status);
            $this->assertSame(2, AiUniverseTicket::count()); // Kein doppeltes Folge-Ticket im Ticketsystem!
        } finally {
            \Illuminate\Support\Facades\File::deleteDirectory($isolatedRoot);
        }
    }
}

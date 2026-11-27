<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiUniverseTicket;
use App\Services\AI\AntigravityUniverseTicketBridge;
use App\Services\AI\FunkiraTicketRouter;
use App\Services\System\AntigravityDirectInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketStatusSyncHardeningTest extends TestCase
{
    use RefreshDatabase;

    private string $tempInboxDir;
    private AntigravityDirectInboxService $inbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempInboxDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_ticket_sync_test_' . uniqid();
        File::makeDirectory($this->tempInboxDir, 0777, true, true);
        $this->inbox = new AntigravityDirectInboxService($this->tempInboxDir);
        $this->app->instance(AntigravityDirectInboxService::class, $this->inbox);

        $dept = AiDepartment::create([
            'name' => 'System',
            'description' => 'System Abteilung',
            'color' => '#C5A059',
        ]);
        AiAgent::create([
            'name' => 'Antigravity',
            'ai_department_id' => $dept->id,
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempInboxDir)) {
            File::deleteDirectory($this->tempInboxDir);
        }
        parent::tearDown();
    }

    public function test_importing_stage_go_followup_closes_local_awaiting_go_and_updates_original_ticket_without_duplicate(): void
    {
        // 1. Originales Smart-Home-Ticket (Lichterkette) lokal im Status awaiting_go + waiting_for_alina
        $orig = $this->inbox->enqueueMessage([
            'channel' => 'android',
            'title' => 'Smart-Home-Integration (Lichterketten, Rollläden, Gruppen) im Live-Call',
            'body' => 'Lichterketten direkt im Live-Call schaltbar machen.',
            'execution_mode' => 'concept',
            'source' => 'android_app',
        ]);
        $origId = $orig['item']['id'];
        $this->inbox->completeMessage($origId, 'Konzept erstellt, bereit zum Rollout auf dein Go.');

        $origTicket = AntigravityUniverseTicketBridge::findTicketByAgId($origId);
        $this->assertNotNull($origTicket);
        $this->assertSame(AiUniverseTicket::STATUS_WAITING_FOR_ALINA, $origTicket->status);
        $this->assertCount(1, $this->inbox->getPendingBlockades());

        // 2. Stage-Sidecar importiert Folgeauftrag "GO & Antwort zu: ..." mit Referenz-Ticket
        $followupId = 'ag_20261126_180320_3fe9f890';
        $this->inbox->importItems([[
            'id' => $followupId,
            'channel' => 'android',
            'title' => 'GO & Antwort zu: Smart-Home-Integration (Lichterketten, Rollläden, Gruppen) im Live-Call',
            'body' => "Referenz-Ticket: {$origId}\nEntscheidung von Alina: FREIGABE / GO\nAntwort / Vorgabe: Go erteilt",
            'execution_mode' => 'direct',
            'status' => 'pending',
            'source' => 'falcone_go_resolver',
            'created_at' => now()->toIso8601String(),
        ]]);

        // Original-Blockade ist sofort aufgelöst (0 offene Blockaden!) und KEIN Duplikat-Ticket wurde erzeugt!
        $this->assertCount(0, $this->inbox->getPendingBlockades());
        $this->assertSame(1, AiUniverseTicket::count());
        $origTicket->refresh();
        $this->assertContains($origTicket->status, [
            AiUniverseTicket::STATUS_IN_PROGRESS,
            AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK,
        ]);
        $this->assertSame($followupId, $origTicket->action_payload['ag_item_id'] ?? null);

        // 3. Abschluss des Folgeauftrags schließt das Original-Ticket final auf completed!
        $this->inbox->completeMessage($followupId, 'Lichterketten-Integration vollständig live & verifiziert.', true);
        $origTicket->refresh();
        $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $origTicket->status);
    }

    public function test_completing_multihop_followup_transitively_closes_ancestors_and_legacy_duplicate_tickets(): void
    {
        $rootId = 'ag_20261126_185418_f2dbc2b1';
        $midId = 'ag_20261126_185421_6615eac4';
        $leafId = 'ag_20261126_191442_15f3787f';

        // Historische Kette direkt in die Inbox schreiben (als ob sie vor dem Fix entstanden wäre)
        $this->inbox->importItems([
            [
                'id' => $rootId,
                'channel' => 'shop',
                'title' => 'Smart-Home Lichterketten Basis-Konzept',
                'body' => 'Ursprünglicher Auftrag.',
                'execution_mode' => 'concept',
                'status' => 'awaiting_go',
                'source' => 'android_app',
            ],
        ]);

        // Historisch doppelt erzeugtes Ticket simulieren
        $legacyDupTicket = AiUniverseTicket::create([
            'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
            'title' => 'Smart-Home Lichterketten Basis-Konzept (Legacy Duplikat)',
            'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
            'priority' => 'high',
            'ticket_type' => 'antigravity_dev',
            'action_payload' => ['ag_item_id' => $rootId, 'ag_status' => 'awaiting_go'],
            'last_activity_at' => now(),
        ]);

        $this->inbox->importItems([
            [
                'id' => $midId,
                'channel' => 'shop',
                'title' => 'GO & Antwort zu: Smart-Home Lichterketten Basis-Konzept',
                'body' => "Referenz-Ticket: {$rootId}\nEntscheidung: GO",
                'execution_mode' => 'direct',
                'status' => 'delivered',
                'source' => 'falcone_go_resolver',
            ],
            [
                'id' => $leafId,
                'channel' => 'shop',
                'title' => 'GO & Antwort zu: GO & Antwort zu: Smart-Home Lichterketten Basis-Konzept',
                'body' => "Referenz-Ticket: {$midId}\nEntscheidung: GO final",
                'execution_mode' => 'direct',
                'status' => 'delivered',
                'source' => 'falcone_go_resolver',
            ],
        ]);

        // Abschluss des Leaf-Items schließt transitiv midId + rootId + alle Legacy-Duplikat-Tickets!
        $this->inbox->completeMessage($leafId, 'Alle Lichterketten-Schritte live umgesetzt.', true);

        $snapshot = $this->inbox->listInbox(50);
        $statusById = collect($snapshot['items'])->pluck('status', 'id')->all();

        $this->assertSame('completed', $statusById[$leafId] ?? null);
        $this->assertSame('completed', $statusById[$midId] ?? null);
        $this->assertSame('completed', $statusById[$rootId] ?? null);
        $this->assertCount(0, $this->inbox->getPendingBlockades());

        $legacyDupTicket->refresh();
        $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $legacyDupTicket->status);
    }

    public function test_portal_manual_and_action_completion_mirrors_back_to_direct_inbox_and_android_api(): void
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-sync-test@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
        Sanctum::actingAs($admin, ['*']);

        $created = $this->inbox->enqueueMessage([
            'channel' => 'android',
            'title' => 'Lichterkette Wohnzimmer Status-Sync',
            'body' => 'Test für Portal-zu-App Rückspiegelung.',
            'execution_mode' => 'concept',
            'source' => 'android_app',
        ]);
        $agId = $created['item']['id'];
        $this->inbox->completeMessage($agId, 'Wartet auf Go.');

        $ticket = AntigravityUniverseTicketBridge::findTicketByAgId($agId);
        $this->assertNotNull($ticket);
        $this->assertCount(1, $this->inbox->getPendingBlockades());

        // Abschluss über Portal-Quick-Action (performTicketAction -> markTicketStatus)
        FunkiraTicketRouter::performTicketAction($ticket, 'complete', ['channel' => 'web_portal']);

        $ticket->refresh();
        $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $ticket->status);
        $this->assertCount(0, $this->inbox->getPendingBlockades());

        // Beide Android-App Endpunkte melden synchron 0 offene Tickets & 0 Blockaden!
        $this->getJson('/api/system/ag-direct-inbox')
            ->assertOk()
            ->assertJsonCount(0, 'pending_blockades')
            ->assertJsonPath('counts.awaiting_go', 0);

        $this->getJson('/api/ai/tickets?status=open')
            ->assertOk()
            ->assertJsonCount(0, 'tickets')
            ->assertJsonPath('counts.waiting_for_alina', 0);
    }
}

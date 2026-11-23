<?php

namespace Tests\Feature\Ai;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiUniverseTicket;
use App\Services\AI\AntigravityTicketQueueService;
use App\Services\AI\CentralBrainService;
use App\Services\System\AntigravityDirectInboxService;
use App\Services\System\ZeroTokenSelfHealer;
use Database\Seeders\AiAgentSeeder;
use Database\Seeders\AiCompanyStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class AntigravityWorkUntilZeroTicketQueueTest extends TestCase
{
    use RefreshDatabase;

    private string $tempInboxRoot;
    private AntigravityDirectInboxService $inboxService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempInboxRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_wuz_queue_' . Str::lower(Str::random(8));
        $this->inboxService = new AntigravityDirectInboxService($this->tempInboxRoot);
        $this->app->instance(AntigravityDirectInboxService::class, $this->inboxService);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempInboxRoot)) {
            File::deleteDirectory($this->tempInboxRoot);
        }

        parent::tearDown();
    }

    public function test_local_inbox_permissions_are_cross_uid_accessible_and_support_tickets_channel(): void
    {
        $enqueued = $this->inboxService->enqueueMessage([
            'channel' => 'tickets',
            'title' => 'Queue Test Ticket',
            'body' => 'Prüfung der Dateirechte für Windows WSL Daemon.',
            'execution_mode' => 'direct',
            'source' => 'web_portal',
            'skip_universe_mirror' => true,
        ]);

        $this->assertSame('tickets', $enqueued['item']['channel']);
        $inboxFile = $this->inboxService->inboxFilePath();
        $this->assertFileExists($inboxFile);
        $this->assertSame(0666, fileperms($inboxFile) & 0666);
    }

    public function test_serial_work_until_zero_fifo_priority_and_sibling_cleanup(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        // Ticket 1 (normal), Ticket 2 (high -> muss zuerst drankommen!), Ticket 3 (waiting_for_alina -> muss pausieren!)
        $tNormal = AiUniverseTicket::create([
            'ticket_number' => 'SF-9001',
            'title' => 'Kleiner Layout-Fix im Footer',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'priority' => 'normal',
            'ticket_type' => 'antigravity_dev',
            'assigned_to_antigravity' => true,
            'source_channel' => 'web_portal',
            'environment_tier' => 'LOCAL',
            'action_payload' => [
                'type' => 'antigravity_active',
                'execution_mode' => 'direct',
                'ag_history_ids' => ['ag_20261123_090000_aaaa1111'],
            ],
            'last_activity_at' => now(),
        ]);

        $this->inboxService->importItems([[
            'id' => 'ag_20261123_090000_aaaa1111',
            'channel' => 'leitung',
            'title' => 'Alter History-Eintrag zu SF-9001',
            'body' => 'Alt',
            'execution_mode' => 'direct',
            'status' => 'delivered',
            'source' => 'web_portal',
        ]]);

        $tHigh = AiUniverseTicket::create([
            'ticket_number' => 'SF-9002',
            'title' => 'Kritischer SQL-Spaltenfehler beheben',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'priority' => 'high',
            'ticket_type' => 'system_error',
            'assigned_to_antigravity' => true,
            'source_channel' => 'system_sentinel',
            'environment_tier' => 'LOCAL',
            'action_payload' => [
                'type' => 'antigravity_active',
                'execution_mode' => 'direct',
            ],
            'last_activity_at' => now(),
        ]);

        AiUniverseTicket::create([
            'ticket_number' => 'SF-9003',
            'title' => '[Konzept] Wartet auf Freigabe von Alina',
            'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
            'priority' => 'high',
            'ticket_type' => 'antigravity_dev',
            'assigned_to_antigravity' => true,
            'source_channel' => 'web_portal',
            'environment_tier' => 'LOCAL',
            'action_payload' => [
                'type' => 'ag_inbox_blockade',
                'execution_mode' => 'concept',
            ],
            'last_activity_at' => now(),
        ]);

        $queue = app(AntigravityTicketQueueService::class);

        // 1. High-Priority SF-9002 wird zuerst gezogen (SF-9003 auf waiting_for_alina wird strikt übersprungen)
        $claim1 = $queue->claimNextDispatchableTicket(180, -1);
        $this->assertSame('dispatch', $claim1['action']);
        $this->assertSame('SF-9002', $claim1['ticket_number']);
        $this->assertSame(2, $claim1['open_count']);
        $this->assertSame('tickets', $claim1['channel']);
        $agIdHigh = (string) $claim1['ag_item_id'];
        $this->assertNotEmpty($agIdHigh);

        $queue->acknowledgeDispatched('SF-9002', 1);

        // 2. Solange SF-9002 aktiv im Worker-Chat läuft (chat_idle_seconds = 10), blockiert MaxInFlight=1 Ticket #2!
        $busyCheck = $queue->claimNextDispatchableTicket(180, 10);
        $this->assertSame('wait', $busyCheck['action']);
        $this->assertSame('SF-9002', $busyCheck['in_flight_ticket']);

        // 3. SF-9002 wird erledigt (inkl. Lokal-First Satz "bereit für Stage-Rollout auf dein Go." -> darf NICHT auf awaiting_go hängen!)
        $completedHigh = $this->inboxService->completeMessage(
            $agIdHigh,
            'SQL-Spaltenfehler behoben und per PHPUnit verifiziert. Lokal grün, bereit für Stage-Rollout auf dein Go.'
        );
        $this->assertSame('completed', $completedHigh['status']);
        $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $tHigh->fresh()->status);

        // 4. Sofort im nächsten Aufruf (0s Wartezeit) zieht die Queue automatisch SF-9001 nach!
        $claim2 = $queue->claimNextDispatchableTicket(180, 500);
        $this->assertSame('dispatch', $claim2['action']);
        $this->assertSame('SF-9001', $claim2['ticket_number']);
        $this->assertSame(1, $claim2['open_count']);
        $agIdNormal = (string) $claim2['ag_item_id'];

        $queue->acknowledgeDispatched('SF-9001', 1);
        $this->inboxService->completeMessage($agIdNormal, 'Footer-Layout lokal bereinigt und getestet.');

        $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $tNormal->fresh()->status);

        // Alter Sibling-Eintrag ag_20261123_090000_aaaa1111 wurde automatisch mit auf completed geräumt!
        $snap = $this->inboxService->listInbox(20);
        $this->assertSame(0, $snap['counts']['open']);

        // 5. Queue meldet jetzt sauber: 0 offene Antigravity-Tickets -> idle!
        $finalCheck = $queue->claimNextDispatchableTicket(180, 500);
        $this->assertSame('idle', $finalCheck['status']);
        $this->assertSame('none', $finalCheck['action']);
        $this->assertSame(0, $finalCheck['open_count']);
    }

    public function test_watchdog_recovers_stalled_ticket_and_trips_circuit_breaker_after_three_attempts(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $stalled = AiUniverseTicket::create([
            'ticket_number' => 'SF-9010',
            'title' => 'Blockierter externer Dienst',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'priority' => 'high',
            'ticket_type' => 'system_error',
            'assigned_to_antigravity' => true,
            'source_channel' => 'system_sentinel',
            'environment_tier' => 'LOCAL',
            'action_payload' => ['type' => 'antigravity_active', 'execution_mode' => 'direct'],
            'last_activity_at' => now(),
        ]);

        $healthyNext = AiUniverseTicket::create([
            'ticket_number' => 'SF-9011',
            'title' => 'Nächstes gesundes Ticket dahinter',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'priority' => 'normal',
            'ticket_type' => 'antigravity_dev',
            'assigned_to_antigravity' => true,
            'source_channel' => 'web_portal',
            'environment_tier' => 'LOCAL',
            'action_payload' => ['type' => 'antigravity_active', 'execution_mode' => 'direct'],
            'last_activity_at' => now(),
        ]);

        $queue = app(AntigravityTicketQueueService::class);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $claim = $queue->claimNextDispatchableTicket(60, 300);
            $this->assertSame('dispatch', $claim['action']);
            $this->assertSame('SF-9010', $claim['ticket_number']);
            $this->assertSame($attempt, $claim['attempt']);

            $queue->acknowledgeDispatched('SF-9010', $attempt);

            // Zeitstempel um 2 Minuten zurückdrehen (simuliert Restart / abgebrochenen Turn)
            $stalled->refresh();
            $p = $stalled->action_payload;
            $p['queue_dispatched_at'] = now()->subMinutes(3)->toIso8601String();
            $stalled->update(['action_payload' => $p]);
        }

        // Nach 3 erfolglosen Versuchen greift die Schutzbremse: SF-9010 geht auf waiting_for_alina
        // und SF-9011 wird im selben Atemzug als nächstes Ticket gezogen!
        $afterBreaker = $queue->claimNextDispatchableTicket(60, 300);
        $this->assertSame('dispatch', $afterBreaker['action']);
        $this->assertSame('SF-9011', $afterBreaker['ticket_number']);
        $this->assertSame(AiUniverseTicket::STATUS_WAITING_FOR_ALINA, $stalled->fresh()->status);
        $this->assertSame(AiUniverseTicket::STATUS_IN_PROGRESS, $healthyNext->fresh()->status);
    }

    public function test_zero_token_self_healer_auto_enqueues_unrepaired_code_errors_into_antigravity_queue(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $res = ZeroTokenSelfHealer::handleSystemError(
            'SQLSTATE[42S22]: Column not found: 1054 Unknown column test_col',
            'Illuminate\\Database\\QueryException',
            'app/Services/ExampleService.php',
            88
        );

        $this->assertSame('created_bundled_ticket', $res['action']);
        $ticket = AiUniverseTicket::findOrFail($res['ticket_id']);
        $this->assertTrue((bool) $ticket->assigned_to_antigravity);
        $this->assertSame('Systemi', $ticket->agent?->name);
        $this->assertNotEmpty($ticket->action_payload['ag_item_id'] ?? null);
        $this->assertSame('tickets', $ticket->action_payload['ag_channel'] ?? null);
    }

    public function test_autopilot_worker_prompt_enforces_fix_and_harden_in_one_breath_with_brain_history_and_category_guards(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        // 1. Früherer abgeschlossener Vorfall am selben Modul im Wissensgehirn hinterlegen
        CentralBrainService::upsertDeduplicatedKnowledge(
            title: '[SF-8800] System-Fehler: StreamFactory.php:241 Timeout',
            content: "## Lösung, Code-Härtung & Verifikation\nUrsache: DNS-Timeout an StreamFactory.php | Härtung: Fallback auf File-Cache aktiviert.",
            categoryName: 'Ticket-Abschlussberichte',
            tags: ['Ticket-Abschlussbericht', 'Code-Härtung'],
            sourcePath: 'ticket:SF-8800'
        );

        // 2. Wiederholter System-Fehler (5x aufgetreten) wird für den Ticket-Autopilot eingereiht
        $ticket = AiUniverseTicket::create([
            'ticket_number' => 'SF-9050',
            'title' => 'System-Fehler: StreamFactory.php:241 (php_network_getaddresses)',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'priority' => 'high',
            'ticket_type' => 'system_error',
            'assigned_to_antigravity' => true,
            'source_channel' => 'system_sentinel',
            'environment_tier' => 'STAGE',
            'error_fingerprint' => 'fp_streamfactory_241',
            'occurrence_count' => 5,
            'action_payload' => [
                'type' => 'antigravity_active',
                'execution_mode' => 'direct',
                'category' => 'redis_connectivity_or_dns',
                'file' => 'vendor/predis/predis/src/Connection/StreamFactory.php',
                'line' => 241,
                'exception_class' => 'Predis\\Connection\\ConnectionException',
            ],
            'last_activity_at' => now(),
        ]);

        $queue = app(AntigravityTicketQueueService::class);
        $claim = $queue->claimNextDispatchableTicket(180, -1);

        $this->assertSame('dispatch', $claim['action']);
        $prompt = (string) $claim['prompt'];

        // Prüfe: 4-Stufen "Lösen + Code-Härtung in einem Atemzug" Mandat, Wiederholungs-Warnung (5x), Kategorie-Checkliste & Brain-Historie
        $this->assertStringContainsString('Verbindliches 4-Stufen-Protokoll (Lösen + Code-Härtung in einem Atemzug):', $prompt);
        $this->assertStringContainsString('1. Ursachen-Fix statt Symptom-Pflaster:', $prompt);
        $this->assertStringContainsString('2. Code-Härtung an Fehlerstelle & Nachbarpfaden:', $prompt);
        $this->assertStringContainsString('Netzwerk-, DNS-, Stream- und I/O-Resilienz:', $prompt);
        $this->assertStringContainsString('3. Regressions-Schutz verankern:', $prompt);
        $this->assertStringContainsString('Wiederholungs-Warnung: Dieser Vorfall trat bereits 5x auf.', $prompt);
        $this->assertStringContainsString('Frühere Ticket-Historie zum selben Modul (Wiederholungs-Schutz):', $prompt);
        $this->assertStringContainsString('[SF-8800] System-Fehler: StreamFactory.php:241 Timeout', $prompt);
        $this->assertStringContainsString('Ursache: <Wurzelursache> | Fix: <Behebung> | Härtung: <Code-Absicherung + Nachbarstellen> | Test: <grüner Testnachweis>', $prompt);
        // Strikte Einhaltung Baustein 1: 0 KI-Gedankenstriche
        $this->assertStringNotContainsString(' – ', $prompt);
        $this->assertStringNotContainsString(' — ', $prompt);

        // 3. Strukturierter Abschluss speichert Code-Härtung und Fehlerort sauber im Wissensgehirn (ohne fälschliches awaiting_go!)
        $agId = (string) $claim['ag_item_id'];
        $queue->acknowledgeDispatched('SF-9050', 1);

        $completed = $this->inboxService->completeMessage(
            $agId,
            'Ursache: Nicht auflösbarer Redis-Host in StreamFactory | Fix: Verbindungsaufbau mit engem Timeout gekapselt | Härtung: Alle Nachbar-Aufrufe in Cache/Session-Boot auf File-Fallback gehärtet | Test: PHPUnit RedisFallbackTest grün.'
        );

        $this->assertSame('completed', $completed['status']);
        $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $ticket->fresh()->status);

        $brainReport = AiKnowledgeBase::where('source_path', 'ticket:SF-9050')->with('tags')->first();
        $this->assertNotNull($brainReport);
        $this->assertStringContainsString('## Lösung, Code-Härtung & Verifikation', (string) $brainReport->content);
        $this->assertStringContainsString('Gehärteter Fehlerort:', (string) $brainReport->content);
        $this->assertStringContainsString('(vor Fix 5x aufgetreten)', (string) $brainReport->content);
        $this->assertContains('Code-Härtung', $brainReport->tags->pluck('name')->all());
    }
}

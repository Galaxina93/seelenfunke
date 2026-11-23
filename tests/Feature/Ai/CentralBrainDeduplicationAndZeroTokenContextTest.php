<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Services\AI\CentralBrainService;
use App\Services\AI\FunkiraTicketRouter;
use App\Services\AI\KnowledgeReportSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralBrainDeduplicationAndZeroTokenContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_stage_deduplication_prevents_any_duplicate_knowledge_in_brain(): void
    {
        $baselineCount = AiKnowledgeBase::count();

        // 1. Initial creation
        $first = CentralBrainService::upsertDeduplicatedKnowledge(
            title: 'Abschlussbericht: xTool F2 Ultra UV Fokus-Kalibrierung Spezialtest',
            content: 'Spezialtest-Eintrag: Der optimale Fokus für den K9 Seelen-Kristall liegt bei 32,8 bis 33,1 mm mit 5W UV-Laser.',
            categoryName: 'Produkte',
            tags: ['Laser', 'K9'],
            sourcePath: 'manual:focus-guide-test'
        );

        $this->assertNotNull($first);
        $this->assertSame($baselineCount + 1, AiKnowledgeBase::count());

        // 2. Same source_path with updated text -> updates existing row (count stays baseline + 1)
        $bySource = CentralBrainService::upsertDeduplicatedKnowledge(
            title: 'xTool F2 Ultra UV Fokus-Kalibrierung Spezialtest v2',
            content: 'Spezialtest-Eintrag: Der optimale Fokus für den K9 Seelen-Kristall liegt bei 32,8 bis 33,1 mm mit 5W UV-Laser im 140x140mm Raster.',
            categoryName: 'Produkte',
            tags: ['Conveyor'],
            sourcePath: 'manual:focus-guide-test'
        );

        $this->assertSame($first->id, $bySource->id);
        $this->assertSame($baselineCount + 1, AiKnowledgeBase::count());
        $this->assertGreaterThanOrEqual(3, $bySource->fresh('tags')->tags->count());

        // 3. Exact canonical content_sha256 under a completely different title -> updates existing row
        $byHash = CentralBrainService::upsertDeduplicatedKnowledge(
            title: 'Kopie unter anderem Namen Spezialtest',
            content: "Spezialtest-Eintrag: Der optimale Fokus für den K9 Seelen-Kristall liegt bei 32,8 bis 33,1 mm   mit 5W UV-Laser im 140x140mm Raster.\n\n",
            categoryName: 'Produkte',
            tags: ['Duplikat-Test']
        );

        $this->assertSame($first->id, $byHash->id);
        $this->assertSame($baselineCount + 1, AiKnowledgeBase::count());

        // 4. Normalized title match ("Konzept: ..." vs base title) -> updates existing row
        $byTitle = CentralBrainService::upsertDeduplicatedKnowledge(
            title: 'Konzept: Kopie unter anderem Namen Spezialtest',
            content: 'Spezialtest-Erweiterung für den xTool F2 Ultra UV Laser bei K9 Kristallglas, Schiefer und eloxiertem Aluminium in Gifhorn.',
            categoryName: 'Produkte'
        );

        $this->assertSame($first->id, $byTitle->id);
        $this->assertSame($baselineCount + 1, AiKnowledgeBase::count());

        // 5. High Jaccard token similarity (>= 0.82) in same category -> merges instead of duplicating!
        $bySimilarity = CentralBrainService::upsertDeduplicatedKnowledge(
            title: 'Spezialtest Erweiterung xTool F2 Ultra UV Laser K9 Kristallglas Schiefer Aluminium Gifhorn',
            content: 'Spezialtest-Erweiterung für den xTool F2 Ultra UV Laser bei K9 Kristallglas, Schiefer und eloxiertem Aluminium in Gifhorn.',
            categoryName: 'Produkte'
        );

        $this->assertSame($first->id, $bySimilarity->id);
        $this->assertSame($baselineCount + 1, AiKnowledgeBase::count());
    }

    public function test_completing_ticket_automatically_creates_deduplicated_completion_report_in_brain(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $dept = AiDepartment::create([
            'name' => 'System',
            'slug' => 'system',
            'color' => 'red-500',
            'icon' => 'server',
            'order_index' => 1,
        ]);

        $agent = AiAgent::create([
            'name' => 'Systemi',
            'wake_word' => 'systemi',
            'role_description' => 'System-Wächter',
            'system_prompt' => 'Du bist Systemi.',
            'model' => 'gemini-2.5-flash',
            'ai_department_id' => $dept->id,
            'is_active' => true,
        ]);

        $ticket = AiUniverseTicket::create([
            'ticket_number' => 'SF-0999',
            'title' => 'Sanctum Token Lock Optimierung',
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'priority' => 'normal',
            'ticket_type' => 'system_task',
            'ai_department_id' => $dept->id,
            'assigned_agent_id' => $agent->id,
            'source_channel' => 'web_portal',
            'environment_tier' => 'TESTING',
        ]);

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'alina',
            'sender_name' => 'Alina',
            'channel' => 'web_portal',
            'content' => 'Bitte prüfe und optimiere die Sanctum Token Schreibzugriffe.',
        ]);

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'agent',
            'sender_name' => 'Systemi',
            'ai_agent_id' => $agent->id,
            'channel' => 'web_portal',
            'content' => 'PersonalAccessToken mit 5-Minuten-Throttle aktiviert.',
            'executed_tools' => ['system_get_health'],
        ]);

        FunkiraTicketRouter::setManualTicketStatus($ticket, AiUniverseTicket::STATUS_COMPLETED);

        $report = AiKnowledgeBase::where('source_path', 'ticket:SF-0999')->first();
        $this->assertNotNull($report, 'Completing a ticket must automatically store its completion report in AiKnowledgeBase');
        $this->assertStringContainsString('[SF-0999] Sanctum Token Lock Optimierung', $report->title);
        $this->assertStringContainsString('PersonalAccessToken mit 5-Minuten-Throttle', $report->content);

        // Re-recording the same ticket updates in place without creating a second entry
        CentralBrainService::recordTicketCompletionReport($ticket->fresh(['messages', 'department', 'agent']), 'Aktualisierte Zusammenfassung.');
        $this->assertSame(1, AiKnowledgeBase::where('source_path', 'ticket:SF-0999')->count());
    }

    public function test_zero_token_instant_context_runs_in_milliseconds_for_internal_agents_and_blocks_funki(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $dept = AiDepartment::create([
            'name' => 'Produkte',
            'slug' => 'produkte',
            'color' => 'blue-500',
            'icon' => 'cube',
            'order_index' => 2,
        ]);

        AiLearnedPreference::create([
            'scope_key' => 'packaging:standard_box',
            'ai_department_id' => $dept->id,
            'subject_label' => 'Geschenkbox',
            'rule_summary' => 'Immer schwarze Westpack-Schmuckbox (+4,90 EUR) empfehlen.',
            'is_active' => true,
        ]);

        CentralBrainService::upsertDeduplicatedKnowledge(
            title: '[SF-0500] Bambu Lab A1 Stützstruktur-Regel',
            content: 'Für alle Manufaktur-3D-Drucke auf dem Bambu Lab A1 gilt 0 % Stützstruktur, 45 Grad Fasen und 15 % Gyroid-Infill.',
            categoryName: 'Ticket-Abschlussberichte',
            tags: ['3D-Druck', 'Bambu'],
            sourcePath: 'ticket:SF-0500'
        );

        $produkti = AiAgent::create([
            'name' => 'Produkti',
            'wake_word' => 'produkti',
            'role_description' => 'Produkt-Spezialist',
            'system_prompt' => 'Du bist Produkti.',
            'model' => 'gemini-2.5-flash',
            'ai_department_id' => $dept->id,
            'is_active' => true,
        ]);

        $funki = AiAgent::create([
            'name' => 'Funki',
            'wake_word' => 'funki',
            'role_description' => 'Kundensupport',
            'system_prompt' => 'Du bist Funki.',
            'model' => 'gemini-2.5-flash',
            'is_active' => true,
        ]);

        // Warm once, then assert millisecond retrieval
        $firstCtx = CentralBrainService::buildZeroTokenInstantContext($dept->id, 'Welche Parameter gelten beim Bambu Lab A1 3D-Druck?', $produkti);
        $t0 = hrtime(true);
        $cachedCtx = CentralBrainService::buildZeroTokenInstantContext($dept->id, 'Welche Parameter gelten beim Bambu Lab A1 3D-Druck?', $produkti);
        $elapsedMs = (hrtime(true) - $t0) / 1_000_000;

        $this->assertLessThan(25.0, $elapsedMs, "Expected millisecond context retrieval, took {$elapsedMs} ms");
        $this->assertStringContainsString('[GLOBALE SYSTEM-ARCHITEKTUR (0-TOKEN DIREKTWISSEN)]', $firstCtx);
        $this->assertStringContainsString('Westpack-Schmuckbox', $firstCtx);
        $this->assertStringContainsString('Gyroid-Infill', $cachedCtx);

        // Air-gapped customer support bot Funki MUST receive empty string!
        $funkiCtx = CentralBrainService::buildZeroTokenInstantContext($dept->id, 'Welche Parameter gelten beim Bambu Lab A1 3D-Druck?', $funki);
        $this->assertSame('', $funkiCtx);
    }

    public function test_legacy_abschlussberichte_folder_is_eliminated_and_brain_snapshot_sync_works(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('Abschlussberichte'));
        $this->assertFileExists(CentralBrainService::resolveSnapshotPath());

        $stats = app(KnowledgeReportSyncService::class)->sync();
        $this->assertGreaterThan(50, $stats['scanned']);
        $this->assertGreaterThan(50, AiKnowledgeBase::count());
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiUniverseTickets;
use App\Models\Admin\Admin;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\AgentSkillBudgetGuard;
use App\Services\AI\FunkiraTicketRouter;
use App\Services\AI\TicketDeliverableService;
use Database\Seeders\AiAgentSeeder;
use Database\Seeders\AiCompanyStructureSeeder;
use Database\Seeders\SystemCronjobSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TicketDeliverables3dHologramAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-3d-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_3d_print_evaluator_and_routing_assign_lasi_and_produkti_while_keeping_funki_locked(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        // 1. Laser-Klickplatte routet automatisch zu Lasi
        $triageLaser = FunkiraTicketRouter::classifyIntent('Bitte baue eine neue xTool Klickplatte für die Seelen-Pfote');
        $this->assertSame('Lasi', $triageLaser['agent_name']);
        $this->assertSame('Produkte', $triageLaser['department_name']);

        // 2. Allgemeiner 3D-Druck / Bambu Organizer routet automatisch zu Produkti
        $triage3d = FunkiraTicketRouter::classifyIntent('Ich brauche ein 3D-Druck Modell als Regal-Spender für den Bambu Lab A1');
        $this->assertSame('Produkti', $triage3d['agent_name']);
        $this->assertSame('Produkte', $triage3d['department_name']);

        // 3. Pflicht-Evaluator hinterfragt fehlende Maße, Eingreif-Luft und xTool-Durchschussfenster
        $evalMissing = AIFunctionsRegistry::execute('print3d_evaluate_design_parameters', [
            'purpose' => 'xTool Laser-Klickplatte für K9-Kristall',
            'width_mm' => 0,
            'depth_mm' => 0,
            'height_mm' => 0,
            'clearance_above_mm' => 75,
            'exposed_to_heat_or_uv_laser' => true,
            'has_spring_clips' => false,
        ]);

        $this->assertSame('evaluated', $evalMissing['status']);
        $this->assertNotEmpty($evalMissing['missing_parameters_to_ask_alina']);
        $this->assertNotEmpty($evalMissing['engineering_warnings']);
        $this->assertStringContainsString('NICHT blind modellieren', $evalMissing['next_step_directive']);

        // 4. Funki bleibt 100 % unverändert (darf weder print3d noch ticket_attach_deliverable erhalten)
        $immutable = AgentSkillBudgetGuard::getImmutableFunkiToolIdentifiers();
        $this->assertNotContains('print3d_evaluate_design_parameters', $immutable);
        $this->assertNotContains('ticket_attach_deliverable', $immutable);
    }

    public function test_sticky_deliverables_attach_to_ticket_messages_expose_funkenwerk3d_uri_and_render_in_files_view(): void
    {
        Storage::fake('workspace');
        Storage::fake('local');

        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $res = FunkiraTicketRouter::dispatchMessage('Konzept und 3D-Modell für Stiftehalter prüfen', [
            'channel' => 'web_portal',
        ]);
        /** @var AiUniverseTicket $ticket */
        $ticket = $res['ticket'];
        $this->assertNotNull($ticket);

        // 1. Konzept (.md) und 3D-Modell (.stl) über ticket_attach_deliverable direkt ans Ticket heften
        $conceptRes = AIFunctionsRegistry::execute('ticket_attach_deliverable', [
            'ticket_id' => $ticket->ticket_number,
            'filename' => 'Konzept_Stiftehalter.md',
            'content_text' => "# 3D Konzept\n\n- **0 % Support**\n- `10 % Lightning` Infill",
        ]);
        $this->assertSame('success', $conceptRes['status'] ?? null);

        $asciiStl = "solid testcube\nfacet normal 0 0 1\nouter loop\nvertex 0 0 0\nvertex 10 0 0\nvertex 0 10 0\nendloop\nendfacet\nendsolid testcube\n";
        $stlRes = AIFunctionsRegistry::execute('ticket_attach_deliverable', [
            'ticket_id' => $ticket->ticket_number,
            'filename' => 'Stiftehalter_BambuA1.stl',
            'content_text' => $asciiStl,
            'dimensions_mm' => ['width' => 80, 'depth' => 80, 'height' => 110],
            'filament_grams' => 94,
        ]);
        $this->assertSame('success', $stlRes['status'] ?? null);
        $this->assertSame('3d', $stlRes['attachment']['category'] ?? null);
        $this->assertStringStartsWith('funkenwerk3d://open?path=', (string) ($stlRes['attachment']['funkenwerk_3d_uri'] ?? ''));

        // 2. Zentrale Dateien-Ansicht im Ticketsystem zeigt beide Einträge + Inline-Konzept-Reader funktioniert
        $lw = Livewire::actingAs($admin, 'admin')
            ->test(AiUniverseTickets::class)
            ->call('setMainView', 'files')
            ->assertSet('mainView', 'files')
            ->assertSee('Konzept_Stiftehalter.md')
            ->assertSee('Stiftehalter_BambuA1.stl')
            ->assertSee('Funkenwerk 3D')
            ->call('openConceptModal', $conceptRes['attachment']['rel_path'], 'Konzept_Stiftehalter.md')
            ->assertSet('conceptModalTitle', 'Konzept_Stiftehalter.md');

        $this->assertStringContainsString('<strong>0 % Support</strong>', (string) $lw->get('conceptModalHtml'));
    }

    public function test_automated_4_week_lifecycle_archives_old_files_preserves_pinned_files_and_purges_ancient_garbage(): void
    {
        Storage::fake('workspace');
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);
        $this->seed(SystemCronjobSeeder::class);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $res = FunkiraTicketRouter::dispatchMessage('Test-Ticket für 4-Wochen-Datei-Autopilot', ['channel' => 'web_portal']);
        /** @var AiUniverseTicket $ticket */
        $ticket = $res['ticket'];

        // Datei A: 35 Tage alt, NICHT angepinnt -> muss automatisch ins .gz-Archiv wandern!
        $oldMeta = TicketDeliverableService::storeBytesForTicket(
            str_repeat("solid block\n", 80),
            'Altmodell_35_Tage.stl',
            $ticket->ticket_number,
            ['created_at' => now()->subDays(35)->toIso8601String(), 'pinned' => false]
        );

        // Datei B: 45 Tage alt, ABER mit ★ Pin geschützt -> muss aktiv bleiben!
        $pinnedMeta = TicketDeliverableService::storeBytesForTicket(
            "# Wichtige Master-Referenz\nBleibt dauerhaft aktiv.",
            'Master_Referenz.md',
            $ticket->ticket_number,
            ['created_at' => now()->subDays(45)->toIso8601String(), 'pinned' => true]
        );

        $msg = AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'agent',
            'sender_name' => 'Antigravity',
            'channel' => 'web_portal',
            'content' => 'Zwei Dateien bereitgestellt.',
            'attachments' => [$oldMeta, $pinnedMeta],
        ]);

        // Cronjob ausführen (Standard: 28 Tage -> Archiv)
        Artisan::call('tickets:cleanup-files');

        $msg->refresh();
        $atts = $msg->attachments;
        $this->assertTrue((bool) ($atts[0]['archived'] ?? false), '35 Tage alte ungeschützte STL muss archiviert sein.');
        $this->assertStringEndsWith('.stl.gz', (string) ($atts[0]['archive_rel_path'] ?? ''));
        $this->assertFalse(Storage::disk('workspace')->exists($oldMeta['rel_path']));
        $this->assertTrue(Storage::disk('workspace')->exists($atts[0]['archive_rel_path']));

        $this->assertFalse((bool) ($atts[1]['archived'] ?? false), 'Angepinnte Datei darf niemals zwangsarchiviert werden.');
        $this->assertTrue(Storage::disk('workspace')->exists($pinnedMeta['rel_path']));

        // 1-Klick Reaktivierung aus dem Archiv holt die .stl entpackt zurück in den Direktzugriff
        $restored = TicketDeliverableService::restoreArchivedAttachment($msg->id, (string) $atts[0]['id']);
        $this->assertTrue($restored);

        $msg->refresh();
        $this->assertFalse((bool) ($msg->attachments[0]['archived'] ?? true));
        $this->assertTrue(Storage::disk('workspace')->exists($msg->attachments[0]['rel_path']));

        // Und nach > 180 Tagen im Archiv wird ungeschützter Alt-Müll restlos physisch gelöscht
        $ancient = $msg->attachments;
        $ancient[0]['created_at'] = now()->subDays(220)->toIso8601String();
        $msg->update(['attachments' => $ancient]);
        TicketDeliverableService::runAutomatedLifecycleCleanup(28, 180); // Erst archivieren

        $msg->refresh();
        $archState = $msg->attachments;
        $archPath = (string) $archState[0]['archive_rel_path'];
        $archState[0]['archived_at'] = now()->subDays(190)->toIso8601String();
        $msg->update(['attachments' => $archState]);

        $purgeStats = TicketDeliverableService::runAutomatedLifecycleCleanup(28, 180);
        $this->assertSame(1, $purgeStats['purged_archive_count']);
        $this->assertFalse(Storage::disk('workspace')->exists($archPath));
    }
}

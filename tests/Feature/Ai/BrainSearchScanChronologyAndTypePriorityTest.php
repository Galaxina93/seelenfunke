<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiWorkspaceDocument;
use App\Models\Customer\Customer;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\AgentSkillBudgetGuard;
use App\Services\AI\CentralBrainService;
use App\Services\System\KdriveTrichterRelayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BrainSearchScanChronologyAndTypePriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_brain_search_prioritizes_chronological_latest_scan_over_laser_and_system_docs(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 1. Störfeuer-Einträge exakt wie in Ticket SF-0131 (enthalten literal "Letzter", "Scan", "Dokumentation")
        CentralBrainService::upsertDeduplicatedKnowledge(
            title: 'Brennpunkt-Maße & Laser-Arbeitsdokumentation (xTool F2 Ultra UV)',
            content: "## Letzter Check vor dem Klick auf Lasern\nVollständige Laser-Arbeitsdokumentation für K9 Kristallglas.",
            categoryName: 'Laser',
            tags: ['Laser', 'xTool', 'Brennpunkt'],
            sourcePath: 'Abschlussberichte/Laser/Brennpunkt_Masse.md',
            chronologicalDate: Carbon::parse('2026-11-23 00:15:00', 'Europe/Berlin')
        );

        CentralBrainService::upsertDeduplicatedKnowledge(
            title: 'System-Dokumentation: Neurale Analyse',
            content: "## Rekursiver Datei-Scan\nScannt alle Systemdateien und prüft das neuestes Modul-Dokument.",
            categoryName: 'System',
            tags: ['System', 'Analyse'],
            sourcePath: 'Abschlussberichte/System/Neurale_Analyse.md',
            chronologicalDate: Carbon::parse('2026-11-23 00:20:00', 'Europe/Berlin')
        );

        // 2. Älterer echter Scan (Oktober 2026)
        KdriveTrichterRelayService::recordScanInCentralBrain([
            'sha256' => hash('sha256', 'older-bkk-scan'),
            'file_name' => '2026-10-02_BKK-firmus_Schreiben.pdf',
            'remote_path' => '06_Gesundheit/Krankenkasse/2026-10-02_BKK-firmus_Schreiben.pdf',
            'target_folder' => '06_Gesundheit/Krankenkasse',
            'extension' => 'pdf',
            'size_bytes' => 190809,
            'title' => 'BKK firmus Schreiben',
            'summary' => 'Älteres eingescanntes Schreiben der BKK firmus vom 02.10.2026.',
            'extracted_date' => '2026-10-02',
            'tags' => ['BKK firmus', 'Gesundheit'],
            'source' => 'android_scanner',
            'scanned_at' => Carbon::parse('2026-10-02 10:59:00', 'Europe/Berlin'),
        ]);

        // 3. Neuester echter Scan (Renteninformation vom 22.11.2026 17:44:57)
        KdriveTrichterRelayService::recordScanInCentralBrain([
            'sha256' => '0c01d4d75876d8c2300d886dd2ff3d6f02a78d2b34d0fa9186c78ba6f5500aa5',
            'file_name' => '2026-11-22_DRV_Renteninformation.pdf',
            'remote_path' => '05_Familie_Ausweise_Vorsorge/Rente_Vorsorge/2026/2026-11-22_DRV_Renteninformation.pdf',
            'target_folder' => '05_Familie_Ausweise_Vorsorge/Rente_Vorsorge/2026',
            'extension' => 'pdf',
            'size_bytes' => 1766237,
            'title' => 'DRV Renteninformation',
            'summary' => 'Eingescannte Renteninformation der Deutschen Rentenversicherung (DRV) vom 22.11.2026.',
            'extracted_date' => '2026-11-22',
            'tags' => ['DRV', 'Renteninformation', 'Rente'],
            'source' => 'android_scanner',
            'scanned_at' => Carbon::parse('2026-11-22 17:44:57', 'Europe/Berlin'),
        ]);

        // A) Exakte Funkira-Abfrage aus Ticket SF-0131 (ohne optionale Parameter)
        $res1 = AIFunctionsRegistry::executeSearchBrain([
            'query' => 'letzter Scan / neuestes Dokument',
        ]);

        $this->assertSame('success', $res1['status']);
        $this->assertNotEmpty($res1['results']);
        $this->assertStringContainsString('Renteninformation', (string) ($res1['results'][0]['title'] ?? ''));
        $this->assertSame('2026-11-22_DRV_Renteninformation.pdf', $res1['latest_scan']['filename'] ?? null);

        foreach ($res1['results'] as $item) {
            $title = (string) ($item['title'] ?? '');
            $this->assertStringNotContainsString('Brennpunkt-Maße', $title);
            $this->assertStringNotContainsString('Neurale Analyse', $title);
        }

        // B) Natürlichsprachliche Frage von Alina
        $res2 = AIFunctionsRegistry::executeSearchBrain([
            'query' => 'Welches Dokument wurde als letztes eingescannt?',
        ]);

        $this->assertSame('success', $res2['status']);
        $this->assertStringContainsString('Renteninformation', (string) ($res2['results'][0]['title'] ?? ''));
        $this->assertTrue((bool) ($res2['results'][0]['is_scan'] ?? false));

        // C) Explizite Schema-Parameter doc_type=scan & sort_by=latest + Chronologie-Beweis (Nov 22 vor Okt 02)
        $res3 = AIFunctionsRegistry::executeSearchBrain([
            'query' => 'Scan',
            'doc_type' => 'scan',
            'sort_by' => 'latest',
        ]);

        $this->assertSame('success', $res3['status']);
        $this->assertGreaterThanOrEqual(2, count($res3['workspace_documents']));
        $this->assertSame('2026-11-22_DRV_Renteninformation.pdf', $res3['workspace_documents'][0]['filename']);
        $this->assertSame('2026-10-02_BKK-firmus_Schreiben.pdf', $res3['workspace_documents'][1]['filename']);

        // D) Normale Laser-Fachabfrage liefert weiterhin regulär die Brennpunkt-Maße auf Platz 1
        $resLaser = AIFunctionsRegistry::executeSearchBrain([
            'query' => 'Brennpunkt-Maße xTool Fokus',
        ]);

        $this->assertSame('success', $resLaser['status']);
        $this->assertStringContainsString('Brennpunkt-Maße', (string) ($resLaser['results'][0]['title'] ?? ''));
    }

    public function test_kdrive_trichter_upload_automatically_indexes_scan_and_ignores_e2e_probes(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $pdfBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";

        // 1. E2E-Prüfsonde darf die Scan-Chronik NICHT verschmutzen
        $probeFile = UploadedFile::fake()->createWithContent('Funkenwerk_E2E_Pruefung_abc123.pdf', $pdfBytes);
        KdriveTrichterRelayService::processUploadedFile($probeFile, null, 'e2e_probe');

        $this->assertFalse(
            AiWorkspaceDocument::where('filename', 'like', 'Funkenwerk_E2E_Pruefung_%')->exists()
        );

        // 2. Echter Scanner-Upload wird sofort in AiWorkspaceDocument & AiKnowledgeBase protokolliert
        $scanFile = UploadedFile::fake()->createWithContent('Scan_20261123_103000.pdf', $pdfBytes . '%Renteninfo-Neu');
        $uploadRes = KdriveTrichterRelayService::processUploadedFile(
            $scanFile,
            'DRV Renteninformation Aktuell',
            'android_scanner'
        );

        $this->assertTrue((bool) ($uploadRes['success'] ?? false));

        $wsDoc = AiWorkspaceDocument::onlyScans()->orderByDesc('created_at')->first();
        $this->assertNotNull($wsDoc);
        $this->assertStringContainsString('Renteninformation_Aktuell', $wsDoc->filename);
        $this->assertTrue($wsDoc->isScanDocument());
        $this->assertSame('https://kdrive.infomaniak.com/app/drive/2216011', $wsDoc->url);

        $kbScan = AiKnowledgeBase::where('source_path', 'like', 'scan:kdrive:%')->latest('created_at')->first();
        $this->assertNotNull($kbScan);
        $this->assertStringContainsString('DRV Renteninformation Aktuell', $kbScan->title);

        $searchRes = AIFunctionsRegistry::executeSearchBrain([
            'query' => 'letzter Scan / neuestes Dokument',
        ]);
        $this->assertSame('success', $searchRes['status']);
        $this->assertSame($wsDoc->filename, $searchRes['latest_scan']['filename'] ?? null);

        // Aufräumen der temporären Relay-Datei
        if (!empty($uploadRes['relay_id'])) {
            KdriveTrichterRelayService::ackAndDelete((string) $uploadRes['relay_id']);
        }
    }

    public function test_non_admin_and_funki_cannot_access_scanned_documents_in_brain(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $denied = AIFunctionsRegistry::executeSearchBrain([
            'query' => 'letzter Scan / neuestes Dokument',
        ]);

        $this->assertSame('error', $denied['status'] ?? null);
        $this->assertNotContains('brain_search', AgentSkillBudgetGuard::IMMUTABLE_FUNKI_TOOLS);
        $this->assertNotContains('workspace_get_latest_uploads', AgentSkillBudgetGuard::IMMUTABLE_FUNKI_TOOLS);
    }
}

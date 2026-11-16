<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Services\AI\AiAuthHelper;
use App\Services\AI\Functions\AiBrainFuncs;
use App\Services\AI\KnowledgeReportSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KnowledgeReportSyncAndBrainSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeBrainCaller(): object
    {
        return new class {
            use AiBrainFuncs;
        };
    }

    private function resetAllGuards(): void
    {
        foreach (['web', 'admin', 'employee', 'customer', 'sanctum'] as $guard) {
            try {
                $g = Auth::guard($guard);
                if (method_exists($g, 'forgetUser')) {
                    $g->forgetUser();
                }
                if (method_exists($g, 'logout')) {
                    $g->logout();
                }
            } catch (\Throwable $ignore) {
            }
        }
        Auth::shouldUse('web');
    }

    public function test_sync_service_categorizes_autotags_skips_unchanged_and_preserves_manual_wiki_entries(): void
    {
        $manualCat = AiKnowledgeBaseCategory::create([
            'name' => 'Manuelles Wiki',
            'slug' => 'manuelles-wiki',
        ]);

        $manualEntry = AiKnowledgeBase::create([
            'title' => 'Manuelle Privatnotiz Alinas Tresor',
            'slug' => 'manuelle-privatnotiz-alinas-tresor',
            'source_path' => null,
            'ai_knowledge_base_category_id' => $manualCat->id,
            'content' => 'Diese handgeschriebene Notiz darf niemals vom Report-Sync verändert oder gelöscht werden.',
            'is_published' => true,
        ]);

        $tmpDir = sys_get_temp_dir() . '/sf_kb_sync_test_' . uniqid();
        File::makeDirectory($tmpDir . '/Produkte', 0755, true);
        File::makeDirectory($tmpDir . '/Konzepte', 0755, true);
        File::makeDirectory($tmpDir . '/Workflows', 0755, true);
        File::makeDirectory($tmpDir . '/Prüfprotokoll', 0755, true);

        try {
            File::put(
                $tmpDir . '/README.md',
                "# Zentrale Übersicht aller Abschlussberichte\n\nÜberblick über das Seelenfunke Ökosystem."
            );

            File::put(
                $tmpDir . '/Produkte/Produkte_Laser_Kalibrierung.md',
                "# Produkte: Laser-Kalibrierung & Rohlinge\n\n"
                . "Einsatz von xTool F2 Ultra UV mit RA3 Rotationsaufsatz im Pfad `/admin/products` und `/api/laser/frame`.\n"
                . "Optimiert durch `GlbOptimizerService`, `ProductConfiguratorController` und `ProductConfigurator.php` sowie `funkenwerk_city_3d.js`."
            );

            File::put(
                $tmpDir . '/Konzepte/Konzept_Vertex_Louvre.md',
                "# Konzept: Vertex AI & Louvre Heldenhalle\n\n"
                . "Anbindung über `VertexAiService` und `FunkenwerkHeroesController` auf dem Mini-PC (`minipc`)."
            );

            File::put(
                $tmpDir . '/Workflows/Workflow_Stage_Deploy.md',
                "# Workflow: Mittwald Stage-Deployment\n\n"
                . "Automatisiertes Rollout nach Mittwald (`stage.mein-seelenfunke.de`) inklusive `SystemPrepareLiveCommand`."
            );

            File::put(
                $tmpDir . '/Prüfprotokoll/Audit_Stripe_Checkout.md',
                "# Prüfprotokoll: Stripe Checkout & DSGVO\n\n"
                . "Geprüft in `OrderCheckout.php` und `StripeWebhookController` unter `/admin/orders`."
            );

            $service = app(KnowledgeReportSyncService::class);

            // 1. Erster Sync-Lauf: 5 Berichte neu importiert
            $firstRun = $service->sync($tmpDir, false);
            $this->assertSame(5, $firstRun['scanned']);
            $this->assertSame(5, $firstRun['created']);
            $this->assertSame(0, $firstRun['updated']);
            $this->assertSame(0, $firstRun['skipped_unchanged']);
            $this->assertSame(0, $firstRun['deleted_stale']);

            // Kategorien & Auto-Tagging prüfen
            $rootEntry = AiKnowledgeBase::with(['category', 'tags'])
                ->where('source_path', 'Abschlussberichte/README.md')
                ->firstOrFail();
            $this->assertSame('Übersicht', $rootEntry->category?->name);
            $this->assertSame(AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE, $rootEntry->visibility_scope);
            $this->assertTrue($rootEntry->is_published);

            $prodEntry = AiKnowledgeBase::with(['category', 'tags'])
                ->where('source_path', 'Abschlussberichte/Produkte/Produkte_Laser_Kalibrierung.md')
                ->firstOrFail();
            $this->assertSame('Produkte', $prodEntry->category?->name);
            $prodTags = $prodEntry->tags->pluck('name')->all();
            $this->assertLessThanOrEqual(15, count($prodTags));
            $this->assertContains('abschlussbericht', $prodTags);
            $this->assertContains('xtool', $prodTags);
            $this->assertContains('ra3', $prodTags);
            $this->assertContains('GlbOptimizerService', $prodTags);
            $this->assertContains('ProductConfigurator.php', $prodTags);
            $this->assertContains('funkenwerk_city_3d.js', $prodTags);
            $this->assertContains('/admin/products', $prodTags);

            $konzeptEntry = AiKnowledgeBase::with(['category', 'tags'])
                ->where('source_path', 'Abschlussberichte/Konzepte/Konzept_Vertex_Louvre.md')
                ->firstOrFail();
            $this->assertSame('Konzepte', $konzeptEntry->category?->name);
            $this->assertContains('konzept', $konzeptEntry->tags->pluck('name')->all());
            $this->assertContains('vertex', $konzeptEntry->tags->pluck('name')->all());
            $this->assertContains('louvre', $konzeptEntry->tags->pluck('name')->all());

            $auditEntry = AiKnowledgeBase::with(['category', 'tags'])
                ->where('source_path', 'Abschlussberichte/Prüfprotokoll/Audit_Stripe_Checkout.md')
                ->firstOrFail();
            $this->assertSame('Prüfprotokoll', $auditEntry->category?->name);
            $this->assertContains('pruefprotokoll', $auditEntry->tags->pluck('name')->all());
            $this->assertContains('stripe', $auditEntry->tags->pluck('name')->all());

            // 2. Zweiter Sync-Lauf ohne Dateiänderung: 100 % SHA-256-Delta-Skip
            $secondRun = $service->sync($tmpDir, false);
            $this->assertSame(5, $secondRun['scanned']);
            $this->assertSame(0, $secondRun['created']);
            $this->assertSame(0, $secondRun['updated']);
            $this->assertSame(5, $secondRun['skipped_unchanged']);
            $this->assertSame(0, $secondRun['deleted_stale']);

            // 3. Eine Datei ändern und eine Datei löschen -> 1 Update, 1 Stale-Delete, 3 Skips, manueller Eintrag unberührt!
            File::put(
                $tmpDir . '/Workflows/Workflow_Stage_Deploy.md',
                "# Workflow: Mittwald Stage-Deployment v2\n\nErweitert um `AiSyncKnowledgeReportsCommand`."
            );
            File::delete($tmpDir . '/Prüfprotokoll/Audit_Stripe_Checkout.md');

            $thirdRun = $service->sync($tmpDir, false);
            $this->assertSame(4, $thirdRun['scanned']);
            $this->assertSame(0, $thirdRun['created']);
            $this->assertSame(1, $thirdRun['updated']);
            $this->assertSame(3, $thirdRun['skipped_unchanged']);
            $this->assertSame(1, $thirdRun['deleted_stale']);

            $this->assertDatabaseMissing('ai_knowledge_bases', [
                'source_path' => 'Abschlussberichte/Prüfprotokoll/Audit_Stripe_Checkout.md',
            ]);

            // Manueller Eintrag (source_path = null) ist 100 % unversehrt!
            $freshManual = AiKnowledgeBase::find($manualEntry->id);
            $this->assertNotNull($freshManual);
            $this->assertNull($freshManual->source_path);
            $this->assertSame('Manuelle Privatnotiz Alinas Tresor', $freshManual->title);
            $this->assertSame(
                'Diese handgeschriebene Notiz darf niemals vom Report-Sync verändert oder gelöscht werden.',
                $freshManual->content
            );

            // Artisan-Command Test inkl. --force
            $exitCode = Artisan::call('ai:sync-knowledge-reports', [
                '--path' => $tmpDir,
                '--force' => true,
            ]);
            $this->assertSame(0, $exitCode);
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }

    public function test_real_repository_abschlussberichte_sync_imports_all_markdown_files_cleanly(): void
    {
        $service = app(KnowledgeReportSyncService::class);
        $first = $service->sync(base_path('Abschlussberichte'), false);

        $this->assertGreaterThanOrEqual(159, $first['scanned']);
        $this->assertSame($first['scanned'], $first['created']);
        $this->assertSame(0, $first['skipped_unchanged']);

        $second = $service->sync(base_path('Abschlussberichte'), false);
        $this->assertSame($first['scanned'], $second['skipped_unchanged']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['updated']);
    }

    public function test_brain_search_slices_deep_markdown_chapters_without_token_overload_and_returns_source_path(): void
    {
        $admin = Admin::factory()->create();
        Auth::guard('admin')->login($admin);

        $bestellungenCat = AiKnowledgeBaseCategory::create([
            'name' => 'Bestellungen',
            'slug' => 'kb-abt-bestellungen',
        ]);

        // Baue einen > 40 KB großen Fachbericht mit einem relevanten Kapitel tief am Dokumentende
        $longChapters = ["# Bestellungen: Tiefenanalyse Laser-Manufaktur & Produktions-Pipeline\n\nAllgemeine Einführung in den Leitstand."];
        for ($i = 1; $i <= 10; $i++) {
            $padding = str_repeat(
                "Standard-Prozessbeschreibung für Versandkartonage, Etikettendruck und Qualitätskontrolle im Kapitel {$i}. ",
                42
            );
            $longChapters[] = "## {$i}. Allgemeines Logistik-Kapitel {$i}\n{$padding}";
        }

        $longChapters[] = "## 11. RA3 Rotationsaufsatz Fokusmatrix & Thermogravur\n"
            . "Kritischer Referenzwert für Thermobecher: Der exakte Fokusabstand beträgt 14,25 mm bei 28 kHz Pulsfrequenz "
            . "und 1.650 mm/s Vorschub auf dem xTool F2 Ultra UV inklusive SafetyPro IF2 2.0.";

        $longChapters[] = "## 12. Nachgelagerte Archivierung\n" . str_repeat("Archiv-Metadaten für abgeschlossene Chargen. ", 35);

        $fullMarkdown = implode("\n\n", $longChapters);
        $this->assertGreaterThan(40000, mb_strlen($fullMarkdown));

        $report = AiKnowledgeBase::create([
            'title' => 'Bestellungen: Tiefenanalyse Laser-Manufaktur & Produktions-Pipeline',
            'slug' => 'bestellungen-tiefenanalyse-laser-manufaktur',
            'source_path' => 'Abschlussberichte/Bestellungen/Bestellungen_Laser_Tiefenanalyse.md',
            'content_sha256' => hash('sha256', $fullMarkdown),
            'ai_knowledge_base_category_id' => $bestellungenCat->id,
            'content' => $fullMarkdown,
            'is_published' => true,
            'visibility_scope' => AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
        ]);

        // Zweiter irrelevanter Bericht als Rausch-Gegenprobe
        AiKnowledgeBase::create([
            'title' => 'Marketing: Newsletter-Planung Herbst',
            'slug' => 'marketing-newsletter-planung-herbst',
            'source_path' => 'Abschlussberichte/Marketing/Marketing_Newsletter_Herbst.md',
            'content_sha256' => hash('sha256', '# Marketing'),
            'ai_knowledge_base_category_id' => $bestellungenCat->id,
            'content' => '# Marketing: Newsletter-Planung Herbst' . "\n\n" . 'Betreffzeilen für Oktober.',
            'is_published' => true,
            'visibility_scope' => AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
        ]);

        $caller = $this->makeBrainCaller();
        $result = $caller::executeSearchBrain([
            'query' => 'RA3 Rotationsaufsatz Fokusmatrix 14,25 mm',
        ]);

        $this->assertSame('success', $result['status']);
        $this->assertNotEmpty($result['knowledge_base']);

        $topHit = $result['knowledge_base'][0];
        $this->assertSame('Abschlussberichte/Bestellungen/Bestellungen_Laser_Tiefenanalyse.md', $topHit['source_path']);
        $this->assertSame('Bestellungen', $topHit['category']);
        $this->assertTrue($topHit['content_sliced']);
        $this->assertLessThanOrEqual(2400, mb_strlen($topHit['content']));
        $this->assertStringContainsString('11. RA3 Rotationsaufsatz Fokusmatrix & Thermogravur', $topHit['content']);
        $this->assertStringContainsString('14,25 mm bei 28 kHz Pulsfrequenz', $topHit['content']);
        $this->assertContains('11. RA3 Rotationsaufsatz Fokusmatrix & Thermogravur', $topHit['matched_sections']);

        // Synchronisierte Berichte (source_path != null) sind vor versehentlichem brain_delete_entry / brain_update_entry geschützt
        $delAttempt = $caller::executeDeleteBrainEntry([
            'search_query' => 'Tiefenanalyse Laser-Manufaktur',
        ]);
        $this->assertSame('error', $delAttempt['status']);
        $this->assertDatabaseHas('ai_knowledge_bases', ['id' => $report->id]);
    }

    public function test_guest_customer_influencer_tax_advisor_and_internal_employee_are_strictly_blocked_on_all_brain_functions(): void
    {
        $cat = AiKnowledgeBaseCategory::create([
            'name' => 'System',
            'slug' => 'kb-abt-system',
        ]);

        $secretReport = AiKnowledgeBase::create([
            'title' => 'System: Geheimer Infrastruktur- & Sicherheitsbericht',
            'slug' => 'system-geheimer-infrastruktur-bericht',
            'source_path' => 'Abschlussberichte/System/System_Sicherheitsbericht.md',
            'content_sha256' => hash('sha256', 'STRENG_VERTRAULICH_CONTAINER_991'),
            'ai_knowledge_base_category_id' => $cat->id,
            'content' => '# System-Sicherheit' . "\n\n" . 'STRENG_VERTRAULICH_CONTAINER_991',
            'is_published' => true,
            'visibility_scope' => AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
        ]);

        $manualNote = AiKnowledgeBase::create([
            'title' => 'Interne Chef-Notiz',
            'slug' => 'interne-chef-notiz',
            'source_path' => null,
            'ai_knowledge_base_category_id' => $cat->id,
            'content' => 'Nur für Alina und Falcone bestimmt.',
            'is_published' => true,
            'visibility_scope' => AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
        ]);

        $customer = Customer::factory()->create();
        $influencer = Employee::create([
            'first_name' => 'Inf',
            'last_name' => 'Partner',
            'email' => 'influencer-brain-guard@example.test',
            'role' => 'influencer',
            'password' => Hash::make('Secret123!'),
        ]);
        $taxAdvisor = Employee::create([
            'first_name' => 'Steuer',
            'last_name' => 'Kanzlei',
            'email' => 'tax-brain-guard@example.test',
            'role' => 'tax_advisor',
            'password' => Hash::make('Secret123!'),
        ]);
        $internalEmployee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Werkstatt',
            'email' => 'internal-brain-guard@example.test',
            'role' => 'internal',
            'password' => Hash::make('Secret123!'),
        ]);

        $caller = $this->makeBrainCaller();

        $unauthorizedActors = [
            'guest_unauthenticated' => function (): void {
                $this->resetAllGuards();
                $this->assertFalse(AiAuthHelper::check());
                $this->assertFalse(AiAuthHelper::isAdmin());
            },
            'customer' => function () use ($customer): void {
                $this->resetAllGuards();
                Auth::guard('customer')->login($customer);
                $this->assertTrue(AiAuthHelper::isCustomer());
                $this->assertFalse(AiAuthHelper::isAdmin());
            },
            'influencer' => function () use ($influencer): void {
                $this->resetAllGuards();
                Auth::guard('employee')->login($influencer);
                $this->assertTrue(AiAuthHelper::isExternalPartner());
                $this->assertFalse(AiAuthHelper::isAdmin());
            },
            'tax_advisor' => function () use ($taxAdvisor): void {
                $this->resetAllGuards();
                Auth::guard('employee')->login($taxAdvisor);
                $this->assertTrue(AiAuthHelper::isExternalPartner());
                $this->assertFalse(AiAuthHelper::isAdmin());
            },
            'internal_employee' => function () use ($internalEmployee): void {
                $this->resetAllGuards();
                Auth::guard('employee')->login($internalEmployee);
                $this->assertTrue(AiAuthHelper::isEmployee());
                $this->assertFalse(AiAuthHelper::isAdmin());
            },
        ];

        foreach ($unauthorizedActors as $actorLabel => $setupAuth) {
            $setupAuth();

            $searchRes = $caller::executeSearchBrain(['query' => 'STRENG_VERTRAULICH_CONTAINER_991']);
            $this->assertSame('error', $searchRes['status'], "executeSearchBrain must block {$actorLabel}");
            $this->assertArrayNotHasKey('knowledge_base', $searchRes);
            $this->assertStringNotContainsString('STRENG_VERTRAULICH_CONTAINER_991', json_encode($searchRes) ?: '');

            $saveRes = $caller::executeSaveToBrain([
                'title' => 'Unbefugter Eintrag ' . $actorLabel,
                'content' => 'Darf nicht gespeichert werden',
                'tags' => ['hack'],
            ]);
            $this->assertSame('error', $saveRes['status'], "executeSaveToBrain must block {$actorLabel}");

            $updateRes = $caller::executeUpdateBrainEntry([
                'search_query' => 'Interne Chef-Notiz',
                'new_content' => 'Manipuliert durch ' . $actorLabel,
            ]);
            $this->assertSame('error', $updateRes['status'], "executeUpdateBrainEntry must block {$actorLabel}");

            $deleteRes = $caller::executeDeleteBrainEntry([
                'search_query' => 'Interne Chef-Notiz',
            ]);
            $this->assertSame('error', $deleteRes['status'], "executeDeleteBrainEntry must block {$actorLabel}");
        }

        // Datenbank ist nach allen Abwehrtests unverändert
        $this->assertDatabaseMissing('ai_knowledge_bases', ['title' => 'Unbefugter Eintrag guest_unauthenticated']);
        $this->assertDatabaseHas('ai_knowledge_bases', [
            'id' => $secretReport->id,
            'source_path' => 'Abschlussberichte/System/System_Sicherheitsbericht.md',
        ]);
        $this->assertDatabaseHas('ai_knowledge_bases', [
            'id' => $manualNote->id,
            'content' => 'Nur für Alina und Falcone bestimmt.',
        ]);

        // Gegenprobe: Authentifizierter Admin hat vollen Lese- und Schreibzugriff auf manuelle Notizen & Berichte
        $this->resetAllGuards();
        $admin = Admin::factory()->create();
        Auth::guard('admin')->login($admin);

        $adminSearch = $caller::executeSearchBrain(['query' => 'STRENG_VERTRAULICH_CONTAINER_991']);
        $this->assertSame('success', $adminSearch['status']);
        $this->assertSame('Abschlussberichte/System/System_Sicherheitsbericht.md', $adminSearch['knowledge_base'][0]['source_path']);
    }
}

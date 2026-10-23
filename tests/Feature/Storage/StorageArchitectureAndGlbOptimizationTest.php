<?php

namespace Tests\Feature\Storage;

use App\Livewire\Shop\Master\MasterStorageCapacity;
use App\Models\Admin\Admin;
use App\Models\System\SystemCronjob;
use App\Services\AI\Functions\AiHolidayPlannerFuncs;
use App\Services\AI\Functions\AiMapControlFuncs;
use App\Services\AI\Functions\AiPersonaFuncs;
use App\Services\AI\Functions\AiSystemFuncs;
use App\Services\CartFileCleanupService;
use App\Services\Storage\GlbOptimizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StorageArchitectureAndGlbOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_configuration_uses_consolidated_3_zone_paths(): void
    {
        $this->assertSame('temp/livewire-tmp', config('livewire.temporary_file_upload.directory'));
        $this->assertSame(storage_path('app/temp/backup-temp'), config('backup.backup.temporary_directory'));
        $this->assertStringStartsWith('system/', config('backup.backup.name'));
        $this->assertContains(storage_path('app/temp'), config('backup.backup.source.files.exclude'));
        $this->assertContains(storage_path('app/buchhaltung/erictresor/eric-linux'), config('backup.backup.source.files.exclude'));
    }

    public function test_physical_storage_directories_are_consolidated_and_free_of_legacy_bloat(): void
    {
        // Legacy bloat must not exist
        $this->assertDirectoryDoesNotExist(storage_path('app/buchhaltung/erictresor/eric_doku'));
        $this->assertFileDoesNotExist(storage_path('app/buchhaltung/erictresor/ERiC-43.4.6.0-Linux-x86_64.jar'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/system/testdata'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/buchhaltung'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/agenten/workspace'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/dossiers'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/holidays'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/maps'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/places'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/reports'));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/linktree'));

        // Active ERiC C++ Linux runtime must remain intact
        $this->assertDirectoryExists(storage_path('app/buchhaltung/erictresor/eric-linux'));

        // Consolidated Zone 1, Zone 2 & Zone 3 directories must exist
        $this->assertDirectoryExists(storage_path('app/temp/livewire-tmp'));
        $this->assertDirectoryExists(storage_path('app/temp/mail-attachments'));
        $this->assertDirectoryExists(storage_path('app/temp/backup-temp'));
        $this->assertDirectoryExists(storage_path('app/public/agenten/exports/dossiers'));
        $this->assertDirectoryExists(storage_path('app/public/agenten/exports/holidays'));
        $this->assertDirectoryExists(storage_path('app/public/agenten/exports/maps'));
        $this->assertDirectoryExists(storage_path('app/public/agenten/exports/places'));
        $this->assertDirectoryExists(storage_path('app/public/agenten/exports/reports'));
        $this->assertDirectoryExists(storage_path('app/public/marketing/linktree'));
    }

    public function test_all_product_glb_models_exist_and_are_optimized(): void
    {
        $optimizer = app(GlbOptimizerService::class);

        $expectedModels = [
            storage_path('app/public/produkte/products/seelen-kristall/t_seelenk.glb'),
            storage_path('app/public/produkte/products/seelen-anhaenger/seelenanhaenger_3d_.glb'),
            storage_path('app/public/produkte/products/weizenglas-personalisiert/beer_glas_3d.glb'),
        ];

        foreach ($expectedModels as $absPath) {
            $this->assertFileExists($absPath, "Expected 3D model missing: {$absPath}");

            $stats = $optimizer->inspectGlb($absPath);

            $this->assertTrue($stats['exists'], "GlbOptimizerService::inspectGlb failed for {$absPath}");
            $this->assertTrue($stats['is_optimized'], "Model should be flagged as optimized: {$absPath}");
            $this->assertTrue($stats['is_marked_optimized'], "Model should have seelenfunke_optimized metadata: {$absPath}");
            $this->assertLessThan(2.5, $stats['size_mb'], "Optimized GLB should be < 2.5 MB, got {$stats['size_mb']} MB for {$absPath}");
            $this->assertLessThanOrEqual(60000, $stats['triangles'], "Triangles should be <= 60,000, got {$stats['triangles']} for {$absPath}");
            $this->assertGreaterThan(1000, $stats['triangles'], "Model should retain geometry (>1,000 triangles) for {$absPath}");
        }
    }

    public function test_ai_export_functions_write_to_agenten_exports_directory(): void
    {
        $dossierRes = AiPersonaFuncs::executePersonaGeneratePdf([
            'name' => 'Max Mustermann',
            'summary' => 'Kurzprofil für Test',
            'target_action' => 'download',
        ]);
        $this->assertEquals('success', $dossierRes['status']);
        $this->assertStringContainsString('/storage/agenten/exports/dossiers/', $dossierRes['pdf_url']);
        if (!empty($dossierRes['file_name'])) {
            @unlink(storage_path('app/public/agenten/exports/dossiers/' . $dossierRes['file_name']));
        }

        $holidayRes = AiHolidayPlannerFuncs::executeHolidayGeneratePdfPlan([
            'title' => 'Mallorca Sommer',
            'description' => 'Kurzurlaub',
            'start_date' => '01.08.2026',
            'end_date' => '10.08.2026',
            'travel_logistics' => [],
            'itinerary' => [],
            'general_tips' => ['Sonnencreme'],
            'target_action' => 'download',
        ]);
        $this->assertEquals('success', $holidayRes['status']);
        $this->assertStringContainsString('/storage/agenten/exports/holidays/', $holidayRes['pdf_url']);
        if (!empty($holidayRes['file_name'])) {
            @unlink(storage_path('app/public/agenten/exports/holidays/' . $holidayRes['file_name']));
        }

        $mapRes = AiMapControlFuncs::executeMapGeneratePdfSummary([
            'title' => 'Route Nord',
            'description' => 'Streckenübersicht',
            'locations' => ['Hannover', 'Hamburg'],
            'target_action' => 'download',
        ]);
        $this->assertEquals('success', $mapRes['status']);
        $this->assertStringContainsString('/storage/agenten/exports/maps/', $mapRes['pdf_url']);
        if (!empty($mapRes['file_name'])) {
            @unlink(storage_path('app/public/agenten/exports/maps/' . $mapRes['file_name']));
        }

        $placeRes = AiMapControlFuncs::executeMapGeneratePlacesPdf([
            'title' => 'Standorte',
            'places' => [['name' => 'Seelenfunke Manufaktur', 'address' => 'Musterstr. 1']],
            'target_action' => 'download',
        ]);
        $this->assertEquals('success', $placeRes['status']);
        $this->assertStringContainsString('/storage/agenten/exports/places/', $placeRes['pdf_url']);
        if (!empty($placeRes['file_name'])) {
            @unlink(storage_path('app/public/agenten/exports/places/' . $placeRes['file_name']));
        }

        $reportRes = AiSystemFuncs::executeGeneratePdfReport([
            'title' => 'Quartalsbericht Q3',
            'content_markdown' => '# Umsatzentwicklung positiv',
            'design' => 'generic',
            'target_action' => 'download',
        ]);
        $this->assertEquals('success', $reportRes['status']);
        $reportUrl = $reportRes['_event']['detail']['url'] ?? '';
        $reportFilename = $reportRes['_event']['detail']['filename'] ?? '';
        $this->assertStringContainsString('/storage/agenten/exports/reports/', $reportUrl);
        if (!empty($reportFilename)) {
            @unlink(storage_path('app/public/agenten/exports/reports/' . $reportFilename));
        }
    }

    public function test_cleanup_expired_temp_and_exports_deletes_old_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Storage::disk('local')->put('temp/livewire-tmp/temp_upload.tmp', 'temp-data');
        Storage::disk('local')->put('temp/mail-attachments/mail.pdf', 'mail-data');
        Storage::disk('public')->put('agenten/exports/dossiers/dossier_test.html', '<html></html>');
        Storage::disk('public')->put('agenten/ai-chat-uploads/chat_img.png', 'img-data');

        $service = app(CartFileCleanupService::class);

        // With threshold = -1 (cutoff in the future), all 4 files must be cleaned
        $stats = $service->cleanupExpiredTempAndExports(
            dryRun: false,
            tempHours: -1,
            exportDays: -1,
            chatUploadDays: -1
        );

        $this->assertEquals(4, $stats['scanned']);
        $this->assertEquals(4, $stats['deleted']);
        $this->assertFalse(Storage::disk('local')->exists('temp/livewire-tmp/temp_upload.tmp'));
        $this->assertFalse(Storage::disk('local')->exists('temp/mail-attachments/mail.pdf'));
        $this->assertFalse(Storage::disk('public')->exists('agenten/exports/dossiers/dossier_test.html'));
        $this->assertFalse(Storage::disk('public')->exists('agenten/ai-chat-uploads/chat_img.png'));
    }

    public function test_master_storage_capacity_component_loads_zones_and_cronjobs(): void
    {
        $admin = Admin::forceCreate([
            'id' => (string) Str::uuid(),
            'first_name' => 'Master',
            'last_name' => 'Admin',
            'email' => 'admin-storage-test@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        SystemCronjob::create([
            'name' => 'Verwaiste Uploads & Warenkörbe bereinigen',
            'description' => 'Bereinigt nächtlich unbestellte Uploads und Temp-Dateien',
            'command' => 'shop:cleanup-orphaned-uploads',
            'schedule' => '0 4 * * *',
            'is_active' => true,
            'status' => 'success',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(MasterStorageCapacity::class)
            ->call('loadStorageDetails')
            ->assertSet('level', 0)
            ->assertViewHas('storageZones', function ($zones) {
                return count($zones) === 3
                    && $zones[0]['key'] === 'private'
                    && $zones[1]['key'] === 'public'
                    && $zones[2]['key'] === 'temp_system';
            })
            ->call('clearTempDirectory')
            ->call('runOrphanedCleanup')
            ->assertStatus(200);
    }
}

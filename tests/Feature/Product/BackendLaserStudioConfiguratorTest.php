<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\LaserStudioWorkspace;
use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Product\LaserStudioProject;
use App\Services\Export\FileDownloadService;
use App\Services\Laser\LaserQrCodeService;
use App\Services\Laser\XtoolLaserCockpitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BackendLaserStudioConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_configurator_page_renders_laser_studio_top_and_collapses_libraries(): void
    {
        $admin = Admin::factory()->create();

        $res = $this->actingAs($admin, 'admin')->get('/admin/product-configurator');

        $res->assertOk();
        $res->assertSee('Laser-Studio · Universeller Projektkonfigurator');
        $res->assertSee('Anleitungsvideo');
        $res->assertSee('Konfigurator-Hintergrundvideos');
        $res->assertSee('Symbol-Bibliothek Verwaltung');
        $res->assertSee('Spruch- &amp; Widmungs-Bibliothek', false);
        $res->assertSee('x-data="{ open: false }"', false);
    }

    public function test_laser_studio_full_workflow_qr_card_custom_presets_and_2_step_laser_pipeline(): void
    {
        Storage::fake('public');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $orderCountBefore = OrderOrder::count();

        $component = Livewire::test(LaserStudioWorkspace::class)
            // 1. Neues beidseitiges Geschenk-/QR-Karten-Projekt (86x54 mm) anlegen
            ->call('createProject', 'card_86x54')
            ->assertNotSet('activeProjectId', null)
            ->set('projectTitle', 'Instagram & TikTok Werbekarte Gold')
            ->set('surfaceTone', 'gold_card')
            // 2. Vorderseite: Text + Instagram Vektor-QR-Code hinzufügen
            ->call('addTextLayer', 'Handveredelt in Gifhorn')
            ->call('addQrCodeToCanvas', 'instagram')
            // 3. Eigenes Material-Preset speichern & anwenden
            ->set('laserParams.power_percent', 78)
            ->set('laserParams.speed_mms', 275)
            ->set('laserParams.focus_mm', 0.6)
            ->set('newCustomPresetName', 'Goldkarte Spezial 0,6mm')
            ->call('saveCurrentAsStudioPreset')
            // 4. Auf Rückseite wechseln & Linktree QR-Code platzieren
            ->call('switchSide', 'back')
            ->assertSet('activeSide', 'back')
            ->call('addQrCodeToCanvas', 'linktree')
            ->call('switchSide', 'front')
            // 5. Sicherheitssperre prüfen: Ungespeichertes Projekt darf NICHT an den Laser gesendet werden
            ->call('refreshLaserCameraAndAlign')
            ->assertSet('workflowStep', 2)
            ->assertSet('isProjectSaved', false)
            ->call('sendProjectDirectlyToLaser')
            ->assertSet('workflowStep', 2)
            // Erst nach explizitem Speichern ist "An Laser senden" freigeschaltet
            ->call('saveMetadataOnly', false)
            ->assertSet('isProjectSaved', true)
            ->call('triggerFraming')
            ->call('sendProjectDirectlyToLaser')
            ->assertSet('workflowStep', 3);

        $projectId = $component->get('activeProjectId');
        $project = LaserStudioProject::findOrFail($projectId);

        $this->assertSame('Instagram & TikTok Werbekarte Gold', $project->title);
        $this->assertEquals(86.0, $project->width_mm);
        $this->assertEquals(54.0, $project->height_mm);
        $this->assertTrue($project->has_back_side);
        $this->assertNotNull($project->last_lasered_at);

        // Keine Schein-Bestellungen in der Buchhaltung erzeugt!
        $this->assertSame($orderCountBefore, OrderOrder::count());

        // Echte .xcs & .xf Erzeugung über die zentrale Produktions-Pipeline prüfen
        $designPayload = app(FileDownloadService::class)->buildLaserDesignPayload($project, 'front', true);
        $this->assertEquals(86.0, $designPayload['widthMm']);
        $this->assertEquals(54.0, $designPayload['heightMm']);
        $this->assertNotEmpty($designPayload['stageEngravePngBytes']);

        $xcs = app(XtoolLaserCockpitService::class)->generateXcsProjectBytes($project, 'front');
        $this->assertStringContainsString('STUDIO-', $xcs['filename']);
        $this->assertNotEmpty($xcs['bytes']);

        // Vektor-QR-Code ist randscharfes SVG ohne weißen Vollflächen-Kasten
        $qrCheck = LaserQrCodeService::generateLaserReadyQrSvg('https://mein-seelenfunke.de/links');
        $this->assertStringContainsString('<svg', $qrCheck['svg']);
        $this->assertStringNotContainsString('fill="#ffffff"', strtolower($qrCheck['svg']));

        // Projekt duplizieren, archivieren und löschen testen
        $component->call('closeProject')
            ->call('duplicateProject', $projectId);
        $this->assertSame(2, LaserStudioProject::count());

        $copy = LaserStudioProject::where('id', '!=', $projectId)->firstOrFail();
        $component->call('toggleArchiveProject', (string) $copy->id);
        $this->assertSame('archived', $copy->fresh()->status);

        $component->call('deleteProject', (string) $copy->id);
        $this->assertSame(1, LaserStudioProject::count());
    }

    public function test_real_product_configurator_runs_in_laser_freehand_mode_and_computes_tight_element_framing(): void
    {
        Storage::fake('public');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // Simuliertes Live-Kamerabild des xTool F2 Ultra UV (z. B. Schere auf dem 200x200 mm Laserbett)
        $camImg = imagecreatetruecolor(400, 400);
        $dark = imagecolorallocate($camImg, 25, 28, 32);
        imagefill($camImg, 0, 0, $dark);
        ob_start();
        imagejpeg($camImg, null, 85);
        $jpgBytes = (string) ob_get_clean();
        imagedestroy($camImg);
        Storage::disk('public')->put('bestellungen/xtool/background.jpg', $jpgBytes);

        // 1. Freies 200x200 mm Kamera-Projekt im Workspace starten
        $workspace = Livewire::test(LaserStudioWorkspace::class)
            ->call('createProject', 'free_bed')
            ->assertSee('Free-Hand Laser-Ansicht')
            ->assertSee('Smart-Rahmen: Element-Bereich');

        $projectId = (string) $workspace->get('activeProjectId');
        $project = LaserStudioProject::findOrFail($projectId);
        $this->assertSame('free_bed', $project->mode);
        $this->assertEquals(200.0, $project->width_mm);
        $this->assertEquals(200.0, $project->height_mm);

        // 2. Den echten Seelenfunke-ProductConfigurator im Modus context='laser_freehand' testen (inkl. Livewire Roundtrips!)
        $configurator = Livewire::test(ProductConfigurator::class, [
            'product' => $project,
            'context' => 'laser_freehand',
            'initialData' => array_merge($project->configuration ?? [], ['laser_studio_project_id' => $project->id]),
        ])
            ->assertSet('context', 'laser_freehand')
            ->assertSet('laserStudioProjectId', $project->id)
            ->assertSet('config_confirmed', true)
            ->assertDontSee('Direkt zur Kasse')
            ->assertDontSee('30-Tage-Bestpreis')
            ->assertSee('Projekt speichern');

        // 3. Text gezielt rechts-oben (z. B. x=65% => ~130 mm, y=30% => ~60 mm auf die Scherenklinge) setzen + QR-Code testen
        $configurator->set('texts', [[
            'id' => 'txt_scissors_1',
            'text' => 'Alina',
            'font' => 'Arial',
            'align' => 'center',
            'x' => 65.0,
            'y' => 30.0,
            'size' => 0.45,
            'rotation' => 0,
        ]])
            ->call('saveWithSnapshot', null);

        $project->refresh();
        $this->assertSame('Alina', $project->configuration['texts'][0]['text'] ?? null);
        $this->assertSame(0, OrderOrder::count());

        // 4. Smart-Bounding-Box-Prüfung: Statt 200x200 mm umschließt der Rahmen eng den Schriftzug bei ~X=130 mm, Y=60 mm!
        $wsInstance = $workspace->instance();
        $tightBounds = $wsInstance->computeRenderedContentBoundingBoxMm($project, 'front');
        $this->assertNotNull($tightBounds);
        $this->assertGreaterThan(110.0, $tightBounds['center_x_mm']);
        $this->assertLessThan(150.0, $tightBounds['center_x_mm']);
        $this->assertGreaterThan(45.0, $tightBounds['center_y_mm']);
        $this->assertLessThan(75.0, $tightBounds['center_y_mm']);
        $this->assertLessThan(60.0, $tightBounds['width_mm']);
        $this->assertLessThan(40.0, $tightBounds['height_mm']);

        // 5. Vektor-QR-Code direkt im Free-Hand-ProductConfigurator erzeugen & prüfen
        $configurator->call('addQrCodeInFreehand', 'instagram');
        $project->refresh();
        $this->assertNotEmpty($project->configuration['logos'] ?? []);
        $this->assertTrue((bool) ($project->configuration['logos'][0]['is_qr'] ?? false));
    }

    public function test_laser_freehand_save_turns_button_green_without_top_banner_and_supports_photo_studio_grayscale_editing(): void
    {
        Storage::fake('public');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $workspace = Livewire::test(LaserStudioWorkspace::class)
            ->call('createProject', 'free_bed')
            ->assertSee('Dateiupload-Verwaltung');

        $projectId = (string) $workspace->get('activeProjectId');
        $project = LaserStudioProject::findOrFail($projectId);

        // 1. Echtes farbiges Testfoto erzeugen & im Free-Hand-ProductConfigurator hochladen
        $photo = imagecreatetruecolor(120, 120);
        for ($y = 0; $y < 120; $y++) {
            for ($x = 0; $x < 120; $x++) {
                $col = imagecolorallocate($photo, (int) (($x / 120) * 240), (int) (($y / 120) * 200), 90);
                imagesetpixel($photo, $x, $y, $col);
            }
        }
        ob_start();
        imagejpeg($photo, null, 90);
        $jpegBytes = (string) ob_get_clean();
        imagedestroy($photo);

        $uploadedFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('alinas_portrait.jpg', $jpegBytes);

        $configurator = Livewire::test(ProductConfigurator::class, [
            'product' => $project,
            'context' => 'laser_freehand',
            'initialData' => array_merge($project->configuration ?? [], ['laser_studio_project_id' => $project->id]),
        ])
            ->assertSee('Erfolgreich gespeichert!')
            ->set('new_files', [$uploadedFile])
            ->call('saveWithSnapshot', null)
            ->assertSet('saved', true);

        // 2. Prüfen: Workspace übernimmt Speicher-Event OHNE grüne Top-Banner-Meldung ($statusNotice === null)
        $project->refresh();
        $workspace->dispatch('save-laser-freehand-data', projectId: $project->id, configData: $project->configuration, activeSide: 'front')
            ->assertSet('statusNotice', null);

        // 3. Dateiupload-Verwaltung unter "Laserparameter & Medium" listet das Foto inkl. Original & Laser-Foto-Studio-Button
        $files = $workspace->instance()->projectUploadedFiles;
        $this->assertNotEmpty($files);
        $rasterFile = collect($files)->firstWhere('is_raster', true);
        $this->assertNotNull($rasterFile);
        $this->assertTrue($rasterFile['has_untouched_original']);
        $this->assertFalse($rasterFile['is_laser_optimized']);

        // 4. 60-FPS Laser-Foto-Studio öffnen, Graustufen + Kontrast + Jarvis aktivieren & übernehmen
        $fileKey = (string) $rasterFile['raw_key'];
        $workspace->call('openLaserPhotoStudio', $fileKey)
            ->assertSet('isLaserPhotoStudioOpen', true)
            ->assertSet('photoStudioFileKey', $fileKey)
            ->call('autoOptimizeLaserPhoto')
            ->call('saveLaserPhotoStudio', [
                'enabled' => true,
                'grayscale' => true,
                'invert' => false,
                'brightness' => 5,
                'contrast' => 28,
                'gamma' => 1.15,
                'highlight_cap' => 76,
                'sharpness' => 90,
                'dither_mode' => 'grayscale',
                'threshold' => 128,
            ])
            ->assertSet('isLaserPhotoStudioOpen', false)
            ->assertDispatched('laser-freehand-photo-edited');

        $project->refresh();
        $adj = $project->configuration['laser_photo_adjustments'][$fileKey] ?? null;
        $this->assertIsArray($adj);
        $this->assertTrue((bool) ($adj['enabled'] ?? false));
        $this->assertTrue((bool) ($adj['grayscale'] ?? false));
        $this->assertSame('grayscale', $adj['dither_mode'] ?? null);
        $this->assertNotEmpty($adj['edited_path'] ?? '');
        Storage::disk('public')->assertExists($adj['edited_path']);

        // 5. Produktions-Pipeline nutzt das nachbearbeitete Graustufen-Bild automatisch für den Laser-Export
        $designPayload = app(FileDownloadService::class)->buildLaserDesignPayload($project, 'front', true);
        $this->assertNotEmpty($designPayload['stageEngravePngBytes']);

        // 6. Exakte 200x200 mm 1:1 Kamera-Kalibrierung im free_bed Modus prüfen (kein 195-mm-Clamp, kein Versatz!)
        $xf = app(XtoolLaserCockpitService::class)->generateXfPackageBytes($project, 'front', 100.0, 100.0, 0.0, 200.0, 200.0, 'rect');
        $this->assertEquals(100.0, $xf['center_x_mm']);
        $this->assertEquals(100.0, $xf['center_y_mm']);
        $this->assertEquals(200.0, $xf['width_mm']);
        $this->assertEquals(200.0, $xf['height_mm']);
    }

    public function test_custom_card_qr_code_engraves_at_exact_bed_coordinates_without_transform_shrink_or_double_offset(): void
    {
        Storage::fake('public');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // Exakt Alinas Fall: 86x54 mm Projekt ("Geschenk- & QR-Karte"), ausgerichtet bei X=147.5 mm, Y=93.5 mm
        $workspace = Livewire::test(LaserStudioWorkspace::class)
            ->call('createProject', 'card_86x54');

        $projectId = (string) $workspace->get('activeProjectId');
        $project = LaserStudioProject::findOrFail($projectId);

        $workspace->set('centerXMm', 147.5)
            ->set('centerYMm', 93.5)
            ->call('saveMetadataOnly', true);

        $project->refresh();
        $vCfg = $project->toVirtualConfiguratorProduct('front')->configurator_settings;
        // grüner Rahmen bei X=147.5 (73.75%), Breite 86 mm (43%) => left = 52.25% .. 95.25%
        // grüner Rahmen bei Y=93.5 (46.75%), Höhe 54 mm (27%)   => top  = 33.25% .. 60.25%
        $this->assertEqualsWithDelta(52.25, (float) $vCfg['area_left'], 0.05);
        $this->assertEqualsWithDelta(33.25, (float) $vCfg['area_top'], 0.05);

        $qr = \App\Services\Laser\LaserQrCodeService::generateLaserReadyQrSvg('https://mein-seelenfunke.de/links');
        $this->assertStringNotContainsString('#fefefe', $qr['svg']);
        $this->assertStringNotContainsString('transform=', $qr['svg']);

        // QR-Code links im grünen Rahmen auf der Scherenklinge platzieren: x = 58.0% (= 116.0 mm!), y = 50.0% (= 100.0 mm!), size = 40 (= 16 mm breit!)
        $conf = $project->configuration;
        $conf['logos'] = [[
            'id' => 'qr_blade_test',
            'type' => 'saved',
            'value' => $qr['storage_path'],
            'url' => '/storage/' . $qr['storage_path'],
            'x' => 58.0,
            'y' => 50.0,
            'size' => 40.0,
            'rotation' => 0,
            'is_qr' => true,
        ]];
        $conf['has_unsaved_freehand_changes'] = false;
        $project->configuration = $conf;
        $project->syncStudioDimensionsToConfig();
        $project->save();

        $xf = app(XtoolLaserCockpitService::class)->generateXfPackageBytes(
            $project,
            'front',
            147.5,
            93.5,
            0.0,
            86.0,
            54.0,
            'contour'
        );

        // GCode-Koordinaten auslesen: Zentrum MUSS bei ~X=116 mm, ~Y=100 mm liegen und ~15 mm breit sein (NICHT bei X=153 mm / 5 mm klein!)
        preg_match_all('/G1X([0-9.]+)(?:Y([0-9.]+))?S[1-9]\d*/', $xf['motion_gcode'], $m);
        $this->assertNotEmpty($m[1], 'QR-Code muss echte Gravurzeilen in motion.gcode erzeugen');

        $xs = array_map('floatval', $m[1]);
        $minBurnX = min($xs);
        $maxBurnX = max($xs);
        $burnSpanX = $maxBurnX - $minBurnX;
        $burnCenterX = ($minBurnX + $maxBurnX) * 0.5;

        $this->assertGreaterThan(12.0, $burnSpanX, 'QR-Code darf nicht durch fehlendes SVG-Transform um Faktor 13x geschrumpft sein');
        $this->assertLessThan(20.0, $burnSpanX);
        $this->assertEqualsWithDelta(116.0, $burnCenterX, 1.5, 'QR-Code muss exakt bei X=116 mm (Scherenklinge) statt X=153 mm (Griff) lasern!');

        preg_match_all('/G0X[0-9.]+Y([0-9.]+)/', $xf['motion_gcode'], $my);
        $ys = array_map('floatval', $my[1]);
        $burnCenterY = (min($ys) + max($ys)) * 0.5;
        $this->assertEqualsWithDelta(100.0, $burnCenterY, 1.5, 'QR-Code muss exakt bei Y=100 mm lasern!');
    }

    public function test_freehand_qr_preset_creates_pure_qr_without_unwanted_subtitle_and_prunes_deleted_files(): void
    {
        Storage::fake('public');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $workspace = Livewire::test(LaserStudioWorkspace::class)
            ->call('createProject', 'card_86x54');

        $projectId = (string) $workspace->get('activeProjectId');
        $project = LaserStudioProject::findOrFail($projectId);

        // Simuliere eine verwaiste Alt-QR-Datei in files[], die nicht mehr in logos[] liegt
        $conf = $project->configuration;
        $conf['files'] = ['laser-studio/qr/orphan_old.svg'];
        $conf['logos'] = [];
        $project->configuration = $conf;
        $project->save();

        $configurator = Livewire::test(ProductConfigurator::class, [
            'product' => $project,
            'context' => 'laser_freehand',
            'initialData' => array_merge($project->configuration ?? [], ['laser_studio_project_id' => $project->id]),
        ]);

        // Beim Mount müssen verwaiste Dateien direkt aus uploaded_files bereinigt sein
        $this->assertSame([], $configurator->get('uploaded_files'));

        // 1-Klick-Vorlage "linktree" darf KEINEN ungefragten Textlayer ("Alle Kanäle auf einen Blick") miterzeugen
        $configurator->call('addQrCodeInFreehand', 'linktree');
        $this->assertCount(1, $configurator->get('logos'));
        $this->assertCount(0, $configurator->get('texts'));
        $this->assertCount(1, $configurator->get('uploaded_files'));

        $qrPath = (string) $configurator->get('uploaded_files')[0];

        // Dateiupload-Verwaltung zeigt exakt 1 aktive Datei als QR-Code an
        $workspace->call('handleFreehandFilesUpdated', $project->id);
        $filesList = $workspace->instance()->projectUploadedFiles;
        $this->assertCount(1, $filesList);
        $this->assertStringContainsString('QR-Code', (string) $filesList[0]['label']);

        // Löschen über die Dateiupload-Verwaltung entfernt Datei UND Canvas-Logo synchron
        $workspace->call('removeStudioUploadedFile', $qrPath);
        $this->assertSame([], $workspace->instance()->projectUploadedFiles);

        $project->refresh();
        $this->assertSame([], $project->configuration['logos'] ?? []);
        $this->assertSame([], $project->configuration['files'] ?? []);
    }
}

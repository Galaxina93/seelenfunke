<?php

namespace Tests\Feature\Livewire\Shop\Order;

use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\Laser\XtoolLaserCockpitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XtoolLaserCockpitTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): Admin
    {
        return Admin::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name'  => 'Steinhauer',
            'email'      => 'alina-' . Str::random(5) . '@mein-seelenfunke.de',
            'password'   => bcrypt('secret123'),
        ]);
    }

    protected function createPersonalizableLaserProduct(): Product
    {
        return Product::create([
            'name'        => 'Goldenes Seelen-Herz',
            'slug'        => 'goldenes-seelen-herz-' . Str::lower(Str::random(5)),
            'type'        => 'physical',
            'status'      => 'active',
            'price'       => 3490,
            'width'       => 40,
            'height'      => 40,
            'length'      => 2,
            'weight'      => 28,
            'configurator_settings' => [
                'allow_personalization' => true,
                'personalization_mode'  => 'studio',
                'overlay_type'          => 'plane',
                'area_shape'            => 'rect',
                'area_left'             => 15,
                'area_top'              => 15,
                'area_width'            => 70,
                'area_height'           => 70,
                'xtool_plane_width'     => 40,
                'xtool_plane_height'    => 40,
                'xtool_equipment_key'   => 'flatbed_standard',
                'xtool_process_mode'    => 'Prozess auf flacher Oberfläche',
                'xtool_material'        => 'Edelstahl 14K vergoldet',
                'xtool_focus_mm'        => 3.8,
                'xtool_dot_duration_us' => 140,
                'xtool_power_percent'   => 85,
                'xtool_dpi'             => 650,
                'xtool_passes'          => 2,
                'xtool_frequency_khz'   => 60,
                'xtool_bitmap_mode'     => 'Jarvis (Raster)',
                'xtool_engrave_mode'    => 'Bi-direktional',
            ],
        ]);
    }

    protected function createSampleOrderWithItem(Product $product): array
    {
        $order = OrderOrder::create([
            'order_number'     => 'SF-2026-9901',
            'email'            => 'kunde@example.com',
            'status'           => 'processing',
            'payment_status'   => 'paid',
            'payment_method'   => 'stripe',
            'billing_address'  => [
                'first_name'  => 'Maria',
                'last_name'   => 'Muster',
                'address'     => 'Carl-Goerdeler-Ring 26',
                'postal_code' => '38518',
                'city'        => 'Gifhorn',
                'country'     => 'DE',
            ],
            'shipping_address' => [
                'first_name'  => 'Maria',
                'last_name'   => 'Muster',
                'address'     => 'Carl-Goerdeler-Ring 26',
                'postal_code' => '38518',
                'city'        => 'Gifhorn',
                'country'     => 'DE',
            ],
            'subtotal_price'   => 3490,
            'tax_amount'       => 557,
            'shipping_price'   => 0,
            'total_price'      => 3490,
        ]);

        $item = OrderOrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'quantity'      => 1,
            'unit_price'    => 3490,
            'tax_rate'      => 19.0,
            'total_price'   => 3490,
            'configuration' => [
                'texts' => [
                    [
                        'text'     => 'Für immer im Herzen',
                        'font'     => 'Montserrat',
                        'align'    => 'center',
                        'x'        => 50,
                        'y'        => 58,
                        'size'     => 0.9,
                        'rotation' => 0,
                    ],
                ],
                'logos' => [
                    [
                        'type'     => 'vector',
                        'value'    => 'vectors/Liebe/herz.svg',
                        'url'      => '/storage/produkte/configurator/vectors/Liebe/herz.svg',
                        'x'        => 50,
                        'y'        => 38,
                        'size'     => 40,
                        'rotation' => 0,
                    ],
                ],
            ],
        ]);

        return [$order, $item];
    }

    #[Test]
    public function it_detects_blank_position_on_200x200mm_camera_bed_without_cloud_api(): void
    {
        $product = $this->createPersonalizableLaserProduct();
        $cockpit = app(XtoolLaserCockpitService::class);

        $detected = $cockpit->detectBlankPositionOnBed($product, [], 'front');

        $this->assertIsArray($detected);
        $this->assertEquals(40.0, $detected['blank_width_mm']);
        $this->assertEquals(40.0, $detected['blank_height_mm']);
        $this->assertGreaterThanOrEqual(15.0, $detected['center_x_mm']);
        $this->assertLessThanOrEqual(185.0, $detected['center_x_mm']);
        $this->assertGreaterThanOrEqual(15.0, $detected['center_y_mm']);
        $this->assertLessThanOrEqual(185.0, $detected['center_y_mm']);
        $this->assertGreaterThanOrEqual(55, $detected['confidence']);
        $this->assertNotEmpty($detected['camera_url']);
    }

    #[Test]
    public function it_generates_valid_xcs_workspace_v2_project_with_laser_params_and_ignored_ghost_contour(): void
    {
        $product = $this->createPersonalizableLaserProduct();
        [, $item] = $this->createSampleOrderWithItem($product);

        $cockpit = app(XtoolLaserCockpitService::class);
        $xcs = $cockpit->generateXcsProjectBytes($item, 'front', 96.5, 98.5, 2.5);

        $this->assertStringEndsWith('.xcs', $xcs['filename']);
        $this->assertGreaterThan(500, strlen($xcs['bytes']));

        $tmpZip = tempnam(sys_get_temp_dir(), 'test_xcs_');
        file_put_contents($tmpZip, $xcs['bytes']);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmpZip) === true);

        $this->assertEquals('v2', $zip->getFromName('.format'));

        $meta = json_decode((string) $zip->getFromName('meta/persistence-meta.json'), true);
        $this->assertEquals('xcs-workspace-v2', $meta['protocol']);

        $project = json_decode((string) $zip->getFromName('project.json'), true);
        $this->assertTrue($project['__v2__']);
        $this->assertEquals('GS009-CLASS-4', $project['extId']);
        $this->assertEquals('F2 Ultra UV', $project['extName']);

        $canvasId = $project['activeCanvasId'];
        $canvasMeta = json_decode((string) $zip->getFromName("canvases/{$canvasId}.json"), true);
        $this->assertEquals('BATCH_TEMPLATE', $canvasMeta['batchData']['entryType']);

        $displays = json_decode((string) $zip->getFromName("canvases/{$canvasId}/displays-0.json"), true)['displays'];
        $this->assertCount(2, $displays);

        $bitmapDisplay = collect($displays)->firstWhere('type', 'BITMAP');
        $ghostDisplay = collect($displays)->firstWhere('type', 'PATH');

        $this->assertNotNull($bitmapDisplay);
        $this->assertNotNull($ghostDisplay);
        // Gravurfläche workingArea = 70 % von 40 mm = 28,0 x 28,0 mm -> 96.5 - (28 / 2) = 82.5 mm, 98.5 - (28 / 2) = 84.5 mm
        $this->assertEquals(82.5, $bitmapDisplay['x']);
        $this->assertEquals(84.5, $bitmapDisplay['y']);
        $this->assertEquals(2.5, $bitmapDisplay['angle']);
        $this->assertEquals('#00bcd4', $ghostDisplay['lineColor']);

        $device = json_decode((string) $zip->getFromName('devices/device-GS009-CLASS-4-1.json'), true);
        $modeBlock = $device['processing'][$canvasId]['modes']['LASER_PLANE'];

        // Geister-Kontur muss zwingend in ignoredDisplayIds stehen (Nicht lasern!)
        $this->assertContains($ghostDisplay['id'], $modeBlock['ignoredDisplayIds']);
        $this->assertEquals(3.8, $modeBlock['data']['thickness']);
        $this->assertEquals(1098, $modeBlock['data']['material']);

        $profileValues = reset($modeBlock['profiles'])['base']['values'];
        $this->assertEquals(140, $profileValues['dotDuration']);
        $this->assertEquals(85, $profileValues['power']);
        $this->assertEquals(650, $profileValues['dpi']);
        $this->assertEquals(2, $profileValues['repeat']);
        $this->assertEquals(60, $profileValues['frequency']);
        $this->assertEquals('jarvis', $profileValues['bitmapMode']);
        $this->assertEquals('zMode', $profileValues['bitmapScanMode']);

        $zip->close();
        @unlink($tmpZip);
    }

    #[Test]
    public function it_generates_valid_xf_tar_package_and_rotated_border_gcode(): void
    {
        $product = $this->createPersonalizableLaserProduct();
        [, $item] = $this->createSampleOrderWithItem($product);

        $cockpit = app(XtoolLaserCockpitService::class);
        $xf = $cockpit->generateXfPackageBytes($item, 'front', 100.0, 100.0, 0.0);

        $this->assertStringEndsWith('.xf', $xf['filename']);
        $this->assertStringContainsString('# GS009-4 HEAD', $xf['border_gcode']);
        $this->assertStringContainsString('G0X86.000Y86.000F1440000', $xf['border_gcode']);
        $this->assertStringContainsString('G1X114.000Y86.000S133F1440000', $xf['border_gcode']);
        $this->assertStringContainsString('# GS009-4 BITMAP HEAD', $xf['motion_gcode']);
        $this->assertStringContainsString('M9064 B3', $xf['motion_gcode']);
        $this->assertStringContainsString('G22', $xf['motion_gcode']);
        $this->assertStringContainsString('M523P40', $xf['motion_gcode']);
        $this->assertStringContainsString('M6 P1', $xf['motion_gcode']);
        $this->assertStringContainsString('ustar', $xf['bytes']);
        $this->assertStringContainsString('preview.jpg', $xf['bytes']);
        $this->assertStringContainsString('border.gcode', $xf['bytes']);
        $this->assertStringContainsString('motion.gcode', $xf['bytes']);
        $this->assertStringContainsString('description.json', $xf['bytes']);
        $this->assertEquals('MXFK007003260530H0194A6', $xf['description']['deviceInfo']['snCode']);
    }

    #[Test]
    public function livewire_order_overview_renders_laser_cockpit_and_executes_framing_and_direct_send(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $product = $this->createPersonalizableLaserProduct();
        [$order, $item] = $this->createSampleOrderWithItem($product);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('selectedOrderId', $order->id)
            ->assertSet('selectedOrderItemId', $item->id)
            ->assertSet('laserWorkflowStep', 1)
            ->assertSet('showLaserDetails', false)
            ->assertSee('In Bearbeitung (0/1)')
            ->assertSee('Dateien')
            ->assertSee('Kosten')
            ->assertSee('Kunde')
            ->assertSee('Adresse')
            ->assertSee('xTool F2 Ultra UV · Live-Laser-Cockpit')
            ->assertSee('Live-Konfigurations-Vorschau')
            ->assertSee('Details anzeigen')
            ->assertSee('Kamera & KI-Ausrichten')
            ->assertSee('An Laser senden')
            ->assertSee('Richte aus...')
            ->assertSee('Sende an Laser...')
            ->assertSee('Kamera & KI-Ausrichtung...')
            ->assertSee('Übertrage an xTool...')
            ->assertSee('@keyframes sfLaserBtnSpin', false)
            ->assertSee('sf-laser-btn-spin', false)
            ->assertSee('wire:target="refreshLaserCameraAndAlign,sendJobDirectlyToLaser"', false)
            ->assertSee('wire:loading.remove wire:target="refreshLaserCameraAndAlign"', false)
            ->assertSee('wire:loading wire:target="refreshLaserCameraAndAlign"', false)
            ->assertSee('wire:loading.remove wire:target="sendJobDirectlyToLaser"', false)
            ->assertSee('wire:loading wire:target="sendJobDirectlyToLaser"', false)
            ->assertSee('wire:loading wire:target="triggerLaserFraming"', false)
            ->assertDontSee(" \u{2013} ")
            ->assertSee('Weiter zur Verpackung')
            ->assertDontSee('Status / Optionen')
            ->assertDontSee('Schritt 1 · In Bearbeitung (0/1)')
            ->assertDontSee('Schritt 2 · Versand')
            ->assertDontSee('Schritt 3 · Abgeschlossen')
            ->assertDontSee('Artikel 1 von 1')
            ->assertDontSee('Optional einblendbar')
            ->assertDontSee('xTool Anleitung')
            ->assertSee('x-show="showLaserDetails"', false);

        $component->call('toggleLaserDetails')
            ->assertSet('showLaserDetails', true)
            ->assertDispatched('order-ui-sync');

        $component->call('refreshLaserCameraAndAlign')
            ->assertSet('laserWorkflowStep', 2)
            ->assertSet('laserCameraRefreshToken', 1)
            ->assertDispatched('laser-cockpit-sync')
            ->assertDispatched('order-feedback');

        $cockpitDataAfterRefresh = $component->instance()->laserCockpitData;
        $this->assertSame(1, $cockpitDataAfterRefresh['refresh_token']);
        $this->assertStringContainsString('&r=1', $cockpitDataAfterRefresh['camera_url']);

        $component->call('updateLaserCoordinates', 92.5, 104.0, -3.5)
            ->assertSet('laserCenterXMm', 92.5)
            ->assertSet('laserCenterYMm', 104.0)
            ->assertSet('laserAngleDeg', -3.5);

        $component->call('nudgeLaserPosition', 0.5, -1.0, 0.5)
            ->assertSet('laserCenterXMm', 93.0)
            ->assertSet('laserCenterYMm', 103.0)
            ->assertSet('laserAngleDeg', -3.0);

        $component->call('triggerLaserFraming', $item->id, 'front')
            ->assertSet('laserWorkflowStep', 2)
            ->assertDispatched('order-feedback');

        $component->call('sendJobDirectlyToLaser', $item->id, 'front')
            ->assertSet('laserWorkflowStep', 3)
            ->assertDispatched('order-feedback');

        $queueState = app(XtoolLaserCockpitService::class)->getQueueState(false);
        $this->assertEquals(2, $queueState['pending_count']);
        $this->assertEquals('engrave', $queueState['pending_jobs'][0]['type']);
        $this->assertEquals('framing', $queueState['pending_jobs'][1]['type']);
        $this->assertEquals(93.0, $queueState['pending_jobs'][0]['center_x_mm']);
        $this->assertEquals(103.0, $queueState['pending_jobs'][0]['center_y_mm']);
    }

    #[Test]
    public function autonomous_three_step_order_workflow_and_configurator_checklist_progresses_cleanly(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createPersonalizableLaserProduct();
        [$order, $item] = $this->createSampleOrderWithItem($product);

        // 0. Ohne Extras/Kundenwunsch werden in Schritt 1 KEINE leeren "Nein / OK"-Konfigurator-Vorgaben eingeblendet
        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertDontSee('Konfigurator-Vorgaben')
            ->assertDontSee('Standard-Schutzpolsterung');

        // Bestellung startet im Status "pending" (Wartend) mit allen 3 Konfigurator-Optionen
        $order->update(['status' => 'pending']);
        $item->update([
            'configuration' => array_merge($item->configuration ?? [], [
                'notes'           => 'Bitte das Herz minimal höher platzieren',
                'selected_addons' => ['gift_wrap_black_gold', 'gift_box_westpack'],
                'gift_wrap_mode'  => 'loose',
                'addons_summary'  => [
                    [
                        'id'        => 'gift_wrap_black_gold',
                        'name'      => 'Seelenfunke Geschenkpapier Schwarz-Gold (als Bogen zum Selbstverpacken mitliefern)',
                        'wrap_mode' => 'loose',
                    ],
                    [
                        'id'   => 'gift_box_westpack',
                        'name' => 'Edle Schmuckbox Schwarz-Gold',
                    ],
                ],
            ]),
        ]);

        $checklist = $item->fresh()->resolveManufacturingChecklist();
        $this->assertTrue($checklist['notes']['has_note']);
        $this->assertSame('Bitte das Herz minimal höher platzieren', $checklist['notes']['text']);
        $this->assertTrue($checklist['gift_wrap']['selected']);
        $this->assertSame('loose', $checklist['gift_wrap']['mode']);
        $this->assertSame('Ja (Nur als Bogen dazu legen)', $checklist['gift_wrap']['short_label']);
        $this->assertTrue($checklist['gift_box']['selected']);
        $this->assertSame('Ja (Edle Schmuckbox Schwarz-Gold)', $checklist['gift_box']['short_label']);

        // 1. Aufruf im Status "pending" zeigt die zentrierte Kachel "Wartend" + Button "Jetzt starten"
        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSee('Wartend')
            ->assertSee('Jetzt starten')
            ->assertSee('Ja (Nur als Bogen dazu legen)')
            ->assertSee('Ja (1x Edle Schmuckbox)');

        // 2. Klick auf "Jetzt starten" -> Status autonom auf "processing", Schritt 1 ("In Bearbeitung") öffnet sich
        $component->call('startOrderProcessing')
            ->assertSet('status', 'processing')
            ->assertSet('activeOrderStep', 1)
            ->assertSee('Schritt 1 · In Bearbeitung')
            ->assertSee('Kundenwunsch')
            ->assertSee('Bitte das Herz minimal höher platzieren')
            ->assertSee('Geschenkpapier')
            ->assertSee('Edle Schmuckbox');

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertStringContainsString('Schritt 1 (In Bearbeitung)', (string) $order->fresh()->workshop_note);

        // 3. Einzelne Konfigurator-Checklistenpunkte abhaken -> beim letzten Punkt wechselt es autonom zu Schritt 2
        $component->call('toggleItemChecklistTask', $item->id, 'notes')
            ->call('toggleItemChecklistTask', $item->id, 'gift_wrap')
            ->call('toggleItemChecklistTask', $item->id, 'gift_box')
            ->call('toggleItemChecklistTask', $item->id, 'engraving')
            ->assertSet('activeOrderStep', 2)
            ->assertSee('Reinigen · Isopropanol & Tuch')
            ->assertSee('Schmuckbox')
            ->assertSee('Geschenkpapier · Beilegen')
            ->assertSee('Dankeskarte')
            ->assertDontSee('Standard-Schutzpolsterung');

        $this->assertTrue($item->fresh()->is_completed);
        $this->assertStringContainsString('Schritt 1 abgeschlossen', (string) $order->fresh()->workshop_note);

        // 4. Schritt 2 abschließen (Versendet) -> Status autonom auf "shipped", wechselt zu Schritt 3
        $component->call('completeStepTwoShipped', false)
            ->assertSet('status', 'shipped')
            ->assertSet('activeOrderStep', 3)
            ->assertSee('✓ 1 Artikel erledigt')
            ->assertDontSee('Alle 1 Artikel');

        $this->assertSame('shipped', $order->fresh()->status);

        // 5. Schritt 3 abschließen (Abgeschlossen) -> Status autonom auf "completed"
        $component->call('completeStepThreeOrder')
            ->assertSet('status', 'completed')
            ->assertSet('activeOrderStep', 3);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertStringContainsString('Schritt 3 erledigt', (string) $order->fresh()->workshop_note);
    }

    #[Test]
    public function it_warps_raw_4000x3000_camera_capture_using_21x21_factory_calibration_grid(): void
    {
        $cockpit = app(XtoolLaserCockpitService::class);

        $rawImg = imagecreatetruecolor(800, 600);
        $bg = imagecolorallocate($rawImg, 25, 28, 32);
        $white = imagecolorallocate($rawImg, 240, 240, 245);
        imagefill($rawImg, 0, 0, $bg);
        imagefilledrectangle($rawImg, 350, 250, 450, 350, $white);

        ob_start();
        imagejpeg($rawImg, null, 85);
        $rawBytes = (string) ob_get_clean();
        imagedestroy($rawImg);

        $tmpTarget = sys_get_temp_dir() . '/sf_test_warped_' . Str::random(6) . '.jpg';
        $ok = $cockpit->warpRawCameraImageWithCalib($rawBytes, $tmpTarget);

        $this->assertTrue($ok);
        $this->assertFileExists($tmpTarget);
        $dims = getimagesize($tmpTarget);
        $this->assertSame(2000, $dims[0]);
        $this->assertSame(2000, $dims[1]);

        @unlink($tmpTarget);
    }

    #[Test]
    public function multi_item_order_advances_item_by_item_and_verifies_packing_station_checks(): void
    {
        $admin = $this->createAdmin();
        $product1 = $this->createPersonalizableLaserProduct();
        $product2 = Product::create([
            'name'        => 'Der Seelen Kristall',
            'slug'        => 'der-seelen-kristall-' . Str::lower(Str::random(5)),
            'type'        => 'physical',
            'status'      => 'active',
            'price'       => 8990,
            'width'       => 180,
            'height'      => 180,
            'length'      => 40,
            'weight'      => 950,
            'configurator_settings' => [
                'allow_personalization' => true,
                'personalization_mode'  => 'studio',
                'overlay_type'          => 'plane',
                'area_shape'            => 'circle',
                'area_left'             => 16.0,
                'area_top'              => 11.6,
                'area_width'            => 68.0,
                'area_height'           => 68.0,
                'xtool_plane_width'     => 161.5,
                'xtool_plane_height'    => 161.5,
                'xtool_material'        => 'K9 Kristallglas',
                'xtool_focus_mm'        => 20.0,
            ],
        ]);

        [$order, $item1] = $this->createSampleOrderWithItem($product1);
        $item2 = OrderOrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product2->id,
            'product_name'  => $product2->name,
            'quantity'      => 2,
            'unit_price'    => 8990,
            'tax_rate'      => 19.0,
            'total_price'   => 17980,
            'configuration' => [
                'texts' => [['text' => 'Frohe Weihnachten!']],
            ],
        ]);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('activeOrderStep', 1)
            ->assertSet('selectedOrderItemId', $item1->id)
            ->assertSee('Artikel 1 von 2');

        // Abschluss von Position 1 springt automatisch weiter zu Position 2 (bleibt in Schritt 1)
        $component->call('completeCurrentItemAndAdvance', $item1->id)
            ->assertSet('activeOrderStep', 1)
            ->assertSet('selectedOrderItemId', $item2->id)
            ->assertSee('Artikel 2 von 2');

        // Abschluss von Position 2 wechselt automatisch in Schritt 2 (Verpackung & Versand)
        $component->call('completeCurrentItemAndAdvance', $item2->id)
            ->assertSet('activeOrderStep', 2)
            ->assertSee('Schritt 2 · Verpackung & Versand')
            ->assertSee('Reinigen · Isopropanol & Tuch')
            ->assertSee('Dankeskarte')
            ->assertSee('Aufkleber Zerbrechlich')
            ->assertSee('Der Seelen Kristall')
            ->assertDontSee('Standard-Schutzpolsterung');

        // Packtisch-Pruefpunkte abhaken
        $component->call('toggleOrderPackingCheck', 'cleaned')
            ->call('toggleOrderPackingCheck', 'thank_you_card')
            ->call('toggleOrderPackingCheck', 'fragile_sticker');

        $overview = $order->fresh()->getManufacturingOverview();
        $this->assertTrue($overview['cleaned_checked']);
        $this->assertTrue($overview['thank_you_card_checked']);
        $this->assertTrue($overview['requires_fragile_sticker']);
        $this->assertTrue($overview['fragile_sticker_checked']);

        $packingPlan = $order->fresh()->getPackingStationPlan();
        $this->assertCount(2, $packingPlan['items']);
        $this->assertNotEmpty($packingPlan['cartons']);

        $component->call('completeStepTwoShipped', false)
            ->assertSet('activeOrderStep', 3)
            ->assertSee('✓ Alle 2 Artikel erledigt');
    }

    #[Test]
    public function step_one_two_tab_switcher_and_four_header_modals_open_cleanly(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createPersonalizableLaserProduct();
        [$order] = $this->createSampleOrderWithItem($product);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('stepOneTab', 'cockpit')
            ->assertSet('activeHeaderModal', null)
            ->assertDontSee('Optional einblendbar')
            ->assertDontSee('Laser-Steckbrief')
            ->call('setStepOneTab', 'preview')
            ->assertSet('stepOneTab', 'preview')
            ->call('setStepOneTab', 'cockpit')
            ->assertSet('stepOneTab', 'cockpit');

        // 1. Dateien-Modal
        $component->call('openHeaderModal', 'files')
            ->assertSet('activeHeaderModal', 'files')
            ->assertSee('Dateien & Downloads')
            ->assertSee('xTool Projekt (.xcs)');

        // 2. Kostenübersicht-Modal (€)
        $component->call('openHeaderModal', 'costs')
            ->assertSet('activeHeaderModal', 'costs')
            ->assertSee('Kostenübersicht');

        // 3. Kunden Infos-Modal
        $component->call('openHeaderModal', 'customer')
            ->assertSet('activeHeaderModal', 'customer')
            ->assertSee('Kunden Infos')
            ->assertSee('Rechnungsadresse');

        // 4. Lieferadresse-Modal (GPS)
        $component->call('openHeaderModal', 'shipping')
            ->assertSet('activeHeaderModal', 'shipping')
            ->assertSee('Lieferadresse')
            ->assertSee('Identisch mit Rechnung')
            ->call('closeHeaderModal')
            ->assertSet('activeHeaderModal', null);
    }

    #[Test]
    public function seelen_kristall_and_all_catalog_products_have_single_outer_contour_and_exact_millimeter_dimensions(): void
    {
        $this->seed(\Database\Seeders\ProductSeeder::class);

        $exportService = app(\App\Services\Export\FileDownloadService::class);
        $cockpitService = app(XtoolLaserCockpitService::class);

        $expectedDimensions = [
            'seelen-kristall'         => [161.5, 161.5],
            'seelen-anhaenger'        => [45.0, 40.0],
            'seelen-talisman'         => [28.0, 50.0],
            'seelen-pfote-hundemarke' => [40.0, 22.0],
            'goldenes-seelen-herz'    => [25.0, 22.0],
            'id-armband-silber-herz'  => [24.0, 5.5],
            'id-armband-gold-herz'    => [24.0, 5.5],
            'geschenkgutschein'       => [86.0, 54.0],
        ];

        $order = OrderOrder::create([
            'order_number'     => 'SF-2026-MM-CALIB',
            'email'            => 'kalibrierung@example.com',
            'status'           => 'processing',
            'payment_status'   => 'paid',
            'payment_method'   => 'stripe',
            'billing_address'  => ['first_name' => 'Alina', 'last_name' => 'Test', 'address' => 'Ring 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Alina', 'last_name' => 'Test', 'address' => 'Ring 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'subtotal_price'   => 9990,
            'tax_amount'       => 1595,
            'shipping_price'   => 0,
            'total_price'      => 9990,
        ]);

        foreach ($expectedDimensions as $slug => [$expectedW, $expectedH]) {
            $product = Product::where('slug', $slug)->firstOrFail();

            [$blankW, $blankH] = $cockpitService->resolvePhysicalBlankDimensionsMm($product, []);
            $this->assertEqualsWithDelta($expectedW, $blankW, 0.01, "Blank width mismatch for {$slug}");
            $this->assertEqualsWithDelta($expectedH, $blankH, 0.01, "Blank height mismatch for {$slug}");

            $item = OrderOrderItem::create([
                'order_id'      => $order->id,
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'quantity'      => 1,
                'unit_price'    => 2990,
                'tax_rate'      => 19.0,
                'total_price'   => 2990,
                'configuration' => [
                    'texts' => [
                        ['text' => 'Millimeter Test', 'x' => 50.0, 'y' => ($slug === 'seelen-kristall' ? 45.6 : 50.0), 'size' => 1.0, 'font' => 'Montserrat'],
                    ],
                ],
            ]);

            $payload = $exportService->buildLaserDesignPayload($item, 'front', true);
            $this->assertEqualsWithDelta($expectedW, $payload['blankWidthMm'], 0.01, "Blank widthMm mismatch for {$slug}");
            $this->assertEqualsWithDelta($expectedH, $payload['blankHeightMm'], 0.01, "Blank heightMm mismatch for {$slug}");

            $productCfg = is_array($product->configurator_settings) ? $product->configurator_settings : [];
            $waCal = $exportService->resolveEngravingWorkingAreaCalibration($product, $productCfg, $item->configuration ?? [], false);
            $expectedEngraveW = (float) $waCal['engraveWidthMm'];
            $expectedEngraveH = (float) $waCal['engraveHeightMm'];

            $this->assertEqualsWithDelta($expectedEngraveW, $payload['widthMm'], 0.02, "Working-area widthMm mismatch for {$slug}");
            $this->assertEqualsWithDelta($expectedEngraveH, $payload['heightMm'], 0.02, "Working-area heightMm mismatch for {$slug}");

            // Zwingend NUR EIN geschlossener Rahmen, bündig auf 0..widthMm x 0..heightMm (0,0 mm Lücke!)
            $ghostDPath = $payload['ghostContourDPath'];
            $this->assertSame(1, substr_count($ghostDPath, 'M '), "Expected single contour path for {$slug}, got multiple M commands");
            $this->assertSame(1, substr_count($ghostDPath, ' Z'), "Expected single closed contour for {$slug}");

            preg_match_all('/[ML]\s*(-?\d+\.\d+)\s+(-?\d+\.\d+)/', $ghostDPath, $m);
            $xs = array_map('floatval', $m[1]);
            $ys = array_map('floatval', $m[2]);
            $this->assertEqualsWithDelta(0.0, min($xs), 0.15, "Min X of contour for {$slug} must start at 0 mm");
            $this->assertEqualsWithDelta($expectedEngraveW, max($xs), 0.15, "Max X of contour for {$slug} must match {$expectedEngraveW} mm");
            $this->assertEqualsWithDelta(0.0, min($ys), 0.15, "Min Y of contour for {$slug} must start at 0 mm");
            $this->assertEqualsWithDelta($expectedEngraveH, max($ys), 0.15, "Max Y of contour for {$slug} must match {$expectedEngraveH} mm");

            $polyXs = array_column($payload['workingAreaPolygonPct'], 'x');
            $polyYs = array_column($payload['workingAreaPolygonPct'], 'y');
            $this->assertEqualsWithDelta(0.0, min($polyXs), 0.3, "Working-area polygon min X must be 0 % for {$slug}");
            $this->assertEqualsWithDelta(100.0, max($polyXs), 0.3, "Working-area polygon max X must be 100 % for {$slug}");
            $this->assertEqualsWithDelta(0.0, min($polyYs), 0.3, "Working-area polygon min Y must be 0 % for {$slug}");
            $this->assertEqualsWithDelta(100.0, max($polyYs), 0.3, "Working-area polygon max Y must be 100 % for {$slug}");

            if ($slug === 'seelen-kristall') {
                $this->assertEqualsWithDelta(133.927, $payload['widthMm'], 0.02);
                $this->assertEqualsWithDelta(133.927, $payload['heightMm'], 0.02);
            }
        }

        // Prüfe zudem 0,1-mm-Synchronität zwischen HUD & Input im Livewire-Cockpit (Seelen-Kristall zeigt exakt 133,9 × 133,9 mm)
        $admin = $this->createAdmin();
        $kristallItem = $order->items()->first();
        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->call('selectItemForPreview', $kristallItem->id)
            ->call('toggleLaserDetails')
            ->call('updateLaserCoordinates', 103.8, 99.0, 0.0)
            ->assertSet('laserCenterXMm', 103.8)
            ->assertSet('laserCenterYMm', 99.0)
            ->assertSet('laserAngleDeg', 0.0)
            ->assertSet('laserWidthMm', 133.9)
            ->assertSet('laserHeightMm', 133.9)
            ->assertSee('step="0.1"', false)
            ->assertSee('x-model.number="xMm"', false)
            ->assertSee('@laser-cockpit-sync.window', false)
            ->assertSee('133,9 × 133,9 mm');
    }

    #[Test]
    public function shipping_address_comparison_and_redundant_company_name_suppression_work_accurately(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createPersonalizableLaserProduct();
        [$order] = $this->createSampleOrderWithItem($product);

        // Gleiche Adresse, aber billing_address enthält zusätzliche Metadaten + redundantes company == Kundenname + 'Deutschland' vs 'DE'
        $order->update([
            'billing_address' => [
                'first_name'  => 'Alina',
                'last_name'   => 'Steinhauer',
                'company'     => 'Alina Steinhauer',
                'email'       => 'alina.stone@t-online.de',
                'phone'       => '+49 1590 1966864',
                'address'     => 'Carl-Goerdeler-Ring 26',
                'postal_code' => '38518',
                'city'        => 'Gifhorn',
                'country'     => 'Deutschland',
            ],
            'shipping_address' => [
                'country'     => 'DE',
                'city'        => 'Gifhorn',
                'postal_code' => '38518',
                'address'     => 'Carl-Goerdeler-Ring 26',
                'first_name'  => 'Alina',
                'last_name'   => 'Steinhauer',
                'company'     => '',
            ],
        ]);

        $this->assertTrue(OrderOrder::isRedundantCompanyName('Alina Steinhauer', 'Alina Steinhauer'));
        $this->assertFalse(OrderOrder::isRedundantCompanyName('Mein-Seelenfunke', 'Alina Steinhauer'));
        $this->assertFalse($order->fresh()->hasDifferentShippingAddress());

        // PDF / E-Mail Kontaktdaten unterdrücken redundanten Firmennamen ebenfalls
        $invoiceHelper = new class {
            use \App\Traits\FormatsECommerceData;

            public function extract($model): array
            {
                return $this->getMailContactData($model);
            }
        };
        $contactData = $invoiceHelper->extract($order->fresh());
        $this->assertSame('', $contactData['firma']);

        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSee('Identisch mit Rechnung')
            ->assertDontSee('Abweichend von Rechnung');

        // Echte abweichende Lieferadresse wird sauber als abweichend erkannt
        $order->update([
            'shipping_address' => [
                'first_name'  => 'Max',
                'last_name'   => 'Mustermann',
                'address'     => 'Hauptstraße 99',
                'postal_code' => '10115',
                'city'        => 'Berlin',
                'country'     => 'DE',
            ],
        ]);
        $this->assertTrue($order->fresh()->hasDifferentShippingAddress());

        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSee('Abweichend von Rechnung');
    }

    #[Test]
    public function configurator_2d_preview_eliminates_curved_text_style_collision_and_centers_textarea_symmetrically(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createPersonalizableLaserProduct();
        [$order, $item] = $this->createSampleOrderWithItem($product);

        $html = Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class, [
                'product'     => $product->id,
                'initialData' => $item->configuration,
                'qty'         => 1,
                'context'     => 'preview',
            ])
            ->html();

        // Kein x-show + :style String-Konflikt auf den geraden/gebogenen Text-Containern (verhindert 24px Versatz nach oben)
        $this->assertStringContainsString('<template x-if="!isTextCurved(currentTexts[index], index)">', $html);
        $this->assertStringContainsString('<template x-if="isTextCurved(currentTexts[index], index)">', $html);
        $this->assertStringNotContainsString('x-show="isTextCurved(', $html);
        $this->assertStringNotContainsString('x-show="!isTextCurved(', $html);
        $this->assertStringContainsString('padding: 10px 10px;', $html);

        $scriptSource = (string) file_get_contents(resource_path('views/livewire/shop/product/product-configurator/partials/scripts_frontend_1.blade.php'));
        $this->assertStringContainsString("el.style.padding = '10px 10px';", $scriptSource);
    }

    #[Test]
    public function minipc_laser_relay_poll_and_complete_cycle_works_with_monitor_pulse_token_and_blocks_non_admins(): void
    {
        $isolatedRelayDir = storage_path('app/private/testing_xtool_relay_' . Str::lower(Str::random(8)));
        $cockpit = new XtoolLaserCockpitService();
        $cockpit->setRelayTestingOptions($isolatedRelayDir, true, 0.25, 0.25);
        $this->app->instance(XtoolLaserCockpitService::class, $cockpit);

        try {
            // 1. Auth-Matrix: Gast -> 401, Kunde & Mitarbeiter/Influencer/Steuerberater -> 403
            $this->getJson('/api/shop/workshop-focus/laser-relay/poll')->assertStatus(401);
            $this->postJson('/api/shop/workshop-focus/laser-relay/complete', [])->assertStatus(401);

            $customer = \App\Models\Customer\Customer::factory()->create();
            \Laravel\Sanctum\Sanctum::actingAs($customer, ['*']);
            $this->getJson('/api/shop/workshop-focus/laser-relay/poll')->assertStatus(403);

            foreach (['internal', 'influencer', 'tax_advisor'] as $role) {
                $emp = \App\Models\Employee\Employee::create([
                    'first_name' => ucfirst($role),
                    'last_name'  => 'RelayBlock',
                    'email'      => $role . '-relay-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
                    'role'       => $role,
                    'password'   => bcrypt('Secret123!'),
                ]);
                \Laravel\Sanctum\Sanctum::actingAs($emp, ['*']);
                $this->getJson('/api/shop/workshop-focus/laser-relay/poll')->assertStatus(403);
            }

            // 2. Heruntergestuftes Admin monitor:pulse Token des Werkstatt Mini-PCs darf pollen & quittieren!
            $this->app['auth']->forgetGuards();
            $admin = $this->createAdmin();
            $miniPcToken = $admin->createToken('MiniPcWorkshopRelay', ['monitor:pulse'])->plainTextToken;

            $emptyPoll = $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
                ->getJson('/api/shop/workshop-focus/laser-relay/poll?laser_online=1');

            $emptyPoll->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'job'     => null,
                ]);

            // Heartbeat mit laser_online=1 schaltet probeLaserReachability() in 0 ms auf true!
            $this->assertTrue($cockpit->probeLaserReachability());

            // 3. capture_camera Relay-Zyklus inkl. warped_jpeg_base64 Speicherung nach background.jpg
            $camJob = $cockpit->enqueueRelayJob('capture_camera', [
                'laser_ip' => '192.168.188.150',
            ]);

            $claimResp = $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
                ->getJson('/api/shop/workshop-focus/laser-relay/poll?laser_online=1');

            $claimResp->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('job.job_id', $camJob['job_id'])
                ->assertJsonPath('job.action', 'capture_camera')
                ->assertJsonPath('job.laser_ip', '192.168.188.150');

            // Zweiter Poll während laufender Bearbeitung liefert null (LOCK_EX Single-Claim)
            $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
                ->getJson('/api/shop/workshop-focus/laser-relay/poll?laser_online=1')
                ->assertStatus(200)
                ->assertJsonPath('job', null);

            // Erzeuge echtes > 5 KB JPEG (600x600 mit Rauschmuster, damit Dateigröße > 5 KB garantiert ist)
            $img = imagecreatetruecolor(600, 600);
            for ($y = 0; $y < 600; $y += 4) {
                for ($x = 0; $x < 600; $x += 4) {
                    $col = imagecolorallocate($img, ($x * 37 + $y * 13) & 255, ($x * 19 + $y * 53) & 255, ($x + $y) & 255);
                    imagefilledrectangle($img, $x, $y, $x + 3, $y + 3, $col);
                }
            }
            ob_start();
            imagejpeg($img, null, 95);
            $jpegBytes = (string) ob_get_clean();
            imagedestroy($img);
            $this->assertGreaterThan(5120, strlen($jpegBytes));

            $completeResp = $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
                ->postJson('/api/shop/workshop-focus/laser-relay/complete', [
                    'job_id'             => $camJob['job_id'],
                    'ok'                 => true,
                    'laser_online'       => true,
                    'state'              => 'CAMERA_OK',
                    'error'              => null,
                    'warped_jpeg_base64' => base64_encode($jpegBytes),
                ]);

            $completeResp->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('camera_saved', true);

            $waitedCam = $cockpit->waitForRelayJobCompletion($camJob['job_id'], 0.5);
            $this->assertTrue($waitedCam['ok'] ?? false);
            $this->assertSame($jpegBytes, file_get_contents(Storage::disk('public')->path('bestellungen/xtool/background.jpg')));

            // 4. send_xf Relay-Zyklus
            $xfPayload = base64_encode('MOCK_TAR_XF_STREAM_PAYLOAD');
            $xfJob = $cockpit->enqueueRelayJob('send_xf', [
                'laser_ip'    => '192.168.188.150',
                'xf_base64'   => $xfPayload,
                'gcode'       => 'G0X100Y100',
                'center_x_mm' => 98.5,
                'center_y_mm' => 102.0,
                'width_mm'    => 40.0,
                'height_mm'   => 40.0,
                'angle_deg'   => -2.5,
            ]);

            $xfPoll = $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
                ->getJson('/api/shop/workshop-focus/laser-relay/poll?laser_online=1');

            $xfPoll->assertStatus(200)
                ->assertJsonPath('job.job_id', $xfJob['job_id'])
                ->assertJsonPath('job.action', 'send_xf')
                ->assertJsonPath('job.xf_base64', $xfPayload)
                ->assertJsonPath('job.center_x_mm', 98.5)
                ->assertJsonPath('job.center_y_mm', 102);

            $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
                ->postJson('/api/shop/workshop-focus/laser-relay/complete', [
                    'job_id'       => $xfJob['job_id'],
                    'ok'           => true,
                    'laser_online' => true,
                    'state'        => 'WORK_PREPARED',
                ])->assertStatus(200);

            $waitedXf = $cockpit->waitForRelayJobCompletion($xfJob['job_id'], 0.5);
            $this->assertTrue($waitedXf['ok'] ?? false);
            $this->assertSame('WORK_PREPARED', $waitedXf['state'] ?? null);
        } finally {
            if (is_dir($isolatedRelayDir)) {
                \Illuminate\Support\Facades\File::deleteDirectory($isolatedRelayDir);
            }
        }
    }

    #[Test]
    public function refresh_laser_camera_and_align_emits_honest_warning_when_offline_and_success_only_on_live_frame(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createPersonalizableLaserProduct();
        [$order] = $this->createSampleOrderWithItem($product);

        // 1. Offline / kein Live-Frame (Stage ohne aktive Mini-PC-Brücke oder Laser ausgeschaltet) -> ZWINGEND warning statt Fake-Success!
        $isolatedRelayDir = storage_path('app/private/testing_xtool_offline_' . Str::lower(Str::random(8)));
        $offlineCockpit = new XtoolLaserCockpitService();
        $offlineCockpit->setRelayTestingOptions($isolatedRelayDir, true, 0.1, 0.1);
        $this->app->instance(XtoolLaserCockpitService::class, $offlineCockpit);

        try {
            Livewire::actingAs($admin, 'admin')
                ->test(OrderOverview::class)
                ->call('openDetail', $order->id)
                ->call('refreshLaserCameraAndAlign')
                ->assertDispatched('order-feedback', function (string $name, array $params): bool {
                    return ($params['type'] ?? null) === 'warning'
                        && ($params['title'] ?? null) === 'Kein Live-Bild vom Laser empfangen'
                        && str_contains((string) ($params['message'] ?? ''), '192.168.188.151');
                });
        } finally {
            if (is_dir($isolatedRelayDir)) {
                \Illuminate\Support\Facades\File::deleteDirectory($isolatedRelayDir);
            }
        }

        // 2. Echtes Live-Bild via minipc_lan_relay -> grüne Erfolgsmeldung!
        $liveMock = \Mockery::mock(XtoolLaserCockpitService::class)->makePartial();
        $liveMock->shouldReceive('detectBlankPositionOnBed')
            ->andReturn([
                'center_x_mm'       => 97.5,
                'center_y_mm'       => 101.0,
                'angle_deg'         => 0.0,
                'blank_width_mm'    => 40.0,
                'blank_height_mm'   => 40.0,
                'confidence'        => 94,
                'detected'          => true,
                'camera_url'        => asset('storage/bestellungen/xtool/background.jpg') . '?v=' . time(),
                'camera_updated_at' => now()->format('d.m.Y H:i:s'),
                'source'            => 'minipc_lan_relay',
                'laser_online'      => true,
                'laser_ip'          => '192.168.188.150',
                'relay_error'       => null,
            ]);
        $this->app->instance(XtoolLaserCockpitService::class, $liveMock);

        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->call('refreshLaserCameraAndAlign')
            ->assertDispatched('order-feedback', function (string $name, array $params): bool {
                return ($params['type'] ?? null) === 'success'
                    && ($params['title'] ?? null) === 'Live-Kamerabild & KI-Ausrichtung aktiv';
            });
    }

    #[Test]
    public function cockpit_resize_handles_persist_scaled_dimensions_and_propagate_to_laser_framing_and_xf_gcode(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();

        $darkImg = imagecreatetruecolor(80, 80);
        imagealphablending($darkImg, false);
        imagesavealpha($darkImg, true);
        imagefill($darkImg, 0, 0, imagecolorallocatealpha($darkImg, 0, 0, 0, 0));
        ob_start();
        imagepng($darkImg);
        $darkPng = (string) ob_get_clean();
        imagedestroy($darkImg);

        $crystal = Product::create([
            'name'        => 'Der Seelen Kristall',
            'slug'        => 'seelen-kristall-resize-' . Str::lower(Str::random(5)),
            'type'        => 'physical',
            'status'      => 'active',
            'price'       => 6990,
            'width'       => 180,
            'height'      => 200,
            'length'      => 40,
            'weight'      => 920,
            'configurator_settings' => [
                'allow_personalization' => true,
                'overlay_type'          => 'plane',
                'area_shape'            => 'circle',
                'area_left'             => 16.0,
                'area_top'              => 11.6,
                'area_width'            => 68.0,
                'area_height'           => 68.0,
                'material_type'         => 'glass',
                'template_package'      => 'seelen-kristall',
                'xtool_plane_width'     => 161.5,
                'xtool_plane_height'    => 161.5,
                'xtool_plate_box_pct'   => ['left' => 9.0, 'top' => 4.6, 'width' => 82.0, 'height' => 82.0],
                'xtool_contour_shape'   => 'circle',
                'xtool_material'        => 'K9 Kristallglas',
                'xtool_dpi'             => 100,
            ],
        ]);

        $relUpload = 'bestellungen/cart-uploads/test_resize_dark_' . Str::lower(Str::random(6)) . '.png';
        Storage::disk('public')->put($relUpload, $darkPng);

        try {
            [$order, $item] = $this->createSampleOrderWithItem($crystal);
            $item->update([
                'configuration' => [
                    'logos' => [[
                        'x'        => 50.0,
                        'y'        => 45.6,
                        'value'    => $relUpload,
                        'url'      => '/storage/' . $relUpload,
                        'size'     => 500.0,
                        'type'     => 'saved',
                        'rotation' => 0,
                    ]],
                    'texts' => [[
                        'text' => 'WYSIWYG Resize',
                        'font' => 'Georgia',
                        'x'    => 50.0,
                        'y'    => 58.0,
                        'size' => 1.1,
                    ]],
                ],
            ]);

            // 1. Initiales Öffnen lädt exakt die innere Gravurfläche (133,9 x 133,9 mm) und rendert alle 4 Resize-Anfasser
            $component = Livewire::actingAs($admin, 'admin')
                ->test(OrderOverview::class)
                ->call('openDetail', $order->id)
                ->assertSet('laserWidthMm', 133.9)
                ->assertSet('laserHeightMm', 133.9)
                ->assertSee('data-resize-handle="nw"', false)
                ->assertSee('data-resize-handle="ne"', false)
                ->assertSee('data-resize-handle="se"', false)
                ->assertSee('data-resize-handle="sw"', false)
                ->assertSee('cursor-nwse-resize', false)
                ->assertSee('cursor-nesw-resize', false)
                ->assertSee("startDrag(\$event, 'resize')", false);

            // 2. Skalierung per Maus-Drag auf 118,0 x 118,0 mm um Mittelpunkt (102,0 / 98,0 mm) persistieren
            $component->call('updateLaserCoordinates', $item->id, 102.0, 98.0, 0.0, 118.0, 118.0)
                ->assertSet('laserCenterXMm', 102.0)
                ->assertSet('laserCenterYMm', 98.0)
                ->assertSet('laserWidthMm', 118.0)
                ->assertSet('laserHeightMm', 118.0);

            $savedCoords = $item->fresh()->configuration['laser_cockpit_coordinates']['front'] ?? null;
            $this->assertIsArray($savedCoords);
            $this->assertSame(102.0, (float) $savedCoords['x_mm']);
            $this->assertSame(98.0, (float) $savedCoords['y_mm']);
            $this->assertSame(118.0, (float) $savedCoords['w_mm']);
            $this->assertSame(118.0, (float) $savedCoords['h_mm']);

            // 3. Sicherheits-Clamp (< 10 mm wird auf min. 10 mm gehalten, Abwärtskompatibilität 3-Arg lässt wMm/hMm bestehen)
            $component->call('updateLaserCoordinates', $item->id, 102.0, 98.0, 0.0, 3.0, 3.0)
                ->assertSet('laserWidthMm', 10.0)
                ->assertSet('laserHeightMm', 10.0);

            $component->call('updateLaserCoordinates', $item->id, 102.0, 98.0, 0.0, 118.0, 118.0);
            $component->call('updateLaserCoordinates', 100.0, 100.0, 0.0)
                ->assertSet('laserCenterXMm', 100.0)
                ->assertSet('laserCenterYMm', 100.0)
                ->assertSet('laserWidthMm', 118.0)
                ->assertSet('laserHeightMm', 118.0);

            // 4. Erneutes Öffnen der Bestellung stellt die gespeicherte Größe (118,0 x 118,0 mm) wieder her
            $reopened = Livewire::actingAs($admin, 'admin')
                ->test(OrderOverview::class)
                ->call('openDetail', $order->id)
                ->assertSet('laserCenterXMm', 100.0)
                ->assertSet('laserCenterYMm', 100.0)
                ->assertSet('laserWidthMm', 118.0)
                ->assertSet('laserHeightMm', 118.0);

            // 5. "2 AN LASER SENDEN" übergibt exakt das skalierte 118,0 x 118,0 mm Maß an border.gcode & motion.gcode!
            $reopened->call('sendJobDirectlyToLaser', $item->id, 'front')
                ->assertSet('laserWorkflowStep', 3);

            $queueState = app(XtoolLaserCockpitService::class)->getQueueState(false);
            $this->assertSame(118.0, (float) $queueState['pending_jobs'][0]['width_mm']);
            $this->assertSame(118.0, (float) $queueState['pending_jobs'][0]['height_mm']);
            // 100.0 - (118.0 / 2) = 41.000 mm bis 159.000 mm
            $this->assertStringContainsString('G0X41.000Y41.000', $queueState['pending_jobs'][0]['border_gcode']);
            $this->assertStringContainsString('G1X159.000Y41.000', $queueState['pending_jobs'][0]['border_gcode']);

            $xfScaled = app(XtoolLaserCockpitService::class)->generateXfPackageBytes($item->fresh(), 'front');
            $maxRadius = 0.0;
            $curX = 100.0;
            $curY = 100.0;
            foreach (explode("\n", $xfScaled['motion_gcode']) as $line) {
                if (preg_match('/^G([01])(?:X([0-9.]+))?(?:Y([0-9.]+))?(?:S(\d+))?/', trim($line), $m)) {
                    $nx = isset($m[2]) && $m[2] !== '' ? (float) $m[2] : $curX;
                    $ny = isset($m[3]) && $m[3] !== '' ? (float) $m[3] : $curY;
                    $ps = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
                    if ($m[1] === '1' && $ps > 0) {
                        $maxRadius = max($maxRadius, hypot($curX - 100.0, $curY - 100.0), hypot($nx - 100.0, $ny - 100.0));
                    }
                    $curX = $nx;
                    $curY = $ny;
                }
            }
            $this->assertGreaterThan(57.5, $maxRadius, 'Skalierter Laser-Job muss den 59,0-mm-Radius ausfüllen');
            $this->assertLessThanOrEqual(59.6, $maxRadius, 'Skalierter Laser-Job darf den 118,0-mm-Kreis (r = 59,0 mm) nicht überschreiten');

            // 6. "1 KAMERA & KI-AUSRICHTEN" setzt wMm x hMm proportional wieder auf die innere Gravurfläche (133,9 mm)
            $reopened->call('refreshLaserCameraAndAlign')
                ->assertSet('laserWidthMm', 133.9)
                ->assertSet('laserHeightMm', 133.9);
        } finally {
            Storage::disk('public')->delete($relUpload);
        }
    }
}

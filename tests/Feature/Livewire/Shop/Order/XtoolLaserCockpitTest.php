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
        // 96.5 - (40 / 2) = 76.5 mm, 98.5 - (40 / 2) = 78.5 mm
        $this->assertEquals(76.5, $bitmapDisplay['x']);
        $this->assertEquals(78.5, $bitmapDisplay['y']);
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
        $this->assertStringContainsString('G0X80.000Y80.000F1440000', $xf['border_gcode']);
        $this->assertStringContainsString('G1X120.000Y80.000S133F1440000', $xf['border_gcode']);
        $this->assertStringContainsString('ustar', $xf['bytes']);
        $this->assertStringContainsString('preview.jpg', $xf['bytes']);
        $this->assertStringContainsString('border.gcode', $xf['bytes']);
        $this->assertStringContainsString('motion.bgcode', $xf['bytes']);
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
            ->assertSee('xTool F2 Ultra UV · Live-Laser-Cockpit')
            ->assertSee('Live-Konfigurations-Vorschau')
            ->assertSee('Details anzeigen')
            ->assertSee('Kamera & KI-Ausrichten')
            ->assertSee('An Laser senden')
            ->assertSee('Weiter zur Verpackung')
            ->assertDontSee('Artikel 1 von 1')
            ->assertDontSee('Rahmen-Vorschau')
            ->assertDontSee('.xcs Backup')
            ->assertDontSee('Optional einblendbar')
            ->assertDontSee('xTool Anleitung');

        $component->call('toggleLaserDetails')
            ->assertSet('showLaserDetails', true)
            ->assertSee('Details ausblenden')
            ->assertSee('Rahmen-Vorschau')
            ->assertSee('.xcs Backup');

        $component->call('refreshLaserCameraAndAlign')
            ->assertSet('laserWorkflowStep', 2)
            ->assertSet('laserCameraRefreshToken', 1)
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
            ->assertSee('1× Schwarze Westpack Schmuckbox eingelegt')
            ->assertSee('Schwarz-Gold-Geschenkpapier lose als Bogen beigelegt')
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
            ->assertSee('Dankeskarte beigelegt')
            ->assertSee('Warnaufkleber „Zerbrechlich“ außen aufgeklebt')
            ->assertSee('Der Seelen Kristall')
            ->assertDontSee('Standard-Schutzpolsterung');

        // Packtisch-Prüfpunkte abhaken
        $component->call('toggleOrderPackingCheck', 'thank_you_card')
            ->call('toggleOrderPackingCheck', 'fragile_sticker');

        $overview = $order->fresh()->getManufacturingOverview();
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
            $this->assertEqualsWithDelta($expectedW, $payload['widthMm'], 0.01, "Payload widthMm mismatch for {$slug}");
            $this->assertEqualsWithDelta($expectedH, $payload['heightMm'], 0.01, "Payload heightMm mismatch for {$slug}");

            // Zwingend NUR EIN geschlossener Außenrahmen (kein doppelter Rahmen!)
            $ghostDPath = $payload['ghostContourDPath'];
            $this->assertSame(1, substr_count($ghostDPath, 'M '), "Expected single contour path for {$slug}, got multiple M commands");
            $this->assertSame(1, substr_count($ghostDPath, ' Z'), "Expected single closed contour for {$slug}");

            // Prüfe exakte Millimeter-Spannweite der Außenkontur (0.000 .. expectedW / expectedH)
            preg_match_all('/[ML]\s*(-?\d+\.\d+)\s+(-?\d+\.\d+)/', $ghostDPath, $m);
            $xs = array_map('floatval', $m[1]);
            $ys = array_map('floatval', $m[2]);
            $this->assertEqualsWithDelta(0.0, min($xs), 0.15, "Min X of contour for {$slug} must start at 0 mm");
            $this->assertEqualsWithDelta($expectedW, max($xs), 0.15, "Max X of contour for {$slug} must match {$expectedW} mm");
            $this->assertEqualsWithDelta(0.0, min($ys), 0.15, "Min Y of contour for {$slug} must start at 0 mm");
            $this->assertEqualsWithDelta($expectedH, max($ys), 0.15, "Max Y of contour for {$slug} must match {$expectedH} mm");

            if ($slug === 'seelen-kristall') {
                // Beim Seelenkristall muss die 2D-Scheibenmitte (50.0 %, 45.6 %) exakt auf (50 %, 50 %) = (90 mm, 90 mm) liegen
                $polyXs = array_column($payload['workingAreaPolygonPct'], 'x');
                $polyYs = array_column($payload['workingAreaPolygonPct'], 'y');
                $this->assertEqualsWithDelta(50.0, (min($polyXs) + max($polyXs)) * 0.5, 0.2);
                $this->assertEqualsWithDelta(50.0, (min($polyYs) + max($polyYs)) * 0.5, 0.2);
            }
        }

        // Prüfe zudem 0,1-mm-Synchronität zwischen HUD & Input im Livewire-Cockpit
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
            ->assertSee('step="0.1"', false)
            ->assertSee("\$wire.entangle('laserCenterXMm')", false)
            ->assertSee('161,5 × 161,5 mm');
    }
}

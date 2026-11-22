<?php

namespace Tests\Feature;

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

class OrderLaserCockpitMediaAndShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    protected function createAdmin(): Admin
    {
        return Admin::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name'  => 'Steinhauer',
            'email'      => 'alina-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password'   => bcrypt('Secret123!'),
        ]);
    }

    protected function createLaserProduct(bool $darkSurface = true): Product
    {
        return Product::create([
            'name'        => 'Der Seelenanhänger',
            'slug'        => 'seelen-anhaenger-' . Str::lower(Str::random(6)),
            'type'        => 'physical',
            'status'      => 'active',
            'price'       => 3490,
            'width'       => 45,
            'height'      => 40,
            'length'      => 2,
            'weight'      => 32,
            'configurator_settings' => [
                'allow_personalization' => true,
                'personalization_mode'  => 'studio',
                'overlay_type'          => 'plane',
                'area_shape'            => 'rect',
                'area_left'             => 15,
                'area_top'              => 15,
                'area_width'            => 70,
                'area_height'           => 70,
                'xtool_plane_width'     => 45,
                'xtool_plane_height'    => 40,
                'xtool_equipment_key'   => 'flatbed_standard',
                'xtool_process_mode'    => 'Prozess auf flacher Oberfläche',
                'xtool_material'        => $darkSurface ? 'Schwarzer Edelstahl' : 'Edelstahl 14K vergoldet',
                'engraving_polarity'    => $darkSurface ? 'white_on_dark' : 'dark_on_light',
                'xtool_focus_mm'        => 21.5,
                'xtool_dot_duration_us' => 190,
                'xtool_power_percent'   => 65,
                'xtool_dpi'             => 600,
                'xtool_passes'          => 1,
                'xtool_frequency_khz'   => 60,
                'xtool_bitmap_mode'     => 'Jarvis',
                'xtool_engrave_mode'    => 'Bidirektional',
            ],
        ]);
    }

    protected function createSampleGradientPortraitPng(int $w = 120, int $h = 120): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rad = hypot($x - ($w / 2.0), $y - ($h / 2.0)) / ($w * 0.65);
                $lum = (int) max(15, min(245, round(235 - ($rad * 185) + (($x % 9) * 3))));
                $col = imagecolorallocatealpha($img, $lum, max(0, $lum - 8), min(255, $lum + 6), 0);
                imagesetpixel($img, $x, $y, $col);
            }
        }

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    protected function createOrderWithPhotoUpload(Product $product, string $relPhotoPath, ?string $relSnapPath = null): array
    {
        $order = OrderOrder::create([
            'order_number'     => 'SF-2026-' . random_int(1000, 9999),
            'email'            => 'kunde@mein-seelenfunke.de',
            'status'           => 'processing',
            'payment_status'   => 'paid',
            'payment_method'   => 'stripe',
            'billing_address'  => [
                'first_name'  => 'Laura',
                'last_name'   => 'Sonnenschein',
                'address'     => 'Carl-Goerdeler-Ring 26',
                'postal_code' => '38518',
                'city'        => 'Gifhorn',
                'country'     => 'DE',
            ],
            'shipping_address' => [
                'first_name'  => 'Laura',
                'last_name'   => 'Sonnenschein',
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

        $config = [
            'files' => [$relPhotoPath],
            'logos' => [
                [
                    'type'     => 'saved',
                    'value'    => $relPhotoPath,
                    'url'      => '/storage/' . $relPhotoPath,
                    'x'        => 50,
                    'y'        => 44,
                    'size'     => 65,
                    'rotation' => 0,
                ],
            ],
            'texts' => [
                [
                    'text'     => 'Für immer im Herzen',
                    'font'     => 'Montserrat',
                    'align'    => 'center',
                    'x'        => 50,
                    'y'        => 78,
                    'size'     => 0.9,
                    'rotation' => 0,
                ],
            ],
        ];

        if ($relSnapPath !== null) {
            $config['snapshot_path'] = ['front' => $relSnapPath];
        }

        $item = OrderOrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'quantity'      => 1,
            'unit_price'    => 3490,
            'tax_rate'      => 19.0,
            'total_price'   => 3490,
            'configuration' => $config,
        ]);

        return [$order, $item];
    }

    #[Test]
    public function header_removes_old_files_button_and_renders_media_dropdown_below_laser_parameters_and_in_fallback_steps(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->createAdmin();
        $product = $this->createLaserProduct(true);

        $relPhoto = 'bestellungen/cart-uploads/kunden_portraet.png';
        $relRawPhoto = 'bestellungen/cart-uploads/kunden_portraet_raw.png';
        $relSnap = 'system/snapshots/front_snap.png';
        $pngBytes = $this->createSampleGradientPortraitPng(96, 96);

        Storage::disk('public')->put($relPhoto, $pngBytes);
        Storage::disk('public')->put($relRawPhoto, $pngBytes);
        Storage::disk('public')->put($relSnap, $pngBytes);

        [$order, $item] = $this->createOrderWithPhotoUpload($product, $relPhoto, $relSnap);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('selectedOrderId', $order->id);

        $html = $component->html();

        // 1. Oben links ist der alte "Dateien"-Button entfernt; Pill-Leiste zeigt nur Kosten | Kunde | Adresse
        $this->assertStringNotContainsString("openHeaderModalClient('files')", $html);
        $this->assertStringContainsString("openHeaderModalClient('costs')", $html);
        $this->assertStringContainsString("openHeaderModalClient('customer')", $html);
        $this->assertStringContainsString("openHeaderModalClient('shipping')", $html);
        $this->assertStringNotContainsString('.xcs Backup', $html);

        // 2. Medien-Dropdown sitzt direkt unter "Laserparameter anpassen" und vor "Kamera & KI-Ausrichten"
        $paramsPos = strpos($html, 'Laserparameter anpassen');
        $mediaPos = strpos($html, 'data-order-media-dropdown');
        $step1BtnPos = strpos($html, 'Kamera &amp; KI-Ausrichten');
        $this->assertNotFalse($paramsPos);
        $this->assertNotFalse($mediaPos);
        $this->assertNotFalse($step1BtnPos);
        $this->assertGreaterThan($paramsPos, $mediaPos);
        $this->assertLessThan($step1BtnPos, $mediaPos);

        // 3. Medien-Inhalt: Sicherung (Lightbox statt target="_blank"), Original-Badge, _raw Download, Zahnrad & alle 3 Export-Buttons
        $this->assertStringContainsString('Medien', $html);
        $this->assertStringContainsString('Sicherung (Vorderseite)', $html);
        $this->assertStringContainsString('openMediaLightbox(', $html);
        $this->assertStringContainsString('kunden_portraet.png', $html);
        $this->assertStringContainsString('Original', $html);
        $this->assertStringContainsString('kunden_portraet_raw.png', $html);
        $this->assertStringContainsString('data-open-photo-studio', $html);
        $this->assertStringContainsString("openLaserPhotoStudio('{$item->id}', '{$relPhoto}')", $html);
        $this->assertStringContainsString('XTOOL PROJEKT (.XCS)', $html);
        $this->assertStringContainsString('LASER-SVG', $html);
        $this->assertStringContainsString('KONTUR-PNG', $html);

        // 4. Fallback außerhalb des Laser-Cockpits (Status Wartend, Schritt 2 Versand, Schritt 3 Abgeschlossen)
        $order->update(['status' => 'pending']);
        $pendingHtml = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->html();
        $this->assertStringContainsString('Wartend', $pendingHtml);
        $this->assertStringContainsString('data-order-media-fallback', $pendingHtml);
        $this->assertStringContainsString('data-order-media-dropdown', $pendingHtml);

        $order->update(['status' => 'processing']);
        $step2Html = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->call('setOrderStep', 2)
            ->html();
        $this->assertStringContainsString('Schritt 2 · Verpackung &amp; Versand', $step2Html);
        $this->assertStringContainsString('data-order-media-dropdown', $step2Html);
    }

    #[Test]
    public function laser_coordinates_persist_true_001_mm_precision_without_rounding_to_tenths(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->createAdmin();
        $product = $this->createLaserProduct(false);
        $relPhoto = 'bestellungen/cart-uploads/precision_photo.png';
        Storage::disk('public')->put($relPhoto, $this->createSampleGradientPortraitPng(64, 64));

        [$order, $item] = $this->createOrderWithPhotoUpload($product, $relPhoto);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->call('updateLaserCoordinates', $item->id, 104.27, 88.63, -1.35, 31.48, 27.94, 4)
            ->assertSet('laserCenterXMm', 104.27)
            ->assertSet('laserCenterYMm', 88.63)
            ->assertSet('laserAngleDeg', -1.35)
            ->assertSet('laserWidthMm', 31.48)
            ->assertSet('laserHeightMm', 27.94)
            ->assertDispatched('laser-cockpit-sync', function (string $name, array $params): bool {
                return ($params['seq'] ?? null) === 4
                    && (float) ($params['x'] ?? 0) === 104.27
                    && (float) ($params['y'] ?? 0) === 88.63
                    && (float) ($params['angle'] ?? 0) === -1.35;
            });

        $savedCoords = $item->fresh()->configuration['laser_cockpit_coordinates']['front'] ?? null;
        $this->assertIsArray($savedCoords);
        $this->assertSame(104.27, (float) $savedCoords['x_mm']);
        $this->assertSame(88.63, (float) $savedCoords['y_mm']);
        $this->assertSame(-1.35, (float) $savedCoords['angle_deg']);
        $this->assertSame(31.48, (float) $savedCoords['w_mm']);
        $this->assertSame(27.94, (float) $savedCoords['h_mm']);

        // Nudge im 0,02 mm Mikro-Schritt erhält ebenfalls volle 0,01 mm Präzision
        $component->call('nudgeLaserPosition', 0.02, -0.02, 0.05)
            ->assertSet('laserCenterXMm', 104.29)
            ->assertSet('laserCenterYMm', 88.61)
            ->assertSet('laserAngleDeg', -1.3);

        // Volle 360°-Drehung (-180° bis +180°) inkl. Wrap-Around über ±180° hinaus (z. B. 135° oder 210° -> -150°) verifizieren
        $component->call('updateLaserCoordinates', $item->id, 104.29, 88.61, 137.42, 31.48, 27.94, 5)
            ->assertSet('laserAngleDeg', 137.42);
        $this->assertSame(137.42, (float) ($item->fresh()->configuration['laser_cockpit_coordinates']['front']['angle_deg'] ?? 0));

        $component->call('nudgeLaserPosition', 0.0, 0.0, 50.0)
            ->assertSet('laserAngleDeg', -172.58);

        // Erneutes Öffnen der Bestellung lädt volle 360°-Winkel & 0,01 mm Koordinaten verlustfrei
        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('laserCenterXMm', 104.29)
            ->assertSet('laserCenterYMm', 88.61)
            ->assertSet('laserAngleDeg', -172.58)
            ->assertSet('laserWidthMm', 31.48)
            ->assertSet('laserHeightMm', 27.94);
    }

    #[Test]
    public function laser_photo_studio_opens_untouched_original_auto_optimizes_and_saves_processed_png_and_item_adjustments(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->createAdmin();
        $product = $this->createLaserProduct(true);

        $relPhoto = 'bestellungen/cart-uploads/familie_sommer.png';
        $relRawPhoto = 'bestellungen/cart-uploads/familie_sommer_raw.png';
        Storage::disk('public')->put($relPhoto, $this->createSampleGradientPortraitPng(80, 80));
        Storage::disk('public')->put($relRawPhoto, $this->createSampleGradientPortraitPng(140, 140));

        [$order, $item] = $this->createOrderWithPhotoUpload($product, $relPhoto);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id);

        $tokenBefore = (int) $component->get('laserCameraRefreshToken');

        // 1. Path-Traversal / fremde Datei wird strikt geblockt
        $component->call('openLaserPhotoStudio', $item->id, '../../.env')
            ->assertSet('isLaserPhotoStudioOpen', false);

        // 2. Öffnen des Laser-Foto-Studios lädt automatisch das unberührte _raw-Originalbild
        $component->call('openLaserPhotoStudio', $item->id, $relPhoto)
            ->assertSet('isLaserPhotoStudioOpen', true)
            ->assertSet('photoStudioItemId', $item->id)
            ->assertSet('photoStudioFileKey', $relPhoto)
            ->assertSet('photoStudioOriginalRelPath', $relRawPhoto)
            ->assertSee('Laser-Foto-Studio')
            ->assertSee('Automatisch Werte setzen')
            ->assertSee('Graustufen aktivieren')
            ->assertSee('Invertieren (Dunkler Rohling)')
            ->assertSee('Gamma (Mitteltöne)')
            ->assertSee('Lichter-Schutz (Max. Laser-Dichte)')
            ->assertSee('Schärfe (Kanten)')
            ->assertSee('Raster-Modus')
            ->assertSee('Für Laser übernehmen');

        $previewUrl = (string) $component->get('photoStudioPreviewUrl');
        $origUrl = (string) $component->get('photoStudioOriginalUrl');
        $this->assertStringStartsNotWith('data:', $previewUrl);
        $this->assertStringStartsNotWith('data:', $origUrl);
        $this->assertStringContainsString('/storage/bestellungen/xtool/photo-edits/preview_', $previewUrl);
        $this->assertLessThan(260, strlen($previewUrl));
        $this->assertStringContainsString('/storage/' . $relRawPhoto, $origUrl);

        $studioHtml = $component->html();
        $this->assertStringContainsString('window.sfCreateLaserPhotoStudio', $studioHtml);
        $this->assertStringContainsString('data-studio-live-canvas="result"', $studioHtml);
        $this->assertStringContainsString('data-studio-live-canvas="split"', $studioHtml);
        $this->assertStringContainsString('Wird übernommen...', $studioHtml);

        // 2a-fix. Anti-Moire Display-Skalierung, 3-Pass-Blur, Serpentine Dithering, WYSIWYG-Rohling & MorphDOM-sichere Tabs
        $this->assertStringContainsString('computeTargetDisplayMetrics()', $studioHtml);
        $this->assertStringContainsString('threePassGaussianApprox(', $studioHtml);
        $this->assertStringContainsString('((((x * 5281) + (y * 11035)) & 31) - 15.5) * 0.15', $studioHtml);
        $this->assertStringContainsString('for (let x = w - 1; x >= 0; x--)', $studioHtml);
        $this->assertStringContainsString('const markR = invert ? 242 : 24;', $studioHtml);
        $this->assertStringContainsString('const baseR = invert ? 18 : 250;', $studioHtml);
        $this->assertStringContainsString("this.studioBg = this.params.invert ? 'dark' : 'light';", $studioHtml);
        $this->assertStringContainsString(":class=\"studioView === 'result' ? 'flex items-center justify-center w-full h-full' : 'hidden'\"", $studioHtml);
        $this->assertStringContainsString(":class=\"studioView === 'original' ? 'flex items-center justify-center w-full h-full' : 'hidden'\"", $studioHtml);
        $this->assertStringContainsString(":class=\"studioView === 'split' ? 'grid grid-cols-1 sm:grid-cols-2 gap-3 w-full items-center' : 'hidden'\"", $studioHtml);
        $this->assertStringNotContainsString('x-show="studioView ===', $studioHtml);

        // 2b. #[Locked]-Schutz gegen clientseitige Pfad-Manipulation von photoStudioOriginalRelPath & photoStudioFileKey
        try {
            $component->set('photoStudioOriginalRelPath', 'buchhaltung/receipts/secret.pdf');
            $this->fail('Locked-Property photoStudioOriginalRelPath darf clientseitig nicht überschrieben werden.');
        } catch (\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException) {
            $this->assertSame($relRawPhoto, $component->get('photoStudioOriginalRelPath'));
        }

        // 3. "✨ Automatisch Werte setzen" berechnet Idealwerte auf dem Originalbild und feuert Client-Sync
        $component->call('autoOptimizeLaserPhoto')
            ->assertSet('isLaserPhotoStudioOpen', true)
            ->assertDispatched('laser-studio-params-synced');

        $autoParams = $component->get('photoStudioParams');
        $this->assertIsArray($autoParams);
        $this->assertTrue($autoParams['enabled']);
        $this->assertTrue($autoParams['grayscale']);
        $this->assertGreaterThanOrEqual(0.35, (float) $autoParams['gamma']);
        $this->assertLessThanOrEqual(2.50, (float) $autoParams['gamma']);
        $this->assertGreaterThanOrEqual(30, (int) $autoParams['highlight_cap']);
        $this->assertLessThanOrEqual(100, (int) $autoParams['highlight_cap']);

        // 3b. "Zurücksetzen" feuert ebenfalls laser-studio-params-synced ohne Base64-Payload
        $component->call('resetLaserPhotoStudioParams')
            ->assertDispatched('laser-studio-params-synced');
        $this->assertStringStartsNotWith('data:', (string) $component->get('photoStudioPreviewUrl'));

        // 4. Einziger Request beim Klick auf "Für Laser übernehmen": saveLaserPhotoStudio($clientParams)
        $customClientParams = [
            'grayscale'     => true,
            'invert'        => true,
            'brightness'    => -6,
            'contrast'      => 24,
            'gamma'         => 1.65,
            'highlight_cap' => 76,
            'sharpness'     => 95,
            'dither_mode'   => 'stucki',
            'threshold'     => 132,
        ];

        $component->call('saveLaserPhotoStudio', $customClientParams)
            ->assertSet('isLaserPhotoStudioOpen', false)
            ->assertDispatched('order-feedback');

        $this->assertGreaterThan($tokenBefore, (int) $component->get('laserCameraRefreshToken'));

        $freshConfig = $item->fresh()->configuration;
        $savedAdj = $freshConfig['laser_photo_adjustments'][$relPhoto] ?? null;
        $this->assertIsArray($savedAdj);
        $this->assertTrue($savedAdj['enabled']);
        $this->assertSame(-6, (int) $savedAdj['brightness']);
        $this->assertSame(24, (int) $savedAdj['contrast']);
        $this->assertSame(1.65, (float) $savedAdj['gamma']);
        $this->assertSame(76, (int) $savedAdj['highlight_cap']);
        $this->assertSame(95, (int) $savedAdj['sharpness']);
        $this->assertSame('stucki', $savedAdj['dither_mode']);
        $this->assertSame(132, (int) $savedAdj['threshold']);
        $this->assertNotEmpty($savedAdj['edited_path']);
        $this->assertNotEmpty($savedAdj['updated_at']);
        Storage::disk('public')->assertExists($savedAdj['edited_path']);

        // Badge "Laser-optimiert" erscheint sofort im Medien-Dropdown
        $this->assertStringContainsString('Laser-optimiert', $component->html());
    }

    #[Test]
    public function pending_order_renders_magic_loading_animation_and_starts_processing_cleanly(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->createAdmin();
        $product = $this->createLaserProduct(true);
        $relPhoto = 'bestellungen/cart-uploads/magic_start_test.png';
        Storage::disk('public')->put($relPhoto, $this->createSampleGradientPortraitPng(64, 64));

        [$order, $item] = $this->createOrderWithPhotoUpload($product, $relPhoto);
        $order->update(['status' => 'pending']);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('status', 'pending');

        $pendingHtml = $component->html();
        $this->assertStringContainsString('Wartend', $pendingHtml);
        $this->assertStringContainsString('data-pending-order-card', $pendingHtml);
        $this->assertStringContainsString('startingMagic: false', $pendingHtml);
        $this->assertStringContainsString('sf-order-magic-ring', $pendingHtml);
        $this->assertStringContainsString('sf-order-magic-star', $pendingHtml);
        $this->assertStringContainsString('sf-order-micro-spark', $pendingHtml);
        $this->assertStringContainsString('Jetzt starten', $pendingHtml);
        $this->assertStringContainsString('✦ Magie wird aktiviert...', $pendingHtml);
        $this->assertStringContainsString('$wire.startOrderProcessing()', $pendingHtml);

        $component->call('startOrderProcessing')
            ->assertSet('status', 'processing')
            ->assertSet('activeOrderStep', 1)
            ->assertDispatched('order-feedback');

        $freshOrder = $order->fresh();
        $this->assertSame('processing', $freshOrder->status);
        $this->assertStringContainsString('Schritt 1 (In Bearbeitung)', (string) $freshOrder->workshop_note);

        $stepOneHtml = $component->html();
        $this->assertStringContainsString('Schritt 1 · In Bearbeitung', $stepOneHtml);
        $this->assertStringContainsString($item->product_name, $stepOneHtml);
    }

    #[Test]
    public function camera_light_mode_double_contrast_frame_dimmer_and_shift_fine_gear_render_with_zero_en_dashes(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->createAdmin();
        $product = $this->createLaserProduct(false);
        $relPhoto = 'bestellungen/cart-uploads/light_mode_test.png';
        Storage::disk('public')->put($relPhoto, $this->createSampleGradientPortraitPng(64, 64));

        [$order, $item] = $this->createOrderWithPhotoUpload($product, $relPhoto);

        $capturedArgs = [];
        $cockpitMock = \Mockery::mock(XtoolLaserCockpitService::class)->makePartial();
        $cockpitMock->shouldReceive('detectBlankPositionOnBed')
            ->andReturnUsing(function (...$args) use (&$capturedArgs) {
                $capturedArgs = $args;

                return [
                    'center_x_mm'       => 101.34,
                    'center_y_mm'       => 97.68,
                    'angle_deg'         => 0.45,
                    'blank_width_mm'    => 45.0,
                    'blank_height_mm'   => 40.0,
                    'engrave_width_mm'  => 31.50,
                    'engrave_height_mm' => 28.00,
                    'confidence'        => 95,
                    'detected'          => true,
                    'camera_url'        => asset('storage/bestellungen/xtool/background.jpg') . '?v=' . time(),
                    'camera_updated_at' => now()->format('d.m.Y H:i:s'),
                    'source'            => 'minipc_lan_relay',
                    'laser_online'      => true,
                    'laser_ip'          => '192.168.188.150',
                    'relay_error'       => null,
                ];
            });
        $this->app->instance(XtoolLaserCockpitService::class, $cockpitMock);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('laserCameraLightMode', 'normal')
            ->assertSee('Blendfrei (LED aus)')
            ->assertSee('Gedimmt')
            ->assertSee('Licht an');

        // Wechsel auf 'anti_glare' & 'dim' speichert in configuration['laser_camera_light_mode'] und übergibt es an detectBlankPositionOnBed
        $component->call('setLaserCameraLightMode', 'anti_glare', $item->id)
            ->assertSet('laserCameraLightMode', 'anti_glare');
        $this->assertSame('anti_glare', $item->fresh()->configuration['laser_camera_light_mode'] ?? null);

        $component->call('setLaserCameraLightMode', 'dim', $item->id)
            ->assertSet('laserCameraLightMode', 'dim');
        $this->assertSame('dim', $item->fresh()->configuration['laser_camera_light_mode'] ?? null);

        $component->call('captureLaserCameraSnapshot', 'front')
            ->assertSet('laserCenterXMm', 101.34)
            ->assertSet('laserCenterYMm', 97.68)
            ->assertSet('laserAngleDeg', 0.45);

        $this->assertSame('dim', $capturedArgs[1]['laser_camera_light_mode'] ?? null);
        $this->assertSame('dim', $capturedArgs[4] ?? null);

        $html = $component->html();

        // Haarfeiner Doppel-Kontrast-Rahmen + Abdunkeln & Motiv-Kontrast + Shift 0,01 mm Fein-Getriebe & Pfeiltasten verifizieren
        $this->assertStringContainsString('stroke="rgba(15, 23, 42, 0.70)"', $html);
        $this->assertStringContainsString('stroke-width="1.45"', $html);
        $this->assertStringContainsString('stroke="#00d8ff"', $html);
        $this->assertStringContainsString('stroke-width="0.85"', $html);
        $this->assertStringContainsString('stroke-dasharray="3.5,2.5"', $html);
        $this->assertStringNotContainsString('fill="rgba(2, 132, 199, 0.08)"', $html);
        $this->assertStringContainsString('w-2 h-2', $html);
        $this->assertStringContainsString('w-3.5 h-3.5', $html);
        $this->assertStringContainsString('translate(-50%, -85%)', $html);
        $this->assertStringContainsString('data-cockpit-bg-dimmer', $html);
        $this->assertStringContainsString('Abdunkeln', $html);
        $this->assertStringContainsString('data-cockpit-overlay-contrast', $html);
        $this->assertStringContainsString('Motiv-Kontrast', $html);
        $this->assertStringContainsString('step="0.01"', $html);
        $this->assertStringContainsString('e.shiftKey', $html);
        $this->assertStringContainsString('fineMode ? 0.15 : 1.0', $html);
        $this->assertStringContainsString('fineMode ? 0.02 : 0.20', $html);
        $this->assertStringContainsString('SHIFT · FEIN-MODUS (0,01 MM / 0,01°)', $html);
        $this->assertStringContainsString('normalizeAngleDeg(', $html);
        $this->assertStringContainsString('const rotateGear = fineMode ? 0.08 : 1.0;', $html);
        $this->assertStringContainsString('min="-180"', $html);
        $this->assertStringContainsString('max="180"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('handleViewportKeydown($event)', $html);

        // 0 KI-Gedankenstriche in allen Dateien aus Arbeitspaket C
        $touchedFiles = [
            app_path('Livewire/Shop/Order/OrderOverview.php'),
            resource_path('views/livewire/shop/order/order-overview.blade.php'),
            resource_path('views/livewire/shop/order/order-offer-detail-content.blade.php'),
            resource_path('views/livewire/shop/order/order-overview-partials/detail-header.blade.php'),
            resource_path('views/livewire/shop/order/order-overview-partials/header-modals.blade.php'),
            resource_path('views/livewire/shop/order/order-overview-partials/media-dropdown-card.blade.php'),
            resource_path('views/livewire/shop/order/order-overview-partials/laser-photo-studio-modal.blade.php'),
            resource_path('views/livewire/shop/order/order-offer-detail-partials/laser-cockpit-card.blade.php'),
            base_path('tests/Feature/OrderLaserCockpitMediaAndShiftTest.php'),
        ];

        foreach ($touchedFiles as $path) {
            $this->assertFileExists($path);
            $src = (string) file_get_contents($path);
            $this->assertSame(
                0,
                substr_count($src, " \u{2013} "),
                'KI-Gedankenstrich gefunden in: ' . basename($path)
            );
            $this->assertSame(
                0,
                substr_count($src, " \u{2014} "),
                'Geviertstrich mit Leerzeichen gefunden in: ' . basename($path)
            );
        }
    }

    #[Test]
    public function laser_photo_studio_renders_header_profile_dropdown_and_persists_profile_templates_on_product(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->createAdmin();
        $product = $this->createLaserProduct(true);
        $settings = $product->configurator_settings;
        $settings['xtool_profiles'] = [
            [
                'id'              => 'prof_silber_kontrast',
                'title'           => 'Weiß / Silber-Kontrast · Lamellenrost',
                'is_default'      => true,
                'focus_mm'        => 19.9,
                'dot_duration_us' => 280,
                'power_percent'   => 85,
                'dpi'             => 600,
                'frequency_khz'   => 40,
                'speed_mms'       => 450,
                'passes'          => 1,
                'bitmap_mode'     => 'Jarvis',
                'engrave_mode'    => 'Bidirektional',
            ],
            [
                'id'              => 'prof_fein_800',
                'title'           => 'Feinporträt 800 DPI',
                'is_default'      => false,
                'focus_mm'        => 19.9,
                'dot_duration_us' => 220,
                'power_percent'   => 70,
                'dpi'             => 800,
                'frequency_khz'   => 55,
                'speed_mms'       => 380,
                'passes'          => 1,
                'bitmap_mode'     => 'Stucki',
                'engrave_mode'    => 'Bidirektional',
            ],
        ];
        $product->update(['configurator_settings' => $settings]);

        $relPhoto = 'bestellungen/cart-uploads/studio_profile_test.png';
        Storage::disk('public')->put($relPhoto, $this->createSampleGradientPortraitPng(90, 90));

        [$order, $item] = $this->createOrderWithPhotoUpload($product, $relPhoto);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->call('openLaserPhotoStudio', $item->id, $relPhoto)
            ->assertSet('isLaserPhotoStudioOpen', true)
            ->assertSet('photoStudioActiveProfileId', 'prof_silber_kontrast');

        $html = $component->html();

        // 1. Header-Dropdown oben rechts vor dem Schließen-Button + Vorlagen-Box unter Raster-Modus
        $titlePos = strpos($html, 'Laser-Foto-Studio');
        $dropdownPos = strpos($html, 'data-studio-profile-dropdown');
        $closeBtnPos = strpos($html, 'wire:click="closeLaserPhotoStudio"');
        $rasterPos = strpos($html, 'Raster-Modus');
        $templateBoxPos = strpos($html, 'data-studio-profile-template-box');

        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($dropdownPos);
        $this->assertNotFalse($closeBtnPos);
        $this->assertNotFalse($rasterPos);
        $this->assertNotFalse($templateBoxPos);
        $this->assertGreaterThan($titlePos, $dropdownPos);
        $this->assertLessThan($closeBtnPos, $dropdownPos);
        $this->assertStringContainsString('Laserprofil-Vorlage', $html);
        $this->assertStringContainsString('Im aktuellen Laserprofil speichern', $html);
        $this->assertStringContainsString('+ Als neues Laserprofil speichern', $html);
        $this->assertStringContainsString('wire:ignore', $html);

        // 2. Speichern im aktuellen Produkt-Laserprofil hinterlegt photo_studio am Produkt
        $templateParams = [
            'grayscale'     => true,
            'invert'        => true,
            'contrast'      => 28,
            'brightness'    => -8,
            'gamma'         => 1.55,
            'highlight_cap' => 72,
            'sharpness'     => 110,
            'dither_mode'   => 'stucki',
            'threshold'     => 134,
        ];

        $component->call('savePhotoStudioToActiveLaserProfile', 'prof_silber_kontrast', $templateParams)
            ->assertDispatched('laser-studio-profiles-synced')
            ->assertDispatched('order-feedback');

        $profilesAfterSave = $component->get('photoStudioProfiles');
        $savedDefault = collect($profilesAfterSave)->firstWhere('id', 'prof_silber_kontrast');
        $this->assertIsArray($savedDefault);
        $this->assertIsArray($savedDefault['photo_studio'] ?? null);
        $this->assertSame(28, (int) $savedDefault['photo_studio']['contrast']);
        $this->assertSame(1.55, (float) $savedDefault['photo_studio']['gamma']);
        $this->assertSame('stucki', $savedDefault['photo_studio']['dither_mode']);

        // 3. Neues Laserprofil direkt aus dem Foto-Studio anlegen & automatisch aktivieren
        $portraitLightParams = [
            'grayscale'     => true,
            'invert'        => true,
            'contrast'      => 18,
            'brightness'    => 6,
            'gamma'         => 1.35,
            'highlight_cap' => 80,
            'sharpness'     => 95,
            'dither_mode'   => 'jarvis',
            'threshold'     => 128,
        ];

        $component->call('createLaserProfileFromPhotoStudio', 'Porträt Hell', 'prof_fein_800', $portraitLightParams)
            ->assertDispatched('laser-studio-profiles-synced')
            ->assertDispatched('order-feedback');

        $newActiveId = (string) $component->get('photoStudioActiveProfileId');
        $this->assertNotSame('', $newActiveId);
        $this->assertNotSame('prof_silber_kontrast', $newActiveId);

        $createdMeta = collect($component->get('photoStudioProfiles'))->firstWhere('id', $newActiveId);
        $this->assertIsArray($createdMeta);
        $this->assertSame('Porträt Hell', $createdMeta['title']);
        $this->assertSame(18, (int) ($createdMeta['photo_studio']['contrast'] ?? 0));

        // 4. Umschalten per selectPhotoStudioLaserProfile lädt die gespeicherte Foto-Vorlage des gewählten Profils
        $component->call('selectPhotoStudioLaserProfile', 'prof_silber_kontrast')
            ->assertSet('photoStudioActiveProfileId', 'prof_silber_kontrast')
            ->assertDispatched('laser-studio-params-synced');

        $loadedParams = $component->get('photoStudioParams');
        $this->assertSame(28, (int) $loadedParams['contrast']);
        $this->assertSame('stucki', $loadedParams['dither_mode']);

        // 5. Bei einer frischen Bestellung ohne laser_photo_adjustments lädt openLaserPhotoStudio direkt photo_studio des Standardprofils
        $relPhoto2 = 'bestellungen/cart-uploads/fresh_order_photo.png';
        Storage::disk('public')->put($relPhoto2, $this->createSampleGradientPortraitPng(80, 80));
        [$order2, $item2] = $this->createOrderWithPhotoUpload($product->fresh(), $relPhoto2);

        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order2->id)
            ->call('openLaserPhotoStudio', $item2->id, $relPhoto2)
            ->assertSet('photoStudioParams.contrast', 28)
            ->assertSet('photoStudioParams.gamma', 1.55)
            ->assertSet('photoStudioParams.dither_mode', 'stucki');
    }

    #[Test]
    public function step_one_embeds_editable_order_configurator_and_syncs_cockpit_on_order_item_updated(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->createAdmin();
        $product = Product::create([
            'name'              => 'Das Goldene Seelen-Herz',
            'slug'              => 'goldenes-seelen-herz-' . Str::lower(Str::random(6)),
            'type'              => 'physical',
            'status'            => 'active',
            'is_personalizable' => true,
            'price'             => 3990,
            'width'             => 25,
            'height'            => 22,
            'length'            => 4,
            'weight'            => 18,
            'configurator_settings' => [
                'allow_personalization' => true,
                'personalization_mode'  => 'studio',
                'overlay_type'          => 'plane',
                'area_shape'            => 'rect',
                'area_left'             => 15,
                'area_top'              => 15,
                'area_width'            => 70,
                'area_height'           => 70,
                'xtool_plane_width'     => 25,
                'xtool_plane_height'    => 22,
                'xtool_equipment_key'   => 'flatbed_standard',
                'xtool_process_mode'    => 'Prozess auf flacher Oberfläche',
                'xtool_material'        => 'Edelstahl 14K IP vergoldet',
                'xtool_focus_mm'        => 21.5,
                'xtool_dot_duration_us' => 220,
                'xtool_power_percent'   => 65,
                'xtool_dpi'             => 600,
                'xtool_passes'          => 1,
                'xtool_frequency_khz'   => 65,
                'xtool_bitmap_mode'     => 'Jarvis',
                'xtool_engrave_mode'    => 'Bidirektional',
            ],
        ]);

        $v1Snap = 'system/snapshots/v1_customer.png';
        $v2Snap = 'system/snapshots/v2_admin.png';
        $png = $this->createSampleGradientPortraitPng(64, 64);
        Storage::disk('public')->put($v1Snap, $png);
        Storage::disk('public')->put($v2Snap, $png);

        $relPhoto = 'bestellungen/cart-uploads/herz_motiv.png';
        Storage::disk('public')->put($relPhoto, $png);

        [$order, $item] = $this->createOrderWithPhotoUpload($product, $relPhoto, $v2Snap);
        $conf = $item->configuration;
        $conf['configuration_history'] = [[
            'version'       => 1,
            'actor_type'    => 'customer',
            'actor_label'   => 'Original-Entwurf beim Kauf',
            'saved_at'      => '2026-11-18T14:13:00+01:00',
            'snapshot_path' => ['front' => $v1Snap],
        ]];
        $conf['last_admin_adjustment'] = [
            'adjusted_at' => '2026-11-18T21:05:00+01:00',
            'adjusted_by' => 'Mein-Seelenfunke Admin',
            'version'     => 2,
        ];
        $item->update(['configuration' => $conf]);

        // 1. In 'processing' ist der Admin-Konfigurator aktiv bearbeitbar (context="order_edit" -> Child-Mount + Admin Blueprint-Verlauf)
        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id);

        $processingBlade = (string) file_get_contents(resource_path('views/livewire/shop/order/order-offer-detail-content.blade.php'));
        $this->assertStringContainsString('context="order_edit"', $processingBlade);
        $this->assertStringContainsString(':cartItem="$previewItem"', $processingBlade);

        $html = $component->html();
        $this->assertStringContainsString('data-admin-blueprint-history', $html);
        $this->assertStringContainsString('Blueprint-Verlauf (2)', $html);
        $this->assertStringContainsString('Original-Entwurf beim Kauf', $html);
        $this->assertStringContainsString('Mein-Seelenfunke Admin', $html);

        // 2. Event order-item-updated synchronisiert das Laser-Cockpit sofort
        $tokenBefore = (int) $component->get('laserCameraRefreshToken');
        $component->dispatch('order-item-updated', itemId: $item->id)
            ->assertDispatched('order-feedback', function (string $name, array $params): bool {
                return str_contains((string) ($params['message'] ?? ''), 'Laser-Cockpit synchronisiert');
            });
        $this->assertGreaterThan($tokenBefore, (int) $component->get('laserCameraRefreshToken'));

        // 3. Bei bereits versendeten Bestellungen ('shipped') bleibt die Vorschau schreibgeschützt (context="preview")
        $order->update(['status' => 'shipped']);
        $shippedHtml = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->call('setOrderStep', 1)
            ->html();
        $this->assertStringContainsString('Schritt 1 · In Bearbeitung', $shippedHtml);
    }

    #[Test]
    public function two_sided_order_displays_stepper_switches_primary_cta_to_back_side_and_guards_premature_shipping_transition(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->createAdmin();
        $product = $this->createLaserProduct(false);
        $relPhoto = 'bestellungen/cart-uploads/herz_front.png';
        Storage::disk('public')->put($relPhoto, $this->createSampleGradientPortraitPng(64, 64));

        [$order, $item] = $this->createOrderWithPhotoUpload($product, $relPhoto);
        $conf = $item->configuration;
        $conf['texts_back'] = [[
            'text'     => 'Für immer vereint · 22.11.2026',
            'font'     => 'Great Vibes',
            'align'    => 'center',
            'x'        => 50,
            'y'        => 50,
            'size'     => 1.0,
            'rotation' => 0,
        ]];
        $item->update(['configuration' => $conf]);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('activeOrderStep', 1)
            ->assertSet('laserCockpitSide', 'front');

        $htmlFront = $component->html();
        // 1. Deutlicher 2-Seiten-Badge im Header + breiter 1/2 Stepper im Cockpit + primärer CTA führt auf Seite 2/2
        $this->assertStringContainsString('data-step1-two-sided-badge', $htmlFront);
        $this->assertStringContainsString('2 Seiten (0/2)', $htmlFront);
        $this->assertStringContainsString('data-cockpit-two-sided-stepper', $htmlFront);
        $this->assertStringContainsString('Beidseitig (0/2)', $htmlFront);
        $this->assertStringContainsString('Rückseite · Offen', $htmlFront);
        $this->assertStringContainsString('data-step1-switch-to-back-cta', $htmlFront);
        $this->assertStringContainsString('Zur Rückseite wechseln (Seite 2/2)', $htmlFront);

        // 2. Backend-Schutznetz: Ein direkter Aufruf von completeStepOneAndProceedToShipping auf Seite 1 springt NICHT in Schritt 2, sondern wechselt auf Rückseite!
        $component->call('completeStepOneAndProceedToShipping')
            ->assertSet('activeOrderStep', 1)
            ->assertSet('laserCockpitSide', 'back')
            ->assertDispatched('order-feedback');

        // 3. Sobald die Rückseite aktiv ist, steht der reguläre "Weiter zur Verpackung"-CTA bereit und erlaubt den Abschluss zu Schritt 2
        $htmlBack = $component->html();
        $this->assertStringNotContainsString('data-step1-switch-to-back-cta', $htmlBack);
        $this->assertStringContainsString('Weiter zur Verpackung', $htmlBack);

        $component->call('completeStepOneAndProceedToShipping')
            ->assertSet('activeOrderStep', 2);
    }
}

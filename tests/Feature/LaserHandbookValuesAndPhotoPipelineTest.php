<?php

namespace Tests\Feature;

use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\Export\FileDownloadService;
use App\Services\Laser\LaserPhotoPreprocessor;
use App\Services\Laser\XtoolLaserCockpitService;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Validierte xTool F2 Ultra UV (5 W · 355 nm) Handbuchwerte (PDF 1 & PDF 2) + physikalisch kalibrierte
 * Foto-Pipeline (UV-Dot-Gain) + Multi-Layer .xcs / .xf Export (Foto-Bitmap-Layer vs. Text-/Vektor-Layer).
 */
class LaserHandbookValuesAndPhotoPipelineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Erwartete Werte direkt aus den Werkstatthandbüchern (unabhängig vom Katalog-Code notiert).
     * [polarity, bitmap: focus, power, µs, kHz, DPI, passes | vector: focus, power, mm/s, kHz, L/cm, passes]
     */
    private const EXPECTED = [
        'seelen-pfote-hundemarke'   => ['dark_on_light', [3.2, 45, 210, 65, 800, 1], [3.2, 42, 110, 65, 330, 1]],
        'goldenes-seelen-herz'      => ['dark_on_light', [5.4, 48, 220, 65, 800, 1], [5.4, 46, 100, 65, 350, 1]],
        'seelen-talisman'           => ['dark_on_light', [3.5, 46, 215, 65, 800, 1], [3.6, 44, 105, 65, 330, 1]],
        'seelen-anhaenger'          => ['white_on_dark', [19.9, 85, 280, 40, 600, 1], [19.9, 70, 450, 40, 300, 1]],
        'id-armband-silber-herz'    => ['dark_on_light', [2.2, 75, 240, 50, 1000, 2], [2.2, 75, 180, 50, 400, 2]],
        'id-armband-gold-herz'      => ['dark_on_light', [2.5, 34, 180, 70, 1000, 1], [2.5, 34, 130, 70, 400, 1]],
        'seelen-kristall'           => ['white_on_dark', [33.1, 100, 450, 40, 500, 1], [33.1, 60, 80, 40, 300, 1]],
        'weizenglas-personalisiert' => ['white_on_dark', [74.0, 55, 260, 40, 400, 1], [74.0, 55, 380, 40, 300, 1]],
    ];

    private function seededProducts(): void
    {
        $this->seed(ProductSeeder::class);
    }

    private function createItem(Product $product, array $configuration): OrderOrderItem
    {
        $order = OrderOrder::create([
            'order_number'     => 'ORD-2026-HB-' . Str::upper(Str::random(4)),
            'email'            => 'handbuch@example.com',
            'status'           => 'processing',
            'payment_status'   => 'paid',
            'payment_method'   => 'stripe',
            'billing_address'  => ['first_name' => 'Max', 'last_name' => 'Muster', 'address' => 'Ring 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Muster', 'address' => 'Ring 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'subtotal_price'   => 4990,
            'tax_amount'       => 797,
            'shipping_price'   => 0,
            'total_price'      => 4990,
        ]);

        return OrderOrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'quantity'      => 1,
            'unit_price'    => 4990,
            'tax_rate'      => 19.0,
            'total_price'   => 4990,
            'configuration' => $configuration,
        ]);
    }

    /**
     * Synthetisches helles Porträt: Gesichtshaut RGB 185-225 (Ellipse) auf dunklem Grund (RGB ~22).
     * $mode: 'rgb' = echtes Farbfoto, 'upload' = Konfigurator-Alpha-Maske (dunkle Oberfläche), 'probe' = Gesichtsmaske.
     */
    private function syntheticPortraitPng(string $mode): string
    {
        $w = 320;
        $h = 320;
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        mt_srand(7);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $dx = ($x - 160) / 96.0;
                $dy = ($y - 160) / 128.0;
                $r2 = ($dx * $dx) + ($dy * $dy);
                $inFace = $r2 <= 1.0;
                if ($mode === 'probe') {
                    imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, 0, 0, 0, $inFace ? 0 : 127));
                    continue;
                }
                $l = $inFace
                    ? max(185, min(225, 185 + (int) round(40 * (1 - $r2)) + mt_rand(-4, 4)))
                    : 22 + mt_rand(-6, 6);
                if ($mode === 'rgb') {
                    imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $l, max(0, $l - 12), max(0, $l - 22), 0));
                } else {
                    $alpha = max(0, min(127, 123 - (int) round(pow($l / 255.0, 0.85) * 105)));
                    imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, 0, 0, 0, $alpha));
                }
            }
        }
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    private function photoLogo(string $png, float $x = 50.0, float $y = 72.0, float $size = 240.0): array
    {
        return ['x' => $x, 'y' => $y, 'size' => $size, 'rotation' => 0, 'type' => 'saved', 'url' => 'data:image/png;base64,' . base64_encode($png)];
    }

    /**
     * @return array{face: float, background: float, face_px: int}
     */
    private function measureFaceDensity(string $laserPng, string $probePng): array
    {
        $img = imagecreatefromstring($laserPng);
        $probe = imagecreatefromstring($probePng);
        $w = imagesx($img);
        $h = imagesy($img);
        $faceSum = 0.0;
        $faceN = 0;
        $bgSum = 0.0;
        $bgN = 0;
        $alphaAt = static fn ($im, int $x, int $y): int => (imagecolorat($im, $x, $y) >> 24) & 0x7F;
        for ($y = 3; $y < $h - 3; $y++) {
            for ($x = 3; $x < $w - 3; $x++) {
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 120) {
                    continue;
                }
                $d = LaserPhotoPreprocessor::densityFromGray($c & 0xFF) * ((127 - $a) / 127.0);
                $pa = $alphaAt($probe, $x, $y);
                $inner = $pa < 10 && $alphaAt($probe, $x - 3, $y) < 10 && $alphaAt($probe, $x + 3, $y) < 10
                    && $alphaAt($probe, $x, $y - 3) < 10 && $alphaAt($probe, $x, $y + 3) < 10;
                if ($inner) {
                    $faceSum += $d;
                    $faceN++;
                } elseif ($pa >= 120) {
                    $bgSum += $d;
                    $bgN++;
                }
            }
        }
        imagedestroy($img);
        imagedestroy($probe);

        return [
            'face'       => $faceN ? $faceSum / $faceN : 0.0,
            'background' => $bgN ? $bgSum / $bgN : 0.0,
            'face_px'    => $faceN,
        ];
    }

    private function openZip(string $bytes): \ZipArchive
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hb_xcs_');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        register_shutdown_function(static fn () => @unlink($tmp));

        return $zip;
    }

    #[Test]
    public function it_seeds_validated_vector_and_bitmap_values_with_polarity_for_every_product(): void
    {
        $this->seededProducts();

        foreach (self::EXPECTED as $slug => [$polarity, $bitmap, $vector]) {
            $product = Product::where('slug', $slug)->first();
            $this->assertNotNull($product, "Produkt {$slug} muss geseedet sein");
            $s = $product->configurator_settings;

            $this->assertSame($polarity, $s['xtool_photo_substrate_polarity'] ?? null, "{$slug}: Polarität");

            [$bFocus, $bPower, $bDot, $bFreq, $bDpi, $bPasses] = $bitmap;
            $this->assertEqualsWithDelta($bFocus, (float) $s['xtool_bitmap_focus_mm'], 0.001, "{$slug}: Bitmap-Fokus");
            $this->assertSame($bPower, (int) $s['xtool_bitmap_power_percent'], "{$slug}: Bitmap-Leistung");
            $this->assertSame($bDot, (int) $s['xtool_bitmap_dot_duration_us'], "{$slug}: Bitmap-Punktdauer");
            $this->assertSame($bFreq, (int) $s['xtool_bitmap_frequency_khz'], "{$slug}: Bitmap-Frequenz");
            $this->assertSame($bDpi, (int) $s['xtool_bitmap_dpi'], "{$slug}: Bitmap-DPI");
            $this->assertSame($bPasses, (int) $s['xtool_bitmap_passes'], "{$slug}: Bitmap-Pässe");

            // Kompatible Top-Level-Schlüssel = Bitmap-Werte (Schritt 1 / xTool Studio Bild-Element)
            $this->assertEqualsWithDelta($bFocus, (float) $s['xtool_focus_mm'], 0.001, "{$slug}: xtool_focus_mm");
            $this->assertSame($bPower, (int) $s['xtool_power_percent'], "{$slug}: xtool_power_percent");
            $this->assertSame($bDot, (int) $s['xtool_dot_duration_us'], "{$slug}: xtool_dot_duration_us");
            $this->assertSame($bDpi, (int) $s['xtool_dpi'], "{$slug}: xtool_dpi");
            $this->assertSame($bFreq, (int) $s['xtool_frequency_khz'], "{$slug}: xtool_frequency_khz");
            $this->assertSame($bPasses, (int) $s['xtool_passes'], "{$slug}: xtool_passes");

            [$vFocus, $vPower, $vSpeed, $vFreq, $vLpc, $vPasses] = $vector;
            $this->assertEqualsWithDelta($vFocus, (float) $s['xtool_vector_focus_mm'], 0.001, "{$slug}: Vektor-Fokus");
            $this->assertSame($vPower, (int) $s['xtool_vector_power_percent'], "{$slug}: Vektor-Leistung");
            $this->assertSame($vSpeed, (int) $s['xtool_vector_speed_mms'], "{$slug}: Vektor-Speed");
            $this->assertSame($vFreq, (int) $s['xtool_vector_frequency_khz'], "{$slug}: Vektor-Frequenz");
            $this->assertSame($vLpc, (int) $s['xtool_vector_lpc'], "{$slug}: Vektor-L/cm");
            $this->assertSame($vPasses, (int) $s['xtool_vector_passes'], "{$slug}: Vektor-Pässe");
            $this->assertSame($vSpeed, (int) $s['xtool_speed_mms'], "{$slug}: xtool_speed_mms");
            $this->assertSame($vLpc, (int) $s['xtool_lpc'], "{$slug}: xtool_lpc");
            $this->assertStringContainsString("{$vPower} % · {$vSpeed} mm/s · {$vLpc} Linien/cm · {$vFreq} kHz", (string) $s['xtool_vector_note']);

            // Seeder, Schritt-1-Katalog und Migration sind 1:1 synchron
            $fromCatalog = XtoolLaserCockpitService::buildProductLaserSettingsFromHandbook($slug);
            foreach ($fromCatalog as $key => $value) {
                if (in_array($key, ['xtool_engrave_mode', 'xtool_material', 'xtool_process_mode', 'xtool_equipment_key'], true)) {
                    continue;
                }
                $this->assertEquals($value, $s[$key] ?? null, "{$slug}: {$key} weicht vom Handbuch-Katalog ab");
            }
        }

        // Seelen-Pfote: Tiefschwarz-Prio-1-Preset (PDF 1 Pos. 01/02) im Schritt-1-Katalog wählbar
        $pfote = XtoolLaserCockpitService::getHandbookProductProfile('seelen-pfote-hundemarke');
        $tief = $pfote['presets']['pdf1_tiefschwarz_100khz'];
        $this->assertSame([3.8, 95, 25, 100, 1000], [$tief['vector']['focus_mm'], $tief['vector']['power_percent'], $tief['vector']['speed_mms'], $tief['vector']['frequency_khz'], $tief['vector']['lpc']]);
        $this->assertSame([3.6, 95, 1100, 100, 1270], [$tief['bitmap']['focus_mm'], $tief['bitmap']['power_percent'], $tief['bitmap']['dot_duration_us'], $tief['bitmap']['frequency_khz'], $tief['bitmap']['dpi']]);

        $kristall = XtoolLaserCockpitService::getHandbookProductProfile('seelen-kristall');
        $this->assertSame('inner_3d', $kristall['presets']['inner_3d_subsurface']['equipment_key']);
        $this->assertSame('Jarvis', $kristall['presets']['inner_3d_subsurface']['bitmap']['bitmap_mode']);

        $options = XtoolLaserCockpitService::getStepOnePresetOptions(Product::where('slug', 'seelen-anhaenger')->first());
        $keys = array_column($options, 'key');
        $this->assertContains('pdf2_silber_kontrast', $keys);
        $this->assertContains('naturschiefer', $keys);
        $this->assertContains('messing_poliert', $keys);
    }

    #[Test]
    public function it_syncs_existing_products_non_destructively_and_idempotently_via_migration(): void
    {
        $product = Product::create([
            'name'     => 'Der Seelenanhänger',
            'slug'     => 'seelen-anhaenger',
            'type'     => 'physical',
            'status'   => 'active',
            'price'    => 4990,
            'quantity' => 7,
            'width'    => 45,
            'height'   => 40,
            'configurator_settings' => [
                'xtool_plane_width'     => 45.0,
                'xtool_plane_height'    => 40.0,
                'xtool_contour_shape'   => 'heart_anhaenger',
                'area_top'              => 54.19,
                'xtool_material'        => '0,2 mm Aluminiumlegierung',
                'xtool_engrave_mode'    => 'Zweidirektional',
                'xtool_focus_mm'        => 19.9,
                'xtool_power_percent'   => 90,
                'xtool_dot_duration_us' => 300,
                'xtool_dpi'             => 500,
                'xtool_vector_note'     => '',
            ],
        ]);

        $migration = require database_path('migrations/2026_11_17_160000_sync_validated_xtool_uv_handbook_laser_values.php');
        $migration->up();

        $fresh = $product->fresh();
        $s = $fresh->configurator_settings;
        $this->assertSame(4990, (int) $fresh->price);
        $this->assertSame(7, (int) $fresh->quantity);
        $this->assertEquals(45.0, $s['xtool_plane_width']);
        $this->assertSame('heart_anhaenger', $s['xtool_contour_shape']);
        $this->assertEquals(54.19, $s['area_top']);
        $this->assertSame('Zweidirektional', $s['xtool_engrave_mode'], 'Bestehender Gravurmodus bleibt erhalten');
        $this->assertSame(85, (int) $s['xtool_power_percent']);
        $this->assertSame(280, (int) $s['xtool_dot_duration_us']);
        $this->assertSame(600, (int) $s['xtool_dpi']);
        $this->assertSame(70, (int) $s['xtool_vector_power_percent']);
        $this->assertSame(450, (int) $s['xtool_vector_speed_mms']);
        $this->assertSame('white_on_dark', $s['xtool_photo_substrate_polarity']);

        // Idempotent: zweiter Lauf ändert nichts mehr
        $before = $fresh->getRawOriginal('configurator_settings');
        $beforeUpdated = (string) $fresh->updated_at;
        $this->travel(5)->minutes();
        $migration->up();
        $again = $product->fresh();
        $this->assertSame($before, $again->getRawOriginal('configurator_settings'));
        $this->assertSame($beforeUpdated, (string) $again->updated_at);
    }

    #[Test]
    public function it_exports_separate_photo_bitmap_and_text_vector_layers_with_knockout_halo_on_seelen_anhaenger(): void
    {
        $this->seededProducts();
        $product = Product::where('slug', 'seelen-anhaenger')->firstOrFail();

        // Shop-Koordinaten innerhalb der Gravurfläche des Herzens (area_top 54,19 % · area_height 40,33 %)
        $item = $this->createItem($product, [
            'logos' => [$this->photoLogo($this->syntheticPortraitPng('rgb'), 50.0, 72.0, 220.0)],
            'texts' => [[
                'text' => 'Mama & Papa', 'font' => 'Montserrat', 'align' => 'center',
                'x' => 50.0, 'y' => 76.0, 'size' => 1.4, 'rotation' => 0,
            ]],
        ]);

        $payload = app(FileDownloadService::class)->buildLaserDesignPayload($item, 'front', true);
        $this->assertNotEmpty($payload['photoLayerPngBytes']);
        $this->assertNotEmpty($payload['vectorLayerPngBytes']);
        $this->assertSame('halftone', $payload['photoLayerInfo']['class']);
        $this->assertSame('white_on_dark', $payload['photoLayerInfo']['polarity']);
        $this->assertGreaterThan(50, $payload['textKnockoutPixels'], 'Lesbarkeits-Schutzsaum muss im Foto freigestanzt werden');

        // Knockout: Unter jedem Text-Pixel lasert das Foto-Layer nicht (Gray 255), das Vektor-Layer feuert dort
        $photoGd = imagecreatefromstring($payload['photoLayerPngBytes']);
        $vectorGd = imagecreatefromstring($payload['vectorLayerPngBytes']);
        $glyphPx = 0;
        $photoFiresUnderGlyph = 0;
        for ($y = 0; $y < imagesy($vectorGd); $y++) {
            for ($x = 0; $x < imagesx($vectorGd); $x++) {
                $v = imagecolorat($vectorGd, $x, $y);
                if ((($v >> 24) & 0x7F) < 40 && ($v & 0xFF) < 60) {
                    $glyphPx++;
                    $p = imagecolorat($photoGd, $x, $y);
                    if ((($p >> 24) & 0x7F) < 120 && ($p & 0xFF) < 250) {
                        $photoFiresUnderGlyph++;
                    }
                }
            }
        }
        $this->assertGreaterThan(100, $glyphPx);
        $this->assertSame(0, $photoFiresUnderGlyph, 'Foto darf unter der Schrift nicht lasern');
        imagedestroy($photoGd);
        imagedestroy($vectorGd);

        $xcs = app(XtoolLaserCockpitService::class)->generateXcsProjectBytes($item, 'front', 100.0, 100.0, 0.0);
        $this->assertTrue($xcs['is_multi_layer']);
        $zip = $this->openZip($xcs['bytes']);
        $project = json_decode((string) $zip->getFromName('project.json'), true);
        $canvasId = $project['activeCanvasId'];
        $canvas = json_decode((string) $zip->getFromName("canvases/{$canvasId}.json"), true);
        $displays = json_decode((string) $zip->getFromName("canvases/{$canvasId}/displays-0.json"), true)['displays'];

        $this->assertCount(3, $displays);
        $this->assertCount(3, $canvas['layerData']);
        $bitmaps = array_values(array_filter($displays, fn ($d) => $d['type'] === 'BITMAP'));
        $this->assertCount(2, $bitmaps);
        [$photoDisplay, $vectorDisplay] = $bitmaps;

        // 1:1 Geometrie-Parität beider Layer (Hundertstel-Millimeter)
        foreach (['x', 'y', 'angle', 'width', 'height', 'offsetX', 'offsetY'] as $k) {
            $this->assertEqualsWithDelta($photoDisplay[$k], $vectorDisplay[$k], 0.005, "Layer-Geometrie {$k}");
        }
        $this->assertEquals($photoDisplay['scale'], $vectorDisplay['scale']);
        $this->assertSame('#000000', $photoDisplay['layerColor']);
        $this->assertSame('#1E40AF', $vectorDisplay['layerColor']);
        $this->assertNotSame($photoDisplay['resourcePath'], $vectorDisplay['resourcePath']);
        $this->assertNotFalse($zip->getFromName($vectorDisplay['resourcePath']));
        $this->assertNotFalse($zip->getFromName('seelenfunke/Laser_Foto_Jarvis_Vorschau.png'));

        $device = json_decode((string) $zip->getFromName('devices/device-GS009-CLASS-4-1.json'), true);
        $mode = $device['processing'][$canvasId]['modes']['LASER_PLANE'];
        $this->assertCount(2, $mode['profiles']);
        $this->assertCount(2, $mode['bindings']);
        $this->assertEqualsWithDelta(19.9, $mode['data']['thickness'], 0.001);

        $photoProfile = collect($mode['profiles'])->firstWhere('values.seelenfunkeLayer', 'photo_bitmap');
        $vectorProfile = collect($mode['profiles'])->firstWhere('values.seelenfunkeLayer', 'text_vector');
        $this->assertSame('BITMAP_ENGRAVING', $photoProfile['processingType']);
        $this->assertSame(85, $photoProfile['values']['power']);
        $this->assertSame(280, $photoProfile['values']['dotDuration']);
        $this->assertSame(600, $photoProfile['values']['dpi']);
        $this->assertSame(40, $photoProfile['values']['frequency']);
        $this->assertSame('jarvis', $photoProfile['values']['bitmapMode']);
        $this->assertSame(1, $photoProfile['values']['repeat']);

        $this->assertSame('FILL_VECTOR_ENGRAVING', $vectorProfile['processingType']);
        $this->assertSame(70, $vectorProfile['values']['power']);
        $this->assertSame(450, $vectorProfile['values']['speed']);
        $this->assertSame(40, $vectorProfile['values']['frequency']);
        $this->assertSame(300, $vectorProfile['values']['density']);
        $this->assertSame(762, $vectorProfile['values']['dpi']);

        $bindingByDisplay = collect($mode['bindings'])->mapWithKeys(fn ($b) => [$b['displayIds'][0] => $b['baseProfileId']]);
        $this->assertSame($photoProfile['id'], $bindingByDisplay[$photoDisplay['id']]);
        $this->assertSame($vectorProfile['id'], $bindingByDisplay[$vectorDisplay['id']]);
        $zip->close();

        // .xf: zwei Bitmap-Blöcke (Jarvis-Foto + Text) mit getrennten Laserwerten
        $xf = app(XtoolLaserCockpitService::class)->generateXfPackageBytes($item, 'front', 100.0, 100.0, 0.0);
        $this->assertTrue($xf['is_multi_layer']);
        $this->assertCount(2, $xf['description']['processingParams']);
        $this->assertSame(85, $xf['description']['processingParams'][0]['power']);
        $this->assertSame(280, $xf['description']['processingParams'][0]['dotDuration']);
        $this->assertSame(70, $xf['description']['processingParams'][1]['power']);
        $this->assertSame(450, $xf['description']['processingParams'][1]['speed']);
        $this->assertSame(300, $xf['description']['processingParams'][1]['linesPerCm']);
        $this->assertSame(2, substr_count($xf['motion_gcode'], '# GS009-4 BITMAP HEAD'));
        $this->assertSame(1, substr_count($xf['motion_gcode'], '# GS009-4 HEAD'));
        $this->assertStringContainsString('"bitmapMode":"jarvis"', $xf['motion_gcode']);
        $this->assertStringContainsString('"bitmapMode":"threshold"', $xf['motion_gcode']);
        $this->assertStringContainsString('"speed":450', $xf['motion_gcode']);
    }

    #[Test]
    public function it_keeps_bright_portrait_faces_between_35_and_65_percent_laser_density_on_seelen_anhaenger(): void
    {
        $this->seededProducts();
        $product = Product::where('slug', 'seelen-anhaenger')->firstOrFail();
        $svc = app(FileDownloadService::class);

        $probeItem = $this->createItem($product, ['logos' => [$this->photoLogo($this->syntheticPortraitPng('probe'))], 'texts' => []]);
        $probePng = $svc->buildLaserDesignPayload($probeItem, 'front', true)['stageEngravePngBytes'];

        foreach (['rgb', 'upload'] as $mode) {
            $item = $this->createItem($product, ['logos' => [$this->photoLogo($this->syntheticPortraitPng($mode))], 'texts' => []]);
            $payload = $svc->buildLaserDesignPayload($item, 'front', true);
            $this->assertSame('halftone', $payload['photoLayerInfo']['class'], "{$mode}: Porträt muss als Halbton-Foto erkannt werden");

            foreach (['stageEngravePngBytes', 'photoLayerPngBytes'] as $key) {
                $m = $this->measureFaceDensity($payload[$key], $probePng);
                $this->assertGreaterThan(2000, $m['face_px']);
                $this->assertGreaterThanOrEqual(0.35, $m['face'], "{$mode}/{$key}: Gesicht zu dunkel ({$m['face']})");
                $this->assertLessThanOrEqual(0.65, $m['face'], "{$mode}/{$key}: Gesicht überstrahlt ({$m['face']})");
                $this->assertLessThan(0.05, $m['background'], "{$mode}/{$key}: dunkler Hintergrund muss ruhig schwarz bleiben");
            }
        }

        // Physik-Kurven der Polaritäten
        $this->assertSame(0.0, LaserPhotoPreprocessor::densityFromLuminance(0.10, 'white_on_dark'));
        $this->assertLessThanOrEqual(LaserPhotoPreprocessor::HIGHLIGHT_CAP, LaserPhotoPreprocessor::densityFromLuminance(1.0, 'white_on_dark'));
        $this->assertEqualsWithDelta(0.78 * pow(0.8, 1.9), LaserPhotoPreprocessor::densityFromLuminance(0.8, 'white_on_dark'), 0.0001);
        $this->assertSame(0.0, LaserPhotoPreprocessor::densityFromLuminance(0.95, 'dark_on_light'));
        $this->assertEqualsWithDelta(pow(0.8, 1.4) * 0.82, LaserPhotoPreprocessor::densityFromLuminance(0.2, 'dark_on_light'), 0.0001);
        $this->assertSame(56, LaserPhotoPreprocessor::grayFromDensity(0.78));
        $this->assertSame(255, LaserPhotoPreprocessor::grayFromDensity(0.0));
    }

    #[Test]
    public function it_maps_dark_on_light_photos_and_keeps_silhouettes_on_threshold(): void
    {
        $this->seededProducts();
        $herz = Product::where('slug', 'goldenes-seelen-herz')->firstOrFail();
        $svc = app(FileDownloadService::class);

        // Halbton auf Gold: helle Haut bleibt (fast) reines Gold, dunkler Grund wird dunkel graviert
        $item = $this->createItem($herz, ['logos' => [$this->photoLogo($this->syntheticPortraitPng('rgb'), 50.0, 60.0, 240.0)], 'texts' => []]);
        $payload = $svc->buildLaserDesignPayload($item, 'front', true);
        $this->assertSame('dark_on_light', $payload['photoLayerInfo']['polarity']);
        $this->assertSame('halftone', $payload['photoLayerInfo']['class']);
        $meanDensity = LaserPhotoPreprocessor::measureMeanDensity((string) $payload['photoLayerPngBytes']);
        $this->assertGreaterThan(0.05, $meanDensity);
        $this->assertLessThanOrEqual(LaserPhotoPreprocessor::DARK_ON_LIGHT_SCALE, $meanDensity);

        // Reine 1-Bit-Silhouette -> Schwellenwert im Foto-Layer
        $sil = imagecreatetruecolor(200, 200);
        imagealphablending($sil, false);
        imagesavealpha($sil, true);
        imagefill($sil, 0, 0, imagecolorallocatealpha($sil, 0, 0, 0, 127));
        imagefilledellipse($sil, 100, 100, 150, 150, imagecolorallocatealpha($sil, 0, 0, 0, 0));
        ob_start();
        imagepng($sil);
        $silPng = (string) ob_get_clean();
        imagedestroy($sil);

        $pfote = Product::where('slug', 'seelen-pfote-hundemarke')->firstOrFail();
        $silItem = $this->createItem($pfote, [
            'logos' => [$this->photoLogo($silPng, 41.0, 45.0, 150.0)],
            'texts' => [['text' => 'Luna', 'font' => 'Montserrat', 'align' => 'center', 'x' => 41.0, 'y' => 70.0, 'size' => 1.0, 'rotation' => 0]],
        ]);
        $silPayload = $svc->buildLaserDesignPayload($silItem, 'front', true);
        $this->assertSame('silhouette', $silPayload['photoLayerInfo']['class']);

        $xcs = app(XtoolLaserCockpitService::class)->generateXcsProjectBytes($silItem, 'front', 100.0, 100.0, 0.0);
        $zip = $this->openZip($xcs['bytes']);
        $project = json_decode((string) $zip->getFromName('project.json'), true);
        $device = json_decode((string) $zip->getFromName('devices/device-GS009-CLASS-4-1.json'), true);
        $mode = $device['processing'][$project['activeCanvasId']]['modes']['LASER_PLANE'];
        $photoProfile = collect($mode['profiles'])->firstWhere('values.seelenfunkeLayer', 'photo_bitmap');
        $vectorProfile = collect($mode['profiles'])->firstWhere('values.seelenfunkeLayer', 'text_vector');
        $this->assertSame('threshold', $photoProfile['values']['bitmapMode']);
        $this->assertSame(45, $photoProfile['values']['power']);
        $this->assertSame(210, $photoProfile['values']['dotDuration']);
        $this->assertSame(42, $vectorProfile['values']['power']);
        $this->assertSame(110, $vectorProfile['values']['speed']);
        $this->assertSame(65, $vectorProfile['values']['frequency']);
        $this->assertSame(330, $vectorProfile['values']['density']);
        $this->assertNull($xcs['photo_jarvis_png'], 'Silhouetten benötigen keine Jarvis-Vorschau');
        $zip->close();
    }
}

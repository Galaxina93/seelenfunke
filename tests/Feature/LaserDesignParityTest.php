<?php

namespace Tests\Feature;

use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\Export\FileDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LaserDesignParityTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): Admin
    {
        return Admin::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name'  => 'Steinhauer',
            'email'      => 'alina-parity-' . Str::random(5) . '@mein-seelenfunke.de',
            'password'   => bcrypt('secret123'),
        ]);
    }

    protected function createSeelenKristallProductWithLegacyBox(): Product
    {
        return Product::create([
            'name'        => 'Der Seelen Kristall',
            'slug'        => 'seelen-kristall',
            'type'        => 'physical',
            'status'      => 'active',
            'price'       => 6990,
            'width'       => 180,
            'height'      => 200,
            'length'      => 40,
            'weight'      => 920,
            'configurator_settings' => [
                'allow_logo'          => true,
                'overlay_type'        => 'plane',
                'area_shape'          => 'circle',
                'area_left'           => 16.0,
                'area_top'            => 11.6,
                'area_width'          => 68.0,
                'area_height'         => 68.0,
                'material_type'       => 'glass',
                'template_package'    => 'seelen-kristall',
                'xtool_plane_width'   => 161.5,
                'xtool_plane_height'  => 161.5,
                'xtool_contour_shape' => 'circle',
                'xtool_plate_box_pct' => [
                    'top'    => 6.48,
                    'left'   => 10.88,
                    'width'  => 78.24,
                    'height' => 78.24,
                ],
            ],
        ]);
    }

    protected function createSampleOrder(Product $product, array $configuration): OrderOrderItem
    {
        $order = OrderOrder::create([
            'order_number'     => 'ORD-2026-PARITY-' . Str::upper(Str::random(4)),
            'email'            => 'parity@example.com',
            'status'           => 'processing',
            'payment_status'   => 'paid',
            'payment_method'   => 'stripe',
            'billing_address'  => [
                'first_name'  => 'Max',
                'last_name'   => 'Mustermann',
                'address'     => 'Ring 1',
                'postal_code' => '38518',
                'city'        => 'Gifhorn',
                'country'     => 'DE',
            ],
            'shipping_address' => [
                'first_name'  => 'Max',
                'last_name'   => 'Mustermann',
                'address'     => 'Ring 1',
                'postal_code' => '38518',
                'city'        => 'Gifhorn',
                'country'     => 'DE',
            ],
            'subtotal_price'   => 6990,
            'tax_amount'       => 1116,
            'shipping_price'   => 0,
            'total_price'      => 6990,
        ]);

        return OrderOrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'quantity'      => 1,
            'unit_price'    => 6990,
            'tax_rate'      => 19.0,
            'total_price'   => 6990,
            'configuration' => $configuration,
        ]);
    }

    #[Test]
    public function it_renders_vector_symbols_above_raster_photos_by_zindex_with_arc_cutouts_and_148mm_facet_calibration(): void
    {
        $this->actingAs($this->createAdmin(), 'admin');
        $product = $this->createSeelenKristallProductWithLegacyBox();

        // Synthetisches Alpha-maskiertes Porträtfoto (dunkle Stirnpartie gdAlpha=35 -> Grauwert ~70)
        $photo = imagecreatetruecolor(160, 144);
        imagealphablending($photo, false);
        imagesavealpha($photo, true);
        $darkTone = imagecolorallocatealpha($photo, 0, 0, 0, 35);
        imagefill($photo, 0, 0, $darkTone);
        ob_start();
        imagepng($photo);
        $photoPng = (string) ob_get_clean();
        imagedestroy($photo);

        $item = $this->createSampleOrder($product, [
            'logos' => [
                [
                    'x'        => 50.0,
                    'y'        => 20.6684,
                    'url'      => '/storage/produkte/configurator/vectors/Feiertage/weihnachtsbaum.svg',
                    'value'    => 'vectors/Feiertage/weihnachtsbaum.svg',
                    'size'     => 80.0,
                    'type'     => 'vector',
                    'zIndex'   => 31,
                    'rotation' => 0,
                ],
                [
                    'x'        => 50.0,
                    'y'        => 50.7658,
                    'url'      => 'data:image/png;base64,' . base64_encode($photoPng),
                    'size'     => 432.13,
                    'type'     => 'saved',
                    'rotation' => 0,
                ],
            ],
            'texts' => [
                [
                    'x'        => 50.0,
                    'y'        => 64.875,
                    'font'     => 'Dancing Script',
                    'size'     => 1.25,
                    'text'     => "Frohe Weihnachten!\n- Familie Mustermann -",
                    'align'    => 'center',
                    'curve'    => 0,
                    'rotation' => 0,
                ],
            ],
        ]);

        $svc = app(FileDownloadService::class);
        $payload = $svc->buildLaserDesignPayload($item, 'front', true);

        $this->assertEqualsWithDelta(161.5, $payload['widthMm'], 0.01);
        $this->assertEqualsWithDelta(161.5, $payload['heightMm'], 0.01);
        $this->assertSame(1, substr_count($payload['ghostContourDPath'], 'M '));

        // Kalibrierte Innenfläche vor dem ~11,0 mm breiten Kristall-Facettenkranz:
        // 68.0 / 82.00 = 82.93 % (= Ø 133,93 mm Arbeitskreis konzentrisch im Ø 161,5 mm Außenrahmen -> 13,79 mm Randabstand je Seite)
        $polyXs = array_column($payload['workingAreaPolygonPct'], 'x');
        $polyYs = array_column($payload['workingAreaPolygonPct'], 'y');
        $spanPct = max($polyXs) - min($polyXs);
        $this->assertEqualsWithDelta(82.93, $spanPct, 0.35);
        $this->assertEqualsWithDelta(13.79, (min($polyXs) / 100.0) * 161.5, 0.20);
        $this->assertEqualsWithDelta(13.79, ((100.0 - max($polyXs)) / 100.0) * 161.5, 0.20);
        $this->assertEqualsWithDelta(13.79, (min($polyYs) / 100.0) * 161.5, 0.20);
        $this->assertEqualsWithDelta(13.79, ((100.0 - max($polyYs)) / 100.0) * 161.5, 0.20);

        $gd = imagecreatefromstring($payload['stageEngravePngBytes']);
        $this->assertNotFalse($gd);
        $w = imagesx($gd);
        $h = imagesy($gd);

        // 1. Weihnachtsbaum (zIndex=31) liegt ÜBER dem Porträtfoto (Default zIndex=11):
        // Im oberen Baum-Bereich (x=50 %, y=10..28 %) müssen tiefschwarze Baum-Pixel (R=0) UND helle Kontrast-Halo-Pixel (R>220) existieren
        $foundBlackTree = false;
        $foundTreeHalo = false;
        $foundBaubleHole = false;
        $cx = (int) round($w * 0.50);

        for ($y = (int) round($h * 0.10); $y <= (int) round($h * 0.28); $y++) {
            for ($x = (int) round($w * 0.42); $x <= (int) round($w * 0.58); $x++) {
                $c = imagecolorat($gd, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a === 0) {
                    $r = ($c >> 16) & 0xFF;
                    if ($r === 0) {
                        $foundBlackTree = true;
                    } elseif ($r > 220) {
                        $foundTreeHalo = true;
                    }
                }
            }
        }

        // Oberste Christbaumkugel (viewBox 512: Kugelmitte (256, 196) -> relativ zu (256, 256) = -60 Einheiten):
        // Muss als ausgestanztes Loch (R > 40 statt 0) innerhalb des schwarzen Baums erhalten bleiben!
        $mappedTreeY = ((20.6684 - 4.60) / 82.00) * $h;
        $mappedTreeW = (((80.0 * (100.0 / 82.00)) / 500.0) * $w);
        $baubleCenterY = (int) round($mappedTreeY + ((-60.0 / 512.0) * $mappedTreeW));
        $baubleColor = imagecolorat($gd, $cx, $baubleCenterY);
        if ((($baubleColor >> 16) & 0xFF) > 40) {
            $foundBaubleHole = true;
        }

        // Sternspitze oben am Baum (viewBox y=36 -> -220 Einheiten über Baumzentrum) muss ebenfalls schwarz sein
        $starCenterY = (int) round($mappedTreeY + ((-205.0 / 512.0) * $mappedTreeW));
        $foundStarBlack = false;
        for ($sy = $starCenterY - 6; $sy <= $starCenterY + 6; $sy++) {
            if (((imagecolorat($gd, $cx, max(0, $sy)) >> 16) & 0xFF) === 0) {
                $foundStarBlack = true;
                break;
            }
        }

        imagedestroy($gd);

        $this->assertTrue($foundBlackTree, 'Weihnachtsbaum (zIndex 31) muss über dem Porträtfoto (zIndex 11) sichtbar sein');
        $this->assertTrue($foundTreeHalo, 'Weihnachtsbaum über dunklem Foto benötigt einen hellen Kontrast-Halo');
        $this->assertTrue($foundBaubleHole, 'Christbaumkugeln (SVG Arc + evenodd) müssen ausgestanzt bleiben');
        $this->assertTrue($foundStarBlack, 'Stern an der Baumspitze muss vollständig gerendert werden');

        // 2. Auch im SVG-Export steht das <g> des Weihnachtsbaums hinter dem <image> des Fotos
        $svgResp = $svc->downloadLaserSvg($item, 'front');
        ob_start();
        $svgResp->sendContent();
        $svgXml = (string) ob_get_clean();

        $posImage = strpos($svgXml, '<image ');
        $posVectorPath = strpos($svgXml, '<path fill="#000000"');
        $this->assertNotFalse($posImage);
        $this->assertNotFalse($posVectorPath);
        $this->assertGreaterThan($posImage, $posVectorPath, 'Im SVG muss ein Vektor-Logo mit zIndex=31 nach dem Foto (zIndex=11) stehen');
    }

    #[Test]
    public function it_never_shifts_or_inflates_photo_position_when_one_side_has_a_gap(): void
    {
        $svc = new FileDownloadService();
        $poly = $svc->buildWorkingAreaPolygonPercent([
            'area_shape'  => 'circle',
            'area_left'   => 4.18,
            'area_top'    => 4.18,
            'area_width'  => 91.64,
            'area_height' => 91.64,
        ]);

        // Bild (200x180) mit präzisem schwarzem Referenzstreifen exakt in der Bildmitte (y = 89..91)
        $src = imagecreatetruecolor(200, 180);
        imagealphablending($src, false);
        imagesavealpha($src, true);
        $light = imagecolorallocatealpha($src, 0, 0, 0, 110);
        $darkMarker = imagecolorallocatealpha($src, 0, 0, 0, 0);
        imagefill($src, 0, 0, $light);
        imagefilledrectangle($src, 0, 89, 199, 91, $darkMarker);
        ob_start();
        imagepng($src);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($src);

        // Positionierung: Oben überstehend (y=46 %), unten ~5 % Abstand zur unteren Kreiskante
        // Früher hat der asymmetrische 6,5 %-Bleed das Bild hier aufgeblasen und nach unten verschoben!
        $clipped = $svc->clipAndNormalizeRasterLogoForLaser(
            $pngBytes,
            'png',
            [
                'x'        => 50.0,
                'y'        => 46.0,
                'size'     => 500.0,
                'rotation' => 0.0,
            ],
            161.5,
            161.5,
            $poly
        );

        $this->assertNotNull($clipped);
        $outGd = imagecreatefromstring($clipped['pngData']);
        $this->assertNotFalse($outGd);

        $outW = imagesx($outGd);
        $outH = imagesy($outGd);
        $midX = (int) round($outW * 0.5);

        $darkYs = [];
        for ($y = 0; $y < $outH; $y++) {
            $c = imagecolorat($outGd, $midX, $y);
            if ((($c >> 24) & 0x7F) >= 126) {
                continue;
            }
            $g = ($c >> 16) & 0xFF;
            if ($g < 60) {
                $darkYs[] = $y;
            }
        }
        imagedestroy($outGd);

        $this->assertNotEmpty($darkYs);
        $centerDarkY = (min($darkYs) + max($darkYs)) * 0.5;
        $markerYMm = $clipped['yMm'] + (($centerDarkY / max(1, $outH)) * $clipped['heightMm']);
        $expectedCenterYMm = 0.46 * 161.5;

        $this->assertEqualsWithDelta(
            $expectedCenterYMm,
            $markerYMm,
            0.35,
            'Die Bildmitte darf relativ zum Gravurbereich und Text niemals eigenmächtig verschoben werden'
        );
    }

    #[Test]
    public function it_renders_svg_circles_polygons_and_nonzero_winding_cutouts_without_stray_halo_on_empty_stage(): void
    {
        $product = Product::create([
            'name'        => 'Kristall Vektor Test',
            'slug'        => 'seelen-kristall-vektor-test',
            'type'        => 'physical',
            'status'      => 'active',
            'price'       => 6990,
            'width'       => 161,
            'height'      => 161,
            'configurator_settings' => [
                'overlay_type'       => 'plane',
                'area_shape'         => 'circle',
                'area_left'          => 16.0,
                'area_top'           => 11.6,
                'area_width'         => 68.0,
                'area_height'        => 68.0,
                'template_package'   => 'seelen-kristall',
                'xtool_plane_width'  => 161.5,
                'xtool_plane_height' => 161.5,
            ],
        ]);

        $item = $this->createSampleOrder($product, [
            'logos' => [
                [
                    'x'        => 50.0,
                    'y'        => 34.0,
                    'url'      => '/storage/produkte/configurator/vectors/Liebe/brautpaar.svg',
                    'value'    => 'vectors/Liebe/brautpaar.svg',
                    'size'     => 120.0,
                    'type'     => 'vector',
                    'rotation' => 0,
                ],
            ],
            'texts' => [],
        ]);

        $payload = app(FileDownloadService::class)->buildLaserDesignPayload($item, 'front', true);
        $gd = imagecreatefromstring($payload['stageEngravePngBytes']);
        $this->assertNotFalse($gd);

        $w = imagesx($gd);
        $h = imagesy($gd);

        // Auf einer Bühne ohne Hintergrundfoto dürfen keine weißen Halo-Pixel (R > 200 mit Alpha 0) entstehen
        $opaqueWhiteCount = 0;
        for ($y = 0; $y < $h; $y += 6) {
            for ($x = 0; $x < $w; $x += 6) {
                $c = imagecolorat($gd, $x, $y);
                if ((($c >> 24) & 0x7F) === 0 && (($c >> 16) & 0xFF) > 200) {
                    $opaqueWhiteCount++;
                }
            }
        }
        $this->assertSame(0, $opaqueWhiteCount, 'Ohne darunterliegendes Foto darf kein weißer Halo auf transparentem Grund entstehen');

        // Prüfe Kopf des Bräutigams (<circle cx="32.5" cy="13.5" r="10.1"/> in viewBox 0 0 128 128)
        $mappedX = 0.50 * $w;
        $mappedY = (((34.0 - 4.60) / 82.00)) * $h;
        $logoWidthPx = (((120.0 * (100.0 / 82.00)) / 500.0)) * $w;
        $scale = $logoWidthPx / 128.0;

        $groomHeadX = (int) round($mappedX + ((32.5 - 64.0) * $scale));
        $groomHeadY = (int) round($mappedY + ((13.5 - 64.0) * $scale));
        $headPixel = imagecolorat($gd, $groomHeadX, $groomHeadY);

        // Prüfe ausgestanztes Innenkleid der Braut (nonzero gegenläufiger Innenpfad um x=89, y=100)
        $dressInnerX = (int) round($mappedX + ((89.0 - 64.0) * $scale));
        $dressInnerY = (int) round($mappedY + ((105.0 - 64.0) * $scale));
        $dressInnerAlpha = (imagecolorat($gd, $dressInnerX, $dressInnerY) >> 24) & 0x7F;

        imagedestroy($gd);

        $this->assertSame(0, ($headPixel >> 24) & 0x7F, 'Kopf des Bräutigams (<circle>) muss opak gezeichnet werden');
        $this->assertSame(0, ($headPixel >> 16) & 0xFF, 'Kopf des Bräutigams (<circle>) muss tiefschwarz sein');
        $this->assertSame(127, $dressInnerAlpha, 'Innenfläche des Brautkleids (nonzero Gegenpfad) muss transparent ausgestanzt bleiben');

        // Cache-Salt v5-crystal-82pct + Kalibrierungs-Hash in OrderOverview verifizieren
        $overviewSource = (string) file_get_contents(app_path('Livewire/Shop/Order/OrderOverview.php'));
        $this->assertStringContainsString("'v5-crystal-82pct|'", $overviewSource);
        $this->assertStringContainsString('json_encode($calibration)', $overviewSource);
    }

    #[Test]
    public function it_aligns_outer_cyan_frame_with_inner_engrave_area_and_motion_gcode_for_crystal_rect_circle_and_custom_blanks(): void
    {
        $svc = app(FileDownloadService::class);
        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);

        // 1. Alle 3 historischen Seelen-Kristall DB-Boxen (78.24 %, 74.20 %, 71.54 %) müssen konsistent auf 82.00 % gehoben werden
        foreach ([
            ['left' => 10.88, 'top' => 6.48, 'width' => 78.24, 'height' => 78.24],
            ['left' => 12.90, 'top' => 8.50, 'width' => 74.20, 'height' => 74.20],
            ['left' => 14.23, 'top' => 9.83, 'width' => 71.54, 'height' => 71.54],
        ] as $legacyBox) {
            $crystal = $this->createSeelenKristallProductWithLegacyBox();
            $crystal->slug = 'seelen-kristall-' . Str::lower(Str::random(5));
            $cfg = $crystal->configurator_settings;
            $cfg['xtool_plate_box_pct'] = $legacyBox;
            $crystal->configurator_settings = $cfg;
            $crystal->save();

            $cal = $svc->resolvePhysicalBlankCalibration($crystal, $cfg, [], false);
            $this->assertEqualsWithDelta(161.5, $cal['widthMm'], 0.01);
            $this->assertEqualsWithDelta(161.5, $cal['heightMm'], 0.01);
            $this->assertEqualsWithDelta(82.00, $cal['plateBoxPct']['width'], 0.01);
            $this->assertEqualsWithDelta(9.00, $cal['plateBoxPct']['left'], 0.01);
            $this->assertEqualsWithDelta(4.60, $cal['plateBoxPct']['top'], 0.01);
        }

        // Vollflächiges dunkles Testbild auf Seelen-Kristall -> prüfen, dass AUCH im echten Laser-Job (motion.gcode)
        // kein einziger Laserpuls in den geschliffenen Facettenkranz (Radius > 67,2 mm um Rohlingmitte) feuert!
        $darkImg = imagecreatetruecolor(120, 120);
        imagealphablending($darkImg, false);
        imagesavealpha($darkImg, true);
        imagefill($darkImg, 0, 0, imagecolorallocatealpha($darkImg, 0, 0, 0, 0));
        ob_start();
        imagepng($darkImg);
        $darkPng = (string) ob_get_clean();
        imagedestroy($darkImg);

        $crystalProduct = $this->createSeelenKristallProductWithLegacyBox();
        $crystalProduct->slug = 'seelen-kristall-gcode-' . Str::lower(Str::random(4));
        $crystalCfg = $crystalProduct->configurator_settings;
        $crystalCfg['xtool_dpi'] = 100; // Schnell für G-Code-Geometrietest
        $crystalProduct->configurator_settings = $crystalCfg;
        $crystalProduct->save();

        $crystalItem = $this->createSampleOrder($crystalProduct, [
            'logos' => [[
                'x'        => 50.0,
                'y'        => 45.6,
                'url'      => 'data:image/png;base64,' . base64_encode($darkPng),
                'size'     => 500.0,
                'type'     => 'saved',
                'rotation' => 0,
            ]],
            'texts' => [],
        ]);

        $centerXMm = 100.0;
        $centerYMm = 100.0;
        $xf = $cockpit->generateXfPackageBytes($crystalItem, 'front', $centerXMm, $centerYMm, 0.0);

        // Blauer Rahmen / Framing-Border liegt auf der 161,5 mm Außenkante (100 - 80,75 = 19,25 mm bis 180,75 mm)
        $this->assertStringContainsString('G0X19.250Y19.250', $xf['border_gcode']);
        $this->assertStringContainsString('G1X180.750Y19.250', $xf['border_gcode']);

        // Laser-Gravurpulse (G1 ... S>0) müssen exakt im inneren Arbeitskreis (Radius <= 67,3 mm statt 80,75 mm) bleiben!
        $maxEngraveRadiusMm = 0.0;
        $curX = $centerXMm;
        $curY = $centerYMm;
        foreach (explode("\n", $xf['motion_gcode']) as $line) {
            if (preg_match('/^G([01])(?:X([0-9.]+))?(?:Y([0-9.]+))?(?:S(\d+))?/', trim($line), $m)) {
                $nextX = isset($m[2]) && $m[2] !== '' ? (float) $m[2] : $curX;
                $nextY = isset($m[3]) && $m[3] !== '' ? (float) $m[3] : $curY;
                $powerS = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
                if ($m[1] === '1' && $powerS > 0) {
                    $maxEngraveRadiusMm = max(
                        $maxEngraveRadiusMm,
                        hypot($curX - $centerXMm, $curY - $centerYMm),
                        hypot($nextX - $centerXMm, $nextY - $centerYMm)
                    );
                }
                $curX = $nextX;
                $curY = $nextY;
            }
        }

        $this->assertGreaterThan(65.5, $maxEngraveRadiusMm, 'Gravurkreis muss den inneren Arbeitsbereich (~66,96 mm Radius) ausfüllen');
        $this->assertLessThanOrEqual(67.5, $maxEngraveRadiusMm, 'Kein Laserpuls darf in den geschliffenen Facettenkranz (ab ~69,75 mm Radius) ragen');

        // 2. Rechteckiger Rohling (60 x 40 mm) mit 10 % / 15 % Sicherheitsrand
        $rectProduct = Product::create([
            'name'   => 'Schiefer Rechteck',
            'slug'   => 'schiefer-rechteck-test',
            'type'   => 'physical',
            'status' => 'active',
            'price'  => 2490,
            'width'  => 60,
            'height' => 40,
            'configurator_settings' => [
                'overlay_type'       => 'plane',
                'area_shape'         => 'rect',
                'area_left'          => 10.0,
                'area_top'           => 15.0,
                'area_width'         => 80.0,
                'area_height'        => 70.0,
                'xtool_plane_width'  => 60.0,
                'xtool_plane_height' => 40.0,
            ],
        ]);
        $rectPayload = $svc->buildLaserDesignPayload($this->createSampleOrder($rectProduct, [
            'logos' => [['x' => 50, 'y' => 50, 'url' => 'data:image/png;base64,' . base64_encode($darkPng), 'size' => 500, 'rotation' => 0]],
        ]), 'front', true);
        $this->assertEqualsWithDelta(60.0, $rectPayload['widthMm'], 0.01);
        $this->assertEqualsWithDelta(40.0, $rectPayload['heightMm'], 0.01);
        $this->assertStringContainsString('L 60.000 40.000', $rectPayload['ghostContourDPath']);
        $rectGd = imagecreatefromstring($rectPayload['stageEngravePngBytes']);
        $this->assertSame(127, (imagecolorat($rectGd, 2, (int) (imagesy($rectGd) * 0.5)) >> 24) & 0x7F, 'Linker Sicherheitsrand muss transparent bleiben');
        $this->assertSame(0, (imagecolorat($rectGd, (int) (imagesx($rectGd) * 0.5), (int) (imagesy($rectGd) * 0.5)) >> 24) & 0x7F, 'Innenfläche muss graviert werden');
        imagedestroy($rectGd);

        // 3. Generischer Custom/Herz-Rohling (50 x 40 mm, nicht-quadratisch!) mit freigestelltem transparentem overlay.png
        $overlayPath = 'produkte/test-custom-heart-overlay-' . Str::lower(Str::random(5)) . '.png';
        $ov = imagecreatetruecolor(400, 400);
        imagealphablending($ov, false);
        imagesavealpha($ov, true);
        imagefill($ov, 0, 0, imagecolorallocatealpha($ov, 0, 0, 0, 127));
        // Physischer Rohling belegt im 400x400 Overlay x=60..340 (15..85 % = 70 % Breite) und y=88..312 (22..78 % = 56 % Höhe -> Ratio 1.25 = 50/40)
        imagefilledrectangle($ov, 60, 88, 339, 311, imagecolorallocatealpha($ov, 180, 180, 180, 0));
        ob_start();
        imagepng($ov);
        \Illuminate\Support\Facades\Storage::disk('public')->put($overlayPath, (string) ob_get_clean());
        imagedestroy($ov);

        try {
            $customProduct = Product::create([
                'name'               => 'Custom Herz Schild',
                'slug'               => 'custom-herz-schild-test',
                'type'               => 'physical',
                'status'             => 'active',
                'price'              => 2990,
                'width'              => 50,
                'height'             => 40,
                'preview_image_path' => $overlayPath,
                'configurator_settings' => [
                    'overlay_type'       => 'plane',
                    'area_shape'         => 'custom',
                    'xtool_plane_width'  => 50.0,
                    'xtool_plane_height' => 40.0,
                    // Innere Gravurfläche mit Abstand zum Rohling-Rand (22..78 % x 28..72 %)
                    'custom_points'      => [
                        ['x' => 50.0, 'y' => 30.0],
                        ['x' => 78.0, 'y' => 28.0],
                        ['x' => 78.0, 'y' => 52.0],
                        ['x' => 50.0, 'y' => 72.0],
                        ['x' => 22.0, 'y' => 52.0],
                        ['x' => 22.0, 'y' => 28.0],
                    ],
                ],
            ]);

            $customPayload = $svc->buildLaserDesignPayload($this->createSampleOrder($customProduct, [
                'logos' => [['x' => 50, 'y' => 50, 'url' => 'data:image/png;base64,' . base64_encode($darkPng), 'size' => 500, 'rotation' => 0]],
            ]), 'front', true);

            // Nicht-quadratische Rohlingsmaße (50 x 40 mm) bleiben exakt erhalten
            $this->assertEqualsWithDelta(50.0, $customPayload['widthMm'], 0.01);
            $this->assertEqualsWithDelta(40.0, $customPayload['heightMm'], 0.01);
            // Blauer Außenrahmen umspannt volle 0..50 x 0..40 mm, während der innere Gravurbereich exakt 10 % Rand lässt ((22-15)/70 = 10 %)
            $this->assertStringContainsString('50.000', $customPayload['ghostContourDPath']);
            $this->assertStringContainsString('40.000', $customPayload['ghostContourDPath']);
            $cPolyXs = array_column($customPayload['workingAreaPolygonPct'], 'x');
            $this->assertEqualsWithDelta(10.0, min($cPolyXs), 0.6);
            $this->assertEqualsWithDelta(90.0, max($cPolyXs), 0.6);
        } finally {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($overlayPath);
        }
    }

    #[Test]
    public function it_clips_portrait_photo_and_mustermann_text_cleanly_inside_crystal_inner_zone_and_syncs_workshop_focus_cache(): void
    {
        $this->actingAs($this->createAdmin(), 'admin');
        $product = $this->createSeelenKristallProductWithLegacyBox();

        // Echtes 3:4 Hochkant-Porträtfoto (300 x 400 px, Alpha-maskiert wie Konfigurator-Upload)
        $portrait = imagecreatetruecolor(300, 400);
        imagealphablending($portrait, false);
        imagesavealpha($portrait, true);
        $midTone = imagecolorallocatealpha($portrait, 0, 0, 0, 45);
        imagefill($portrait, 0, 0, $midTone);
        ob_start();
        imagepng($portrait);
        $portraitPng = (string) ob_get_clean();
        imagedestroy($portrait);

        // Fall A: Kreisfüllendes 3:4 Hochkant-Porträt + Weihnachtstext auf Der Seelen Kristall
        $itemFull = $this->createSampleOrder($product, [
            'logos' => [[
                'x'        => 50.0,
                'y'        => 45.6,
                'url'      => 'data:image/png;base64,' . base64_encode($portraitPng),
                'size'     => 360.0,
                'type'     => 'saved',
                'rotation' => 0,
            ]],
            'texts' => [[
                'x'        => 50.0,
                'y'        => 64.875,
                'font'     => 'Dancing Script',
                'size'     => 1.25,
                'text'     => "Frohe Weihnachten!\n- Familie Mustermann -",
                'align'    => 'center',
                'curve'    => 0,
                'rotation' => 0,
            ]],
        ]);

        $svc = app(FileDownloadService::class);
        $payload = $svc->buildLaserDesignPayload($itemFull, 'front', true);
        $gd = imagecreatefromstring($payload['stageEngravePngBytes']);
        $this->assertNotFalse($gd);

        $w = imagesx($gd);
        $h = imagesy($gd);
        $cx = ($w - 1) * 0.5;
        $cy = ($h - 1) * 0.5;
        $pxPerMm = $w / 161.5;
        // Innerer Arbeitskreis hat Radius ~66,96 mm (Ø 133,93 mm); ab Radius >= 68,2 mm liegt der geschliffene Facettenkranz!
        $bevelStartRadiusPx = 68.2 * $pxPerMm;
        $innerTestRadiusPx = 63.0 * $pxPerMm;

        $opaquePixelsOnBevel = 0;
        for ($y = 0; $y < $h; $y += 3) {
            for ($x = 0; $x < $w; $x += 3) {
                if (hypot($x - $cx, $y - $cy) >= $bevelStartRadiusPx) {
                    $alpha = (imagecolorat($gd, $x, $y) >> 24) & 0x7F;
                    if ($alpha < 127) {
                        $opaquePixelsOnBevel++;
                    }
                }
            }
        }
        $this->assertSame(0, $opaquePixelsOnBevel, 'Außerhalb der inneren Gravurzone (auf dem Facettenrand) müssen 100 % aller Pixel transparent sein');

        // In allen 8 Himmelsrichtungen knapp innerhalb des Arbeitskreises (r = 63 mm) ist das kreisfüllende Porträt lückenlos opak (keine senkrechte Schnittkante)
        for ($dir = 0; $dir < 8; $dir++) {
            $ang = ($dir * M_PI) / 4.0;
            $px = (int) round($cx + $innerTestRadiusPx * cos($ang));
            $py = (int) round($cy + $innerTestRadiusPx * sin($ang));
            $alpha = (imagecolorat($gd, $px, $py) >> 24) & 0x7F;
            $this->assertSame(0, $alpha, "Kreisfüllendes Hochkant-Porträt muss in Richtung {$dir} sauber bis an den Innenkreis reichen");
        }
        imagedestroy($gd);

        // SVG-Export wendet workingAreaClip aktiv auf die Zeichengruppe an
        $svgResp = $svc->downloadLaserSvg($itemFull, 'front');
        ob_start();
        $svgResp->sendContent();
        $svgXml = (string) ob_get_clean();
        $this->assertStringContainsString('<g clip-path="url(#workingAreaClip)">', $svgXml);

        // Mini-PC WorkshopFocusApiController nutzt exakt denselben v5-crystal-82pct + Kalibrierungs-Cache-Salt wie OrderOverview
        $focusSource = (string) file_get_contents(app_path('Http/Controllers/Api/WorkshopFocusApiController.php'));
        $this->assertStringContainsString("'v5-crystal-82pct|'", $focusSource);
        $this->assertStringContainsString('json_encode($calibration)', $focusSource);
    }
}

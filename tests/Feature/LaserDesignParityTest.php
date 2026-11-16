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

        // Kalibrierte Innenfacette: 68 / 74.20 = 91.64 % (~148,0 mm Durchmesser auf 161,5 mm Kristall)
        $polyXs = array_column($payload['workingAreaPolygonPct'], 'x');
        $spanPct = max($polyXs) - min($polyXs);
        $this->assertEqualsWithDelta(91.64, $spanPct, 0.35);

        $gd = imagecreatefromstring($payload['stageEngravePngBytes']);
        $this->assertNotFalse($gd);
        $w = imagesx($gd);
        $h = imagesy($gd);

        // 1. Weihnachtsbaum (zIndex=31) liegt ÜBER dem Porträtfoto (Default zIndex=11):
        // Im oberen Baum-Bereich (x=50 %, y=15..19 %) müssen tiefschwarze Baum-Pixel (R=0) UND helle Kontrast-Halo-Pixel (R>220) existieren
        $foundBlackTree = false;
        $foundTreeHalo = false;
        $foundBaubleHole = false;
        $cx = (int) round($w * 0.50);

        for ($y = (int) round($h * 0.06); $y <= (int) round($h * 0.24); $y++) {
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
        $mappedTreeY = ((20.6684 - 8.50) / 74.20) * $h;
        $mappedTreeW = (((80.0 * (100.0 / 74.20)) / 500.0) * $w);
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
        $mappedY = (((34.0 - 8.50) / 74.20)) * $h;
        $logoWidthPx = (((120.0 * (100.0 / 74.20)) / 500.0)) * $w;
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

        // Cache-Salt v3-parity in OrderOverview verifizieren
        $overviewSource = (string) file_get_contents(app_path('Livewire/Shop/Order/OrderOverview.php'));
        $this->assertStringContainsString("'v3-parity|'", $overviewSource);
    }
}

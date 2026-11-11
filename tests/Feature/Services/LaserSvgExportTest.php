<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\Export\FileDownloadService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaserSvgExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\Admin\Admin::factory()->create(), 'admin');
    }
    /**
     * Test xTool laser SVG export for Order ORD-2026-D6SCCX if present in DB.
     */
    public function test_laser_svg_export_for_ord_2026_d6sccx(): void
    {
        $order = OrderOrder::where('order_number', 'ORD-2026-D6SCCX')->first();

        if (!$order) {
            $order = new OrderOrder();
            $order->order_number = 'ORD-2026-D6SCCX';

            $product = new Product();
            $product->width = 100;
            $product->height = 100;

            $item = new OrderOrderItem();
            $item->id = 'test-ord-2026-d6sccx-item';
            $item->setRelation('order', $order);
            $item->setRelation('product', $product);
            $item->configuration = [
                'logos' => [
                    [
                        'url' => 'http://127.0.0.1/shop/product/configurator/vectors/baby_black.svg',
                        'x' => 50,
                        'y' => 50,
                        'size' => 120,
                        'rotation' => 0
                    ]
                ],
                'texts' => [
                    [
                        'text' => 'Baby Glück',
                        'x' => 50,
                        'y' => 85,
                        'size' => 1.2,
                        'font' => 'Arial'
                    ]
                ]
            ];
        } else {
            $item = $order->items()->first();
        }

        $this->assertNotNull($item, 'Order has at least one item');

        $downloadService = new FileDownloadService();
        $response = $downloadService->downloadLaserSvg($item, 'front');

        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $svgContent = ob_get_clean();

        $this->assertNotEmpty($svgContent);
        $this->assertStringContainsString('<svg', $svgContent);
        // The baby_black vector must be inlined as path, not a broken image tag pointing to localhost
        $this->assertStringContainsString('<path fill="#000000"', $svgContent);
        $this->assertStringNotContainsString('<image href="http://127.0.0.1/shop/product/configurator/vectors/baby_black.svg"', $svgContent, 'Vector should be inlined, not referenced as external image');
    }

    /**
     * Test synthetic OrderItem with vector fallback resolution and path inlining.
     */
    public function test_laser_svg_export_with_vector_fallback(): void
    {
        $order = new OrderOrder();
        $order->order_number = 'TEST-ORD-LASER';

        $product = new Product();
        $product->width = 100;
        $product->height = 100;

        $item = new OrderOrderItem();
        $item->id = 99999;
        $item->setRelation('order', $order);
        $item->setRelation('product', $product);

        // Configure a logo pointing to a vector file with an arbitrary host URL
        $item->configuration = [
            'front' => [
                'logos' => [
                    [
                        'url' => 'http://example.com/some/path/baby_black.svg',
                        'x' => 50,
                        'y' => 50,
                        'size' => 150,
                        'rotation' => 0
                    ]
                ],
                'texts' => [
                    [
                        'text' => 'Hallo Laser',
                        'x' => 50,
                        'y' => 80,
                        'size' => 1.5,
                        'font' => 'Arial',
                        'align' => 'center'
                    ]
                ]
            ]
        ];

        $downloadService = new FileDownloadService();
        $response = $downloadService->downloadLaserSvg($item, 'front');

        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $svgContent = ob_get_clean();

        $this->assertNotEmpty($svgContent);
        // The fallback resolver should have located baby_black.svg in configurator/vectors and inlined it
        $this->assertStringContainsString('<path fill="#000000"', $svgContent);
        $this->assertStringContainsString('Hallo Laser', $svgContent);
        // Ensure no red #FF0000 guide frame is emitted that xTool would engrave as a score line
        $this->assertStringNotContainsString('stroke="#FF0000"', $svgContent);
    }

    /**
     * Test that alpha-masked black PNGs from the configurator are converted to opaque RGB grayscale
     * so xTool Studio bitmap inversion works without turning blank or solid black.
     */
    public function test_laser_svg_converts_alpha_masked_png_to_opaque_rgb_grayscale(): void
    {
        $order = new OrderOrder();
        $order->order_number = 'TEST-ORD-GRAYSCALE';

        $product = new Product();
        $product->width = 100;
        $product->height = 100;

        // Create a 2x1 PNG mimicking applyLaserEffect: pixel(0,0) = black alpha 0 (dark), pixel(1,0) = black alpha 100 (bright)
        $img = imagecreatetruecolor(2, 1);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $darkPixel = imagecolorallocatealpha($img, 0, 0, 0, 0);
        $brightPixel = imagecolorallocatealpha($img, 0, 0, 0, 100);
        imagesetpixel($img, 0, 0, $darkPixel);
        imagesetpixel($img, 1, 0, $brightPixel);
        ob_start();
        imagepng($img);
        $rawPng = ob_get_clean();
        imagedestroy($img);

        $item = new OrderOrderItem();
        $item->id = 99998;
        $item->setRelation('order', $order);
        $item->setRelation('product', $product);
        $item->configuration = [
            'logos' => [
                [
                    'url' => 'data:image/png;base64,' . base64_encode($rawPng),
                    'x' => 50,
                    'y' => 50,
                    'size' => 100,
                    'rotation' => 0,
                ]
            ]
        ];

        $downloadService = new FileDownloadService();
        $response = $downloadService->downloadLaserSvg($item, 'front');

        ob_start();
        $response->sendContent();
        $svgContent = ob_get_clean();

        $this->assertStringNotContainsString('stroke="#FF0000"', $svgContent);
        $this->assertMatchesRegularExpression('/href="data:image\/png;base64,([^"]+)"/', $svgContent);
        preg_match('/href="data:image\/png;base64,([^"]+)"/', $svgContent, $m);
        $exportedPng = base64_decode($m[1]);
        $exportedImg = imagecreatefromstring($exportedPng);

        $c0 = imagecolorat($exportedImg, 0, 0);
        $c1 = imagecolorat($exportedImg, imagesx($exportedImg) - 1, 0);
        imagedestroy($exportedImg);

        // Left pixel (from source x=0) should be dark (R=0), right pixel (from source x=1) should be bright grayscale (R > 180) and both opaque (alpha=0)
        $this->assertEquals(0, ($c0 >> 16) & 0xFF);
        $this->assertGreaterThan(180, ($c1 >> 16) & 0xFF);
        $this->assertEquals(0, ($c1 >> 24) & 0x7F);
    }

    /**
     * Test that full-area photos are clipped to custom_points contour (transparent outside)
     * and overlaid texts (e.g. "Für Philip" in "Dancing Script") are baked into the laser bitmap.
     */
    public function test_laser_svg_and_png_clip_custom_polygon_and_bake_ttf_text_over_full_area_photo(): void
    {
        $order = new OrderOrder();
        $order->order_number = 'ORD-2026-HEARTCLIP';

        $product = new Product();
        $product->width = 40;
        $product->height = 40;
        $product->configurator_settings = [
            'area_shape' => 'custom',
            'custom_points' => [
                ['x' => 50, 'y' => 25],
                ['x' => 85, 'y' => 20],
                ['x' => 85, 'y' => 55],
                ['x' => 50, 'y' => 85],
                ['x' => 15, 'y' => 55],
                ['x' => 15, 'y' => 20],
            ],
        ];

        // Create a uniform mid-gray alpha-masked photo (alpha=64 -> gray ~128)
        $img = imagecreatetruecolor(80, 80);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $midPixel = imagecolorallocatealpha($img, 0, 0, 0, 64);
        imagefill($img, 0, 0, $midPixel);
        ob_start();
        imagepng($img);
        $rawPng = ob_get_clean();
        imagedestroy($img);

        $item = new OrderOrderItem();
        $item->id = 'test-heart-clip-item';
        $item->setRelation('order', $order);
        $item->setRelation('product', $product);
        $item->configuration = [
            'logos' => [
                [
                    'url' => 'data:image/png;base64,' . base64_encode($rawPng),
                    'x' => 50,
                    'y' => 50,
                    'size' => 500,
                    'rotation' => 0,
                ],
            ],
            'texts' => [
                [
                    'text' => 'Für Philip',
                    'x' => 50,
                    'y' => 45,
                    'size' => 1.8,
                    'font' => 'Dancing Script',
                    'align' => 'center',
                    'rotation' => 0,
                ],
            ],
        ];

        $downloadService = new FileDownloadService();
        $pngResp = $downloadService->downloadLaserPng($item, 'front');
        ob_start();
        $pngResp->sendContent();
        $clippedPngBytes = ob_get_clean();

        $this->assertNotEmpty($clippedPngBytes);
        $clippedGd = imagecreatefromstring($clippedPngBytes);
        $this->assertNotFalse($clippedGd);

        $w = imagesx($clippedGd);
        $h = imagesy($clippedGd);

        // Bottom-left and bottom-right corners of the bounding box lie outside the V-shaped bottom of the heart -> must be 100% transparent (GD alpha = 127)
        $cornerBottomLeftAlpha = (imagecolorat($clippedGd, 0, $h - 1) >> 24) & 0x7F;
        $cornerBottomRightAlpha = (imagecolorat($clippedGd, $w - 1, $h - 1) >> 24) & 0x7F;
        $this->assertSame(127, $cornerBottomLeftAlpha, 'Bottom-left corner outside heart polygon must be transparent');
        $this->assertSame(127, $cornerBottomRightAlpha, 'Bottom-right corner outside heart polygon must be transparent');

        // Scan central text band for baked black glyph pixels (R < 30) and white contrast halo pixels (R > 220)
        $foundBlackTextGlyph = false;
        $foundWhiteHalo = false;
        $yStart = (int) floor($h * 0.20);
        $yEnd = (int) ceil($h * 0.55);
        for ($y = $yStart; $y < $yEnd; $y++) {
            for ($x = (int) floor($w * 0.20); $x < (int) ceil($w * 0.80); $x++) {
                $c = imagecolorat($clippedGd, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a === 0) {
                    $r = ($c >> 16) & 0xFF;
                    if ($r < 30) {
                        $foundBlackTextGlyph = true;
                    } elseif ($r > 220) {
                        $foundWhiteHalo = true;
                    }
                }
            }
        }
        imagedestroy($clippedGd);

        $this->assertTrue($foundBlackTextGlyph, 'Text "Für Philip" must be baked as black laser glyphs into the clipped bitmap');
        $this->assertTrue($foundWhiteHalo, 'Text "Für Philip" must have a white contrast halo separating it from the photo');
    }

    /**
     * Test Symbol-Bibliothek vector path resolution with category subfolders.
     */
    public function test_symbol_library_subfolder_vector_resolution(): void
    {
        $service = app(\App\Services\ProductConfiguratorService::class);

        $this->assertEquals('Liebe/amor_pfeil.svg', $service->resolveCanonicalVectorRelPath('Liebe/amor_pfeil.svg'));
        $this->assertEquals('Liebe/amor_pfeil.svg', $service->resolveCanonicalVectorRelPath('amor_pfeil.svg'));
        $this->assertEquals('Liebe/amor_pfeil.svg', $service->resolveCanonicalVectorRelPath('vectors/Liebe/amor_pfeil.svg'));
        $this->assertNull($service->resolveCanonicalVectorRelPath('../.env'));

        $urlWithSubfolder = $service->resolveVectorUrl('Liebe/amor_pfeil.svg');
        $urlFromFlatName = $service->resolveVectorUrl('amor_pfeil.svg');

        $this->assertStringContainsString('/storage/produkte/configurator/vectors/Liebe/amor_pfeil.svg', $urlWithSubfolder);
        $this->assertStringContainsString('/storage/produkte/configurator/vectors/Liebe/amor_pfeil.svg', $urlFromFlatName);
        $this->assertNotNull($service->resolveVectorDiskPath('Liebe/amor_pfeil.svg'));
    }

    /**
     * Test Jahreszeiten-Tab in SystemShopConfig (auto mode switch, manual season selection, manual snow animation).
     */
    public function test_seasons_tab_and_manual_snow_activation_in_system_shop_config(): void
    {
        $cfgService = app(\App\Services\ConfiguratorService::class);
        $this->assertCount(4, $cfgService->getSeasonsDefinition());

        $component = \Livewire\Livewire::test(\App\Livewire\Shop\System\SystemShopConfig::class)
            ->assertStatus(200)
            ->assertSee('Jahreszeiten')
            ->assertSee('Die 4 Jahreszeiten')
            ->assertSee('Schnee (Schneefall)');

        // Switch "Automatisch" off and manually activate Winter (Schnee)
        $component->call('toggleSeasonAutoMode')
            ->assertSet('settings.season_auto_mode', false)
            ->call('selectManualSeason', 'winter')
            ->assertSet('settings.manual_active_season', 'winter')
            ->assertSet('settings.manual_active_animation', 'winter');

        $this->assertEquals('winter', $cfgService->getActiveSeasonKey());
        $activeTheme = $cfgService->getCurrentActiveTheme();
        $this->assertEquals('winter', $activeTheme['theme_key']);
        $this->assertTrue($activeTheme['is_manual'] ?? false);

        // Switch "Automatisch" back on
        $component->call('toggleSeasonAutoMode')
            ->assertSet('settings.season_auto_mode', true)
            ->assertSet('settings.manual_active_animation', '');
    }

    /**
     * Test that a full-area photo stopping ~0.8% short of the left/right heart lobes
     * receives automatic edge-bleed compensation so the exported bitmap reaches the
     * full polygon width without a vertical flat cut on the left and right.
     */
    public function test_full_area_photo_edge_bleed_prevents_vertical_cut_on_heart_lobes(): void
    {
        $product = Product::where('slug', 'seelen-anhaenger')->first();
        if (!$product) {
            $this->seed(\Database\Seeders\ProductSeeder::class);
            $product = Product::where('slug', 'seelen-anhaenger')->firstOrFail();
        }

        $productConfig = is_string($product->configurator_settings)
            ? json_decode($product->configurator_settings, true)
            : ($product->configurator_settings ?? []);

        $svc = new FileDownloadService();
        $poly = $svc->buildWorkingAreaPolygonPercent($productConfig);
        $xs = array_column($poly, 'x');
        $polyWidthPct = max($xs) - min($xs);

        // Create a 600x800 portrait photo (alpha-masked)
        $img = imagecreatetruecolor(60, 80);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $midPixel = imagecolorallocatealpha($img, 0, 0, 0, 64);
        imagefill($img, 0, 0, $midPixel);
        ob_start();
        imagepng($img);
        $rawPng = ob_get_clean();
        imagedestroy($img);

        // Size leaves ~1% gap on left/right inside the heart polygon (98% of polygon width)
        $slightlyShortSize = ($polyWidthPct * 0.98 / 100.0) * 500.0;

        $clipped = $svc->clipAndNormalizeRasterLogoForLaser(
            $rawPng,
            'png',
            [
                'x' => 50.0,
                'y' => 74.0,
                'size' => $slightlyShortSize,
                'rotation' => 0,
            ],
            50.0,
            50.0,
            $poly
        );

        $this->assertNotNull($clipped);
        $expectedWidthMm = ($polyWidthPct / 100.0) * 50.0;
        // With edge-bleed compensation, the exported bitmap spans the full width of the heart polygon
        $this->assertEqualsWithDelta($expectedWidthMm, $clipped['widthMm'], 0.15);
    }
}

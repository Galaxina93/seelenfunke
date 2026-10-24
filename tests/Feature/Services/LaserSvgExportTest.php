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
}

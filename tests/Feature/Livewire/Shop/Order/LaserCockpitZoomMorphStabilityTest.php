<?php

namespace Tests\Feature\Livewire\Shop\Order;

use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\Laser\XtoolLaserCockpitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class LaserCockpitZoomMorphStabilityTest extends TestCase
{
    use DatabaseTransactions;

    protected function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name'  => 'Steinhauer',
            'email'      => 'laser-morph-' . Str::random(8) . '@mein-seelenfunke.de',
            'password'   => Hash::make('secret123'),
        ]);
    }

    protected function createPhysicalLaserOrderWithItem(): array
    {
        $product = Product::create([
            'name'                    => 'Seelenanhaenger Morph Guard Test',
            'slug'                    => 'seelenanhaenger-morph-guard-' . Str::random(8),
            'type'                    => 'physical',
            'status'                  => 'active',
            'price'                   => 2990,
            'quantity'                => 5,
            'track_quantity'          => true,
            'continue_selling_when_out_of_stock' => false,
            'configurator_settings'   => [
                'area_width_mm'  => 41.0,
                'area_height_mm' => 36.5,
                'area_shape'     => 'heart',
                'xtool_material' => 'Eloxiertes Aluminium',
            ],
        ]);

        $order = OrderOrder::create([
            'order_number'    => 'ORD-MORPH-' . strtoupper(Str::random(6)),
            'email'           => 'kunde-morph@example.com',
            'status'          => 'processing',
            'payment_status'  => 'paid',
            'subtotal_price'  => 2990,
            'shipping_price'  => 0,
            'tax_amount'      => 477,
            'total_price'     => 2990,
            'billing_address' => [
                'first_name'  => 'Sarah',
                'last_name'   => 'Sonnenschein',
                'address'     => 'Testweg 1',
                'postal_code' => '38518',
                'city'        => 'Gifhorn',
                'country'     => 'DE',
            ],
        ]);

        $item = OrderOrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'quantity'      => 1,
            'unit_price'    => 2990,
            'total_price'   => 2990,
            'configuration' => [
                'texts' => [
                    [
                        'id'   => 't-1',
                        'text' => 'Kennengelernt 18.12.2007',
                        'x'    => 50,
                        'y'    => 55,
                        'size' => 18,
                    ],
                ],
                'laser_camera_light_mode' => 'normal',
            ],
        ]);

        return [$product, $order, $item];
    }

    #[Test]
    public function cockpit_persistence_actions_are_renderless_and_skip_full_html_morph(): void
    {
        foreach ([
            'updateLaserCoordinates',
            'setLaserCameraLightMode',
            'setLaserFramingMode',
            'copyOtherSideLaserDimensions',
            'nudgeLaserPosition',
        ] as $methodName) {
            $ref = new ReflectionMethod(OrderOverview::class, $methodName);
            $attrs = $ref->getAttributes(Renderless::class);
            $this->assertNotEmpty($attrs, "OrderOverview::{$methodName}() muss #[Renderless] sein, um Morph-Spruenge im Laser-Cockpit auszuschliessen.");
        }

        $admin = $this->createAdmin();
        [, $order, $item] = $this->createPhysicalLaserOrderWithItem();

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id);

        // 1. Lichtmodus auf Blendfrei umschalten: speichert in DB & State ohne HTML-Re-Render
        $component->call('setLaserCameraLightMode', 'anti_glare', $item->id)
            ->assertSet('laserCameraLightMode', 'anti_glare');

        $this->assertArrayNotHasKey(
            'html',
            $component->effects,
            'setLaserCameraLightMode darf keinen 390-KB-HTML-Morph ausloesen.'
        );
        $this->assertSame('anti_glare', $item->fresh()->configuration['laser_camera_light_mode'] ?? null);

        // 2. Koordinaten verschieben: speichert in DB, sendet ack-Event, loest keinen HTML-Morph aus
        $component->call('updateLaserCoordinates', $item->id, 102.35, 98.74, -1.25, 40.91, 36.50, 12)
            ->assertSet('laserCenterXMm', 102.35)
            ->assertSet('laserCenterYMm', 98.74)
            ->assertSet('laserAngleDeg', -1.25)
            ->assertDispatched('laser-cockpit-sync', function (string $name, array $params): bool {
                return ($params['source'] ?? null) === 'ack'
                    && (int) ($params['seq'] ?? 0) === 12
                    && abs((float) ($params['x'] ?? 0) - 102.35) < 0.01;
            });

        $this->assertArrayNotHasKey(
            'html',
            $component->effects,
            'updateLaserCoordinates darf keinen HTML-Morph ausloesen.'
        );

        $saved = $item->fresh()->configuration['laser_cockpit_coordinates']['front'] ?? [];
        $this->assertEqualsWithDelta(102.35, (float) ($saved['x_mm'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(98.74, (float) ($saved['y_mm'] ?? 0), 0.01);
    }

    #[Test]
    public function preview_png_cache_hash_remains_stable_across_coordinate_and_light_mode_saves(): void
    {
        [, , $item] = $this->createPhysicalLaserOrderWithItem();
        $item->load('product');

        $laserGuide = ['material' => 'Eloxiertes Aluminium'];
        $calibration = ['engraveWidthMm' => 41.0, 'engraveHeightMm' => 36.5, 'contourShape' => 'heart'];

        $hashInitial = OrderOverview::buildLaserCockpitPreviewCacheHash($item, 'front', $laserGuide, $calibration);

        // Koordinaten, Lichtmodus, Framing, Checkliste und updated_at aendern sich bei jedem Drag
        $conf = $item->configuration;
        $conf['laser_cockpit_coordinates'] = [
            'front' => ['x_mm' => 108.42, 'y_mm' => 94.15, 'angle_deg' => 2.5, 'w_mm' => 41.0, 'h_mm' => 36.5],
        ];
        $conf['laser_camera_light_mode'] = 'anti_glare';
        $conf['laser_framing_mode'] = 'rect';
        $conf['manufacturing_checklist'] = ['clean_done' => true];
        $conf['laser_active_profile_ids'] = ['front' => 'prof-1'];
        $item->configuration = $conf;
        $item->updated_at = now()->addMinutes(10);
        $item->saveQuietly();

        $hashAfterDrag = OrderOverview::buildLaserCockpitPreviewCacheHash($item->fresh('product'), 'front', $laserGuide, $calibration);
        $this->assertSame(
            $hashInitial,
            $hashAfterDrag,
            'PNG-Preview-Cache-Hash muss bei reinen Koordinaten-, Lichtmodus- oder Timestamp-Aenderungen 100 % stabil bleiben.'
        );

        // Echte Design- oder Materialaenderung muss den Cache sofort invalidieren
        $confChanged = $item->fresh()->configuration;
        $confChanged['texts'][0]['text'] = 'Neuer Gravurtext';
        $item->configuration = $confChanged;
        $item->saveQuietly();

        $hashAfterDesignEdit = OrderOverview::buildLaserCockpitPreviewCacheHash($item->fresh('product'), 'front', $laserGuide, $calibration);
        $this->assertNotSame($hashInitial, $hashAfterDesignEdit);

        $hashAfterMaterialEdit = OrderOverview::buildLaserCockpitPreviewCacheHash(
            $item->fresh('product'),
            'front',
            ['material' => 'Klarglas'],
            $calibration
        );
        $this->assertNotSame($hashAfterDesignEdit, $hashAfterMaterialEdit);
    }

    #[Test]
    public function cockpit_markup_isolates_zoom_stage_minimap_persists_xray_and_guards_ai_align_race(): void
    {
        $admin = $this->createAdmin();
        [, $order, $item] = $this->createPhysicalLaserOrderWithItem();

        $onlineState = false;
        $cockpitMock = \Mockery::mock(XtoolLaserCockpitService::class)->makePartial();
        $cockpitMock->shouldReceive('syncCameraFromHostOrLaser')
            ->andReturnUsing(function () use (&$onlineState): array {
                return [
                    'camera_url'   => '/storage/bestellungen/xtool/background.jpg?v=12345',
                    'updated_at'   => now()->format('d.m.Y H:i:s'),
                    'source'       => 'minipc_lan_relay',
                    'laser_online' => $onlineState,
                    'laser_ip'     => '192.168.188.150',
                ];
            });
        $cockpitMock->shouldReceive('detectBlankPositionOnBed')
            ->andReturnUsing(function () use (&$onlineState): array {
                $onlineState = true;

                return [
                    'detected'          => true,
                    'center_x_mm'       => 100.12,
                    'center_y_mm'       => 101.50,
                    'angle_deg'         => 0.0,
                    'engrave_width_mm'  => 40.91,
                    'engrave_height_mm' => 36.50,
                    'blank_width_mm'    => 45.0,
                    'blank_height_mm'   => 40.0,
                    'confidence'        => 94,
                    'camera_url'        => '/storage/bestellungen/xtool/background.jpg?v=99999',
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
            ->call('openDetail', $order->id);

        $htmlBefore = $component->html();
        preg_match('/<div x-data="([^"]+)"[^>]*wire:key="laser-cockpit-/s', $htmlBefore, $mBefore);
        $this->assertNotEmpty($mBefore[1] ?? '');

        // Interaktive Bereiche muessen mit wire:ignore gegen Livewire-Morphs geschuetzt sein
        $this->assertMatchesRegularExpression('/<div\s+wire:ignore\b(?:[^<>"]|"[^"]*")*data-cockpit-zoom-bar/s', $htmlBefore);
        $this->assertMatchesRegularExpression('/<div\s+wire:ignore\b(?:[^<>"]|"[^"]*")*data-cockpit-bed-stage/s', $htmlBefore);
        $this->assertMatchesRegularExpression('/<div\s+wire:ignore\b(?:[^<>"]|"[^"]*")*data-cockpit-zoom-minimap/s', $htmlBefore);

        // Slider-Sync, Röntgen-Blick-Persistenz & Race-/Scroll-Schutz im Markup verifizieren
        $this->assertStringContainsString('sf-cockpit-root', $htmlBefore);
        $this->assertStringContainsString('sf-cockpit-stepper', $htmlBefore);
        $this->assertStringContainsString('sf-cockpit-primary-actions', $htmlBefore);
        $cockpitBladeSource = (string) file_get_contents(resource_path('views/livewire/shop/order/order-offer-detail-partials/laser-cockpit-card.blade.php'));
        $this->assertStringNotContainsString('sm:flex-nowrap', $cockpitBladeSource);
        $this->assertStringContainsString('x-ref="zoomSlider"', $htmlBefore);
        $this->assertStringContainsString('value="1"', $htmlBefore);
        $this->assertStringContainsString('syncZoomSliderDom()', $htmlBefore);
        $this->assertStringContainsString('toggleXray()', $htmlBefore);
        $this->assertStringContainsString('xrayToggle: this.xrayToggle', $htmlBefore);
        $this->assertStringContainsString('selectCameraLightMode(', $htmlBefore);
        $this->assertStringContainsString('prepareCameraAlign()', $htmlBefore);
        $this->assertStringContainsString('userMovedDuringAlign', $htmlBefore);

        // Kamera & KI-Ausrichten wechselt online false -> true: x-data muss 100 % konstant bleiben
        $component->call('refreshLaserCameraAndAlign', 'front')
            ->assertDispatched('laser-cockpit-sync', function (string $name, array $params): bool {
                return ($params['source'] ?? null) === 'ai_align'
                    && str_contains((string) ($params['camera_url'] ?? ''), 'v=99999')
                    && abs((float) ($params['x'] ?? 0) - 100.12) < 0.01;
            });

        $htmlAfter = $component->html();
        preg_match('/<div x-data="([^"]+)"[^>]*wire:key="laser-cockpit-/s', $htmlAfter, $mAfter);
        $this->assertSame(
            $mBefore[1],
            $mAfter[1],
            'x-data Attribut darf sich auch bei laser_online-Wechsel in refreshLaserCameraAndAlign nicht aendern.'
        );

        // Keine KI-Gedankenstriche in den geaenderten Dateien
        foreach ([
            app_path('Livewire/Shop/Order/OrderOverview.php'),
            resource_path('views/livewire/shop/order/order-overview.blade.php'),
            resource_path('views/livewire/shop/order/order-offer-detail-content.blade.php'),
            resource_path('views/livewire/shop/order/order-offer-detail-partials/laser-cockpit-card.blade.php'),
        ] as $filePath) {
            $contents = (string) file_get_contents($filePath);
            $this->assertStringNotContainsString(' – ', $contents, "KI-Gedankenstrich in {$filePath}");
            $this->assertStringNotContainsString(' — ', $contents, "KI-Geviertstrich in {$filePath}");
        }
    }

    #[Test]
    public function order_steps_and_step_one_tabs_carry_distinct_wire_keys_to_prevent_morph_collision(): void
    {
        $admin = $this->createAdmin();
        [, $order, $item] = $this->createPhysicalLaserOrderWithItem();
        $order->status = 'shipped';
        $order->saveQuietly();

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('activeOrderStep', 3);

        $htmlStep3 = $component->html();
        $this->assertStringContainsString('wire:key="order-detail-panel-' . $order->id . '"', $htmlStep3);
        $this->assertStringContainsString('wire:key="order-step-3-' . $order->id . '"', $htmlStep3);
        $this->assertStringContainsString('wire:key="order-media-fallback-' . $order->id . '-step-3"', $htmlStep3);

        $component->set('activeOrderStep', 2);
        $htmlStep2 = $component->html();
        $this->assertStringContainsString('wire:key="order-step-2-' . $order->id . '"', $htmlStep2);

        $component->set('activeOrderStep', 1);
        $htmlStep1 = $component->html();
        $this->assertStringContainsString('wire:key="order-step-1-' . $order->id . '"', $htmlStep1);
        $this->assertStringContainsString('wire:key="order-step1-tab-cockpit-' . $order->id . '-' . $item->id . '"', $htmlStep1);
        $this->assertStringContainsString('wire:key="order-step1-tab-preview-' . $order->id . '-' . $item->id . '"', $htmlStep1);
        $this->assertStringContainsString('wire:key="order-media-fallback-' . $order->id . '-step-1"', $htmlStep1);
    }
}

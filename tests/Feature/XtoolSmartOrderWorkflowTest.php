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

class XtoolSmartOrderWorkflowTest extends TestCase
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
            'email'      => 'alina-smart-' . Str::random(5) . '@mein-seelenfunke.de',
            'password'   => bcrypt('secret123'),
        ]);
    }

    protected function createHeartPendantProduct(): Product
    {
        return Product::create([
            'name'   => 'Der Seelenanhänger',
            'slug'   => 'seelen-anhaenger',
            'type'   => 'physical',
            'status' => 'active',
            'price'  => 2990,
            'width'  => 30,
            'height' => 27,
            'length' => 2,
            'weight' => 22,
            'configurator_settings' => [
                'allow_personalization' => true,
                'personalization_mode'  => 'studio',
                'overlay_type'          => 'plane',
                'area_shape'            => 'custom',
                'area_left'             => 28.07,
                'area_top'              => 54.05,
                'area_width'            => 43.86,
                'area_height'           => 40.38,
                'xtool_plane_width'     => 28.0,
                'xtool_plane_height'    => 25.8,
                'xtool_equipment_key'   => 'flatbed_standard',
                'xtool_process_mode'    => 'Prozess auf flacher Oberfläche',
                'xtool_material'        => '0,2 mm Aluminiumlegierung',
                'xtool_focus_mm'        => 19.9,
                'xtool_dot_duration_us' => 280,
                'xtool_power_percent'   => 85,
                'xtool_dpi'             => 600,
                'xtool_passes'          => 1,
                'xtool_frequency_khz'   => 40,
                'xtool_bitmap_mode'     => 'Jarvis',
                'xtool_engrave_mode'    => 'Zweidirektional',
                'xtool_profiles'        => [
                    [
                        'id'              => 'prof_front_std',
                        'title'           => 'Weiß / Silber-Kontrast · Lamellenrost',
                        'is_default'      => true,
                        'equipment_key'   => 'flatbed_standard',
                        'process_mode'    => 'Prozess auf flacher Oberfläche',
                        'material'        => '0,2 mm Aluminiumlegierung',
                        'focus_mm'        => 19.9,
                        'power_percent'   => 85,
                        'dot_duration_us' => 280,
                        'speed_mms'       => 450,
                        'dpi'             => 600,
                        'passes'          => 1,
                        'frequency_khz'   => 40,
                        'bitmap_mode'     => 'Jarvis',
                        'engrave_mode'    => 'Zweidirektional',
                    ],
                    [
                        'id'              => 'prof_back_widmung',
                        'title'           => 'Rückseite Widmung & Feintext · Lamellenrost',
                        'is_default'      => false,
                        'equipment_key'   => 'flatbed_standard',
                        'process_mode'    => 'Prozess auf flacher Oberfläche',
                        'material'        => '0,2 mm Aluminiumlegierung',
                        'focus_mm'        => 19.9,
                        'power_percent'   => 55,
                        'dot_duration_us' => 300,
                        'speed_mms'       => 700,
                        'dpi'             => 600,
                        'passes'          => 1,
                        'frequency_khz'   => 40,
                        'bitmap_mode'     => 'Schwellenwert',
                        'engrave_mode'    => 'Zweidirektional',
                    ],
                ],
            ],
        ]);
    }

    protected function createOrderWithConfig(Product $product, array $configuration): array
    {
        $order = OrderOrder::create([
            'order_number'     => 'ORD-2026-' . Str::upper(Str::random(5)),
            'email'            => 'kunde@mein-seelenfunke.de',
            'status'           => 'processing',
            'payment_status'   => 'paid',
            'payment_method'   => 'stripe',
            'billing_address'  => [
                'first_name'  => 'Alina',
                'last_name'   => 'Steinhauer',
                'address'     => 'Carl-Goerdeler-Ring 26',
                'postal_code' => '38518',
                'city'        => 'Gifhorn',
                'country'     => 'DE',
            ],
            'shipping_address' => [
                'first_name'  => 'Alina',
                'last_name'   => 'Steinhauer',
                'address'     => 'Carl-Goerdeler-Ring 26',
                'postal_code' => '38518',
                'city'        => 'Gifhorn',
                'country'     => 'DE',
            ],
            'subtotal_price'   => 2990,
            'tax_amount'       => 477,
            'shipping_price'   => 0,
            'total_price'      => 2990,
        ]);

        $item = OrderOrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'quantity'      => 1,
            'unit_price'    => 2990,
            'tax_rate'      => 19.0,
            'total_price'   => 2990,
            'configuration' => $configuration,
        ]);

        return [$order, $item];
    }

    #[Test]
    public function contour_framing_generates_true_heart_polygon_while_rect_generates_four_corners(): void
    {
        $product = $this->createHeartPendantProduct();
        [, $item] = $this->createOrderWithConfig($product, [
            'texts' => [
                ['text' => 'Noah', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 55, 'size' => 1.2, 'rotation' => 0],
            ],
        ]);

        $cockpit = app(XtoolLaserCockpitService::class);

        $contourBuild = $cockpit->buildItemFramingGcode($item, 'front', 102.5, 98.2, 12.0, 28.0, 25.8, 'contour');
        $rectBuild = $cockpit->buildItemFramingGcode($item, 'front', 102.5, 98.2, 12.0, 28.0, 25.8, 'rect');

        $this->assertSame('contour', $contourBuild['framing_mode']);
        $this->assertSame('heart_anhaenger', $contourBuild['contour_shape']);
        $this->assertSame('rect', $rectBuild['framing_mode']);

        preg_match_all('/^G1X[-+]?\d+(?:\.\d+)?Y[-+]?\d+(?:\.\d+)?/m', $contourBuild['gcode'], $contourMoves);
        preg_match_all('/^G1X[-+]?\d+(?:\.\d+)?Y[-+]?\d+(?:\.\d+)?/m', $rectBuild['gcode'], $rectMoves);

        $this->assertGreaterThan(24, count($contourMoves[0]), 'Heart contour framing must emit multi-point polygon G1 segments.');
        $this->assertCount(4, $rectMoves[0], 'Rect framing must emit exactly 4 closed corner G1 segments.');
        $this->assertStringContainsString('# GS009-4 VECTOR HEAD', $contourBuild['gcode']);
    }

    #[Test]
    public function livewire_cockpit_persists_framing_mode_and_updates_live_border_gcode(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createHeartPendantProduct();
        [$order, $item] = $this->createOrderWithConfig($product, [
            'texts' => [
                ['text' => 'Noah', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 55, 'size' => 1.2, 'rotation' => 0],
            ],
        ]);

        $comp = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSee('1:1 Kontur')
            ->assertSee('Rechteck')
            ->assertSee('Rahmen leuchten');

        $comp->call('setLaserFramingMode', 'rect', $item->id, false)
            ->assertSet('laserFramingMode', 'rect');

        $this->assertSame('rect', $item->fresh()->configuration['laser_framing_mode'] ?? null);

        $comp->call('updateLaserCoordinates', $item->id, 114.25, 91.50, -5.5, 28.0, 25.8, 1);
        $comp->call('setLaserFramingMode', 'contour', $item->id, true)
            ->assertSet('laserFramingMode', 'contour');

        $this->assertSame('contour', $item->fresh()->configuration['laser_framing_mode'] ?? null);
        Storage::disk('public')->assertExists('bestellungen/xtool/latest_border.gcode');

        $savedBorder = (string) Storage::disk('public')->get('bestellungen/xtool/latest_border.gcode');
        preg_match_all('/^G1X[-+]?\d+(?:\.\d+)?Y[-+]?\d+(?:\.\d+)?/m', $savedBorder, $savedMoves);
        $this->assertGreaterThan(24, count($savedMoves[0]));
    }

    #[Test]
    public function gs009_preflight_sensor_mapper_validates_uv_lid_if2_lens_and_safety_polarities(): void
    {
        $healthy = XtoolLaserCockpitService::mapGs009PreflightStatus(true, [
            'uv_ready'     => '1',
            'gap_rk3562'   => 'off',
            'gap_mcu'      => 'off',
            'machine_lock' => 'on',
            'smoking_fan'  => 'install',
            'lens_type'    => 'big',
            'alarms'       => [],
        ], 'flatbed_standard');

        $this->assertTrue($healthy['checked']);
        $this->assertTrue($healthy['all_ready']);
        $this->assertTrue($healthy['uv_ready']);
        $this->assertTrue($healthy['lid_closed']);
        $this->assertTrue($healthy['exhaust_ready']);
        $this->assertTrue($healthy['lens_ok']);
        $this->assertTrue($healthy['safety_ok']);
        $this->assertEmpty($healthy['warnings']);
        $this->assertEmpty($healthy['blockers']);

        $warnAndBlock = XtoolLaserCockpitService::mapGs009PreflightStatus(true, [
            'uv_ready'     => '0',
            'gap_rk3562'   => 'on',
            'gap_mcu'      => 'off',
            'machine_lock' => 'off',
            'smoking_fan'  => 'remove',
            'lens_type'    => 'small',
            'alarms'       => ['E_Z_AXIS'],
        ], 'flatbed_standard');

        $this->assertFalse($warnAndBlock['all_ready']);
        $this->assertFalse($warnAndBlock['uv_ready']);
        $this->assertFalse($warnAndBlock['lid_closed']);
        $this->assertFalse($warnAndBlock['exhaust_ready']);
        $this->assertFalse($warnAndBlock['lens_ok']);
        $this->assertFalse($warnAndBlock['safety_ok']);
        $this->assertCount(4, $warnAndBlock['warnings']);
        $this->assertCount(2, $warnAndBlock['blockers']);

        $crystalLensOk = XtoolLaserCockpitService::mapGs009PreflightStatus(true, [
            'uv_ready'     => '1',
            'gap_rk3562'   => 'off',
            'gap_mcu'      => 'off',
            'machine_lock' => 'on',
            'smoking_fan'  => 'install',
            'lens_type'    => 'small',
            'alarms'       => [],
        ], 'crystal_inner_3d');

        $this->assertTrue($crystalLensOk['all_ready']);
        $this->assertTrue($crystalLensOk['lens_ok']);
    }

    #[Test]
    public function smart_side_taktgeber_switches_front_to_back_with_back_profile_and_then_to_shipping(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createHeartPendantProduct();
        [$order, $item] = $this->createOrderWithConfig($product, [
            'texts' => [
                ['text' => 'Noah', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 55, 'size' => 1.2, 'rotation' => 0],
            ],
            'texts_back' => [
                ['text' => 'Für immer geliebt', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 52, 'size' => 1.0, 'rotation' => 0],
            ],
        ]);

        $comp = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSet('laserCockpitSide', 'front')
            ->assertSet('activeOrderStep', 1);

        // 1. Vorderseite fertig -> schaltet automatisch auf Rückseite + lädt Rückseiten-Laserprofil (55 % / 700 mm/s)
        $comp->call('handleLaserSideEngravingCompleted', $item->id, 'front')
            ->assertSet('laserCockpitSide', 'back')
            ->assertSet('laserWorkflowStep', 1)
            ->assertSet('activeOrderStep', 1);

        $freshAfterFront = $item->fresh();
        $this->assertNotEmpty($freshAfterFront->configuration['laser_engraved_sides']['front'] ?? null);
        $this->assertSame(
            'prof_back_widmung',
            $freshAfterFront->configuration['laser_parameter_overrides']['back']['active_profile_id'] ?? null
        );
        $this->assertSame(
            55,
            (int) ($freshAfterFront->configuration['laser_parameter_overrides']['back']['power_percent'] ?? 0)
        );

        // 2. Rückseite fertig (keine offenen Sonderwünsche) -> hakt Artikel ab und springt automatisch in Schritt 2 (Versand)
        $comp->call('handleLaserSideEngravingCompleted', $item->id, 'back')
            ->assertSet('activeOrderStep', 2);

        $freshAfterBack = $item->fresh();
        $this->assertNotEmpty($freshAfterBack->configuration['laser_engraved_sides']['back'] ?? null);
        $this->assertTrue((bool) $freshAfterBack->is_completed);
    }

    #[Test]
    public function smart_taktgeber_pauses_at_step_one_when_unchecked_customer_note_exists(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createHeartPendantProduct();
        [$order, $item] = $this->createOrderWithConfig($product, [
            'notes' => 'Bitte ein kleines Kärtchen für Oma beilegen',
            'texts' => [
                ['text' => 'Oma', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 55, 'size' => 1.2, 'rotation' => 0],
            ],
        ]);

        $comp = Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->call('handleLaserSideEngravingCompleted', $item->id, 'front')
            ->assertSet('activeOrderStep', 1);

        $fresh = $item->fresh();
        $this->assertTrue((bool) ($fresh->configuration['manufacturing_checklist']['engraving'] ?? false));
        $this->assertFalse((bool) $fresh->is_completed, 'Item must stay open until customer note is checked.');

        // Sobald der Admin den Kundenwunsch abhakt, springt die Bestellung autonom weiter in Schritt 2
        $comp->call('toggleItemChecklistTask', $item->id, 'notes')
            ->assertSet('activeOrderStep', 2);
        $this->assertTrue((bool) $item->fresh()->is_completed);
    }

    #[Test]
    public function cockpit_expands_laser_params_in_single_card_and_eliminates_idle_parity_beep_loop(): void
    {
        $cockpitBlade = (string) file_get_contents(resource_path('views/livewire/shop/order/order-offer-detail-partials/laser-cockpit-card.blade.php'));
        $serviceSource = (string) file_get_contents(app_path('Services/Laser/XtoolLaserCockpitService.php'));

        // 1. Einheitliche Kachel für "Laserparameter anpassen" (klappt mit border-t in derselben Kachel auf)
        $this->assertStringContainsString('data-sf-laser-params-card', $cockpitBlade);
        $this->assertMatchesRegularExpression('/data-sf-laser-params-card[^>]*overflow-hidden/s', $cockpitBlade);
        $this->assertMatchesRegularExpression('/x-show="showLaserParams"[^>]*border-t/s', $cockpitBlade);

        // 2. Kein Hintergrund-Polling-Loop in init() und kein subtree/childList MutationObserver-Endlos-Loop
        $this->assertDoesNotMatchRegularExpression('/init\s*\(\s*\)\s*\{[^}]*startLiveWorkStatusPolling/s', $cockpitBlade);
        $this->assertStringNotContainsString('obs.observe(this.$el', $cockpitBlade);
        $this->assertStringContainsString("obs.observe(this.\$refs.cockpitBootPayload, { attributes: true, attributeFilter: ['data-boot'] })", $cockpitBlade);

        // 3. queryLiveLaserWorkStatus ruft NIEMALS /v1/user/parity auf (0 Verbindungs-Pieptöne bei Status-Abfragen)
        if (preg_match('/public function queryLiveLaserWorkStatus\(.*?\n    \}/s', $serviceSource, $m)) {
            $methodBody = preg_replace('#//.*$#m', '', $m[0]) ?? $m[0];
            $this->assertStringNotContainsString('/v1/user/parity', $methodBody);
        } else {
            $this->fail('queryLiveLaserWorkStatus method not found in XtoolLaserCockpitService.');
        }
    }
}

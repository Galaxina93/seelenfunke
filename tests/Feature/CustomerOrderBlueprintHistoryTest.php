<?php

namespace Tests\Feature;

use App\Livewire\Customer\CustomerOrdersComponent;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerOrderBlueprintHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    protected function createVerifiedCustomer(string $emailPrefix = 'kunde'): Customer
    {
        return Customer::create([
            'first_name'            => 'Laura',
            'last_name'             => 'Sonnenschein',
            'email'                 => $emailPrefix . '-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password'              => bcrypt('Secret123!'),
            'email_verified_at'     => now(),
            'needs_password_change' => false,
        ]);
    }

    protected function createPersonalizableProduct(): Product
    {
        return Product::create([
            'name'              => 'Der Seelenanhänger',
            'slug'              => 'seelen-anhaenger-' . Str::lower(Str::random(6)),
            'type'              => 'physical',
            'status'            => 'active',
            'is_personalizable' => true,
            'price'             => 3490,
            'width'             => 45,
            'height'            => 40,
            'length'            => 2,
            'weight'            => 32,
            'configurator_settings' => [
                'allow_personalization' => true,
                'personalization_mode'  => 'studio',
                'has_back_side'         => true,
                'overlay_type'          => 'plane',
                'area_shape'            => 'rect',
                'area_left'             => 15,
                'area_top'              => 15,
                'area_width'            => 70,
                'area_height'           => 70,
            ],
        ]);
    }

    protected function createTinyPng(): string
    {
        $img = imagecreatetruecolor(48, 48);
        $bg = imagecolorallocate($img, 250, 248, 245);
        $gold = imagecolorallocate($img, 197, 160, 89);
        imagefill($img, 0, 0, $bg);
        imagefilledrectangle($img, 10, 10, 38, 38, $gold);

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    #[Test]
    public function customer_orders_replaces_versiegelt_with_original_design_badge_when_no_admin_adjustment_occurred(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $customer = $this->createVerifiedCustomer('laura');
        $product = $this->createPersonalizableProduct();

        $snapFront = 'system/snapshots/original_front.png';
        Storage::disk('public')->put($snapFront, $this->createTinyPng());

        $order = OrderOrder::create([
            'customer_id'      => $customer->id,
            'order_number'     => 'SF-2026-8101',
            'email'            => $customer->email,
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

        $item = OrderOrderItem::create([
            'order_id'           => $order->id,
            'product_id'         => $product->id,
            'product_name'       => $product->name,
            'quantity'           => 1,
            'unit_price'         => 3490,
            'tax_rate'           => 19.0,
            'total_price'        => 3490,
            'config_fingerprint' => hash('sha256', 'original-customer-design'),
            'configuration'      => [
                'texts'         => [['text' => 'Für Oma Hilde', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 50, 'size' => 1.0, 'rotation' => 0]],
                'snapshot_path' => ['front' => $snapFront],
            ],
        ]);

        $component = Livewire::actingAs($customer, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('showOrder', $order->id)
            ->call('openPreview', $item->id);

        $html = $component->html();

        $this->assertStringNotContainsString('Versiegelt', $html);
        $this->assertStringNotContainsString('VERSIEGELT', $html);
        $this->assertStringContainsString('Original-Design hinterlegt', $html);
        $this->assertStringNotContainsString('Manufaktur-Feinschliff', $html);
    }

    #[Test]
    public function customer_orders_renders_manufaktur_feinschliff_and_clickable_blueprint_timeline_with_in_window_lightbox(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $customer = $this->createVerifiedCustomer('alina-kundin');
        $otherCustomer = $this->createVerifiedCustomer('fremd');
        $product = $this->createPersonalizableProduct();

        $v1Front = 'system/snapshots/bp_v1_front.png';
        $v2Front = 'system/snapshots/bp_v2_front.png';
        $v2Back = 'system/snapshots/bp_v2_back.png';
        $png = $this->createTinyPng();

        Storage::disk('public')->put($v1Front, $png);
        Storage::disk('public')->put($v2Front, $png);
        Storage::disk('public')->put($v2Back, $png);

        $order = OrderOrder::create([
            'customer_id'      => $customer->id,
            'order_number'     => 'SF-2026-8102',
            'email'            => $customer->email,
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

        $item = OrderOrderItem::create([
            'order_id'           => $order->id,
            'product_id'         => $product->id,
            'product_name'       => $product->name,
            'quantity'           => 1,
            'unit_price'         => 3490,
            'tax_rate'           => 19.0,
            'total_price'        => 3490,
            'config_fingerprint' => hash('sha256', 'v2-admin-adjusted'),
            'configuration'      => [
                'texts'                 => [['text' => 'Für immer im Herzen', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 52, 'size' => 0.95, 'rotation' => 0]],
                'texts_back'            => [['text' => '18.11.2026', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 50, 'size' => 0.85, 'rotation' => 0]],
                'snapshot_path'         => [
                    'front' => $v2Front,
                    'back'  => $v2Back,
                ],
                'configuration_history' => [
                    [
                        'version'            => 1,
                        'actor_type'         => 'customer',
                        'actor_label'        => 'Original-Entwurf beim Kauf',
                        'saved_at'           => '2026-11-18T14:13:00Z',
                        'config_fingerprint' => hash('sha256', 'v1-customer'),
                        'snapshot_path'      => [
                            'front' => $v1Front,
                        ],
                        'design_summary'     => [
                            'texts' => ['Fuer immer im Herzen'],
                        ],
                    ],
                ],
                'last_admin_adjustment' => [
                    'adjusted_at' => '2026-11-18T21:05:00Z',
                    'adjusted_by' => 'Mein-Seelenfunke Admin',
                    'version'     => 2,
                ],
            ],
        ]);

        $component = Livewire::actingAs($customer, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('showOrder', $order->id)
            ->call('openPreview', $item->id);

        $html = $component->html();

        // 1. Wort "Versiegelt" ist komplett verschwunden
        $this->assertStringNotContainsString('Versiegelt', $html);
        $this->assertStringNotContainsString('VERSIEGELT', $html);

        // 2. Schwarz-Gold-Badge "Manufaktur-Feinschliff" + exakter Hinweis mit Europe/Berlin Zeitstempel
        $this->assertStringContainsString('Manufaktur-Feinschliff', $html);
        $this->assertStringContainsString('Mein Seelenfunke Admin · Änderungsmaßnahme am 18.11.2026 um 22:05 Uhr durchgeführt', $html);

        // 3. Interaktive Blueprint-Zeitleiste mit Version 1, Version 2, Aktuell & Lightbox im selben Fenster (Vorder-/Rückseite)
        $this->assertStringContainsString('data-customer-blueprint-timeline', $html);
        $this->assertStringContainsString('data-blueprint-version-card="1"', $html);
        $this->assertStringContainsString('data-blueprint-version-card="2"', $html);
        $this->assertStringContainsString('Version 1', $html);
        $this->assertStringContainsString('Dein Original-Entwurf', $html);
        $this->assertStringContainsString('18.11.2026, 15:13 Uhr', $html);
        $this->assertStringContainsString('Version 2', $html);
        $this->assertStringContainsString('Aktuell', $html);
        $this->assertStringContainsString('18.11.2026, 22:05 Uhr', $html);
        $this->assertStringContainsString('data-customer-blueprint-lightbox', $html);
        $this->assertStringContainsString('Blueprint-Vergleich', $html);
        $this->assertStringContainsString('Vorderseite', $html);
        $this->assertStringContainsString('Rückseite', $html);
        $this->assertStringContainsString('bp_v1_front.png', $html);
        $this->assertStringContainsString('bp_v2_front.png', $html);
        $this->assertStringContainsString('bp_v2_back.png', $html);

        // 4. Mandanten-Isolation: Fremder Kunde darf weder Bestellung noch Preview öffnen
        Livewire::actingAs($otherCustomer, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('showOrder', $order->id)
            ->assertSet('selectedOrderId', null)
            ->call('openPreview', $item->id)
            ->assertSet('previewItemId', null);

        // 5. 0 KI-Gedankenstriche und 0 "Versiegelt" in allen Kunden-Bestelldateien
        $customerFiles = [
            app_path('Livewire/Customer/CustomerOrdersComponent.php'),
            resource_path('views/livewire/customer/customer-orders-component.blade.php'),
            resource_path('views/livewire/customer/partials/orders_section.blade.php'),
            base_path('tests/Feature/CustomerOrderBlueprintHistoryTest.php'),
        ];

        foreach ($customerFiles as $filePath) {
            $this->assertFileExists($filePath);
            $content = (string) file_get_contents($filePath);
            $this->assertSame(0, substr_count($content, " \u{2013} "), 'KI-Gedankenstrich gefunden in: ' . basename($filePath));
            $this->assertSame(0, substr_count($content, " \u{2014} "), 'Geviertstrich mit Leerzeichen gefunden in: ' . basename($filePath));
        }

        foreach ([
            resource_path('views/livewire/customer/customer-orders-component.blade.php'),
            resource_path('views/livewire/customer/partials/orders_section.blade.php'),
        ] as $viewPath) {
            $viewSrc = (string) file_get_contents($viewPath);
            $this->assertStringNotContainsString('Versiegelt', $viewSrc);
            $this->assertStringNotContainsString('VERSIEGELT', $viewSrc);
        }
    }

    #[Test]
    public function admin_can_edit_processing_order_item_in_live_configurator_and_records_blueprint_timeline(): void
    {
        $customer = $this->createVerifiedCustomer('admin-edit-kunde');
        $product = $this->createPersonalizableProduct();

        $admin = \App\Models\Admin\Admin::create([
            'first_name' => 'Alina',
            'last_name'  => 'Steinhauer',
            'email'      => 'admin-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password'   => bcrypt('AdminSecret123!'),
        ]);

        $v1Rel = 'bestellungen/order-snapshots/cust_orig_front.png';
        $png1x1 = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2ZkAAAAASUVORK5CYII=');
        Storage::disk('public')->put($v1Rel, $png1x1);

        $order = OrderOrder::create([
            'order_number'     => 'ORD-2026-ADMEDIT',
            'customer_id'      => $customer->id,
            'status'           => 'processing',
            'payment_status'   => 'paid',
            'email'            => $customer->email,
            'billing_address'  => ['first_name' => 'Laura', 'last_name' => 'Sonnenschein', 'address' => 'Hauptweg 7', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Laura', 'last_name' => 'Sonnenschein', 'address' => 'Hauptweg 7', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'subtotal_price'   => 3490,
            'tax_amount'       => 557,
            'shipping_price'   => 0,
            'total_price'      => 3490,
        ]);

        $item = OrderOrderItem::create([
            'order_id'           => $order->id,
            'product_id'         => $product->id,
            'product_name'       => $product->name,
            'quantity'           => 1,
            'unit_price'         => 3490,
            'tax_rate'           => 19.0,
            'total_price'        => 3490,
            'config_fingerprint' => hash('sha256', 'orig-v1'),
            'configuration'      => [
                'texts'                => [['id' => 't1', 'text' => 'Mike & Ema', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 50, 'size' => 1.0, 'rotation' => 0]],
                'texts_back'           => [],
                'logos'                => [],
                'logos_back'           => [],
                'snapshot_path'        => ['front' => $v1Rel],
                'manufacturing_checks' => ['cleaned' => true],
            ],
        ]);

        $newSnapshotDataUrl = 'data:image/png;base64,' . base64_encode($png1x1);

        Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class, [
                'product'  => $product,
                'context'  => 'order_edit',
                'cartItem' => $item,
            ])
            ->assertSet('config_confirmed', true)
            ->call('saveWithSnapshot', ['front' => $newSnapshotDataUrl], [
                'texts'      => [['id' => 't1', 'text' => 'Mike & Emma', 'font' => 'Montserrat', 'align' => 'center', 'x' => 50, 'y' => 50, 'size' => 1.0, 'rotation' => 0]],
                'logos'      => [],
                'texts_back' => [],
                'logos_back' => [],
            ])
            ->assertSet('saved', true);

        $item->refresh();
        $cfg = $item->configuration;

        // Alter Kunden-Snapshot bleibt für die Blueprint-Lightbox erhalten
        $this->assertTrue(Storage::disk('public')->exists($v1Rel));
        $this->assertSame('Mike & Emma', $cfg['texts'][0]['text'] ?? null);
        $this->assertTrue((bool) ($cfg['manufacturing_checks']['cleaned'] ?? false));
        $this->assertCount(1, $cfg['configuration_history'] ?? []);
        $this->assertSame(1, $cfg['configuration_history'][0]['version'] ?? null);
        $this->assertSame('customer', $cfg['configuration_history'][0]['actor_type'] ?? null);
        $this->assertSame($v1Rel, $cfg['configuration_history'][0]['snapshot_path']['front'] ?? null);
        $this->assertSame('Mein Seelenfunke Admin', $cfg['last_admin_adjustment']['adjusted_by'] ?? null);
        $this->assertSame(2, $cfg['last_admin_adjustment']['version'] ?? null);

        $customerHtml = Livewire::actingAs($customer, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('showOrder', $order->id)
            ->call('openPreview', $item->id)
            ->html();

        $this->assertStringContainsString('Mein Seelenfunke Admin · Änderungsmaßnahme am ', $customerHtml);
        $this->assertStringContainsString(' Uhr durchgeführt', $customerHtml);
        $this->assertStringContainsString('data-blueprint-version-card="1"', $customerHtml);
        $this->assertStringContainsString('data-blueprint-version-card="2"', $customerHtml);
    }

    #[Test]
    public function back_side_cutout_mirroring_and_seelen_anhaenger_rim_calibration_are_congruent(): void
    {
        // 1. Armband & Hundemarke: Rückseiten-Gravurfläche spiegelt sich horizontal vom Durchbruchloch weg
        $bracelet = Product::create([
            'name'                  => 'Das Goldene Seelenarmband',
            'slug'                  => 'id-armband-gold-herz',
            'type'                  => 'physical',
            'status'                => 'active',
            'is_personalizable'     => true,
            'price'                 => 4990,
            'width'                 => 24,
            'height'                => 6,
            'configurator_settings' => [
                'has_back_side'    => true,
                'overlay_type'     => 'plane',
                'area_shape'       => 'custom',
                'area_left'        => 36.25,
                'area_top'         => 44.50,
                'area_width'       => 42.25,
                'area_height'      => 11.00,
                'template_package' => 'id-armband-gold-herz',
                'custom_points'    => [
                    ['x' => 36.25, 'y' => 44.50],
                    ['x' => 78.50, 'y' => 44.50],
                    ['x' => 78.50, 'y' => 55.50],
                    ['x' => 36.25, 'y' => 55.50],
                ],
            ],
        ]);

        $dogTag = Product::create([
            'name'                  => 'Die Seelen-Pfote',
            'slug'                  => 'seelen-pfote-hundemarke',
            'type'                  => 'physical',
            'status'                => 'active',
            'is_personalizable'     => true,
            'price'                 => 2490,
            'width'                 => 40,
            'height'                => 22,
            'configurator_settings' => [
                'has_back_side'    => true,
                'overlay_type'     => 'plane',
                'area_shape'       => 'custom',
                'area_left'        => 14.0,
                'area_top'         => 34.0,
                'area_width'       => 52.0,
                'area_height'      => 32.0,
                'template_package' => 'seelen-pfote-hundemarke',
                'custom_points'    => [
                    ['x' => 14.0, 'y' => 34.0],
                    ['x' => 66.0, 'y' => 34.0],
                    ['x' => 66.0, 'y' => 66.0],
                    ['x' => 14.0, 'y' => 66.0],
                ],
            ],
        ]);

        $this->seed(\Database\Seeders\ProductTemplateSeeder::class);

        $braceletTpl = \App\Models\Product\ProductTemplate::where('product_id', $bracelet->id)->firstOrFail();
        $this->assertEqualsWithDelta(42.5, (float) ($braceletTpl->configuration['texts_back'][0]['x'] ?? 0), 0.1);

        $dogTagTpl = \App\Models\Product\ProductTemplate::where('product_id', $dogTag->id)->firstOrFail();
        $this->assertEqualsWithDelta(58.0, (float) ($dogTagTpl->configuration['texts_back'][0]['x'] ?? 0), 0.1);

        $customer = $this->createVerifiedCustomer('mirror-kunde');
        $order = OrderOrder::create([
            'order_number'     => 'ORD-2026-MIRROR',
            'customer_id'      => $customer->id,
            'status'           => 'processing',
            'payment_status'   => 'paid',
            'email'            => $customer->email,
            'billing_address'  => ['first_name' => 'Laura', 'last_name' => 'S', 'address' => 'Weg 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Laura', 'last_name' => 'S', 'address' => 'Weg 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'subtotal_price'   => 4990,
            'tax_amount'       => 797,
            'shipping_price'   => 0,
            'total_price'      => 4990,
        ]);

        $braceletItem = OrderOrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $bracelet->id,
            'product_name'  => $bracelet->name,
            'quantity'      => 1,
            'unit_price'    => 4990,
            'tax_rate'      => 19.0,
            'total_price'   => 4990,
            'configuration' => [
                'texts'      => [['text' => 'LAURA', 'font' => 'Montserrat', 'align' => 'center', 'x' => 62.5, 'y' => 50.0, 'size' => 2.2, 'rotation' => 0]],
                'texts_back' => [['text' => '24.12.2026', 'font' => 'Montserrat', 'align' => 'center', 'x' => 42.5, 'y' => 50.0, 'size' => 2.18, 'rotation' => 0]],
            ],
        ]);

        $svc = app(\App\Services\Export\FileDownloadService::class);
        $frontPayload = $svc->buildLaserDesignPayload($braceletItem, 'front', true);
        $backPayload = $svc->buildLaserDesignPayload($braceletItem, 'back', true);
        $this->assertGreaterThan(1.5, $frontPayload['centerOffsetXMm']);
        $this->assertLessThan(-1.5, $backPayload['centerOffsetXMm']);
        $this->assertEqualsWithDelta(-$frontPayload['centerOffsetXMm'], $backPayload['centerOffsetXMm'], 0.05);

        // 2. Der Seelenanhänger: 2D-Overlay sitzt bündig ohne dunkle Lücke an custom_points (Wangen 30° & 150°)
        $overlayAbs = storage_path('app/public/produkte/products/seelen-anhaenger/overlay.png');
        $glbAbs = storage_path('app/public/produkte/products/seelen-anhaenger/seelenanhaenger_3d_.glb');
        $this->assertFileExists($overlayAbs);
        $this->assertFileExists($glbAbs);

        $im = imagecreatefrompng($overlayAbs);
        $this->assertNotFalse($im);

        // Untere rechte Wange (30°, r_green=128.6 -> r=131px => x=513, y=661) und linke Wange (150° => x=287, y=661) müssen silbern leuchten
        foreach ([[513, 661], [287, 661]] as [$px, $py]) {
            $c = imagecolorat($im, $px, $py);
            $gdAlpha = ($c >> 24) & 0x7F;
            $lum = (0.299 * (($c >> 16) & 0xFF)) + (0.587 * (($c >> 8) & 0xFF)) + (0.114 * ($c & 0xFF));
            $this->assertLessThan(20, $gdAlpha, "Silberrand bei ({$px},{$py}) muss opak sein.");
            $this->assertGreaterThan(80.0, $lum, "Kein dunkler Spalt mehr direkt außerhalb von custom_points bei ({$px},{$py}).");
        }

        // Alte überstehende Backe bei (529, 670) [r=149px] ist sauber freigestellt
        $cOut = imagecolorat($im, 529, 670);
        $this->assertGreaterThanOrEqual(115, ($cOut >> 24) & 0x7F);
        imagedestroy($im);
    }
}

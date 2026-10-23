<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductConfiguratorCalculatorIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\Admin\Admin::factory()->create(), 'admin');
        Cache::flush();
        Cache::put('shop_capacity_level', 0);

        DB::table('tax_rates')->insertOrIgnore([
            ['name' => 'Standard DE', 'rate' => 19.00, 'country_code' => 'DE', 'tax_class' => 'standard', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->product = Product::create([
            'name' => 'Kalkulator Holzbox Test',
            'slug' => 'kalkulator-holzbox-test-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2500, // 25,00 EUR
            'weight' => 0.5,
            'is_personalizable' => true,
            'configurator_settings' => [
                'allow_logo' => true,
                'has_back_side' => true,
            ],
        ]);
    }

    #[Test]
    public function configurator_in_calculator_mode_auto_confirms_and_dispatches_calculator_save()
    {
        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'calculator',
            'qty' => 3,
        ]);

        // Im Calculator-Kontext muss config_confirmed automatisch true sein
        $component->assertSet('config_confirmed', true);
        $component->assertSet('context', 'calculator');
        $component->assertSet('qty', 3);

        // Texte hinzufügen
        $component->set('texts', [
            ['text' => 'Beste Firma Gravur', 'font' => 'Arial', 'color' => '#000000', 'size' => 24, 'x' => 50, 'y' => 50]
        ]);

        // Speichern ohne manuelle Checkbox-Bestätigung (da Calculator-Kontext)
        $component->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('calculator-save', function ($event, $params) {
                $data = $params['data'] ?? [];
                return $data['product_id'] === $this->product->id
                    && $data['qty'] === 3
                    && $data['text'] === 'Beste Firma Gravur';
            })
            ->assertNotDispatched('cart-updated');
    }

    #[Test]
    public function configurator_in_add_mode_requires_manual_confirmation_checkbox()
    {
        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'add',
            'qty' => 1,
        ]);

        // Im Shop-Warenkorb-Modus darf nicht automatisch bestätigt sein
        $component->assertSet('config_confirmed', false);

        // Aufruf von save() ohne Bestätigung muss Fehler werfen
        $component->call('save')
            ->assertHasErrors(['config_confirmed'])
            ->assertNotDispatched('cart-updated')
            ->assertNotDispatched('calculator-save');

        // Nach Bestätigung erfolgreich
        $component->set('config_confirmed', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('cart-updated')
            ->assertNotDispatched('calculator-save');
    }

    #[Test]
    public function configurator_processes_snapshots_and_stores_in_filesystem()
    {
        Storage::fake('public');

        // 1x1 Pixel Dummy Base64 JPEG
        $dummyBase64Front = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';
        $dummyBase64Back = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'calculator',
            'qty' => 2,
        ]);

        $component->call('saveWithSnapshot', [
            'front' => $dummyBase64Front,
            'back' => $dummyBase64Back,
        ]);

        $component->assertHasNoErrors()
            ->assertDispatched('calculator-save', function ($event, $params) {
                $snapshotPath = $params['data']['snapshot_path'] ?? [];
                return !empty($snapshotPath['front'])
                    && !empty($snapshotPath['back'])
                    && Storage::disk('public')->exists($snapshotPath['front'])
                    && Storage::disk('public')->exists($snapshotPath['back']);
            });
    }

    #[Test]
    public function product_calculator_transitions_from_step_2_back_to_step_1_upon_calculator_save()
    {
        // 1. Calculator initialisieren
        $calculator = Livewire::test(ProductCalculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->assertSet('step', 1);

        // 2. Produkt konfigurieren -> Schritt 2
        $calculator->call('openConfig', $this->product->id)
            ->assertSet('step', 2)
            ->assertDispatched('scroll-top');

        // 3. Simuliere das Eintreffen des calculator-save Events aus dem Konfigurator
        $payload = [
            'product_id' => $this->product->id,
            'qty' => 5,
            'text' => 'Firmenjubiläum 2026',
            'texts' => [
                ['text' => 'Firmenjubiläum 2026', 'font' => 'Arial']
            ],
            'snapshot_path' => ['front' => 'system/snapshots/test.jpg'],
            'is_express' => false,
        ];

        $calculator->dispatch('calculator-save', $payload);

        // 4. Verifiziere: Schritt MUSS zurück auf 1 wechseln, Artikel im Warenkorb, Summen berechnet
        $calculator->assertSet('step', 1)
            ->assertDispatched('scroll-top');

        $cartItems = $calculator->get('cartItems');
        $this->assertCount(1, $cartItems);
        $this->assertEquals($this->product->id, $cartItems[0]['product_id']);
        $this->assertEquals(5, $cartItems[0]['qty']);
        $this->assertEquals('Firmenjubiläum 2026', $cartItems[0]['text']);
        $this->assertEquals(['front' => 'system/snapshots/test.jpg'], $cartItems[0]['configuration']['snapshot_path']);

        // 5. Artikel bearbeiten: Schritt 2 erneut öffnen, anpassen und zurück zu Schritt 1
        $calculator->call('editItem', 0)
            ->assertSet('step', 2)
            ->assertSet('editingIndex', 0);

        $payloadUpdated = $payload;
        $payloadUpdated['qty'] = 10;
        $payloadUpdated['text'] = 'Firmenjubiläum 2026 (Update)';

        $calculator->dispatch('calculator-save', $payloadUpdated);

        // Prüfen, dass Schritt wieder 1 ist und kein Duplikat erstellt wurde
        $calculator->assertSet('step', 1);
        $updatedCartItems = $calculator->get('cartItems');
        $this->assertCount(1, $updatedCartItems);
        $this->assertEquals(10, $updatedCartItems[0]['qty']);
        $this->assertEquals('Firmenjubiläum 2026 (Update)', $updatedCartItems[0]['text']);
    }

    #[Test]
    public function non_personalizable_product_bypasses_configurator_directly_into_calculator_cart()
    {
        $nonPersonalizable = Product::create([
            'name' => 'Standard Flyer Nicht-Personalisiert',
            'slug' => 'standard-flyer-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 1500,
            'is_personalizable' => false,
        ]);

        $calculator = Livewire::test(ProductCalculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->assertSet('step', 1);

        // Öffnen des nicht-personalisierbaren Produkts muss Konfigurator (Schritt 2) überspringen
        $calculator->call('openConfig', $nonPersonalizable->id)
            ->assertSet('step', 1);

        $cartItems = $calculator->get('cartItems');
        $this->assertCount(1, $cartItems);
        $this->assertEquals($nonPersonalizable->id, $cartItems[0]['product_id']);
    }

    #[Test]
    public function configurator_renders_3d_expert_notice_when_product_has_3d_model()
    {
        $product3d = Product::create([
            'name' => 'Glasquader 3D Test',
            'slug' => 'glasquader-3d-test-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3000,
            'is_personalizable' => true,
            'three_d_active' => true,
            'three_d_model_path' => 'models/test.glb',
        ]);

        Livewire::test(ProductConfigurator::class, ['product' => $product3d])
            ->assertSee('Hinweis zur 3D-Vorschau')
            ->assertSee('Positionierung und Ausrichtung im Detail und optimieren diese bei Bedarf');
    }

    #[Test]
    public function configurator_renders_2d_expert_notice()
    {
        Livewire::test(ProductConfigurator::class, ['product' => $this->product])
            ->assertSee('Hinweis zur 2D-Gestaltung')
            ->assertSee('Wunschtext, gewünschte Motive/Icons sowie eigene Bilder frei auf der Gestaltungsfläche');
    }

    #[Test]
    public function configurator_renders_three_main_buttons_and_preserves_notes_section()
    {
        Livewire::test(ProductConfigurator::class, ['product' => $this->product])
            ->assertSee('Symbole')
            ->assertSee('Eigene Dateien')
            ->assertSee('Design Sichern')
            ->assertSee('Besondere Wünsche an die Manufaktur');
    }

    #[Test]
    public function configurator_loads_standard_vector_categories_and_items()
    {
        $component = Livewire::test(ProductConfigurator::class, ['product' => $this->product]);
        $vectors = $component->get('vectors');

        $this->assertArrayHasKey('Liebe', $vectors);
        $this->assertArrayHasKey('Familie', $vectors);
        $this->assertArrayHasKey('Tiere', $vectors);
        $this->assertArrayHasKey('Natur', $vectors);
        $this->assertArrayHasKey('Symbole', $vectors);
        $this->assertArrayHasKey('Ornamente', $vectors);
        $this->assertArrayHasKey('Feiertage', $vectors);

        $this->assertGreaterThanOrEqual(10, count($vectors['Liebe']));
        $this->assertGreaterThanOrEqual(5, count($vectors['Familie']));
        $this->assertGreaterThanOrEqual(4, count($vectors['Tiere']));
        $this->assertGreaterThanOrEqual(5, count($vectors['Natur']));
        $this->assertGreaterThanOrEqual(10, count($vectors['Symbole']));
    }

    #[Test]
    public function configurator_renders_undo_redo_controls_and_emoji_warning()
    {
        Livewire::test(ProductConfigurator::class, ['product' => $this->product])
            ->assertSee('Rückgängig (Strg+Z)')
            ->assertSee('Wiederholen (Strg+Y)')
            ->assertSee('Hinweis zur Lasergravur')
            ->assertSee('Emojis')
            ->assertSee('farbige Symbole können per Lasergravur nicht dargestellt werden')
            ->assertSee('handleKeyDown($event)', false);
    }

    #[Test]
    public function configurator_sanitizes_emojis_from_front_and_back_texts_on_save()
    {
        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'calculator'
        ]);

        // Direct method test
        $instance = $component->instance();
        $this->assertEquals('Bester Papa !', $instance->sanitizeLaserText('Bester Papa ❤️🥇🎉!'));
        $this->assertEquals('Liebe Grüße für Schöne Momente & 100% Glück!', $instance->sanitizeLaserText('Liebe Grüße für Schöne Momente & 100% Glück!'));

        // Test during save
        $component->set('texts', [
            ['id' => 't1', 'text' => 'Alles Gute zum Geburtstag! 🎂🎁🥳', 'size' => 1, 'x' => 50, 'y' => 50]
        ]);
        $component->set('texts_back', [
            ['id' => 't2', 'text' => 'In ewiger Liebe ❤️✨', 'size' => 1, 'x' => 50, 'y' => 50]
        ]);
        $component->set('config_confirmed', true);

        $component->call('save', app(\App\Services\CartService::class));

        $component->assertHasNoErrors()
            ->assertDispatched('calculator-save', function ($event, $params) {
                $data = $params['data'] ?? [];
                $texts = $data['texts'] ?? [];
                $textsBack = $data['texts_back'] ?? [];
                return ($texts[0]['text'] ?? '') === 'Alles Gute zum Geburtstag!'
                    && ($textsBack[0]['text'] ?? '') === 'In ewiger Liebe';
            });
    }

    #[Test]
    public function configurator_renders_maximize_controls_and_studio_mode()
    {
        // In customer add mode, maximize button is present
        Livewire::test(ProductConfigurator::class, ['product' => $this->product])
            ->assertSee('Maximal')
            ->assertSee('Verkleinern')
            ->assertSee('Normalansicht')
            ->assertSee('Studio-Modus')
            ->assertSee('toggleMaximize()', false)
            ->assertSee('isMaximized', false);

        // In preview mode (e.g. admin order view / customer order view), maximize button is NOT rendered
        Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'preview'
        ])
            ->assertDontSee('Maximal-Ansicht')
            ->assertDontSee('Normalansicht (Esc)');
    }

    #[Test]
    public function configurator_translates_upload_errors_to_friendly_german()
    {
        $component = Livewire::test(ProductConfigurator::class, ['product' => $this->product]);

        $component->call('_uploadErrored', 'new_files', json_encode([
            'errors' => ['files.0' => ['The files.0 failed to upload.']]
        ]), true);

        $component->assertHasErrors(['new_files.0']);
        $errorMsg = $component->errors()->first('new_files.0');
        $this->assertStringContainsString('Die Datei konnte nicht hochgeladen werden', $errorMsg);
        $this->assertStringNotContainsString('failed to upload', $errorMsg);
    }

    private function createOrderOrder(): \App\Models\Order\OrderOrder
    {
        return \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-' . uniqid(),
            'email' => 'test@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'total_price' => 2500,
            'subtotal_price' => 2500,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann', 'city' => 'Berlin', 'postal_code' => '10115', 'country' => 'DE', 'address' => 'Teststr. 1'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann', 'city' => 'Berlin', 'zip' => '10115', 'country' => 'DE', 'street' => 'Teststr. 1'],
        ]);
    }

    #[Test]
    public function svg_export_prioritizes_xtool_backend_plane_dimensions()
    {
        $product = Product::create([
            'name' => 'Laser Acryl Schild',
            'slug' => 'laser-acryl-schild-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2500,
            'width' => 50,  // fallback
            'height' => 50, // fallback
            'configurator_settings' => [
                'overlay_type' => 'plane',
                'xtool_plane_width' => 150,
                'xtool_plane_height' => 120,
            ],
        ]);

        $order = $this->createOrderOrder();

        $orderItem = \App\Models\Order\OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => [
                'texts' => [
                    ['id' => '1', 'text' => 'Willkommen', 'x' => 50, 'y' => 50, 'size' => 1]
                ]
            ],
        ]);

        $service = app(\App\Services\Export\FileDownloadService::class);
        $response = $service->downloadLaserSvg($orderItem->id);

        $this->assertEquals(200, $response->getStatusCode());
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Überprüfe, dass die xTool Plane Maße (150x120 mm) im SVG Header verwendet werden statt 50x50 mm
        $this->assertStringContainsString('width="150mm"', $content);
        $this->assertStringContainsString('height="120mm"', $content);
        $this->assertStringContainsString('viewBox="0 0 150 120"', $content);
    }

    #[Test]
    public function svg_export_prioritizes_xtool_backend_cylinder_dimensions()
    {
        $product = Product::create([
            'name' => 'Laser Thermobecher',
            'slug' => 'laser-thermobecher-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3500,
            'width' => 40,
            'height' => 80,
            'configurator_settings' => [
                'overlay_type' => 'cylinder',
                'xtool_d_top' => 70,    // Durchmesser 70mm -> Umfang = 70 * pi = 219.91mm
                'xtool_height' => 180,  // Höhe 180mm
            ],
        ]);

        $order = $this->createOrderOrder();

        $orderItem = \App\Models\Order\OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3500,
            'total_price' => 3500,
            'configuration' => [
                'texts' => [
                    ['id' => '1', 'text' => 'Kaffee', 'x' => 50, 'y' => 50, 'size' => 1]
                ]
            ],
        ]);

        $service = app(\App\Services\Export\FileDownloadService::class);
        $response = $service->downloadLaserSvg($orderItem->id);

        $this->assertEquals(200, $response->getStatusCode());
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $expectedWidth = 70 * pi();
        $this->assertStringContainsString('width="' . $expectedWidth . 'mm"', $content);
        $this->assertStringContainsString('height="180mm"', $content);
    }
}



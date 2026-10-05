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
}

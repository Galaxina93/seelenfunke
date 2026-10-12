<?php

namespace Tests\Feature\Livewire\Shop;

use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDHLTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the admin user
        $admin = Admin::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'email' => 'admin@test.de',
            'first_name' => 'Hans',
            'last_name' => 'Wurst',
            'password' => bcrypt('password')
        ]);
        $this->actingAs($admin, 'admin');
        
        // Ensure standard DHL configurations to make the test deterministic
        config(['services.dhl.sandbox' => true]);
        config(['services.dhl.api_key' => 'test-key']);
        config(['services.dhl.api_user' => 'test-user']);
        config(['services.dhl.api_signature' => 'test-signature']);
        config(['services.dhl.ekp' => '2222222222']);
    }

    private function createDummyOrder(): OrderOrder
    {
        return OrderOrder::create([
            'order_number' => 'TEST-12345',
            'email' => 'test@example.com',
            'status' => 'processing',
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'total_price' => 1190,
            'shipping_price' => 0,
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'address' => 'Musterstr. 12',
                'postal_code' => '12345',
                'city' => 'Musterstadt',
                'country' => 'DE'
            ],
            'shipping_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'street' => 'Musterstr. 12',
                'zip' => '12345',
                'city' => 'Musterstadt',
                'country' => 'DE'
            ]
        ]);
    }

    public function test_admin_can_open_dhl_modal()
    {
        $order = $this->createDummyOrder();

        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->assertSet('dhlModalOrderId', $order->id)
            ->assertSet('dhlPackageCount', 1)
            ->assertSet('dhlWeightPerPackage', 0.35)
            ->assertSet('dhlError', null);
    }

    public function test_admin_can_generate_multiple_dhl_labels_success()
    {
        Storage::fake('public');
        $order = $this->createDummyOrder();

        // Fake the DHL Http API response for 2 packages
        Http::fake([
            '*dhl.com/parcel/de/shipping/v2/orders*' => Http::response([
                'items' => [
                    [
                        'sstatus' => ['title' => 'ok'],
                        'shipmentNo' => '00340434273299990001',
                        'label' => ['b64' => base64_encode('fake-pdf-content-1')]
                    ],
                    [
                        'sstatus' => ['title' => 'ok'],
                        'shipmentNo' => '00340434273299990002',
                        'label' => ['b64' => base64_encode('fake-pdf-content-2')]
                    ]
                ]
            ], 200)
        ]);

        // Call Livewire Component Workflow
        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->set('dhlPackageCount', 2)
            ->set('dhlWeightPerPackage', 5.5)
            ->call('generateDhlLabels')
            ->assertHasNoErrors()
            ->assertSet('dhlModalOrderId', null); // Modal should close on success

        // Assert Shipments are saved in database
        $this->assertDatabaseCount('order_shipments', 2);
        
        $shipments = OrderShipment::where('order_id', $order->id)->get();
        $this->assertCount(2, $shipments);
        
        $this->assertEquals('00340434273299990001', $shipments[0]->tracking_number);
        $this->assertEquals('00340434273299990002', $shipments[1]->tracking_number);

        // Check if PDFs were saved to storage
        Storage::disk('public')->assertExists($shipments[0]->shipping_label_path);
        Storage::disk('public')->assertExists($shipments[1]->shipping_label_path);
        
        // Test Download Logic
        Livewire::test(OrderOverview::class)
            ->call('downloadDhlLabel', $shipments[0]->id)
            ->assertFileDownloaded(basename($shipments[0]->shipping_label_path));
    }
    
    public function test_dhl_api_failure_displays_error_in_modal()
    {
        $order = $this->createDummyOrder();

        // Fake a failure DHL Http API response
        Http::fake([
            '*dhl.com/parcel/de/shipping/v2/orders*' => Http::response([
                'items' => [
                    [
                        'sstatus' => [
                            'title' => 'Error',
                            'detail' => 'Hard validation error occured.'
                        ],
                        'validationMessages' => [
                            [
                                'validationMessage' => 'The destination zip code is invalid'
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        // Call Livewire Component Workflow
        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->call('generateDhlLabels')
            ->assertSet('dhlError', 'DHL Label Error: Paket 1: Hard validation error occured. The destination zip code is invalid')
            ->assertSet('dhlModalOrderId', $order->id); // Modal sollte offen bleiben

        // Check that database didn't update
        $this->assertDatabaseCount('order_shipments', 0);
    }

    public function test_dhl_weight_calculation_handles_empty_string_package_count()
    {
        $order = $this->createDummyOrder();

        // Simulate user clearing the input field (which passes an empty string)
        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->set('dhlPackageCount', '') // Livewire 3 autotriggers updatedDhlPackageCount
            ->assertHasNoErrors();
    }

    public function test_console_command_updates_delivery_status_when_all_packages_delivered()
    {
        $order = $this->createDummyOrder();
        $order->update(['status' => 'shipped']); // Require status shipped

        $shipment1 = OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => '11111111',
            'shipping_label_path' => 'fake_1.pdf',
            'carrier' => 'DHL',
            'status' => 'shipped'
        ]);

        $shipment2 = OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => '22222222',
            'shipping_label_path' => 'fake_2.pdf',
            'carrier' => 'DHL',
            'status' => 'shipped'
        ]);

        // Mock Track API
        Http::fake([
            '*api-eu.dhl.com/track/shipments*' => Http::sequence()
                ->push([
                    'shipments' => [
                        ['status' => ['statusCode' => 'delivered', 'status' => 'DELIVERED']]
                    ]
                ], 200)
                ->push([
                    'shipments' => [
                        ['status' => ['statusCode' => 'delivered', 'status' => 'DELIVERED']]
                    ]
                ], 200)
        ]);

        // Run Artisan Command
        $this->artisan('dhl:check-delivery-status')->assertSuccessful();

        $shipment1->refresh();
        $shipment2->refresh();
        $order->refresh();

        $this->assertEquals('delivered', $shipment1->status);
        $this->assertEquals('delivered', $shipment2->status);
        $this->assertEquals('completed', $order->status); // Order status is now completed
    }

    public function test_dhl_modal_calculates_weight_with_products_tara_and_safety_buffer()
    {
        $order = $this->createDummyOrder();

        $productA = \App\Models\Product\Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Acryl Trophäe',
            'slug' => 'acryl-trophae-' . uniqid(),
            'sku' => 'TRP-' . uniqid(),
            'price' => 4500,
            'status' => 'active',
            'type' => 'physical',
            'weight' => 850, // 850g
        ]);

        $productB = \App\Models\Product\Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Holz Anhänger',
            'slug' => 'holz-anhaenger-' . uniqid(),
            'sku' => 'ANH-' . uniqid(),
            'price' => 1200,
            'status' => 'active',
            'type' => 'physical',
            'weight' => 30, // 30g
        ]);

        $order->items()->create([
            'product_id' => $productA->id,
            'product_name' => $productA->name,
            'quantity' => 2, // 1700g
            'unit_price' => 4500,
            'total_price' => 9000,
        ]);

        $order->items()->create([
            'product_id' => $productB->id,
            'product_name' => $productB->name,
            'quantity' => 5, // 150g
            'unit_price' => 1200,
            'total_price' => 6000,
        ]);

        // Net goods: 1700 + 150 = 1850g
        // Tara default: 350g
        // Safety buffer: 50g
        // Total: 2250g = 2.25 kg
        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->assertSet('dhlModalOrderId', $order->id)
            ->assertSet('dhlPackageCount', 1)
            ->assertSet('dhlWeightPerPackage', 2.25)
            ->assertSet('dhlRecommendedWeight', 2.25)
            ->assertSee('Acryl Trophäe')
            ->assertSee('Holz Anhänger')
            ->assertSee('Paket bis 5 kg')
            ->assertSee('1.850 g')
            ->assertSee('2,25 kg');
    }

    public function test_admin_can_apply_dhl_recommendation_after_manual_change()
    {
        $order = $this->createDummyOrder();

        $product = \App\Models\Product\Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Glas Trophäe',
            'slug' => 'glas-trophae-' . uniqid(),
            'sku' => 'GLAS-' . uniqid(),
            'price' => 6000,
            'status' => 'active',
            'type' => 'physical',
            'weight' => 600, // 600g + 350g tara + 50g buffer = 1000g = 1.00 kg
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 6000,
            'total_price' => 6000,
        ]);

        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->assertSet('dhlWeightPerPackage', 1.00)
            ->assertSet('dhlRecommendedWeight', 1.00)
            // Manual adjustment by admin
            ->set('dhlWeightPerPackage', 3.50)
            ->assertSet('dhlWeightPerPackage', 3.50)
            // Click "Empfehlung übernehmen"
            ->call('applyDhlRecommendation')
            ->assertSet('dhlWeightPerPackage', 1.00);
    }

    public function test_dhl_modal_shows_fallback_warning_when_product_has_no_weight()
    {
        $order = $this->createDummyOrder();

        $product = \App\Models\Product\Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Unbekanntes Teil',
            'slug' => 'unbekannt-' . uniqid(),
            'sku' => 'UNB-' . uniqid(),
            'price' => 2000,
            'status' => 'active',
            'type' => 'physical',
            'weight' => 0, // No weight!
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2000,
            'total_price' => 2000,
        ]);

        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->assertSee('Kein Artikelgewicht gepflegt')
            ->assertSee('100 g Schätzwert');
    }

    public function test_dhl_modal_auto_suggests_envelope_for_lightweight_jewelry()
    {
        $order = $this->createDummyOrder();

        $product = \App\Models\Product\Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Der Seelenanhänger',
            'slug' => 'seelenanhaenger-' . uniqid(),
            'sku' => 'ANH-' . uniqid(),
            'price' => 2900,
            'status' => 'active',
            'type' => 'physical',
            'weight' => 19, // 19g
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5, // 95g total
            'unit_price' => 2900,
            'total_price' => 14500,
        ]);

        // 95g Ware + 30g Umschlag-Tara + 10g Puffer = 135g = 0.14 kg
        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->assertSet('dhlPackagingType', 'envelope')
            ->assertSet('dhlRecommendedWeight', 0.14)
            ->assertSet('dhlWeightPerPackage', 0.14)
            ->assertSee('Luftpolstertasche')
            ->assertSee('Für Luftpolstertasche geeignet')
            ->assertSee('0,14 kg')
            ->assertSee('135 g');
    }

    public function test_admin_can_toggle_between_box_and_envelope()
    {
        $order = $this->createDummyOrder();

        $product = \App\Models\Product\Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Der Seelenanhänger',
            'slug' => 'seelenanhaenger-' . uniqid(),
            'sku' => 'ANH-' . uniqid(),
            'price' => 2900,
            'status' => 'active',
            'type' => 'physical',
            'weight' => 19,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => 2900,
            'total_price' => 14500,
        ]);

        $component = Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->assertSet('dhlPackagingType', 'envelope')
            ->assertSet('dhlWeightPerPackage', 0.14);

        // Umschalten auf Karton: 95g Ware + 350g Karton + 50g Puffer = 495g = 0.50 kg
        $component->call('setPackagingType', 'box')
            ->assertSet('dhlPackagingType', 'box')
            ->assertSet('dhlRecommendedWeight', 0.50)
            ->assertSet('dhlWeightPerPackage', 0.50);

        // Zurückschalten auf Umschlag: wieder 0.14 kg
        $component->call('setPackagingType', 'envelope')
            ->assertSet('dhlPackagingType', 'envelope')
            ->assertSet('dhlRecommendedWeight', 0.14)
            ->assertSet('dhlWeightPerPackage', 0.14);
    }
}


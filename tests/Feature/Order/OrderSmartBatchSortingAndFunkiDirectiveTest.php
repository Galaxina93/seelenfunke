<?php

namespace Tests\Feature\Order;

use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use App\Models\Product\Product;
use App\Models\Product\ProductSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderSmartBatchSortingAndFunkiDirectiveTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Customer $customer;
    private Product $trophyProduct;
    private Product $pendantProduct;
    private Product $glassProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'id' => Str::uuid()->toString(),
            'email' => 'alina@mein-seelenfunke.de',
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->admin, 'admin');

        $this->customer = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'kunde@example.de',
            'first_name' => 'Julia',
            'last_name' => 'Meyer',
            'password' => bcrypt('password'),
        ]);

        $this->trophyProduct = Product::create([
            'id' => Str::uuid()->toString(),
            'sku' => 'CRYSTAL-3D-001',
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'description' => 'Optischer K9 Kristall.',
            'attributes' => ['Material' => 'K9 Kristallglas'],
            'price' => 3990,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 50,
            'weight' => 920,
        ]);

        $this->pendantProduct = Product::create([
            'id' => Str::uuid()->toString(),
            'sku' => 'ALU-HEART-001',
            'name' => 'Der Seelenanhänger',
            'slug' => 'seelen-anhaenger',
            'description' => 'Herz-Schlüsselanhänger.',
            'attributes' => ['Material' => 'Aluminium'],
            'price' => 1699,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 50,
            'weight' => 19,
        ]);

        $this->glassProduct = Product::create([
            'id' => Str::uuid()->toString(),
            'sku' => 'GLASS-WEIZEN-001',
            'name' => 'Der Feierabend-Funke',
            'slug' => 'weizenglas-personalisiert',
            'description' => 'Weizenglas mit 360-Grad-Gravur.',
            'attributes' => ['Material' => 'Kristallglas'],
            'price' => 2490,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 50,
            'weight' => 450,
            'configurator_settings' => ['area_shape' => 'cylinder'],
        ]);
    }

    private function makeOrder(string $orderNumber, array $overrides = []): OrderOrder
    {
        $order = new OrderOrder(array_merge([
            'order_number' => $orderNumber,
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'is_express' => false,
            'subtotal_price' => 3990,
            'tax_amount' => 637,
            'shipping_price' => 0,
            'total_price' => 3990,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'billing_address' => [
                'first_name' => 'Julia',
                'last_name' => 'Meyer',
                'address' => 'Hauptstr. 10',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Julia',
                'last_name' => 'Meyer',
                'address' => 'Hauptstr. 10',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
        ], $overrides));

        if (isset($overrides['created_at'])) {
            $order->created_at = $overrides['created_at'];
        }
        $order->save();

        return $order;
    }

    public function test_bundles_identical_articles_together_trophy_pendant_trophy(): void
    {
        // Trophäe (10:00) -> Anhänger (10:05) -> Trophäe (10:10)
        $ordTrophy1 = $this->makeOrder('ORD-TROPHY-1', ['created_at' => now()->subMinutes(30)]);
        $ordTrophy1->items()->create([
            'product_id' => $this->trophyProduct->id,
            'product_name' => $this->trophyProduct->name,
            'quantity' => 1,
            'unit_price' => 3990,
            'total_price' => 3990,
            'configuration' => ['text' => 'Für Oma'],
        ]);

        $ordPendant = $this->makeOrder('ORD-PENDANT-1', ['created_at' => now()->subMinutes(25)]);
        $ordPendant->items()->create([
            'product_id' => $this->pendantProduct->id,
            'product_name' => $this->pendantProduct->name,
            'quantity' => 1,
            'unit_price' => 1699,
            'total_price' => 1699,
            'configuration' => ['text' => 'Lieblingsmensch'],
        ]);

        $ordTrophy2 = $this->makeOrder('ORD-TROPHY-2', ['created_at' => now()->subMinutes(20)]);
        $ordTrophy2->items()->create([
            'product_id' => $this->trophyProduct->id,
            'product_name' => $this->trophyProduct->name,
            'quantity' => 1,
            'unit_price' => 3990,
            'total_price' => 3990,
            'configuration' => ['text' => 'Für Opa'],
        ]);

        $component = Livewire::test(OrderOverview::class);

        $orders = $component->viewData('orders');
        $orderedNumbers = $orders->getCollection()->pluck('order_number')->all();

        // Beide Trophäen stehen direkt hintereinander ganz oben, danach erst der Anhänger!
        $this->assertSame(['ORD-TROPHY-1', 'ORD-TROPHY-2', 'ORD-PENDANT-1'], $orderedNumbers);

        // Funki sagt oben in einem konkreten Satz die Serie + das 3D-Setup an
        $component->assertSee('Serie starten (2 Aufträge gebündelt)')
            ->assertSee('3D-Kristallfokus')
            ->assertSee('Der Seelen Kristall')
            ->assertSee('#ORD-TROPHY-1');
    }

    public function test_completing_an_item_locks_active_laser_focus_and_enables_next_same_article_flow(): void
    {
        // Trophäe (10:00) -> Anhänger 1 (10:05, 2 Positionen) -> Anhänger 2 (10:10)
        $ordTrophy = $this->makeOrder('ORD-TROPHY-A', ['created_at' => now()->subMinutes(40)]);
        $ordTrophy->items()->create([
            'product_id' => $this->trophyProduct->id,
            'product_name' => $this->trophyProduct->name,
            'quantity' => 1,
            'unit_price' => 3990,
            'total_price' => 3990,
            'configuration' => ['text' => 'Ehrenpreis'],
        ]);

        $ordPendant1 = $this->makeOrder('ORD-PENDANT-A', ['created_at' => now()->subMinutes(30)]);
        $pendantItem1 = $ordPendant1->items()->create([
            'product_id' => $this->pendantProduct->id,
            'product_name' => $this->pendantProduct->name,
            'quantity' => 1,
            'unit_price' => 1699,
            'total_price' => 1699,
            'configuration' => ['text' => 'Mama'],
        ]);

        $ordPendant2 = $this->makeOrder('ORD-PENDANT-B', ['created_at' => now()->subMinutes(20)]);
        $ordPendant2->items()->create([
            'product_id' => $this->pendantProduct->id,
            'product_name' => $this->pendantProduct->name,
            'quantity' => 1,
            'unit_price' => 1699,
            'total_price' => 1699,
            'configuration' => ['texts_back' => [['text' => 'Für immer']]],
        ]);

        // Admin arbeitet gerade an ORD-PENDANT-A und hakt den Seelenanhänger als gelasert ab
        $component = Livewire::test(OrderOverview::class)
            ->call('openDetail', $ordPendant1->id)
            ->call('toggleItemCompletion', $pendantItem1->id);

        // Aktiver Fokus merkt sich sofort den Seelenanhänger
        $component->assertSet('activeBatchProductId', $this->pendantProduct->id);

        // Sobald ORD-PENDANT-A versendet wird, rückt ORD-PENDANT-B (gleicher Artikel!) vor die Trophäe!
        $ordPendant1->update(['status' => 'shipped']);

        $listComponent = Livewire::test(OrderOverview::class);
        $orderedNumbers = $listComponent->viewData('orders')->getCollection()->pluck('order_number')->all();
        $this->assertSame(['ORD-PENDANT-B', 'ORD-TROPHY-A', 'ORD-PENDANT-A'], $orderedNumbers);

        // Und Funki erkennt sowohl das letzte Serien-Stück als auch die Vorder- und Rückseiten-Gravur!
        $listComponent->assertSee('Letztes Stück der Serie')
            ->assertSee('Flachbett · Vorder- und Rückseite')
            ->assertSee('#ORD-PENDANT-B');
    }

    public function test_express_and_48h_aging_orders_never_starve_behind_batches(): void
    {
        // 51h alter Einzelauftrag (Weizenglas) vs. frischere Trophäen-Serie vs. neuer Express-Auftrag
        $agedGlass = $this->makeOrder('ORD-AGED-GLASS', ['created_at' => now()->subHours(51)]);
        $agedGlass->items()->create([
            'product_id' => $this->glassProduct->id,
            'product_name' => $this->glassProduct->name,
            'quantity' => 1,
            'unit_price' => 2490,
            'total_price' => 2490,
            'configuration' => ['text' => 'Prost Papa'],
        ]);

        $freshTrophy = $this->makeOrder('ORD-FRESH-TROPHY', ['created_at' => now()->subHours(2)]);
        $freshTrophy->items()->create([
            'product_id' => $this->trophyProduct->id,
            'product_name' => $this->trophyProduct->name,
            'quantity' => 2,
            'unit_price' => 3990,
            'total_price' => 7980,
            'configuration' => ['text' => 'Award'],
        ]);

        $expressPendant = $this->makeOrder('ORD-EXPRESS-NOW', [
            'is_express' => true,
            'created_at' => now()->subMinutes(5),
        ]);
        $expressPendant->items()->create([
            'product_id' => $this->pendantProduct->id,
            'product_name' => $this->pendantProduct->name,
            'quantity' => 1,
            'unit_price' => 1699,
            'total_price' => 1699,
            'configuration' => ['text' => 'Eilig!'],
        ]);

        $component = Livewire::test(OrderOverview::class);
        $orderedNumbers = $component->viewData('orders')->getCollection()->pluck('order_number')->all();

        // 1. Express zuerst, 2. Über 48h wartendes Weizenglas (Frist-Schutz), 3. Frische Trophäe
        $this->assertSame(['ORD-EXPRESS-NOW', 'ORD-AGED-GLASS', 'ORD-FRESH-TROPHY'], $orderedNumbers);
        $component->assertSee('Express sofort lasern')
            ->assertSee('#ORD-EXPRESS-NOW');
    }

    public function test_funki_directive_covers_all_special_states_unpaid_supplier_photo_and_ready_ship(): void
    {
        $supplier = ProductSupplier::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Friedrich Binder (FBM)',
            'website' => 'https://shop.fbm-chains.com',
        ]);

        $bracelet = Product::create([
            'id' => Str::uuid()->toString(),
            'sku' => 'MSF-SILBER-ARMBAND-001',
            'name' => 'Das Echtsilber Gravur-Armband',
            'slug' => 'id-armband-silber-herz',
            'description' => '925er Sterlingsilber.',
            'price' => 7990,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
            'product_supplier_id' => $supplier->id,
            'weight' => 10,
        ]);

        // 1. Unbezahlte Bestellung (älteste)
        $unpaidOrder = $this->makeOrder('ORD-UNPAID-1', [
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'created_at' => now()->subHours(5),
        ]);
        $unpaidOrder->items()->create([
            'product_id' => $this->pendantProduct->id,
            'product_name' => $this->pendantProduct->name,
            'quantity' => 1,
            'unit_price' => 1699,
            'total_price' => 1699,
        ]);

        // 2. Foto-Mangel offen
        $photoOrder = $this->makeOrder('ORD-PHOTO-1', [
            'status' => 'pending',
            'created_at' => now()->subHours(4),
        ]);
        $photoOrder->items()->create([
            'product_id' => $this->trophyProduct->id,
            'product_name' => $this->trophyProduct->name,
            'quantity' => 1,
            'unit_price' => 3990,
            'total_price' => 3990,
            'configuration' => [
                'photo_issue' => [
                    'reason' => 'Unscharf',
                    'resolved' => false,
                ],
            ],
        ]);

        // 3. Rohling beim Lieferanten nachzubestellen
        $supplierOrder = $this->makeOrder('ORD-SUPPLIER-1', [
            'created_at' => now()->subHours(3),
        ]);
        $supItem = $supplierOrder->items()->create([
            'product_id' => $bracelet->id,
            'product_name' => $bracelet->name,
            'quantity' => 1,
            'unit_price' => 7990,
            'total_price' => 7990,
            'configuration' => [
                'supplier_procurement' => [
                    'required' => true,
                    'missing_quantity' => 1,
                    'ordered_from_supplier' => false,
                    'supplier_name' => 'Friedrich Binder (FBM)',
                ],
            ],
        ]);

        // 4. Bereits fertig gelasert -> nur noch DHL-Label erstellen!
        $readyShipOrder = $this->makeOrder('ORD-READY-SHIP', [
            'created_at' => now()->subHours(1),
        ]);
        $readyShipOrder->items()->create([
            'product_id' => $this->pendantProduct->id,
            'product_name' => $this->pendantProduct->name,
            'quantity' => 1,
            'completed_quantity' => 1,
            'is_completed' => true,
            'unit_price' => 1699,
            'total_price' => 1699,
        ]);

        // Reihenfolge prüfen: Ready-Ship (sofort abschließbar) -> Rohling nachbestellen -> Foto-Mangel -> Unbezahlt!
        $component = Livewire::test(OrderOverview::class);
        $orderedNumbers = $component->viewData('orders')->getCollection()->pluck('order_number')->all();
        $this->assertSame(['ORD-READY-SHIP', 'ORD-SUPPLIER-1', 'ORD-PHOTO-1', 'ORD-UNPAID-1'], $orderedNumbers);

        // Zustand A: Fertig gelasert, Label fehlt noch
        $component->assertSee('Fertig gelasert, Label erstellen')
            ->assertSee('#ORD-READY-SHIP');

        // Zustand A2: Label ist bereits generiert -> Funki sagt "Drucke das fertige DHL-Label ... auf dem MUNBYN"
        OrderShipment::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $readyShipOrder->id,
            'tracking_number' => '00340434161094015902',
            'shipping_label_path' => 'bestellungen/dhl_labels/test.pdf',
            'status' => 'shipped',
        ]);
        Livewire::test(OrderOverview::class)
            ->assertSee('Fertig produziert, jetzt versenden')
            ->assertSee('MUNBYN');

        // Ready-Ship versendet -> Jetzt liegt ORD-SUPPLIER-1 oben: Funki fordert Rohling-Bestellung bei FBM an!
        $readyShipOrder->update(['status' => 'shipped']);
        Livewire::test(OrderOverview::class)
            ->assertSee('Jetzt Rohling nachbestellen')
            ->assertSee('Friedrich Binder (FBM)')
            ->assertSee('#ORD-SUPPLIER-1');

        // Rohling als bestellt markiert -> Rutscht in Wartebereich ("im Zulauf") vor Foto-Mangel & Unbezahlt
        $supItem->markSupplierOrdered(true);
        Livewire::test(OrderOverview::class)
            ->assertSee('Warten auf Wareneingang')
            ->assertSee('#ORD-SUPPLIER-1');

        // Supplier-Order erledigt -> Jetzt liegt Foto-Mangel oben
        $supplierOrder->update(['status' => 'completed']);
        Livewire::test(OrderOverview::class)
            ->assertSee('Pausiert wegen Kundenfoto')
            ->assertSee('#ORD-PHOTO-1');

        // Foto-Order erledigt -> Jetzt liegt Unbezahlt oben
        $photoOrder->update(['status' => 'cancelled']);
        Livewire::test(OrderOverview::class)
            ->assertSee('Noch nicht lasern (Zahlung offen)')
            ->assertSee('#ORD-UNPAID-1');
    }
}

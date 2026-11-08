<?php

namespace Tests\Feature\Order;

use App\Livewire\Shop\Order\OrderOverview;
use App\Livewire\Shop\Product\ProductCreate;
use App\Livewire\Shop\System\SystemDeliveryDisplay;
use App\Models\Admin\Admin;
use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryTime;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierProcurementAndLeadTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DeliverySetting::query()->delete();
        DeliveryTime::query()->delete();

        DeliverySetting::create([
            'is_vacation_mode' => false,
            'is_sick_mode' => false,
        ]);

        DeliveryTime::create([
            'name' => 'Standard',
            'min_days' => 3,
            'max_days' => 5,
            'is_active' => true,
        ]);
    }

    public function test_product_in_stock_uses_standard_manufaktur_delivery_time(): void
    {
        $product = Product::create([
            'name' => 'Schieferplatte Lagerware',
            'slug' => 'schieferplatte-lagerware',
            'type' => 'physical',
            'status' => 'active',
            'price' => 3490,
            'quantity' => 5,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => true,
            'is_personalizable' => true,
            'configurator_settings' => [
                'out_of_stock_lead_days_min' => 4,
                'out_of_stock_lead_days_max' => 6,
            ],
        ]);

        $this->assertFalse($product->requiresSupplierProcurement(1));

        $details = DeliverySetting::getDeliveryDetailsForProduct($product, 1);
        $this->assertFalse($details['is_backorder_procurement']);
        $this->assertSame(3, $details['min_days']);
        $this->assertSame(5, $details['max_days']);
        $this->assertStringContainsString('3-5 Werktage', $details['full_text']);
    }

    public function test_product_out_of_stock_adds_supplier_lead_time_to_manufaktur_time(): void
    {
        $supplier = ProductSupplier::create([
            'name' => 'PrintEquipment GmbH',
            'lead_time_land_days' => 5,
            'website' => 'https://www.printequipment.de',
        ]);

        // 1. Mit Produkt-spezifischem Override (4-6 Tage + 3-5 Tage = 7-11 Tage)
        $productWithOverride = Product::create([
            'name' => 'Kristall-Trophäe Auftragsfertigung',
            'slug' => 'kristall-trophaee-auftragsfertigung',
            'type' => 'physical',
            'status' => 'active',
            'price' => 6990,
            'quantity' => 0,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => true,
            'product_supplier_id' => $supplier->id,
            'configurator_settings' => [
                'out_of_stock_lead_days_min' => 4,
                'out_of_stock_lead_days_max' => 6,
            ],
        ]);

        $this->assertTrue($productWithOverride->requiresSupplierProcurement(1));
        $this->assertSame(1, $productWithOverride->getMissingStockQuantity(1));

        $detailsOverride = DeliverySetting::getDeliveryDetailsForProduct($productWithOverride, 1);
        $this->assertTrue($detailsOverride['is_backorder_procurement']);
        $this->assertSame(4, $detailsOverride['supplier_lead_min']);
        $this->assertSame(6, $detailsOverride['supplier_lead_max']);
        $this->assertSame(7, $detailsOverride['min_days']);
        $this->assertSame(11, $detailsOverride['max_days']);
        $this->assertStringContainsString('7-11 Werktage', $detailsOverride['full_text']);

        // 2. Ohne Override -> Fallback auf verknüpften ProductSupplier (5-7 Tage + 3-5 Tage = 8-12 Tage)
        $productWithSupplierFallback = Product::create([
            'name' => 'Eichenholz-Schild Auftragsfertigung',
            'slug' => 'eichenholz-schild-auftragsfertigung',
            'type' => 'physical',
            'status' => 'active',
            'price' => 4990,
            'quantity' => 0,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => true,
            'product_supplier_id' => $supplier->id,
            'configurator_settings' => [],
        ]);

        $detailsFallback = DeliverySetting::getDeliveryDetailsForProduct($productWithSupplierFallback, 1);
        $this->assertTrue($detailsFallback['is_backorder_procurement']);
        $this->assertSame(5, $detailsFallback['supplier_lead_min']);
        $this->assertSame(7, $detailsFallback['supplier_lead_max']);
        $this->assertSame(8, $detailsFallback['min_days']);
        $this->assertSame(12, $detailsFallback['max_days']);
    }

    public function test_system_delivery_display_shows_on_demand_manufacturing_when_out_of_stock(): void
    {
        $product = Product::create([
            'name' => '14k Armband Auftragsfertigung',
            'slug' => '14k-armband-auftragsfertigung',
            'type' => 'physical',
            'status' => 'active',
            'price' => 3990,
            'quantity' => 0,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => true,
            'is_personalizable' => true,
            'configurator_settings' => [
                'out_of_stock_lead_days_min' => 4,
                'out_of_stock_lead_days_max' => 5,
            ],
        ]);

        Livewire::test(SystemDeliveryDisplay::class, ['product' => $product])
            ->assertSee('Auftragsfertigung (Rohling wird auf Bestellung bezogen)')
            ->assertSee('7-10 Werktage');
    }

    public function test_quick_ai_product_creation_sets_supplier_lead_days_and_combined_delivery_attribute(): void
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-test@mein-seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        $supplier = ProductSupplier::create([
            'name' => 'Glücksfieber',
            'lead_time_land_days' => 4,
            'website' => 'https://www.gluecksfieber.de',
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ProductCreate::class)
            ->call('openQuickAiModal')
            ->set('quickAiName', 'Edelstahl Herz-Anhänger Vergoldet')
            ->set('quickAiReorderUrl', 'https://www.gluecksfieber.de/edelstahl-herz-anhaenger-20mm')
            ->set('quickAiPurchasePrice', '2,50')
            ->set('quickAiPrice', '34,90')
            ->set('quickAiLeadDaysMin', 4)
            ->set('quickAiLeadDaysMax', 6)
            ->call('createQuickAiProduct');

        $product = Product::where('name', 'Edelstahl Herz-Anhänger Vergoldet')->first();
        $this->assertNotNull($product);
        $this->assertSame(0, (int) $product->quantity);
        $this->assertTrue((bool) $product->continue_selling_when_out_of_stock);
        $this->assertSame(4, (int) ($product->configurator_settings['out_of_stock_lead_days_min'] ?? 0));
        $this->assertSame(6, (int) ($product->configurator_settings['out_of_stock_lead_days_max'] ?? 0));
        $this->assertStringContainsString('7-11 Werktage', (string) ($product->attributes['Lieferzeit'] ?? ''));
    }

    public function test_admin_can_toggle_supplier_ordered_status_on_order_item(): void
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-order@mein-seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        $supplier = ProductSupplier::create([
            'name' => 'PrintEquipment',
            'lead_time_land_days' => 4,
            'website' => 'https://www.printequipment.de',
        ]);

        $product = Product::create([
            'name' => 'Schieferherz XXL',
            'slug' => 'schieferherz-xxl',
            'type' => 'physical',
            'status' => 'active',
            'price' => 4490,
            'quantity' => 0,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => true,
            'product_supplier_id' => $supplier->id,
            'reorder_url' => 'https://www.printequipment.de/schieferherz-xxl',
        ]);

        $order = OrderOrder::create([
            'order_number' => 'SF-2026-9901',
            'status' => 'pending',
            'payment_status' => 'paid',
            'email' => 'kunde@example.com',
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'address' => 'Musterstr. 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'address' => 'Musterstr. 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'subtotal_price' => 4490,
            'shipping_price' => 0,
            'tax_amount' => 717,
            'total_price' => 4490,
        ]);

        $orderItem = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 4490,
            'tax_rate' => 19,
            'total_price' => 8980,
            'configuration' => [
                'supplier_procurement' => [
                    'required' => true,
                    'missing_qty' => 2,
                    'stock_at_order' => 0,
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'reorder_url' => $product->reorder_url,
                    'supplier_lead_min_days' => 4,
                    'supplier_lead_max_days' => 6,
                    'promised_min_days' => 7,
                    'promised_max_days' => 11,
                    'ordered_from_supplier' => false,
                    'ordered_at' => null,
                ],
            ],
        ]);

        $order->load('items.product.supplier');
        $this->assertTrue($order->hasSupplierProcurementItems());
        $this->assertTrue($order->hasPendingSupplierProcurement());
        $this->assertSame('https://www.printequipment.de/schieferherz-xxl', $orderItem->getSupplierReorderUrl());

        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSee('Rohling nachbestellen')
            ->call('toggleSupplierOrdered', $orderItem->id)
            ->assertSee('Rohling beim Lieferanten bestellt');

        $orderItem->refresh();
        $order->refresh()->load('items.product.supplier');
        $this->assertTrue($orderItem->isSupplierOrderPlaced());
        $this->assertFalse($order->hasPendingSupplierProcurement());
    }
}

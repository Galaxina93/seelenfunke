<?php

namespace Tests\Feature;

use App\Livewire\Customer\CustomerOrdersComponent;
use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DhlDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_order_to_completed_when_dhl_api_reports_delivered()
    {
        config(['services.dhl.tracking_api_key' => 'test-api-key']);

        Http::fake([
            'https://api-eu.dhl.com/track/shipments*' => Http::response([
                'shipments' => [
                    [
                        'status' => [
                            'statusCode' => 'delivered',
                            'status' => 'Zugestellt'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-DHL-001',
            'email' => 'kunde@example.com',
            'payment_status' => 'paid',
            'status' => 'shipped',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 5490,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => '00340434000000000001',
            'carrier' => 'DHL',
            'status' => 'shipped',
        ]);

        $this->artisan('dhl:check-delivery-status')
            ->assertExitCode(0);

        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals('delivered', $shipment->fresh()->status);
    }

    #[Test]
    public function it_keeps_order_in_shipped_when_dhl_reports_in_transit()
    {
        config(['services.dhl.tracking_api_key' => 'test-api-key']);

        Http::fake([
            'https://api-eu.dhl.com/track/shipments*' => Http::response([
                'shipments' => [
                    [
                        'status' => [
                            'statusCode' => 'in_transit',
                            'status' => 'In Zustellung'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-DHL-002',
            'email' => 'kunde@example.com',
            'payment_status' => 'paid',
            'status' => 'shipped',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 5490,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => '00340434000000000002',
            'carrier' => 'DHL',
            'status' => 'shipped',
        ]);

        $this->artisan('dhl:check-delivery-status')
            ->assertExitCode(0);

        $this->assertEquals('shipped', $order->fresh()->status);
        $this->assertEquals('shipped', $shipment->fresh()->status);
    }

    #[Test]
    public function it_triggers_auto_complete_fallback_after_7_days_when_api_fails()
    {
        config(['services.dhl.tracking_api_key' => 'test-api-key']);

        Http::fake([
            'https://api-eu.dhl.com/track/shipments*' => Http::response([
                'error' => 'Unauthorized'
            ], 401)
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-DHL-003',
            'email' => 'kunde@example.com',
            'payment_status' => 'paid',
            'status' => 'shipped',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 5490,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => '00340434000000000003',
            'carrier' => 'DHL',
            'status' => 'shipped',
        ]);

        // Älteres Datum direkt in DB setzen, damit diffInDays >= 7 greift
        DB::table('order_shipments')->where('id', $shipment->id)->update(['created_at' => now()->subDays(8)]);
        DB::table('order_orders')->where('id', $order->id)->update(['updated_at' => now()->subDays(8)]);

        $this->artisan('dhl:check-delivery-status', ['--auto-complete-days' => 7])
            ->assertExitCode(0);

        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals('delivered', $shipment->fresh()->status);
    }

    #[Test]
    public function it_does_not_trigger_auto_complete_fallback_before_threshold()
    {
        config(['services.dhl.tracking_api_key' => 'test-api-key']);

        Http::fake([
            'https://api-eu.dhl.com/track/shipments*' => Http::response([
                'error' => 'Unauthorized'
            ], 401)
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-DHL-004',
            'email' => 'kunde@example.com',
            'payment_status' => 'paid',
            'status' => 'shipped',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 5490,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => '00340434000000000004',
            'carrier' => 'DHL',
            'status' => 'shipped',
        ]);

        DB::table('order_shipments')->where('id', $shipment->id)->update(['created_at' => now()->subDays(3)]);
        DB::table('order_orders')->where('id', $order->id)->update(['updated_at' => now()->subDays(3)]);

        $this->artisan('dhl:check-delivery-status', ['--auto-complete-days' => 7])
            ->assertExitCode(0);

        $this->assertEquals('shipped', $order->fresh()->status);
        $this->assertEquals('shipped', $shipment->fresh()->status);
    }

    #[Test]
    public function it_allows_customer_to_confirm_delivery_in_orders_component()
    {
        $customer = Customer::create([
            'email' => 'kunde@example.com',
            'first_name' => 'Anna',
            'last_name' => 'Schmidt',
            'password' => bcrypt('password123'),
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-CUSTOMER-CONFIRM',
            'customer_id' => $customer->id,
            'email' => 'kunde@example.com',
            'payment_status' => 'paid',
            'status' => 'shipped',
            'subtotal_price' => 3000,
            'tax_amount' => 570,
            'shipping_price' => 490,
            'total_price' => 3490,
            'billing_address' => ['first_name' => 'Anna', 'last_name' => 'Schmidt'],
        ]);

        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => '00340434000000000005',
            'carrier' => 'DHL',
            'status' => 'shipped',
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('confirmDelivery', $order->id);

        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals('delivered', $shipment->fresh()->status);
    }

    #[Test]
    public function it_syncs_shipments_to_delivered_when_admin_updates_status_to_completed()
    {
        $admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'Boss',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->actingAs($admin, 'admin');

        $order = OrderOrder::create([
            'order_number' => 'ORD-ADMIN-SYNC',
            'email' => 'kunde@example.com',
            'payment_status' => 'paid',
            'status' => 'shipped',
            'subtotal_price' => 4000,
            'tax_amount' => 760,
            'shipping_price' => 490,
            'total_price' => 4490,
            'billing_address' => ['first_name' => 'Admin', 'last_name' => 'Test'],
        ]);

        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => '00340434000000000006',
            'carrier' => 'DHL',
            'status' => 'shipped',
        ]);

        Livewire::test(OrderOverview::class)
            ->call('updateStatus', $order->id, 'completed');

        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals('delivered', $shipment->fresh()->status);
    }
}

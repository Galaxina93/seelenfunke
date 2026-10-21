<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiCancellationStockTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_order_cancellation_restores_standard_stock_and_discards_laser_stock(): void
    {
        $admin = Admin::factory()->create();

        $stdProduct = Product::create([
            'name' => 'Standard Untersetzer',
            'slug' => 'standard-untersetzer',
            'price' => 1500,
            'quantity' => 10,
            'track_quantity' => true,
            'is_personalizable' => false,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $laserProduct = Product::create([
            'name' => 'Gelasertes Schild',
            'slug' => 'gelasertes-schild',
            'price' => 4500,
            'quantity' => 10,
            'track_quantity' => true,
            'is_personalizable' => true,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-API-CANCEL-01',
            'email' => 'kunde@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal_price' => 6000,
            'tax_amount' => 1140,
            'total_price' => 7140,
            'billing_address' => ['first_name' => 'Klaus', 'last_name' => 'Müller'],
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $stdProduct->id,
            'product_name' => $stdProduct->name,
            'quantity' => 2,
            'unit_price' => 1500,
            'total_price' => 3000,
            'configuration' => null,
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $laserProduct->id,
            'product_name' => $laserProduct->name,
            'quantity' => 1,
            'unit_price' => 4500,
            'total_price' => 4500,
            'configuration' => ['laser_text' => 'Familie Müller'],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/shop/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $order->fresh()->status);

        // Standard stock restored (+2 = 12)
        $this->assertEquals(12, $stdProduct->fresh()->quantity);

        // Laser product stock NOT restored (remains 10, scrap)
        $this->assertEquals(10, $laserProduct->fresh()->quantity);
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\AI\Functions\AiOrderFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiOrderFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_order_update_status_cancellation_triggers_proper_order_cancellation_handler()
    {
        $stdProduct = Product::create([
            'name' => 'Kerze Standard',
            'slug' => 'kerze-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 1500,
            'quantity' => 10,
            'track_quantity' => true,
            'is_personalizable' => false,
        ]);

        $laserProduct = Product::create([
            'name' => 'Laser Gravur Holz',
            'slug' => 'laser-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3000,
            'quantity' => 5,
            'track_quantity' => true,
        ]);

        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-AI-CANCEL-1',
            'email' => 'kunde@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 4500,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 4500,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        // 1 standard item
        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $stdProduct->id,
            'product_name' => $stdProduct->name,
            'quantity' => 2,
            'unit_price' => 1500,
            'total_price' => 3000,
            'configuration' => null,
        ]);

        // 1 personalized/laser item
        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $laserProduct->id,
            'product_name' => $laserProduct->name,
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
            'configuration' => [
                'texts' => [['text' => 'Individuelle Widmung']]
            ],
        ]);

        // Execute AI status update to cancelled
        $result = AiOrderFuncs::executeOrderUpdateStatus([
            'order_number' => 'ORD-AI-CANCEL-1',
            'status' => 'cancelled'
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('cancelled', $order->fresh()->status);

        // Standard stock MUST be restored (+2)
        $this->assertEquals(12, $stdProduct->fresh()->quantity);

        // Personalized/laser product stock MUST NEVER be restored (remains 5)
        $this->assertEquals(5, $laserProduct->fresh()->quantity);
    }

    public function test_ai_order_update_status_rejects_invalid_status()
    {
        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-AI-INV-1',
            'email' => 'kunde@example.com',
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal_price' => 1000,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 1000,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        $result = AiOrderFuncs::executeOrderUpdateStatus([
            'order_number' => 'ORD-AI-INV-1',
            'status' => 'hacked_status'
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Ungültiger Status', $result['message']);
        $this->assertEquals('pending', $order->fresh()->status);
    }
}

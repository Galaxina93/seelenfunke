<?php

namespace Tests\Feature\Order;

use App\Livewire\Shop\Order\OrderRevocations;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderRevocation;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderRevocationProcessingStockTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test Item',
            'slug' => 'test-item-' . Str::uuid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 1500,
            'quantity' => 10,
            'track_quantity' => true,
        ], $attributes));
    }

    public function test_guests_and_customers_cannot_mount_order_revocations(): void
    {
        Livewire::test(OrderRevocations::class)->assertStatus(403);

        $customer = Customer::factory()->create();
        Livewire::actingAs($customer, 'customer')
            ->test(OrderRevocations::class)
            ->assertStatus(403);
    }

    public function test_revocation_processing_restores_standard_stock_but_never_laser_stock(): void
    {
        Mail::fake();

        $admin = Admin::factory()->create();
        $customer = Customer::factory()->create();

        $stdProduct = $this->createProduct([
            'name' => 'Standard Tasse',
            'quantity' => 10,
            'is_personalizable' => false,
        ]);

        $laserProduct = $this->createProduct([
            'name' => 'Laser Gravur Brett',
            'quantity' => 5,
            'is_personalizable' => true,
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-REV-01',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 3500,
            'tax_amount' => 559,
            'total_price' => 3500,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Muster'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Muster'],
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $stdProduct->id,
            'product_name' => $stdProduct->name,
            'quantity' => 2,
            'unit_price' => 1500,
            'total_price' => 3000,
            'tax_rate' => 19,
            'configuration' => [],
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $laserProduct->id,
            'product_name' => $laserProduct->name,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'tax_rate' => 19,
            'configuration' => ['engraving_text' => 'Liebe Oma'], // Personalized laser item!
        ]);

        $revocation = OrderRevocation::create([
            'order_number' => $order->order_number,
            'name' => 'Max Muster',
            'email' => $customer->email,
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(OrderRevocations::class)
            ->call('markAsProcessed', $revocation->id);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('refunded', $order->payment_status);

        $stdProduct->refresh();
        $laserProduct->refresh();

        // Standard stock is restored (+2 -> 12)
        $this->assertEquals(12, $stdProduct->quantity);

        // Laser product stock is NEVER restored (remains 5 - strictly adheres to user domain rule!)
        $this->assertEquals(5, $laserProduct->quantity);
    }
}

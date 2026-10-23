<?php

namespace Tests\Feature\Order;

use App\Livewire\Shop\Order\OrderCheckout\OrderCheckout;
use App\Models\Cart\Cart;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutPaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer1;
    private Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer1 = Customer::create([
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->customer2 = Customer::create([
            'first_name' => 'Lucy',
            'last_name' => 'VanPelt',
            'email' => 'lucy@example.com',
            'password' => Hash::make('password123'),
        ]);

        $product = Product::create([
            'name' => 'Test Item',
            'slug' => 'test-item',
            'price' => 1000,
            'quantity' => 10,
            'track_quantity' => true,
            'status' => 'active',
        ]);

        $cart = Cart::firstOrCreate(['customer_id' => $this->customer1->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);
    }

    public function test_cannot_complete_foreign_order_via_handle_payment_success(): void
    {
        // Order belonging to Lucy
        $foreignOrder = OrderOrder::create([
            'order_number' => 'ORD-FOREIGN-PAY',
            'customer_id' => $this->customer2->id,
            'email' => $this->customer2->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'total_price' => 5950,
            'billing_address' => ['first_name' => 'Lucy', 'last_name' => 'VanPelt'],
            'shipping_address' => ['first_name' => 'Lucy', 'last_name' => 'VanPelt'],
        ]);

        Livewire::actingAs($this->customer1, 'customer')
            ->test(OrderCheckout::class)
            ->call('handlePaymentSuccess', (string) $foreignOrder->id)
            ->assertHasErrors(['payment']);

        $this->assertEquals('unpaid', $foreignOrder->fresh()->payment_status);
    }

    public function test_cannot_complete_paid_order_with_intent_mismatch(): void
    {
        $order = OrderOrder::create([
            'order_number' => 'ORD-INTENT-MISMATCH',
            'customer_id' => $this->customer1->id,
            'email' => $this->customer1->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'stripe_payment_intent_id' => 'pi_fake_123',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'total_price' => 5950,
            'billing_address' => ['first_name' => 'Charlie', 'last_name' => 'Brown'],
            'shipping_address' => ['first_name' => 'Charlie', 'last_name' => 'Brown'],
        ]);

        // Attempting to call handlePaymentSuccess without valid matching currentPaymentIntentId
        Livewire::actingAs($this->customer1, 'customer')
            ->test(OrderCheckout::class)
            ->call('handlePaymentSuccess', (string) $order->id)
            ->assertHasErrors(['payment']);

        $this->assertEquals('unpaid', $order->fresh()->payment_status);
    }
}

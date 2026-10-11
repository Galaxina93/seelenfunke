<?php

namespace Tests\Feature\Stripe;

use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Livewire\Shop\Order\OrderCheckout\OrderCheckoutSuccess;
use App\Models\Cart\Cart;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StripeCheckoutSuccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.secret' => 'sk_test_mock_secret',
        ]);

        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
    }

    protected function createTestOrder(array $attributes = []): OrderOrder
    {
        $customer = Customer::create([
            'email' => 'customer_success@example.com',
            'first_name' => 'Anna',
            'last_name' => 'Schmidt',
            'password' => bcrypt('secret123'),
        ]);

        return OrderOrder::create(array_merge([
            'order_number' => 'ORD-2026-SUCC01',
            'customer_id' => $customer->id,
            'email' => 'customer_success@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'billing_address' => [
                'first_name' => 'Anna',
                'last_name' => 'Schmidt',
                'company' => '',
                'address' => 'Musterweg 5',
                'postal_code' => '54321',
                'city' => 'Hamburg',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Anna',
                'last_name' => 'Schmidt',
                'company' => '',
                'address' => 'Musterweg 5',
                'postal_code' => '54321',
                'city' => 'Hamburg',
                'country' => 'DE',
            ],
            'subtotal_price' => 4000,
            'tax_amount' => 760,
            'shipping_price' => 495,
            'total_price' => 4760,
        ], $attributes));
    }

    #[Test]
    public function it_completes_order_via_checkout_success_when_intent_succeeds()
    {
        Queue::fake();

        $product = Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Success Test Produkt',
            'slug' => 'succ-test-' . uniqid(),
            'sku' => 'SUCC-' . uniqid(),
            'price' => 4000,
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => true,
            'quantity' => 15,
        ]);

        $intentId = 'pi_test_success_3ds_123';
        $order = $this->createTestOrder([
            'stripe_payment_intent_id' => $intentId,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 3,
            'unit_price' => 4000,
            'tax_rate' => 19.0,
            'total_price' => 12000,
        ]);

        // Complete the order via completePayment
        $order->completePayment($intentId);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        $product->refresh();
        $this->assertEquals(12, $product->quantity); // 15 - 3

        Queue::assertPushed(ProcessOrderDocumentsAndMails::class);
    }

    #[Test]
    public function it_renders_checkout_success_view()
    {
        Livewire::test(OrderCheckoutSuccess::class)
            ->assertStatus(200);
    }
}

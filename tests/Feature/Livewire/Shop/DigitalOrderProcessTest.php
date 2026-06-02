<?php

namespace Tests\Feature\Livewire\Shop;

use App\Livewire\Shop\Order\OrderCheckout\OrderCheckout as Checkout;
use App\Models\Cart\Cart;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class DigitalOrderProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create a digital product
        Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Digital eBook',
            'slug' => 'digital-ebook',
            'description' => 'A digital product.',
            'price' => 1999,
            'status' => 'active',
            'type' => 'digital',
            'digital_download_path' => 'downloads/ebook.pdf',
            'digital_filename' => 'ebook.pdf'
        ]);

        // 2. Create a physical product
        Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Physical Mug',
            'slug' => 'physical-mug',
            'description' => 'A physical product.',
            'price' => 1299,
            'status' => 'active',
            'type' => 'physical'
        ]);

        // Setup Dummy Admin
        $admin = \App\Models\Admin\Admin::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'email' => 'admin_shoptest@test.de',
            'first_name' => 'Hans',
            'last_name' => 'TestAdmin',
            'password' => bcrypt('password')
        ]);
        $this->actingAs($admin);

        config(['services.stripe.key' => '']);
        config(['services.stripe.secret' => '']);
        config(['shop.active_countries' => ['DE' => 'Deutschland']]);
    }

    public function test_pure_digital_order_completed_automatically()
    {
        Queue::fake();
        Session::start();

        $digitalProduct = Product::where('type', 'digital')->first();

        // 1. Manually populate cart with only digital product
        $cart = Cart::create(['session_id' => Session::getId()]);
        $cart->items()->create([
            'product_id' => $digitalProduct->id,
            'quantity' => 1,
            'unit_price' => $digitalProduct->price,
            'total_price' => $digitalProduct->price
        ]);

        // 2. Checkout
        Livewire::test(Checkout::class)
            ->set('email', 'test-digital@kunde.de')
            ->set('first_name', 'Max')
            ->set('last_name', 'Mustermann')
            ->set('address', 'Teststraße 123')
            ->set('city', 'Musterstadt')
            ->set('postal_code', '12345')
            ->set('country', 'DE')
            ->set('terms_accepted', true)
            ->set('privacy_accepted', true)
            ->call('validateAndCreateOrder');

        $order = OrderOrder::latest()->first();
        $this->assertNotNull($order);
        $this->assertTrue($order->isOnlyDigital());

        // Simulate Stripe webhook success (triggers status mapping)
        $order->update(['stripe_payment_intent_id' => 'pi_digital_123']);

        // Call handlePaymentSuccess directly
        Livewire::test(Checkout::class)
            ->call('handlePaymentSuccess', $order->id);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('completed', $order->status); // Purely digital order should go straight to completed!
    }

    public function test_mixed_order_pending_by_default()
    {
        Queue::fake();
        Session::start();

        $digitalProduct = Product::where('type', 'digital')->first();
        $physicalProduct = Product::where('type', 'physical')->first();

        // 1. Populate cart with mixed products
        $cart = Cart::create(['session_id' => Session::getId()]);
        $cart->items()->create([
            'product_id' => $digitalProduct->id,
            'quantity' => 1,
            'unit_price' => $digitalProduct->price,
            'total_price' => $digitalProduct->price
        ]);
        $cart->items()->create([
            'product_id' => $physicalProduct->id,
            'quantity' => 1,
            'unit_price' => $physicalProduct->price,
            'total_price' => $physicalProduct->price
        ]);

        // 2. Checkout
        Livewire::test(Checkout::class)
            ->set('email', 'test-mixed@kunde.de')
            ->set('first_name', 'Max')
            ->set('last_name', 'Mustermann')
            ->set('address', 'Teststraße 123')
            ->set('city', 'Musterstadt')
            ->set('postal_code', '12345')
            ->set('country', 'DE')
            ->set('terms_accepted', true)
            ->set('privacy_accepted', true)
            ->call('validateAndCreateOrder');

        $order = OrderOrder::latest()->first();
        $this->assertNotNull($order);
        $this->assertFalse($order->isOnlyDigital());

        // Simulate payment success
        $order->update(['stripe_payment_intent_id' => 'pi_mixed_123']);

        Livewire::test(Checkout::class)
            ->call('handlePaymentSuccess', $order->id);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('pending', $order->status); // Mixed order should stay pending (awaiting fulfillment of physical items)
    }
}

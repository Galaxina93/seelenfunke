<?php

namespace Tests\Feature;

use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingGiftVoucherLog;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GiftVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected array $dummyBillingAddress = [
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'company' => '',
        'address' => 'Teststraße 12',
        'postal_code' => '12345',
        'city' => 'Musterstadt',
        'country' => 'DE',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup simple config
        config(['shop.active_countries' => ['DE' => 'Deutschland']]);
        config(['services.stripe.secret' => 'test_secret']);

        // Seed settings
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
    }

    #[Test]
    public function it_generates_codes_with_correct_prefix()
    {
        $code = MarketingGiftVoucher::generateCode();
        
        $this->assertStringStartsWith('SEELENFUNKE-', $code);
        // Prefix (12 chars: 'SEELENFUNKE-') + random4 (4 chars) + dash (1 char) + random4 (4 chars) = 21 chars
        $this->assertEquals(21, strlen($code));
    }

    #[Test]
    public function it_creates_gift_vouchers_on_order_fulfillment()
    {
        Queue::fake();
        Mail::fake();

        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 5000,
            'status' => 'active',
            'type' => 'digital',
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-VOUCHER-01',
            'email' => 'customer@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => 5000,
            'subtotal_price' => 5000,
            'tax_amount' => 0,
            'billing_address' => $this->dummyBillingAddress,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 5000,
            'total_price' => 10000,
            'tax_rate' => 0.0,
            'configuration' => [
                'is_gift_voucher' => true,
                'amount_cents' => 5000,
                'recipient_name' => 'Jane Doe',
                'recipient_email' => 'jane@example.com',
                'personal_message' => 'For you!',
                'delivery_method' => 'email'
            ]
        ]);

        // Fulfill the order
        $order->completePayment('pi_test_123');

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('completed', $order->status); // Digital items only

        // Should create 2 vouchers since quantity is 2
        $vouchers = MarketingGiftVoucher::where('order_item_id', $item->id)->get();
        $this->assertCount(2, $vouchers);

        foreach ($vouchers as $voucher) {
            $this->assertStringStartsWith('SEELENFUNKE-', $voucher->code);
            $this->assertEquals(5000, $voucher->initial_value);
            $this->assertEquals(5000, $voucher->current_balance);
            $this->assertEquals('Jane Doe', $voucher->recipient_name);
            $this->assertEquals('jane@example.com', $voucher->recipient_email);
            $this->assertEquals('For you!', $voucher->personal_message);
            $this->assertEquals('email', $voucher->delivery_method);
            $this->assertTrue($voucher->is_active);
        }

        // Verify stock is reduced
        $this->assertEquals(8, $product->fresh()->quantity);
    }

    #[Test]
    public function it_prevents_duplicate_vouchers_upon_double_fulfillment()
    {
        Queue::fake();
        Mail::fake();

        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 5000,
            'status' => 'active',
            'type' => 'digital',
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-VOUCHER-02',
            'email' => 'customer@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => 5000,
            'subtotal_price' => 5000,
            'tax_amount' => 0,
            'billing_address' => $this->dummyBillingAddress,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
            'tax_rate' => 0.0,
            'configuration' => [
                'is_gift_voucher' => true,
                'amount_cents' => 5000,
                'recipient_name' => 'Jane Doe',
                'recipient_email' => 'jane@example.com',
                'personal_message' => 'For you!',
                'delivery_method' => 'email'
            ]
        ]);

        // Fulfill first time
        $order->completePayment('pi_test_123');

        $voucherCount = MarketingGiftVoucher::where('order_item_id', $item->id)->count();
        $this->assertEquals(1, $voucherCount);
        $this->assertEquals(9, $product->fresh()->quantity);

        // Fulfill second time (simulate redirect vs webhook race condition)
        // Reset payment status to simulate
        $order->update(['payment_status' => 'unpaid']);
        $order->completePayment('pi_test_123');

        // Total voucher count should still be 1 (idempotency check)
        $voucherCountAfter = MarketingGiftVoucher::where('order_item_id', $item->id)->count();
        $this->assertEquals(1, $voucherCountAfter);
    }

    #[Test]
    public function it_handles_partial_redemption()
    {
        Queue::fake();
        Mail::fake();

        // 1. Create a voucher
        $voucher = MarketingGiftVoucher::create([
            'code' => 'SEELENFUNKE-PART-IALS',
            'initial_value' => 5000, // 50.00 EUR
            'current_balance' => 5000,
            'recipient_name' => 'Jane Doe',
            'delivery_method' => 'email',
            'is_active' => true,
            'valid_until' => now()->addYears(3),
        ]);

        // 2. Create an order that redeems less than the voucher balance
        $order = OrderOrder::create([
            'order_number' => 'ORD-REDEMPTION-01',
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'coupon_code' => 'SEELENFUNKE-PART-IALS',
            'discount_amount' => 3000, // 30.00 EUR discount
            'total_price' => 1500, // 45.00 EUR gross - 30.00 EUR discount = 15.00 EUR remaining
            'subtotal_price' => 4500,
            'tax_amount' => 0,
            'billing_address' => $this->dummyBillingAddress,
        ]);

        // 3. Fulfill the order
        $order->completePayment('pi_redemption_123');

        // 4. Assertions
        $voucher->refresh();
        // Balance should go from 5000 to 2000
        $this->assertEquals(2000, $voucher->current_balance);
        $this->assertTrue($voucher->is_active);

        // Check logs
        $log = MarketingGiftVoucherLog::where('gift_voucher_id', $voucher->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals($order->id, $log->order_id);
        $this->assertEquals(3000, $log->amount);
        $this->assertEquals(2000, $log->remaining_balance);
    }

    #[Test]
    public function it_handles_full_redemption()
    {
        Queue::fake();
        Mail::fake();

        // 1. Create a voucher
        $voucher = MarketingGiftVoucher::create([
            'code' => 'SEELENFUNKE-FULL-REDEEM',
            'initial_value' => 5000, // 50.00 EUR
            'current_balance' => 5000,
            'recipient_name' => 'Jane Doe',
            'delivery_method' => 'email',
            'is_active' => true,
            'valid_until' => now()->addYears(3),
        ]);

        // 2. Create an order that redeems the full voucher balance
        $order = OrderOrder::create([
            'order_number' => 'ORD-REDEMPTION-02',
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'coupon_code' => 'SEELENFUNKE-FULL-REDEEM',
            'discount_amount' => 5000, // 50.00 EUR discount
            'total_price' => 2000, // 70.00 EUR gross - 50.00 EUR discount = 20.00 EUR remaining
            'subtotal_price' => 7000,
            'tax_amount' => 0,
            'billing_address' => $this->dummyBillingAddress,
        ]);

        // 3. Fulfill the order
        $order->completePayment('pi_redemption_456');

        // 4. Assertions
        $voucher->refresh();
        // Balance should go to 0 and voucher should be deactivated
        $this->assertEquals(0, $voucher->current_balance);
        $this->assertFalse($voucher->is_active);

        // Check logs
        $log = MarketingGiftVoucherLog::where('gift_voucher_id', $voucher->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals($order->id, $log->order_id);
        $this->assertEquals(5000, $log->amount);
        $this->assertEquals(0, $log->remaining_balance);
    }

    #[Test]
    public function it_validates_custom_amount_in_realtime()
    {
        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 0,
            'status' => 'active',
            'type' => 'digital',
        ]);

        // Valid values should not throw errors
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage::class)
            ->set('amount', 'custom')
            ->set('customAmount', 15)
            ->assertHasNoErrors(['customAmount']);

        // Invalid: below 5 €
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage::class)
            ->set('amount', 'custom')
            ->set('customAmount', 4)
            ->assertHasErrors(['customAmount' => 'min']);

        // Invalid: above 1000 €
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage::class)
            ->set('amount', 'custom')
            ->set('customAmount', 1005)
            ->assertHasErrors(['customAmount' => 'max']);

        // Invalid: not step of 5 €
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage::class)
            ->set('amount', 'custom')
            ->set('customAmount', 12)
            ->assertHasErrors(['customAmount']);
    }

    #[Test]
    public function it_fails_validation_on_purchase_page_if_message_exceeds_160_chars()
    {
        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 5000,
            'status' => 'active',
            'type' => 'digital',
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage::class)
            ->set('amount', 50)
            ->set('recipientName', 'Jane Doe')
            ->set('recipientEmail', 'jane@example.com')
            ->set('deliveryMethod', 'email')
            ->set('personalMessage', str_repeat('A', 161))
            ->call('addToCart')
            ->assertHasErrors(['personalMessage' => 'max']);
    }

    #[Test]
    public function it_truncates_personal_message_to_160_chars_on_order_fulfillment()
    {
        Queue::fake();
        Mail::fake();

        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 5000,
            'status' => 'active',
            'type' => 'digital',
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-VOUCHER-03',
            'email' => 'customer@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => 5000,
            'subtotal_price' => 5000,
            'tax_amount' => 0,
            'billing_address' => $this->dummyBillingAddress,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
            'tax_rate' => 0.0,
            'configuration' => [
                'is_gift_voucher' => true,
                'amount_cents' => 5000,
                'recipient_name' => 'Jane Doe',
                'recipient_email' => 'jane@example.com',
                'personal_message' => str_repeat('X', 200), // Exceeds 160 characters
                'delivery_method' => 'email'
            ]
        ]);

        $order->completePayment('pi_test_789');

        $voucher = MarketingGiftVoucher::where('order_item_id', $item->id)->first();
        $this->assertNotNull($voucher);
        $this->assertEquals(160, strlen($voucher->personal_message));
        $this->assertEquals(str_repeat('X', 160), $voucher->personal_message);
    }

    #[Test]
    public function it_does_not_apply_promotional_coupons_to_gift_vouchers()
    {
        // 1. Create a gift voucher product
        $productVoucher = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 5000,
            'status' => 'active',
            'type' => 'digital',
        ]);

        // 2. Create a regular product
        $productRegular = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Standard Schmuck',
            'slug' => 'standard-schmuck',
            'price' => 10000, // 100.00 EUR
            'status' => 'active',
            'type' => 'physical',
        ]);

        // 3. Create a promotional coupon (10% discount)
        $coupon = MarketingVoucher::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'title' => '10% Discount Code',
            'code' => 'TEST10',
            'type' => 'percent',
            'value' => 10, // 10%
            'is_active' => true,
        ]);

        $cartService = app(\App\Services\CartService::class);

        // Case A: Cart has ONLY a gift voucher
        $cartService->addItem($productVoucher, 1, [
            'is_gift_voucher' => true,
            'amount_cents' => 5000,
            'recipient_name' => 'Jane Doe',
            'delivery_method' => 'email'
        ]);

        $res = $cartService->applyCoupon('TEST10');
        $this->assertFalse($res['success']);
        $this->assertEquals('Rabattcodes können nicht auf den Kauf von Geschenkgutscheinen angewendet werden.', $res['message']);

        $totalsOnlyVoucher = $cartService->getTotals();
        $this->assertEquals(5000, $totalsOnlyVoucher['subtotal_gross']);
        $this->assertEquals(0, $totalsOnlyVoucher['discount_amount']);
        $this->assertEquals(5000, $totalsOnlyVoucher['total']);

        // Case B: Cart has BOTH a gift voucher and a regular product
        $cartService->addItem($productRegular, 1);

        $res2 = $cartService->applyCoupon('TEST10');
        $this->assertTrue($res2['success']);

        $totalsBoth = $cartService->getTotals();
        // Subtotal gross = 50.00 EUR (voucher) + 100.00 EUR (regular) = 15000 cents
        $this->assertEquals(15000, $totalsBoth['subtotal_gross']);
        // Discount should be 10% of 100.00 EUR = 10.00 EUR (1000 cents), NOT 15.00 EUR (1500 cents)!
        $this->assertEquals(1000, $totalsBoth['discount_amount']);
        // Total = 150.00 EUR - 10.00 EUR = 140.00 EUR (14000 cents)
        $this->assertEquals(14000, $totalsBoth['total']);
    }

    #[Test]
    public function it_checks_voucher_balance_successfully_when_valid()
    {
        $voucher = MarketingGiftVoucher::create([
            'code' => 'SEELENFUNKE-TEST-CHECK',
            'initial_value' => 7500, // 75.00 EUR
            'current_balance' => 7500,
            'recipient_name' => 'John Doe',
            'delivery_method' => 'email',
            'is_active' => true,
            'valid_until' => now()->addYears(3),
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherBalanceChecker::class)
            ->set('code', 'SEELENFUNKE-TEST-CHECK')
            ->call('checkBalance')
            ->assertHasNoErrors()
            ->assertSet('result', [
                'balance' => '75,00 €',
                'valid_until' => $voucher->valid_until->format('d.m.Y'),
            ]);
    }

    #[Test]
    public function it_fails_to_check_balance_if_invalid_or_expired()
    {
        // 1. Inactive voucher
        $voucherInactive = MarketingGiftVoucher::create([
            'code' => 'SEELENFUNKE-TEST-INACTIVE',
            'initial_value' => 5000,
            'current_balance' => 5000,
            'recipient_name' => 'John Doe',
            'delivery_method' => 'email',
            'is_active' => false,
            'valid_until' => now()->addYears(3),
        ]);

        // 2. Expired voucher
        $voucherExpired = MarketingGiftVoucher::create([
            'code' => 'SEELENFUNKE-TEST-EXPIRED',
            'initial_value' => 5000,
            'current_balance' => 5000,
            'recipient_name' => 'John Doe',
            'delivery_method' => 'email',
            'is_active' => true,
            'valid_until' => now()->subDay(),
        ]);

        // 3. Fully used voucher
        $voucherUsed = MarketingGiftVoucher::create([
            'code' => 'SEELENFUNKE-TEST-USED',
            'initial_value' => 5000,
            'current_balance' => 0,
            'recipient_name' => 'John Doe',
            'delivery_method' => 'email',
            'is_active' => true,
            'valid_until' => now()->addYears(3),
        ]);

        // Test non-existent code
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherBalanceChecker::class)
            ->set('code', 'NONEXISTENT')
            ->call('checkBalance')
            ->assertHasErrors(['code'])
            ->assertSet('result', null);

        // Test inactive
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherBalanceChecker::class)
            ->set('code', 'SEELENFUNKE-TEST-INACTIVE')
            ->call('checkBalance')
            ->assertHasErrors(['code'])
            ->assertSet('result', null);

        // Test expired
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherBalanceChecker::class)
            ->set('code', 'SEELENFUNKE-TEST-EXPIRED')
            ->call('checkBalance')
            ->assertHasErrors(['code'])
            ->assertSet('result', null);

        // Test used
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherBalanceChecker::class)
            ->set('code', 'SEELENFUNKE-TEST-USED')
            ->call('checkBalance')
            ->assertHasErrors(['code'])
            ->assertSet('result', null);
    }

    #[Test]
    public function it_rate_limits_balance_queries()
    {
        $component = \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherBalanceChecker::class);

        // Call 5 times (allowed)
        for ($i = 0; $i < 5; $i++) {
            $component->set('code', 'SEELENFUNKE-ANY')
                ->call('checkBalance');
        }

        // 6th call should fail due to rate limit
        $component->set('code', 'SEELENFUNKE-ANY')
            ->call('checkBalance')
            ->assertHasErrors(['code']);
    }

    #[Test]
    public function it_redirects_old_vouchers_url_to_product_page()
    {
        $response = $this->get('/gutscheine');
        $response->assertRedirect('/produkt/geschenkgutschein');
        $response->assertStatus(301);
    }

    #[Test]
    public function it_renders_gift_voucher_purchase_page_on_product_show()
    {
        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 0,
            'status' => 'active',
            'type' => 'digital',
        ]);

        $response = $this->get(route('product.show', $product->slug));
        $response->assertStatus(200);
        $response->assertSeeLivewire(\App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage::class);
    }

    #[Test]
    public function it_calculates_correct_shipping_cost_for_voucher_purchase_livewire()
    {
        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 0,
            'status' => 'active',
            'type' => 'digital',
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage::class)
            ->assertSet('deliveryMethod', 'email')
            ->assertSet('shippingCost', 0.0)
            ->set('deliveryMethod', 'post')
            ->assertSet('shippingCost', 3.5)
            ->set('amount', 50)
            ->set('recipientName', 'Max Mustermann')
            ->call('addToCart')
            ->assertRedirect(route('cart'));

        $cartService = app(\App\Services\CartService::class);
        $cart = $cartService->getCart();
        $this->assertCount(1, $cart->items);
        $item = $cart->items->first();
        $this->assertEquals('post', $item->configuration['delivery_method']);
        $this->assertEquals(350, $item->configuration['shipping_surcharge']);
    }

    #[Test]
    public function it_calculates_correct_totals_with_only_post_delivery_voucher()
    {
        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 0,
            'status' => 'active',
            'type' => 'digital',
        ]);

        $cartService = app(\App\Services\CartService::class);
        $cartService->addItem($product, 1, [
            'is_gift_voucher' => true,
            'amount_cents' => 5000, // 50.00 €
            'recipient_name' => 'Jane Doe',
            'delivery_method' => 'post',
            'shipping_surcharge' => 350,
        ]);

        $totals = $cartService->getTotals();
        $this->assertEquals(5000, $totals['subtotal_gross']);
        $this->assertEquals(350, $totals['shipping']);
        $this->assertEquals(5350, $totals['total']);
    }

    #[Test]
    public function it_calculates_correct_totals_with_only_email_delivery_voucher()
    {
        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 0,
            'status' => 'active',
            'type' => 'digital',
        ]);

        $cartService = app(\App\Services\CartService::class);
        $cartService->addItem($product, 1, [
            'is_gift_voucher' => true,
            'amount_cents' => 5000, // 50.00 €
            'recipient_name' => 'Jane Doe',
            'delivery_method' => 'email',
            'shipping_surcharge' => 0,
        ]);

        $totals = $cartService->getTotals();
        $this->assertEquals(5000, $totals['subtotal_gross']);
        $this->assertEquals(0, $totals['shipping']);
        $this->assertEquals(5000, $totals['total']);
    }

    #[Test]
    public function it_uses_standard_shipping_rate_when_other_physical_products_exist()
    {
        $productVoucher = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 0,
            'status' => 'active',
            'type' => 'digital',
        ]);

        $productPhysical = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Standard Schmuck',
            'slug' => 'standard-schmuck',
            'price' => 2500, // 25.00 €
            'status' => 'active',
            'type' => 'physical',
        ]);

        $cartService = app(\App\Services\CartService::class);
        // Add physical voucher (3.50 € surcharge when alone)
        $cartService->addItem($productVoucher, 1, [
            'is_gift_voucher' => true,
            'amount_cents' => 2000, // 20.00 €
            'recipient_name' => 'Jane Doe',
            'delivery_method' => 'post',
            'shipping_surcharge' => 350,
        ]);

        // Add standard physical product
        $cartService->addItem($productPhysical, 1);

        $totals = $cartService->getTotals();
        // Subtotal gross = 20.00 € + 25.00 € = 45.00 € (4500 cents)
        $this->assertEquals(4500, $totals['subtotal_gross']);
        // Standard shipping cost (4.90 €) applies because of the physical product, and subtotal 45.00 € is below 50.00 € free threshold
        $this->assertEquals(490, $totals['shipping']);
        $this->assertEquals(4990, $totals['total']);
    }

    #[Test]
    public function it_can_checkout_fully_paid_order_via_voucher_without_stripe()
    {
        // 1. Create a physical product
        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Der Seelenkristall',
            'slug' => 'der-seelenkristall',
            'price' => 3990, // 39.90 €
            'status' => 'active',
            'type' => 'physical',
        ]);

        // 2. Create a gift voucher with 50.00 € balance
        $voucher = MarketingGiftVoucher::create([
            'code' => 'SEELENFUNKE-FULL-COVER',
            'initial_value' => 5000,
            'current_balance' => 5000,
            'recipient_name' => 'Jane Doe',
            'delivery_method' => 'email',
            'is_active' => true,
            'valid_until' => now()->addYears(3),
        ]);

        $cartService = app(\App\Services\CartService::class);
        $cartService->addItem($product, 1);

        // Apply voucher
        $cartService->applyCoupon('SEELENFUNKE-FULL-COVER');

        $totals = $cartService->getTotals();
        // Order total should be 0 because 39.90 € + 4.90 € shipping = 44.80 € total, which is fully covered by the 50.00 € voucher
        $this->assertEquals(4480, $totals['subtotal_gross'] + $totals['shipping']);
        $this->assertEquals(4480, $totals['discount_amount']);
        $this->assertEquals(0, $totals['total']);

        // 3. Checkout
        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderCheckout\OrderCheckout::class)
            ->set('email', 'buyer@example.com')
            ->set('first_name', 'Max')
            ->set('last_name', 'Mustermann')
            ->set('address', 'Teststraße 123')
            ->set('city', 'Musterstadt')
            ->set('postal_code', '12345')
            ->set('country', 'DE')
            ->set('terms_accepted', true)
            ->set('privacy_accepted', true)
            ->call('validateAndCreateOrder')
            ->assertHasNoErrors();

        // The order should be created and immediately paid!
        $order = OrderOrder::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(0, $order->total_price);
        $this->assertEquals(4480, $order->discount_amount);
        
        // Voucher balance should be reduced: 50.00 - 44.80 = 5.20 € (520 cents)
        $voucher->refresh();
        $this->assertEquals(520, $voucher->current_balance);
        $this->assertTrue($voucher->is_active);
    }
}

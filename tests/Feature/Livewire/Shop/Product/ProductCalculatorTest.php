<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator as Calculator;
use App\Models\Product\Product;
use App\Models\Product\ProductTierPrice;
use App\Models\Order\OrderQuoteRequest;
use App\Mail\NewCalcMailToAdmin;
use App\Mail\NewCalcMailToCustomer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Cache::put('shop_capacity_level', 0); // Allow express delivery in tests
        
        // Populate standard tax rates for test
        DB::table('tax_rates')->insertOrIgnore([
            ['name' => 'Standard DE', 'rate' => 19.00, 'country_code' => 'DE', 'tax_class' => 'standard', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function it_can_calculate_multiple_products_and_volumes()
    {
        // 1. Create a base product
        $product = Product::create([
            'name' => 'Test Calculator Product',
            'slug' => 'test-calc-prod',
            'status' => 'active',
            'type' => 'physical',
            'price' => 1000, // 10,00 EUR
            'weight' => 0.5
        ]);

        // 2. Add Tier Pricing
        ProductTierPrice::create([
            'product_id' => $product->id,
            'qty' => 10,
            'percent' => 10, // 10% discount for 10+
        ]);

        ProductTierPrice::create([
            'product_id' => $product->id,
            'qty' => 50,
            'percent' => 20, // 20% discount for 50+
        ]);

        $component = Livewire::test(Calculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->assertSet('step', 1);

        // Add 5 items (No discount, expected 5 * 10 = 50 EUR gross)
        $component->dispatch('calculator-save', [
            'product_id' => $product->id,
            'qty' => 5,
        ]);
        
        $component->assertSet('gesamtKosten', 50.00)
                  ->assertSet('volumeDiscount', 0);

        // Update item to 10 items (10% discount -> 10 * 9 EUR = 90 EUR)
        $component->call('editItem', 0)
                  ->assertSet('step', 2);
                  
        $component->dispatch('calculator-save', [
            'product_id' => $product->id,
            'qty' => 10,
        ]);

        $component->assertSet('gesamtKosten', 90.00)
                  ->assertSet('volumeDiscount', 10.00); // 100 - 90 = 10 discount
    }

    #[Test]
    public function it_handles_express_delivery_and_validation()
    {
        $product = Product::create([
            'name' => 'Express Prod', 'slug' => 'ex-prod', 
            'status' => 'active', 'type' => 'physical', 'price' => 5000, 
            'weight' => 1
        ]);

        $component = Livewire::test(Calculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->dispatch('calculator-save', [
                'product_id' => $product->id,
                'qty' => 1,
            ]);

        $component->assertSet('gesamtKosten', 50.00);

        // Enable Express by updating the item configuration
        $component->call('editItem', 0)
                  ->dispatch('calculator-save', [
                      'product_id' => $product->id,
                      'qty' => 1,
                      'is_express' => true
                  ])
                  ->assertSet('gesamtKosten', 60.00); // 50 + 10 (20% express_surcharge)

        // Proceed to next step
        $component->call('goNext')
                  ->assertHasNoErrors()
                  ->assertSet('step', 3);
    }

    #[Test]
    public function it_submits_quote_and_sends_mails()
    {
        Mail::fake();

        $product = Product::create([
            'name' => 'Mail Prod', 'slug' => 'mail-prod', 
            'status' => 'active', 'type' => 'physical', 'price' => 2000, 
            'weight' => 2
        ]);

        $component = Livewire::test(Calculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->dispatch('calculator-save', [
                'product_id' => $product->id,
                'qty' => 2,
            ])
            ->set('form.vorname', 'Max')
            ->set('form.nachname', 'Mustermann')
            ->set('form.email', 'max@example.com')
            ->set('form.country', 'DE')
            ->set('form.telefon', '0123456789')
            ->call('submit');

        $component->assertSet('step', 4)
                  ->assertSee('erfolgreich');

        // Verify Database Record
        $this->assertDatabaseHas('order_quote_requests', [
            'email' => 'max@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'gross_total' => 4490, // 2 * 20 EUR = 40 EUR + 4.90 Shipping (because < 50 EUR DE) -> 44.90 * 100
        ]);

        $quote = OrderQuoteRequest::where('email', 'max@example.com')->first();
        $this->assertDatabaseHas('order_quote_request_items', [
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Verify Mails
        Mail::assertQueued(NewCalcMailToCustomer::class, function ($mail) {
            return $mail->hasTo('max@example.com');
        });

        Mail::assertQueued(NewCalcMailToAdmin::class, function ($mail) {
            $owner_mail = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
            return $mail->hasTo($owner_mail);
        });
    }

    #[Test]
    public function it_saves_item_tax_rate_and_preserves_quote_price_in_checkout()
    {
        // 1. Create a product with reduced tax rate (e.g. 7%) and a specific price (e.g. 15 EUR)
        $product = Product::create([
            'name' => '7% Product',
            'slug' => '7-percent-prod',
            'status' => 'active',
            'type' => 'physical',
            'price' => 1500, // 15.00 EUR
            'tax_class' => 'reduced',
        ]);

        // 2. Add to calculator and submit a quote request
        $component = Livewire::test(Calculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->dispatch('calculator-save', [
                'product_id' => $product->id,
                'qty' => 10,
            ])
            ->set('form.vorname', 'John')
            ->set('form.nachname', 'Doe')
            ->set('form.email', 'john@example.com')
            ->set('form.country', 'DE')
            ->call('submit');

        $component->assertSet('step', 4);

        // 3. Verify that the quote request item in DB has the 7% tax rate saved!
        $quote = OrderQuoteRequest::where('email', 'john@example.com')->first();
        $this->assertNotNull($quote);
        
        $this->assertDatabaseHas('order_quote_request_items', [
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'tax_rate' => 7.00,
        ]);

        // 4. Change product price in database to 25.00 EUR (live price increases)
        $product->update(['price' => 2500]);

        // 5. Customer accepts quote and goes to checkout
        $cartService = app(\App\Services\CartService::class);
        
        // Simulating OrderQuoteAcceptance proceedToCheckout:
        session()->put('checkout_from_quote_id', $quote->id);
        
        $cart = $cartService->getCart();
        $cart->items()->delete();
        
        $cartService->addItem($product, 10, []);
        
        // Retrieve cart totals
        $totals = $cartService->calculateTotals($cart, 'DE');
        
        // 6. Verify that the unit price in cart is still 15.00 EUR (snapshot quote price), NOT 25.00 EUR!
        $cartItem = $cart->items()->where('product_id', $product->id)->first();
        $this->assertEquals(1500, $cartItem->unit_price);
        $this->assertEquals(15000, $totals['subtotal_gross'] ?? $totals['subtotal_original'] ?? 0);
    }

    #[Test]
    public function it_copies_tax_rate_on_backend_conversion()
    {
        $product = Product::create([
            'name' => '7% Prod backend',
            'slug' => '7-percent-backend',
            'status' => 'active',
            'type' => 'physical',
            'price' => 1000,
            'tax_class' => 'reduced',
        ]);

        $quote = OrderQuoteRequest::create([
            'quote_number' => 'AN-TEST-BACKEND',
            'email' => 'backend@example.com',
            'first_name' => 'Test',
            'last_name' => 'Backend',
            'net_total' => 1000,
            'tax_total' => 70,
            'gross_total' => 1070,
        ]);

        $quoteItem = \App\Models\Order\OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 1000,
            'tax_rate' => 7.00,
            'total_price' => 1000,
        ]);

        // Call convertToOrder on Livewire component OrderQuoteRequests
        Livewire::test(\App\Livewire\Shop\Order\OrderQuoteRequests::class)
            ->call('convertToOrder', $quote->id, 'invoice');

        // Check if order item has the 7% tax rate copied
        $order = \App\Models\Order\OrderOrder::where('email', 'backend@example.com')->first();
        $this->assertNotNull($order);
        
        $this->assertDatabaseHas('order_order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'tax_rate' => 7.00,
        ]);
    }
}

<?php

namespace Tests\Feature\Order;

use App\Livewire\Shop\Order\OrderQuoteAcceptance;
use App\Models\Customer\Customer;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderQuoteRequestItem;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderQuoteAcceptanceQuantitySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_excessive_quantity_is_clamped_to_ten_thousand(): void
    {
        $customer = Customer::factory()->create();

        $product = Product::create([
            'name' => 'Kerze Standard',
            'slug' => 'kerze-standard-' . Str::uuid(),
            'price' => 1000,
            'quantity' => 100,
            'track_quantity' => true,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $quote = OrderQuoteRequest::create([
            'quote_number' => 'ANG-2026-TEST',
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'email' => $customer->email,
            'status' => 'open',
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
            'net_total' => 1000,
            'tax_total' => 190,
            'gross_total' => 1190,
            'is_express' => false,
        ]);

        $item = OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'tax_rate' => 19,
        ]);

        $component = Livewire::test(OrderQuoteAcceptance::class, ['token' => $quote->token]);
        $component->call('editItem', $item->id);

        // Attempt integer overflow with extreme qty
        $component->dispatch('calculator-save', [
            'qty' => 999999999,
        ]);

        $item->refresh();
        // Quantity should be clamped to 10000 maximum!
        $this->assertEquals(10000, $item->quantity);
    }

    public function test_b2b_quote_with_quantity_over_100_retains_full_quantity_and_quote_unit_price_in_cart(): void
    {
        $product = Product::create([
            'name' => 'B2B Schlüsselanhänger',
            'slug' => 'b2b-schluesselanhaenger-' . Str::uuid(),
            'price' => 1500,
            'quantity' => 500,
            'track_quantity' => true,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $quote = OrderQuoteRequest::create([
            'quote_number' => 'ANG-2026-B2B250',
            'first_name' => 'Anna',
            'last_name' => 'Schmidt',
            'company' => 'Schmidt GmbH',
            'email' => 'anna@schmidt-gmbh.de',
            'status' => 'open',
            'token' => Str::random(40),
            'expires_at' => now()->addDays(14),
            'net_total' => 210084,
            'tax_total' => 39916,
            'gross_total' => 250000,
            'is_express' => false,
        ]);

        OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 250,
            'unit_price' => 1000, // Sonderpreis 10,00 € statt 15,00 €
            'total_price' => 250000,
            'tax_rate' => 19,
            'configuration' => [],
        ]);

        Livewire::test(OrderQuoteAcceptance::class, ['token' => $quote->token])
            ->call('proceedToCheckout')
            ->assertRedirect(route('checkout'));

        $cart = app(\App\Services\CartService::class)->getCart(false);
        $this->assertNotNull($cart);
        $cartItem = $cart->items()->first();
        $this->assertNotNull($cartItem);
        $this->assertSame(250, (int) $cartItem->quantity);
        $this->assertSame(1000, (int) $cartItem->unit_price);
        $this->assertSame(250000, (int) $cartItem->total_price);
    }
}

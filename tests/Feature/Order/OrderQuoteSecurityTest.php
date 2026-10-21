<?php

namespace Tests\Feature\Order;

use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderQuoteRequestItem;
use App\Models\Product\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderQuoteSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_is_valid_works_safely_with_null_expires_at(): void
    {
        $quote = OrderQuoteRequest::create([
            'quote_number' => 'AN-NULL-001',
            'email' => 'kunde@example.com',
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'status' => 'open',
            'expires_at' => null,
            'net_total' => 1000,
            'tax_total' => 190,
            'gross_total' => 1190,
        ]);

        $this->assertTrue($quote->isValid());
    }

    public function test_save_item_enforces_minimum_quantity_and_rejects_rejected_quotes(): void
    {
        $product = Product::create([
            'name' => 'Gravur Holz',
            'slug' => 'gravur-holz',
            'price' => 1000,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $quote = OrderQuoteRequest::create([
            'quote_number' => 'AN-REJ-002',
            'token' => 'safe_token_test_123',
            'email' => 'kunde@example.com',
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'status' => 'open',
            'expires_at' => now()->addDays(7),
            'net_total' => 1000,
            'tax_total' => 190,
            'gross_total' => 1190,
        ]);

        $item = OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'tax_rate' => 19.0,
        ]);

        $component = Livewire::test(\App\Livewire\Shop\Order\OrderQuoteAcceptance::class, ['token' => $quote->token])
            ->call('editItem', $item->id);

        // Try submitting negative quantity via calculator-save event
        $component->dispatch('calculator-save', ['qty' => -5]);

        $item->refresh();
        // Quantity must be sanitized to at least 1, not -5!
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(1000, $item->total_price);

        // Now reject the quote
        $quote->update(['status' => 'rejected']);

        $component->call('editItem', $item->id);
        $component->dispatch('calculator-save', ['qty' => 50]);

        $item->refresh();
        // Modification should NOT have applied to rejected quote
        $this->assertEquals(1, $item->quantity);
    }

    public function test_cart_service_ignores_expired_quote_in_session(): void
    {
        $product = Product::create([
            'name' => 'Laser Cut',
            'slug' => 'laser-cut',
            'price' => 3000,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $quote = OrderQuoteRequest::create([
            'quote_number' => 'AN-EXP-003',
            'token' => 'expired_token_abc',
            'email' => 'kunde@example.com',
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'status' => 'open',
            'expires_at' => now()->subDay(), // Expired yesterday
            'net_total' => 1000,
            'tax_total' => 190,
            'gross_total' => 1190,
        ]);

        OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 500, // heavily discounted price in expired quote
            'total_price' => 500,
            'tax_rate' => 19.0,
        ]);

        session(['checkout_from_quote_id' => $quote->id]);

        $cartService = app(CartService::class);
        $resolvedPrice = $cartService->resolveItemUnitPrice($product, 1);

        // Must NOT return discounted 500 from expired quote, must return standard 3000
        $this->assertEquals(3000, $resolvedPrice);
        $this->assertNull(session('checkout_from_quote_id'));
    }
}

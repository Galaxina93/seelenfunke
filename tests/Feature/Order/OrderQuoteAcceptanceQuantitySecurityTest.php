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
}

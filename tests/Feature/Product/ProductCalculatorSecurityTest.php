<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCalculatorSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Holzherz',
            'slug' => 'holzherz-' . Str::uuid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2000,
        ], $attributes));
    }

    public function test_negative_or_zero_quantity_is_sanitized_to_at_least_one(): void
    {
        Mail::fake();

        $product = $this->createProduct([
            'price' => 2000,
        ]);

        $component = Livewire::test(ProductCalculator::class)
            ->call('saveItemFromConfigurator', [
                'product_id' => $product->id,
                'qty' => -5,
                'text' => 'Test Gravur',
            ]);

        $cartItems = $component->get('cartItems');
        $this->assertCount(1, $cartItems);
        $this->assertEquals(1, $cartItems[0]['qty']);

        // Now test zero
        $component->call('saveItemFromConfigurator', [
            'product_id' => $product->id,
            'qty' => 0,
        ]);

        $cartItems = $component->get('cartItems');
        $this->assertEquals(1, $cartItems[1]['qty']);
    }

    public function test_invalid_product_id_is_safely_ignored(): void
    {
        Livewire::test(ProductCalculator::class)
            ->call('saveItemFromConfigurator', [
                'product_id' => 999999,
                'qty' => 3,
            ])
            ->assertSet('cartItems', []);
    }

    public function test_calculator_quote_submission_is_rate_limited(): void
    {
        Mail::fake();
        RateLimiter::clear('calculator-submit:127.0.0.1');

        $product = $this->createProduct([
            'price' => 1500,
        ]);

        $test = Livewire::test(ProductCalculator::class)
            ->set('agb_accepted', true)
            ->call('saveItemFromConfigurator', [
                'product_id' => $product->id,
                'qty' => 2,
            ])
            ->set('form.vorname', 'Erika')
            ->set('form.nachname', 'Muster')
            ->set('form.email', 'erika@example.com')
            ->set('form.country', 'DE');

        for ($i = 0; $i < 5; $i++) {
            $test->call('submit');
            // Re-add items for next submit
            $test->call('saveItemFromConfigurator', [
                'product_id' => $product->id,
                'qty' => 1,
            ]);
        }

        // 6th attempt should trigger rate limiter error
        $test->call('submit')
            ->assertHasErrors(['cart']);
    }
}

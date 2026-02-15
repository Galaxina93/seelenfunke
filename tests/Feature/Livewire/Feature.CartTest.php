<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Shop\Cart\Cart;
use App\Models\Product\Product;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_product_to_cart()
    {
        $product = Product::factory()->create(['price' => 3990, 'status' => 'active']);

        Livewire::test(Cart::class)
            ->call('addToCartHandler', $product->id, 1)
            ->assertDispatched('cart-updated')
            ->assertSee($product->name);
    }

    public function test_coupon_validation()
    {
        Livewire::test(Cart::class)
            ->set('couponCodeInput', 'INVALID123')
            ->call('applyCoupon')
            ->assertHasErrors(['couponCodeInput']);
    }
}

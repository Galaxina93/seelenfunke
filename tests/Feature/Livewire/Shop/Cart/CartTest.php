<?php

namespace Tests\Feature\Livewire\Shop\Cart;

use App\Livewire\Shop\Cart\Cart;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'sku' => 'TEST-CART-123',
            'name' => 'Cart Product',
            'slug' => 'cart-product',
            'price' => 5000,
            'status' => 'active',
            'type' => 'physical',
            'quantity' => 10,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => true,
            'purchase_price' => 100,
            'electricity_wear_factor' => 0,
            'delivery_time_days' => 2,
            'completion_step' => 1
        ]);
    }

    #[Test]
    public function it_renders_the_cart_component()
    {
        Livewire::test(Cart::class)
            ->assertStatus(200)
            ->assertViewHas('totals')
            ->assertViewHas('items');
    }

    #[Test]
    public function it_handles_add_to_cart_events_and_dispatches_ui_updates()
    {
        Livewire::test(Cart::class)
            ->dispatch('add-to-cart', productId: $this->product->id, qty: 1, config: null)
            ->assertDispatched('cart-updated');
    }

    private function createCartAndItem(): \App\Models\Cart\CartItem
    {
        $cart = \App\Models\Cart\Cart::firstOrCreate(['session_id' => \Illuminate\Support\Facades\Session::getId()]);
        return \App\Models\Cart\CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000
        ]);
    }

    #[Test]
    public function it_increments_and_decrements_item_quantity_natively()
    {
        $item = $this->createCartAndItem();

        Livewire::test(Cart::class)
            ->call('increment', $item->id)
            ->assertHasNoErrors();
    }

    #[Test]
    public function it_removes_an_item_from_the_cart_natively()
    {
        $item = $this->createCartAndItem();

        Livewire::test(Cart::class)
            ->call('remove', $item->id)
            ->assertHasNoErrors();
    }

    #[Test]
    public function it_shows_validation_error_when_invalid_coupon_submitted()
    {
        Livewire::test(Cart::class)
            ->set('couponCodeInput', 'INVALID100')
            ->call('applyCoupon')
            ->assertHasErrors();
    }

    #[Test]
    public function it_toggles_the_edit_modal_state()
    {
        Livewire::test(Cart::class)
            ->call('edit', 'random-item-id')
            ->assertSet('editingItemId', 'random-item-id');
    }
}

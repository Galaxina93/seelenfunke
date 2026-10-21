<?php

namespace Tests\Feature\Cart;

use App\Models\Admin\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Product\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_update_or_remove_another_carts_item()
    {
        $cartService = app(CartService::class);

        $product = Product::create([
            'id' => Str::uuid(),
            'name' => 'Kaffeetasse',
            'slug' => 'kaffeetasse',
            'price' => 2000,
            'quantity' => 50,
            'track_quantity' => true,
            'status' => 'active',
        ]);

        // Cart A (Victim)
        $cartA = Cart::create([
            'id' => Str::uuid(),
            'session_id' => 'session_victim_aaa',
        ]);

        $itemA = CartItem::create([
            'id' => Str::uuid(),
            'cart_id' => $cartA->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 2000,
            'total_price' => 4000,
        ]);

        // Cart B (Attacker) - active session
        session(['cart_id' => Str::uuid()->toString()]);
        $activeCart = $cartService->getCart();
        $this->assertNotEquals($cartA->id, $activeCart->id);

        // 1. Attacker attempts to update quantity of Item A
        $cartService->updateQuantity($itemA->id, 99);
        $itemA->refresh();
        $this->assertEquals(2, $itemA->quantity, 'Quantity of CartItem A must not be modified by external session');

        // 2. Attacker attempts to update item with configuration
        $cartService->updateItem($itemA->id, 5, ['laser_text' => 'Hacked']);
        $itemA->refresh();
        $this->assertEquals(2, $itemA->quantity);
        $this->assertNull($itemA->configuration);

        // 3. Attacker attempts to remove Item A
        $cartService->removeItem($itemA->id);
        $this->assertDatabaseHas('cart_items', ['id' => $itemA->id]);

        // 4. Admin CAN manage the cart
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $cartService->updateQuantity($itemA->id, 10);
        $itemA->refresh();
        $this->assertEquals(10, $itemA->quantity, 'Admin should be permitted to update items across carts');
    }
}

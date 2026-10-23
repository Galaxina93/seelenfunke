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

    public function test_gift_voucher_price_and_flag_tampering_are_blocked()
    {
        $cartService = app(CartService::class);

        $regularProduct = Product::create([
            'id' => Str::uuid(),
            'name' => 'Schlüsselanhänger',
            'slug' => 'schluesselanhaenger',
            'price' => 990,
            'quantity' => 50,
            'track_quantity' => true,
            'status' => 'active',
        ]);

        // 1. Attempt to inject is_gift_voucher and 500€ amount_cents into a 9.90€ regular product
        $cartService->addItem($regularProduct, 1, [
            'is_gift_voucher' => true,
            'amount_cents' => 50000,
            'text' => 'Mein Text',
        ]);

        $cart = $cartService->getCart();
        $item = $cart->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals(990, $item->unit_price);
        $this->assertArrayNotHasKey('is_gift_voucher', $item->configuration ?? []);
        $this->assertArrayNotHasKey('amount_cents', $item->configuration ?? []);

        // 2. Attempt negative amount_cents on actual gift voucher product
        $voucherProduct = Product::create([
            'id' => Str::uuid(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 2500,
            'type' => 'digital',
            'status' => 'active',
        ]);

        $cartService->addItem($voucherProduct, 1, [
            'is_gift_voucher' => true,
            'amount_cents' => -50000,
        ]);

        $voucherItem = $cart->items()->where('product_id', $voucherProduct->id)->first();
        $this->assertNotNull($voucherItem);
        $this->assertEquals(500, $voucherItem->unit_price, 'Negative gift voucher amount must be clamped to minimum 5.00 € (500 cents)');
        $this->assertEquals(500, $voucherItem->configuration['amount_cents']);
    }
}

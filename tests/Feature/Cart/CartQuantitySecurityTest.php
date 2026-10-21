<?php

namespace Tests\Feature\Cart;

use App\Models\Product\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartQuantitySecurityTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_converts_negative_quantity_to_one_when_adding_item()
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Warenkorb Test',
            'slug' => 'warenkorb-test-' . Str::random(5),
            'price' => 1500,
            'status' => 'active',
        ]);

        $cartService = app(CartService::class);
        $cartService->addItem($product, -5);

        $cart = $cartService->getCart();
        $this->assertCount(1, $cart->items);
        $this->assertEquals(1, $cart->items->first()->quantity);
        $this->assertEquals(1500, $cart->items->first()->unit_price);
        $this->assertEquals(1500, $cart->items->first()->unit_price * $cart->items->first()->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_removes_item_when_updated_with_zero_or_negative_quantity()
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Warenkorb Test 2',
            'slug' => 'warenkorb-test-2-' . Str::random(5),
            'price' => 2000,
            'status' => 'active',
        ]);

        $cartService = app(CartService::class);
        $cartService->addItem($product, 2);

        $cart = $cartService->getCart();
        $item = $cart->items->first();

        // Update item with quantity 0
        $cartService->updateItem($item->id, 0);

        $cart->refresh();
        $this->assertCount(0, $cart->items);
    }
}

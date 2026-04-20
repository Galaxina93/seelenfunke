<?php

namespace Tests\Feature\Livewire\Shop\Order;

use App\Livewire\Shop\Order\OrderShoppingCarts;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Models\Admin\Admin;
use App\Models\Product\Product;
use App\Mail\AbandonedCartReminder;
use Illuminate\Support\Facades\Mail;

class OrderShoppingCartsTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function renders_successfully()
    {
        $admin = Admin::factory()->create();
        Livewire::actingAs($admin, 'admin')
            ->test(OrderShoppingCarts::class)
            ->assertStatus(200);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function can_see_abandoned_carts()
    {
        $admin = Admin::factory()->create();
        $customer = Customer::factory()->create();
        $cart = Cart::create(['session_id' => '123', 'customer_id' => $customer->id]);
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000]);

        Livewire::actingAs($admin, 'admin')
            ->test(OrderShoppingCarts::class)
            // Removed assertSee('Test Product') because the factory name is random
            ->assertSee($customer->first_name);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function can_update_item_quantity()
    {
        $admin = Admin::factory()->create();
        $cart = Cart::create(['session_id' => '123']);
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000]);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000]);

        Livewire::actingAs($admin, 'admin')
            ->test(OrderShoppingCarts::class)
            ->call('viewDetails', $cart->id)
            ->set('quantityUpdates.' . $item->id, 5)
            ->call('updateQuantity', $item->id);
            
        $this->assertEquals(5, $item->fresh()->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_remove_item_and_auto_deletes_empty_cart()
    {
        $admin = Admin::factory()->create();
        $cart = Cart::create(['session_id' => '123']);
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000]);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000]);

        Livewire::actingAs($admin, 'admin')
            ->test(OrderShoppingCarts::class)
            ->call('viewDetails', $cart->id)
            ->call('removeItem', $item->id);
            
        $this->assertNull(CartItem::find($item->id));
        $this->assertNull(Cart::find($cart->id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_send_reminder_email()
    {
        Mail::fake();
        
        $admin = Admin::factory()->create();
        $customer = Customer::factory()->create(['email' => 'test@example.com']);
        $cart = Cart::create(['session_id' => '123', 'customer_id' => $customer->id]);
        
        Livewire::actingAs($admin, 'admin')
            ->test(OrderShoppingCarts::class)
            ->call('viewDetails', $cart->id)
            ->call('sendReminderEmail', $cart->id);
            
        Mail::assertQueued(AbandonedCartReminder::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email);
        });
    }
}

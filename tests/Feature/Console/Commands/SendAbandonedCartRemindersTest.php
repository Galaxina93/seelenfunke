<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Customer\Customer;
use App\Models\System\SystemSetting;
use App\Models\Product\Product;
use App\Mail\AbandonedCartReminder;

class SendAbandonedCartRemindersTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sends_reminders_for_eligible_carts()
    {
        Mail::fake();
        SystemSetting::create(['key' => 'cart_abandoned_red_hours', 'value' => '3']);

        $customer = Customer::factory()->create(['email' => 'test@example.com']);
        $cart = Cart::create([
            'session_id' => 'abc',
            'customer_id' => $customer->id,
        ]);

        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
        
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(4)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertQueued(AbandonedCartReminder::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email);
        });

        $this->assertNotNull($cart->fresh()->reminder_email_sent_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_send_reminders_if_already_sent()
    {
        Mail::fake();
        SystemSetting::create(['key' => 'cart_abandoned_red_hours', 'value' => '3']);

        $customer = Customer::factory()->create(['email' => 'test2@example.com']);
        $cart = Cart::create([
            'session_id' => 'def',
            'customer_id' => $customer->id,
            'reminder_email_sent_at' => now()->subHour()
        ]);
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(4)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_send_reminders_if_too_recent()
    {
        Mail::fake();
        SystemSetting::create(['key' => 'cart_abandoned_red_hours', 'value' => '3']);

        $customer = Customer::factory()->create(['email' => 'test3@example.com']);
        $cart = Cart::create([
            'session_id' => 'ghi',
            'customer_id' => $customer->id,
        ]);
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(2)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_send_reminders_for_empty_carts()
    {
        Mail::fake();
        SystemSetting::create(['key' => 'cart_abandoned_red_hours', 'value' => '3']);

        $customer = Customer::factory()->create(['email' => 'test4@example.com']);
        $cart = Cart::create([
            'session_id' => 'jkl',
            'customer_id' => $customer->id,
        ]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(4)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_send_reminders_to_guests()
    {
        Mail::fake();
        SystemSetting::create(['key' => 'cart_abandoned_red_hours', 'value' => '3']);

        $cart = Cart::create([
            'session_id' => 'mno',
            'customer_id' => null,
        ]);
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(4)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertNothingQueued();
    }
}

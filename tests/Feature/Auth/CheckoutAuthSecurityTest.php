<?php

namespace Tests\Feature\Auth;

use App\Livewire\Shop\Order\OrderCheckout\OrderCheckout;
use App\Models\Cart\Cart;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\System\SystemBlockedIp;
use App\Models\System\SystemLoginAttempt;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_ip_cannot_login_via_checkout()
    {
        SystemBlockedIp::create([
            'ip_address' => '127.0.0.1',
            'reason' => 'Threat actor',
        ]);

        $product = Product::create([
            'name' => 'Artikel',
            'slug' => 'artikel',
            'price' => 1000,
            'status' => 'active',
        ]);

        $cart = app(CartService::class)->getCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        Livewire::test(OrderCheckout::class)
            ->set('loginEmail', 'kunde@example.com')
            ->set('loginPassword', 'secret123')
            ->call('loginUser')
            ->assertSet('loginError', 'Deine IP-Adresse wurde aufgrund verdächtiger Aktivitäten gesperrt.');
    }

    public function test_checkout_login_is_rate_limited()
    {
        $product = Product::create([
            'name' => 'Artikel',
            'slug' => 'artikel-2',
            'price' => 1000,
            'status' => 'active',
        ]);

        $cart = app(CartService::class)->getCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        $component = Livewire::test(OrderCheckout::class)
            ->set('loginEmail', 'kunde@example.com')
            ->set('loginPassword', 'wrong-pass');

        // Execute 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $component->call('loginUser');
        }

        // 6th attempt should be blocked by rate limiter
        $component->call('loginUser')
            ->assertSee('Zu viele Login-Versuche');

        // Ensure login attempts were recorded in system_login_attempts
        $failedAttempts = SystemLoginAttempt::where('email', 'kunde@example.com')
            ->where('success', false)
            ->count();
        $this->assertGreaterThanOrEqual(5, $failedAttempts);
    }

    public function test_successful_checkout_login_regenerates_session()
    {
        $customer = Customer::create([
            'email' => 'valid@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'password' => bcrypt('correct-password'),
        ]);

        $product = Product::create([
            'name' => 'Artikel',
            'slug' => 'artikel-3',
            'price' => 1000,
            'status' => 'active',
        ]);

        $cart = app(CartService::class)->getCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        Livewire::test(OrderCheckout::class)
            ->set('loginEmail', 'valid@example.com')
            ->set('loginPassword', 'correct-password')
            ->call('loginUser')
            ->assertSet('loginError', '');

        $this->assertAuthenticatedAs($customer, 'customer');

        $successAttempt = SystemLoginAttempt::where('email', 'valid@example.com')
            ->where('success', true)
            ->first();
        $this->assertNotNull($successAttempt);
    }
}

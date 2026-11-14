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

        // Rechtssichere Werbeeinwilligung hinterlegen (Double-Opt-In)
        \App\Models\Marketing\MarketingNewsletterSubscriber::create([
            'email' => $customer->email,
            'is_verified' => true,
        ]);

        $cart = Cart::create([
            'session_id' => 'abc',
            'customer_id' => $customer->id,
        ]);

        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000, 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
        
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(4)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertSent(AbandonedCartReminder::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email);
        });

        $this->assertNotNull($cart->fresh()->reminder_email_sent_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_send_reminders_without_marketing_consent()
    {
        Mail::fake();
        SystemSetting::create(['key' => 'cart_abandoned_red_hours', 'value' => '3']);

        $customer = Customer::factory()->create(['email' => 'noconsent@example.com']);

        $cart = Cart::create([
            'session_id' => 'no_consent_session',
            'customer_id' => $customer->id,
        ]);

        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000, 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
        
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(4)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertNothingSent();
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
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000, 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(4)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertNothingSent();
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
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000, 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(2)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertNothingSent();
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

        Mail::assertNothingSent();
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
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000, 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(4)]);

        $this->artisan('shop:send-abandoned-cart-reminders')
             ->assertSuccessful();

        Mail::assertNothingSent();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resets_reminder_flag_after_empty_cart_so_future_carts_are_reminded_again()
    {
        Mail::fake();
        SystemSetting::create(['key' => 'cart_abandoned_red_hours', 'value' => '3']);

        $customer = Customer::factory()->create([
            'email' => 'wiederkehrer@example.com',
            'email_verified_at' => now(),
        ]);
        \App\Models\Marketing\MarketingNewsletterSubscriber::create([
            'email' => strtoupper($customer->email), // prüft zugleich Case-Insensitive Opt-In Abgleich
            'is_verified' => true,
        ]);

        $product1 = Product::forceCreate([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Erstes Herzgeschenk',
            'slug' => 'erstes-herzgeschenk-' . uniqid(),
            'price' => 2990,
            'status' => 'active',
        ]);
        $product2 = Product::forceCreate([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Zweites Herzgeschenk',
            'slug' => 'zweites-herzgeschenk-' . uniqid(),
            'price' => 3990,
            'status' => 'active',
        ]);

        // 1. Einkaufszyklus: Kunde legt Artikel 1 in den Korb und lässt ihn >3h liegen
        $this->actingAs($customer, 'customer');
        $cartService = app(\App\Services\CartService::class);
        $cartService->addItem($product1, 1);

        $cart = $cartService->getCart(false);
        $originalUpdatedAt = now()->subHours(5)->startOfSecond();
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => $originalUpdatedAt]);

        $this->artisan('shop:send-abandoned-cart-reminders')->assertSuccessful();
        Mail::assertSentCount(1);
        $this->assertNotNull($cart->fresh()->reminder_email_sent_at);
        // updated_at darf durch den reinen Mailversand nicht künstlich auf now() gesetzt worden sein
        $this->assertEquals($originalUpdatedAt->timestamp, $cart->fresh()->updated_at->timestamp);

        // Kunde kauft / leert den Warenkorb -> reminder_email_sent_at MUSS zurückgesetzt werden
        $cartService->emptyCart();
        $this->assertNull($cart->fresh()->reminder_email_sent_at);

        // 2. Einkaufszyklus Wochen später: Kunde legt neues Geschenk ab und vergisst es erneut
        $cartService->addItem($product2, 1);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(5)]);

        $this->artisan('shop:send-abandoned-cart-reminders')->assertSuccessful();
        Mail::assertSentCount(2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function guest_cart_is_merged_on_login_and_resume_link_redirects_cleanly_to_cart_after_session_expiry()
    {
        $customer = Customer::factory()->create([
            'first_name' => 'Marie',
            'email' => 'marie@example.com',
            'password' => bcrypt('SecretPass123!'),
            'email_verified_at' => now(),
        ]);

        $product = Product::forceCreate([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Schieferherz mit Wunschgravur',
            'slug' => 'schieferherz-' . uniqid(),
            'price' => 3490,
            'status' => 'active',
        ]);

        // 1. Als Gast Artikel in den Warenkorb legen und anschließend über /login anmelden
        $cartService = app(\App\Services\CartService::class);
        $cartService->addItem($product, 1, ['texts' => [['text' => 'Für Mama']]]);
        $guestCart = $cartService->getCart(false);
        $this->assertNull($guestCart->customer_id);

        \Livewire\Livewire::test(\App\Livewire\Auth\AuthLogin::class)
            ->set('email', 'marie@example.com')
            ->set('password', 'SecretPass123!')
            ->call('login');

        $customerCart = Cart::where('customer_id', $customer->id)->with('items')->first();
        $this->assertNotNull($customerCart, 'Gast-Warenkorb muss beim Login auf das Kundenkonto übertragen werden.');
        $this->assertCount(1, $customerCart->items);
        $this->assertSame((string) $product->id, (string) $customerCart->items->first()->product_id);

        // 2. 24h später ist die Browser-Session abgelaufen (Logout simulieren) und Kundin klickt den Link in der E-Mail
        auth()->guard('customer')->logout();
        session()->flush();

        $resumeUrl = route('cart.reminder.resume', [
            'id' => $customerCart->id,
            'hash' => $customerCart->getReminderResumeHash(),
        ]);

        $response = $this->get($resumeUrl);
        $response->assertRedirect(route('login'));
        $this->assertSame('/warenkorb', session('url.intended'));
        $this->assertSame('marie@example.com', session('login_prefill_email'));

        // Nach dem Login auf /login landet die Kundin direkt in ihrem gespeicherten /warenkorb (statt auf einem leeren Gast-Korb)
        \Livewire\Livewire::test(\App\Livewire\Auth\AuthLogin::class)
            ->assertSet('email', 'marie@example.com')
            ->set('password', 'SecretPass123!')
            ->call('login')
            ->assertRedirect('/warenkorb');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reminder_email_is_warm_pressure_free_and_excludes_inactive_products()
    {
        $customer = Customer::factory()->create([
            'first_name' => 'Lena',
            'email' => 'lena@example.com',
        ]);

        $cart = Cart::create([
            'session_id' => 'warm_tone_session',
            'customer_id' => $customer->id,
        ]);

        $activeProduct = Product::forceCreate([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Goldene Erinnerungskette',
            'slug' => 'goldene-kette-' . uniqid(),
            'price' => 4990,
            'status' => 'active',
        ]);
        $pausedProduct = Product::forceCreate([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Pausierter Altartikel',
            'slug' => 'pausiert-' . uniqid(),
            'price' => 1990,
            'status' => 'draft',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $activeProduct->id,
            'quantity' => 1,
            'unit_price' => 4990,
            'configuration' => ['texts' => [['text' => 'Lena & Jonas']]],
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $pausedProduct->id,
            'quantity' => 1,
            'unit_price' => 1990,
        ]);

        $mailable = new AbandonedCartReminder($cart);
        $this->assertSame('Deine Auswahl bei Mein Seelenfunke ist noch für dich gespeichert ✨', $mailable->envelope()->subject);

        $html = $mailable->render();
        $this->assertStringContainsString('Hallo Lena,', $html);
        $this->assertStringContainsString('Ganz ohne Eile und völlig unverbindlich', $html);
        $this->assertStringContainsString('Goldene Erinnerungskette', $html);
        $this->assertStringContainsString('Inklusive deiner persönlichen Gestaltung gespeichert', $html);
        $this->assertStringNotContainsString('Pausierter Altartikel', $html);
        $this->assertStringNotContainsString('Du hast da etwas vergessen', $html);
        $this->assertStringNotContainsString('Bitte antworte nicht auf diese E-Mail', $html);
        $this->assertStringNotContainsString(' – ', $html);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_one_click_unsubscribe_and_skips_soft_deleted_customers()
    {
        Mail::fake();
        SystemSetting::create(['key' => 'cart_abandoned_red_hours', 'value' => '3']);

        $customer = Customer::factory()->create(['email' => 'optout@example.com']);
        \App\Models\Marketing\MarketingNewsletterSubscriber::create([
            'email' => $customer->email,
            'is_verified' => true,
        ]);

        $cart = Cart::create(['session_id' => 'optout_sess', 'customer_id' => $customer->id]);
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Herz', 'slug' => 'herz-' . uniqid(), 'price' => 1000, 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(4)]);

        // One-Click Opt-Out aufrufen
        $unsubscribeUrl = route('cart.reminder.unsubscribe', [
            'id' => $customer->id,
            'hash' => $customer->getCartReminderUnsubscribeHash(),
        ]);
        $this->get($unsubscribeUrl)->assertOk();
        $this->assertNotNull($customer->fresh()->profile->cart_reminder_unsubscribed_at);

        $this->artisan('shop:send-abandoned-cart-reminders')->assertSuccessful();
        Mail::assertNothingSent();

        // Zusätzlich prüfen: Deaktiviertes / Soft-Deleted Kundenkonto darf trotz verifiziertem Opt-In niemals angemailt werden
        $deletedCustomer = Customer::factory()->create(['email' => 'deleted@example.com']);
        \App\Models\Marketing\MarketingNewsletterSubscriber::create([
            'email' => $deletedCustomer->email,
            'is_verified' => true,
        ]);
        $deletedCart = Cart::create(['session_id' => 'deleted_sess', 'customer_id' => $deletedCustomer->id]);
        CartItem::create(['cart_id' => $deletedCart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $deletedCart->id)->update(['updated_at' => now()->subHours(4)]);

        $deletedCustomer->delete();

        $this->artisan('shop:send-abandoned-cart-reminders')->assertSuccessful();
        Mail::assertNothingSent();
    }
}

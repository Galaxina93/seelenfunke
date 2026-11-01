<?php

namespace Tests\Feature\Livewire\Shop\Marketing;

use App\Http\Controllers\NewsletterController;
use App\Livewire\Shop\Marketing\MarketingNewsletterPage as NewsletterPage;
use App\Mail\NewsletterVerificationMail;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketingNewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\RateLimiter::clear('newsletter_page_sub_127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('newsletter_unsub_127.0.0.1');
    }

    #[Test]
    public function it_renders_the_newsletter_page()
    {
        Livewire::test(NewsletterPage::class)
            ->assertSet('activeTab', 'subscribe')
            ->assertStatus(200);
    }

    #[Test]
    public function it_requires_valid_email_and_privacy_acceptance_to_subscribe()
    {
        Livewire::test(NewsletterPage::class)
            ->set('email', 'invalid-email')
            ->set('privacy_accepted', false)
            ->call('subscribe')
            ->assertHasErrors(['email' => 'email', 'privacy_accepted' => 'accepted']);

        // Empty email
        Livewire::test(NewsletterPage::class)
            ->set('email', '')
            ->set('privacy_accepted', true)
            ->call('subscribe')
            ->assertHasErrors(['email' => 'required']);
    }

    #[Test]
    public function it_succesfully_subscribes_and_sends_verification_mail()
    {
        Mail::fake();

        Livewire::test(NewsletterPage::class)
            ->set('email', 'test@example.com')
            ->set('privacy_accepted', true)
            ->call('subscribe')
            ->assertHasNoErrors()
            ->assertSet('email', '') // Should be reset
            ->assertSet('privacy_accepted', false) // Should be reset
            ->assertSee('Fast geschafft! Wir haben dir eine Bestätigungs-E-Mail gesendet.');

        $this->assertDatabaseHas('marketing_newsletter_subscribers', [
            'email' => 'test@example.com',
            'privacy_accepted' => true,
            'is_verified' => false,
        ]);

        $subscriber = MarketingNewsletterSubscriber::where('email', 'test@example.com')->first();
        $this->assertNotNull($subscriber->verification_token);

        Mail::assertSent(NewsletterVerificationMail::class, function ($mail) use ($subscriber) {
            return $mail->hasTo('test@example.com') && $mail->subscriber->id === $subscriber->id;
        });
    }

    #[Test]
    public function it_prevents_duplicate_subscriptions()
    {
        Mail::fake();

        MarketingNewsletterSubscriber::create([
            'email' => 'duplicate@example.com',
            'ip_address' => '127.0.0.1',
            'privacy_accepted' => true,
            'is_verified' => true,
            'verification_token' => null
        ]);

        Livewire::test(NewsletterPage::class)
            ->set('email', 'duplicate@example.com')
            ->set('privacy_accepted', true)
            ->call('subscribe')
            ->assertHasNoErrors()
            ->assertSee('Fast geschafft! Wir haben dir eine Bestätigungs-E-Mail gesendet.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('marketing_newsletter_subscribers', 1);
    }

    #[Test]
    public function it_verifies_subscriber_via_controller()
    {
        $token = Str::random(32);
        
        $subscriber = MarketingNewsletterSubscriber::create([
            'email' => 'verify@example.com',
            'ip_address' => '127.0.0.1',
            'privacy_accepted' => true,
            'is_verified' => false,
            'verification_token' => $token
        ]);

        // Direct action call to avoid implicit RouteName dependencies
        $response = $this->get(action([NewsletterController::class, 'verify'], ['token' => $token]));
        
        // Assert redirect to newsletter page with success
        $response->assertRedirect(route('newsletter.page'));
        $response->assertSessionHas('verified', 'Vielen Dank! Deine E-Mail-Adresse wurde erfolgreich bestätigt.');

        $subscriber->refresh();
        
        $this->assertTrue($subscriber->is_verified);
        $this->assertNotNull($subscriber->verified_at);
        $this->assertNull($subscriber->verification_token);
    }

    #[Test]
    public function it_handles_invalid_verification_tokens()
    {
        $response = $this->get(action([NewsletterController::class, 'verify'], ['token' => 'invalid-token-123']));
        
        $response->assertRedirect(route('newsletter.page'));
        $response->assertSessionHas('error', 'Dieser Bestätigungslink ist ungültig oder abgelaufen.');
    }

    #[Test]
    public function it_unsubscribes_an_existing_user()
    {
        $subscriber = MarketingNewsletterSubscriber::create([
            'email' => 'leave@example.com',
            'ip_address' => '127.0.0.1',
            'privacy_accepted' => true,
            'is_verified' => true,
        ]);

        Livewire::test(NewsletterPage::class)
            ->set('email', 'leave@example.com')
            ->call('unsubscribe')
            ->assertHasNoErrors()
            ->assertSee('Falls diese E-Mail-Adresse in unserem Verteiler eingetragen war, wurde sie erfolgreich abgemeldet.');

        $this->assertDatabaseMissing('marketing_newsletter_subscribers', [
            'email' => 'leave@example.com'
        ]);
    }

    #[Test]
    public function it_shows_error_when_unsubscribing_unknown_email()
    {
        Livewire::test(NewsletterPage::class)
            ->set('email', 'unknown@example.com')
            ->call('unsubscribe')
            ->assertHasNoErrors()
            ->assertSee('Falls diese E-Mail-Adresse in unserem Verteiler eingetragen war, wurde sie erfolgreich abgemeldet.');
    }

    #[Test]
    public function it_can_switch_tabs_and_reset_state()
    {
        Livewire::test(NewsletterPage::class)
            ->set('email', 'filled@example.com')
            ->set('privacy_accepted', true)
            ->set('successMessage', 'Some old message')
            ->call('switchTab', 'unsubscribe')
            ->assertSet('activeTab', 'unsubscribe')
            ->assertSet('email', '')
            ->assertSet('privacy_accepted', false)
            ->assertSet('successMessage', '');
    }

    #[Test]
    public function it_uses_frontend_gold_theme_and_supports_granular_birthday_and_anniversary_email_unsubscribe()
    {
        Mail::fake();

        $customer = \App\Models\Customer\Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Hanna',
            'last_name' => 'Gold',
            'email' => 'hanna-gold@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $customer->profile()->update([
            'birthday' => now()->subYears(30)->format('Y-m-d'),
            'receive_birthday_emails' => true,
            'receive_anniversary_emails' => true,
        ]);
        \App\Models\Customer\CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'funken_balance' => 100,
            'funken_total_earned' => 100,
            'level' => 1,
        ]);

        MarketingNewsletterSubscriber::create([
            'email' => 'hanna-gold@example.com',
            'ip_address' => '127.0.0.1',
            'privacy_accepted' => true,
            'is_verified' => true,
            'receive_newsletter' => true,
            'receive_birthday_emails' => true,
            'receive_anniversary_emails' => true,
        ]);

        // 1. Gold-Theme (#C5A059) statt Backend-Marketing-Lila im Frontend prüfen
        $component = Livewire::test(NewsletterPage::class);
        $this->assertSame('#C5A059', $component->instance()->getThemeColorHexProperty());
        $component->assertSee('#C5A059');

        // 2. Differenzierte Abmeldung: Nur "🎂 Geburtstags-Funke" abstellen, Eigentliche News & Kauf-Jubiläum behalten
        Livewire::test(NewsletterPage::class)
            ->call('switchTab', 'unsubscribe')
            ->set('email', 'hanna-gold@example.com')
            ->set('unsub_newsletter', false)
            ->set('unsub_birthday', true)
            ->set('unsub_anniversary', false)
            ->call('unsubscribe')
            ->assertHasNoErrors();

        // Newsletter-Eintrag bleibt für eigentliche News bestehen, aber receive_birthday_emails ist false
        $this->assertDatabaseHas('marketing_newsletter_subscribers', [
            'email' => 'hanna-gold@example.com',
            'receive_newsletter' => true,
            'receive_birthday_emails' => false,
            'receive_anniversary_emails' => true,
        ]);
        $this->assertDatabaseHas('customer_profiles', [
            'customer_id' => $customer->id,
            'receive_birthday_emails' => false,
            'receive_anniversary_emails' => true,
        ]);

        // Beim Geburtstags-Lauf bekommt Hanna trotzdem ihre +50 Funken, aber KEINE Geburtstags-Mail
        $gameService = app(\App\Services\Gamification\GamificationService::class);
        $this->assertFalse($gameService->shouldSendLoyaltyEmail($customer->fresh(), 'birthday'));
        $this->assertTrue($gameService->shouldSendLoyaltyEmail($customer->fresh(), 'anniversary'));

        $res = $gameService->processAnnualLoyaltySparksForCustomer($customer->fresh(), true);
        $this->assertTrue($res['birthday_awarded']);
        Mail::assertNotSent(\App\Mail\GamificationLoyaltySparksMail::class);

        // 3. "Alles komplett abstellen" blockiert alle 3 Kategorien und entfernt aus dem Newsletter-Verteiler
        \Illuminate\Support\Facades\RateLimiter::clear('newsletter_unsub_127.0.0.1');
        Livewire::test(NewsletterPage::class)
            ->call('switchTab', 'unsubscribe')
            ->set('email', 'hanna-gold@example.com')
            ->call('unsubscribeAll')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('marketing_newsletter_subscribers', [
            'email' => 'hanna-gold@example.com',
        ]);
        $this->assertDatabaseHas('customer_profiles', [
            'customer_id' => $customer->id,
            'receive_birthday_emails' => false,
            'receive_anniversary_emails' => false,
        ]);
    }

    #[Test]
    public function it_visually_highlights_active_anmelden_tab_and_anchors_bgm_minimize_transition_and_file_cache_permissions()
    {
        // 1. Seelenpost: Tab "Anmelden" ist visuell eindeutig als aktiv hervorgehoben
        $component = Livewire::test(NewsletterPage::class)
            ->assertSet('activeTab', 'subscribe')
            ->assertSeeHtml('aria-selected="true"')
            ->assertSeeHtml('bi-envelope-check-fill')
            ->assertSeeHtml('linear-gradient(135deg, #d4b475 0%, #C5A059 50%, #b38e46 100%)');

        $component->call('switchTab', 'unsubscribe')
            ->assertSet('activeTab', 'unsubscribe')
            ->assertSeeHtml('bi-sliders');

        // 2. File-Cache-Store ist mit permission 0777 konfiguriert (verhindert LockableFile fopen ErrorException)
        $this->assertSame(0777, config('cache.stores.file.permission'));

        // 3. Funki Musik Dock: Eingeklapptes Icon und ausgeklapptes Panel teilen sich denselben bottom-0 right-0 Ankerpunkt
        $bgmBlade = file_get_contents(resource_path('views/components/gamification/persistent-bgm-dock.blade.php'));
        $this->assertStringContainsString('absolute bottom-0 right-0 origin-center', $bgmBlade);
        $this->assertStringContainsString('absolute bottom-0 right-0 origin-bottom-right', $bgmBlade);
    }
}

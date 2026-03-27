<?php

namespace Tests\Feature\Livewire\Shop\Newsletter;

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

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

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

        Mail::assertQueued(NewsletterVerificationMail::class, function ($mail) use ($subscriber) {
            return $mail->hasTo('test@example.com') && $mail->subscriber->id === $subscriber->id;
        });
    }

    #[Test]
    public function it_prevents_duplicate_subscriptions()
    {
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
            ->assertHasErrors(['email' => 'unique']);
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
            ->assertSee('Du wurdest erfolgreich aus dem Verteiler ausgetragen');

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
            ->assertHasErrors(['email']);
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
}

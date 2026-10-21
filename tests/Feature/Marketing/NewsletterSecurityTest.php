<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\MarketingNewsletter;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class NewsletterSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_subscriber_can_request_new_verification(): void
    {
        Mail::fake();

        $subscriber = MarketingNewsletterSubscriber::create([
            'email' => 'unverified@example.com',
            'privacy_accepted' => true,
            'is_verified' => false,
            'verification_token' => 'old_token_123',
        ]);

        Livewire::test(\App\Livewire\Shop\Marketing\MarketingNewsletterPage::class)
            ->set('email', 'unverified@example.com')
            ->set('privacy_accepted', true)
            ->call('subscribe')
            ->assertHasNoErrors();

        $subscriber->refresh();
        $this->assertNotEquals('old_token_123', $subscriber->verification_token);
        $this->assertFalse($subscriber->is_verified);
    }

    public function test_unsubscribe_does_not_leak_subscriber_existence(): void
    {
        MarketingNewsletterSubscriber::create([
            'email' => 'existing@example.com',
            'privacy_accepted' => true,
            'is_verified' => true,
        ]);

        // Unsubscribe existing
        Livewire::test(\App\Livewire\Shop\Marketing\MarketingNewsletterPage::class)
            ->set('email', 'existing@example.com')
            ->call('unsubscribe')
            ->assertHasNoErrors()
            ->assertSet('successMessage', 'Falls diese E-Mail-Adresse in unserem Verteiler eingetragen war, wurde sie erfolgreich abgemeldet.');

        $this->assertDatabaseMissing('marketing_newsletter_subscribers', [
            'email' => 'existing@example.com'
        ]);

        // Unsubscribe non-existent
        Livewire::test(\App\Livewire\Shop\Marketing\MarketingNewsletterPage::class)
            ->set('email', 'unknown@example.com')
            ->call('unsubscribe')
            ->assertHasNoErrors()
            ->assertSet('successMessage', 'Falls diese E-Mail-Adresse in unserem Verteiler eingetragen war, wurde sie erfolgreich abgemeldet.');
    }

    public function test_send_newsletters_command_resolves_mailable_correctly(): void
    {
        Mail::fake();

        $template = MarketingNewsletter::create([
            'title' => 'Halloween - Kampagne',
            'subject' => 'Happy Halloween',
            'content' => 'Hallo {first_name}',
            'target_event_key' => 'halloween',
            'days_offset' => 0,
            'is_active' => true,
            'type' => 'automated',
        ]);

        $subscriber = MarketingNewsletterSubscriber::create([
            'email' => 'customer@example.com',
            'is_verified' => true,
            'privacy_accepted' => true,
        ]);

        $exitCode = $this->artisan('send-newsletters');
        $exitCode->assertExitCode(0);
    }
}

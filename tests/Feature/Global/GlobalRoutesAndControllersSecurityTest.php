<?php

namespace Tests\Feature\Global;

use App\Models\Marketing\MarketingNewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalRoutesAndControllersSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_revocation_pdf_generation_disables_remote_fetching_and_serves_pdf()
    {
        $response = $this->get('/widerruf/pdf');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_newsletter_verification_rejects_invalid_short_token()
    {
        $response = $this->get('/newsletter/verify/invalid-token');

        $response->assertRedirect(route('newsletter.page'));
        $response->assertSessionHas('error');
    }

    public function test_newsletter_verification_succeeds_with_valid_token()
    {
        $token = Str::random(32);
        $subscriber = MarketingNewsletterSubscriber::create([
            'email' => 'valid-newsletter@example.com',
            'is_verified' => false,
            'verification_token' => $token,
        ]);

        $response = $this->get("/newsletter/verify/{$token}");

        $response->assertRedirect(route('newsletter.page'));
        $response->assertSessionHas('verified');

        $subscriber->refresh();
        $this->assertTrue($subscriber->is_verified);
        $this->assertNull($subscriber->verification_token);
    }
}

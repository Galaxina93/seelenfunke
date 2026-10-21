<?php

namespace Tests\Feature\Auth;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthControllerSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_rejects_non_google_avatar_urls_preventing_ssrf()
    {
        Storage::fake('public');
        Http::fake([
            'http://169.254.169.254/*' => Http::response('AWS metadata', 200),
            'https://evil.attacker.com/*' => Http::response('Malicious payload', 200),
        ]);

        $fakeSocialiteUser = new SocialiteUser();
        $fakeSocialiteUser->id = 'google-ssrf-12345';
        $fakeSocialiteUser->name = 'Max Mustermann';
        $fakeSocialiteUser->email = 'max.muster@example.com';
        $fakeSocialiteUser->avatar = 'https://evil.attacker.com/exploit.jpg';
        $fakeSocialiteUser->user = [
            'email_verified' => true,
            'given_name' => 'Max',
            'family_name' => 'Mustermann',
        ];

        Socialite::shouldReceive('driver->stateless->user')
            ->once()
            ->andReturn($fakeSocialiteUser);

        $response = $this->get(route('google.callback'));

        // Customer created
        $customer = Customer::where('email', 'max.muster@example.com')->first();
        $this->assertNotNull($customer);

        // Evil avatar URL should NOT have been fetched
        Http::assertNothingSent();
    }

    public function test_unverified_google_email_is_rejected()
    {
        $fakeSocialiteUser = new SocialiteUser();
        $fakeSocialiteUser->id = 'google-unverified-999';
        $fakeSocialiteUser->name = 'Untrusted User';
        $fakeSocialiteUser->email = 'untrusted@example.com';
        $fakeSocialiteUser->user = [
            'email_verified' => false,
        ];

        Socialite::shouldReceive('driver->stateless->user')
            ->once()
            ->andReturn($fakeSocialiteUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('customers', [
            'email' => 'untrusted@example.com',
        ]);
    }
}

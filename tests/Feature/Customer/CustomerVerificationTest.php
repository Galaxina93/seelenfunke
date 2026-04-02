<?php

namespace Tests\Feature\Customer;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Verified;
use Tests\TestCase;

class CustomerVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([Verified::class]);
    }

    /**
     * Helper to create a signed verification URL.
     */
    private function getVerificationUrl(Customer $customer, $expirationMinutes = 60, $hash = null): string
    {
        $hashToUse = $hash ?? sha1($customer->getEmailForVerification());

        if ($expirationMinutes) {
            return URL::temporarySignedRoute(
                'customer.verification.verify',
                now()->addMinutes($expirationMinutes),
                ['id' => $customer->id, 'hash' => $hashToUse]
            );
        }

        return URL::signedRoute(
            'customer.verification.verify',
            ['id' => $customer->id, 'hash' => $hashToUse]
        );
    }

    public function test_customer_can_verify_email_with_valid_signature()
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        // Create a fake profile just to ensure the profile logic works properly.
        CustomerProfile::factory()->create([
            'customer_id' => $customer->id,
            'email_verified_at' => null,
        ]);

        $verificationUrl = $this->getVerificationUrl($customer);

        $response = $this->get($verificationUrl);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertNotNull($customer->fresh()->email_verified_at);
        $this->assertNotNull($customer->profile->fresh()->email_verified_at);

        Event::assertDispatched(Verified::class, function ($e) use ($customer) {
            return $e->user->id === $customer->id;
        });
    }

    public function test_verification_fails_with_invalid_hash()
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        // Manipulate hash to be invalid
        $verificationUrl = $this->getVerificationUrl($customer, 60, sha1('invalid@example.com'));

        $response = $this->get($verificationUrl);

        // It should abort with 403 due to hash mismatch (not the signature, because the signature is computed on the invalid hash, making it technically pass Laravel signature check but fail our hash check).
        $response->assertStatus(403);

        $this->assertNull($customer->fresh()->email_verified_at);
        Event::assertNotDispatched(Verified::class);
    }

    public function test_verification_fails_with_invalid_signature()
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        $verificationUrl = $this->getVerificationUrl($customer);

        // Tamper with the URL
        $tamperedUrl = $verificationUrl . 'a';

        $response = $this->get($tamperedUrl);

        // The signed middleware should catch this
        $response->assertStatus(403);

        $this->assertNull($customer->fresh()->email_verified_at);
        Event::assertNotDispatched(Verified::class);
    }

    public function test_verification_fails_if_expired()
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        // Travel to the future
        $verificationUrl = $this->getVerificationUrl($customer, -10); // Expires 10 mins ago

        $response = $this->get($verificationUrl);

        // Submitting an expired signature throws 403 Invalid Signature
        $response->assertStatus(403);

        $this->assertNull($customer->fresh()->email_verified_at);
        Event::assertNotDispatched(Verified::class);
    }

    public function test_verified_event_is_not_dispatched_twice_if_already_verified()
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'email_verified_at' => now(), // Already verified
        ]);

        $verificationUrl = $this->getVerificationUrl($customer);

        $response = $this->get($verificationUrl);

        $response->assertRedirect(route('login'));

        // Since it was already verified, no new event is dispatched
        Event::assertNotDispatched(Verified::class);
    }
}

<?php

namespace Tests\Feature\Customer;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerVerificationSyncSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_verification_synchronizes_both_customer_and_profile_records()
    {
        $customer = Customer::create([
            'email' => 'sync-test@example.com',
            'first_name' => 'Sync',
            'last_name' => 'Tester',
            'password' => bcrypt('password123'),
            'email_verified_at' => null,
        ]);

        $this->assertNull($customer->email_verified_at);
        $this->assertFalse($customer->hasVerifiedEmail());

        $signedUrl = URL::temporarySignedRoute(
            'customer.verification.verify',
            now()->addMinutes(60),
            ['id' => $customer->id, 'hash' => sha1($customer->getEmailForVerification())]
        );

        $response = $this->get($signedUrl);
        $response->assertRedirect(route('login'));

        $customer->refresh();
        $this->assertNotNull($customer->email_verified_at);
        $this->assertTrue($customer->hasVerifiedEmail());

        $profile = CustomerProfile::where('customer_id', $customer->id)->first();
        $this->assertNotNull($profile);
        $this->assertNotNull($profile->email_verified_at);
    }
}

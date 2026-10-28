<?php

namespace Tests\Feature\System;

use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Models\Marketing\MarketingInfluencer;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\Employee\EmployeeHrService;
use App\Services\Marketing\InfluencerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PartnerIsolationAndOnboardingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_influencer_and_tax_advisor_cannot_access_ai_or_api_login_or_funki_command(): void
    {
        $influencer = Employee::create([
            'first_name' => 'Lena',
            'last_name' => 'Creator',
            'email' => 'lena@creator.test',
            'role' => 'influencer',
            'password' => bcrypt('SecretPass123!'),
        ]);

        // 1. API Login blocked for external partner
        $loginRes = $this->postJson('/api/login', [
            'email' => 'lena@creator.test',
            'password' => 'SecretPass123!',
        ]);
        $loginRes->assertStatus(403);

        // 2. AI API routes blocked even with session or Sanctum token
        $this->actingAs($influencer, 'employee')
            ->getJson('/api/ai/schema')
            ->assertStatus(403);

        $this->actingAs($influencer, 'employee')
            ->postJson('/api/ai/execute', ['function' => 'order_get_recent'])
            ->assertStatus(403);

        // 3. Direct AIFunctionsRegistry sandbox blocks internal tools for external partner
        $this->actingAs($influencer, 'employee');
        $execRes = AIFunctionsRegistry::execute('order_get_recent', []);
        $this->assertTrue($execRes['error'] ?? false);
        $this->assertStringContainsString('SANDBOX VIOLATION', $execRes['message'] ?? '');
    }

    public function test_paused_or_archived_employee_and_influencer_accounts_are_marked_inactive(): void
    {
        $employee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@seelenfunke.test',
            'role' => 'internal',
            'password' => bcrypt('SecretPass123!'),
        ]);

        $contract = EmployeeContract::create([
            'employee_id' => $employee->id,
            'job_title' => 'Produktion',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $this->assertTrue($employee->fresh()->isAccountActive());

        $contract->update(['is_active' => false]);
        $this->assertFalse($employee->fresh()->isAccountActive());

        // API login rejected when paused
        $this->postJson('/api/login', [
            'email' => 'max@seelenfunke.test',
            'password' => 'SecretPass123!',
        ])->assertStatus(403);
    }

    public function test_invitation_pin_verification_locks_out_after_five_failed_attempts(): void
    {
        $service = app(InfluencerService::class);
        $slot = $service->createInvitationSlot(['invitation_label' => 'Test Slot']);

        $rawToken = $slot->invitation_token_encrypted;
        $realPin = $slot->invitation_code_encrypted;
        $tokenHash = hash('sha256', $rawToken);
        RateLimiter::clear('invite_pin_inf_' . $tokenHash);

        for ($i = 0; $i < 5; $i++) {
            $this->assertNull($service->verifyInvitationCode($rawToken, '000000'));
        }

        // 6th attempt must fail even with the correct PIN due to per-token lockout
        $this->assertNull($service->verifyInvitationCode($rawToken, $realPin));

        RateLimiter::clear('invite_pin_inf_' . $tokenHash);
        $this->assertNotNull($service->verifyInvitationCode($rawToken, $realPin));
    }

    public function test_influencer_tax_number_and_employee_profile_vat_id_are_encrypted_at_rest_and_hidden(): void
    {
        $employee = Employee::create([
            'first_name' => 'Anna',
            'last_name' => 'Steuer',
            'email' => 'anna@seelenfunke.test',
            'role' => 'influencer',
            'password' => bcrypt('SecretPass123!'),
        ]);

        $employee->profile->update(['vat_id' => 'DE123456789']);
        $freshProfile = $employee->profile->fresh();

        $this->assertSame('DE123456789', $freshProfile->vat_id);
        $this->assertNotSame('DE123456789', $freshProfile->getRawOriginal('vat_id'));
        $this->assertArrayNotHasKey('vat_id', $freshProfile->toArray());

        $influencer = MarketingInfluencer::create([
            'employee_id' => $employee->id,
            'tax_number' => '19/123/45678',
            'is_active' => true,
        ])->fresh();

        $this->assertSame('19/123/45678', $influencer->tax_number);
        $this->assertNotSame('19/123/45678', $influencer->getRawOriginal('tax_number'));
        $this->assertArrayNotHasKey('tax_number', $influencer->toArray());
    }

    public function test_twilio_inbound_webhook_rejects_unauthenticated_requests(): void
    {
        $this->post('/api/twilio/inbound', [])
            ->assertStatus(403);
    }
}

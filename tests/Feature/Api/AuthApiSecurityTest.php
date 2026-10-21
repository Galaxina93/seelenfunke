<?php

namespace Tests\Feature\Api;

use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function password_email_endpoint_does_not_leak_user_existence(): void
    {
        // Unknown email should return 200 with neutral message, NOT 404
        $response = $this->postJson('/api/password/email', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertStringContainsString('Falls ein Konto', $response->json('message'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function password_email_endpoint_accepts_valid_registered_user(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'valid-user@example.com',
        ]);

        $response = $this->postJson('/api/password/email', [
            'email' => 'valid-user@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }
}

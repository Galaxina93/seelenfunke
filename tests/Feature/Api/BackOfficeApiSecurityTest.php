<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackOfficeApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'id' => Str::uuid()->toString(),
            'email' => 'admin@test.de',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'password' => bcrypt('password'),
        ]);

        $this->customer = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'customer@test.de',
            'first_name' => 'Customer',
            'last_name' => 'Test',
            'password' => bcrypt('password'),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_unauthenticated_requests_to_backoffice_apis()
    {
        $this->getJson('/api/funki/financials/kpis')->assertStatus(401);
        $this->getJson('/api/funki/emails/accounts')->assertStatus(401);
        $this->getJson('/api/funki/contacts')->assertStatus(401);
        $this->getJson('/api/funki/tasks/lists')->assertStatus(401);
        $this->getJson('/api/funki/financials/receipt')->assertStatus(401);
        $this->getJson('/api/ai/schema')->assertStatus(401);
        $this->postJson('/api/ai/execute', ['function' => 'get_system_health'])->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_customer_tokens_to_backoffice_apis_with_403()
    {
        Sanctum::actingAs($this->customer, ['*']);

        $this->getJson('/api/funki/financials/kpis')->assertStatus(403);
        $this->getJson('/api/funki/emails/accounts')->assertStatus(403);
        $this->getJson('/api/funki/contacts')->assertStatus(403);
        $this->getJson('/api/funki/tasks/lists')->assertStatus(403);
        $this->getJson('/api/funki/financials/receipt?path=test.pdf')->assertStatus(403);
        $this->getJson('/api/ai/schema')->assertStatus(403);
        $this->postJson('/api/ai/execute', ['function' => 'get_system_health'])->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_admin_tokens_to_access_backoffice_apis()
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->getJson('/api/funki/financials/kpis')->assertStatus(200);
        $this->getJson('/api/funki/emails/accounts')->assertStatus(200);
        $this->getJson('/api/funki/contacts')->assertStatus(200);
        $this->getJson('/api/funki/tasks/lists')->assertStatus(200);
        $this->getJson('/api/ai/schema')->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_all_authenticated_users_to_access_core_user_endpoint()
    {
        Sanctum::actingAs($this->customer, ['*']);
        $resp = $this->getJson('/api/user');
        $resp->assertStatus(200);
        $this->assertEquals('customer', $resp->json('user_type'));

        Sanctum::actingAs($this->admin, ['*']);
        $resp2 = $this->getJson('/api/user');
        $resp2->assertStatus(200);
        $this->assertEquals('admin', $resp2->json('user_type'));
    }
}

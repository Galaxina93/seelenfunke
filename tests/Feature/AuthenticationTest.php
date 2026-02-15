<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_dashboard()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertStatus(200);
    }

    public function test_customer_cannot_access_admin_dashboard()
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->get(route('admin.dashboard'))
            ->assertStatus(302) // Redirect to login
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login()
    {
        $this->get(route('customer.dashboard'))
            ->assertRedirect(route('login'));
    }
}

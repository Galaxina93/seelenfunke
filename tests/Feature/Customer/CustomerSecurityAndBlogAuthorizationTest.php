<?php

namespace Tests\Feature\Customer;

use App\Livewire\Customer\CustomerForcePasswordChange;
use App\Livewire\Customer\CustomerInvoicesComponent;
use App\Livewire\Shop\Marketing\MarketingBlog;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerSecurityAndBlogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_customer_invoices(): void
    {
        Livewire::test(CustomerInvoicesComponent::class)->assertStatus(403);
    }

    public function test_customers_can_mount_customer_invoices(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerInvoicesComponent::class)
            ->assertStatus(200);
    }

    public function test_guests_are_redirected_on_force_password_change_mount(): void
    {
        Livewire::test(CustomerForcePasswordChange::class)
            ->assertRedirect(route('login'));
    }

    public function test_customer_without_password_change_flag_is_redirected(): void
    {
        $customer = Customer::factory()->create([
            'needs_password_change' => false,
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerForcePasswordChange::class)
            ->assertRedirect(route('customer.dashboard'));
    }

    public function test_customer_with_password_change_flag_can_update_password(): void
    {
        $customer = Customer::factory()->create([
            'needs_password_change' => true,
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerForcePasswordChange::class)
            ->set('password', 'NewSecurePassword123')
            ->set('passwordConfirm', 'NewSecurePassword123')
            ->call('submit')
            ->assertRedirect(route('customer.dashboard'));

        $customer->refresh();
        $this->assertFalse((bool) $customer->needs_password_change);
    }

    public function test_guests_and_customers_cannot_mount_marketing_blog(): void
    {
        Livewire::test(MarketingBlog::class)->assertStatus(403);

        $customer = Customer::factory()->create();
        Livewire::actingAs($customer, 'customer')
            ->test(MarketingBlog::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_marketing_blog(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Lukas',
            'last_name' => 'Mitarbeiter',
            'email' => 'lukas-blog@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingBlog::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(MarketingBlog::class)
            ->assertStatus(200);
    }
}

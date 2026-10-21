<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Shop\Marketing\MarketingLandingPages;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingLandingPagesSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_marketing_landing_pages(): void
    {
        Livewire::test(MarketingLandingPages::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_marketing_landing_pages(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(MarketingLandingPages::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_marketing_landing_pages(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Landing',
            'last_name' => 'Manager',
            'email' => 'employee.landing@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingLandingPages::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(MarketingLandingPages::class)
            ->assertStatus(200);
    }
}

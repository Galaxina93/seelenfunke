<?php

namespace Tests\Feature\Master;

use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterAnalyticsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_master_analytics(): void
    {
        Livewire::test(MasterAnalytics::class)->assertStatus(403);
    }

    public function test_customers_and_employees_cannot_mount_master_analytics(): void
    {
        $customer = Customer::factory()->create();
        Livewire::actingAs($customer, 'customer')
            ->test(MasterAnalytics::class)
            ->assertStatus(403);

        $employee = Employee::create([
            'first_name' => 'Agent',
            'last_name' => 'Support',
            'email' => 'employee.master@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($employee, 'employee')
            ->test(MasterAnalytics::class)
            ->assertStatus(403);
    }

    public function test_admins_can_mount_master_analytics(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(MasterAnalytics::class)
            ->assertStatus(200);
    }
}

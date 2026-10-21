<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemDeliveryTimes;
use App\Livewire\Shop\System\SystemLogs;
use App\Livewire\Shop\System\SystemShipping;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemInfrastructureAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_system_infrastructure(): void
    {
        Livewire::test(SystemShipping::class)->assertStatus(403);
        Livewire::test(SystemDeliveryTimes::class)->assertStatus(403);
        Livewire::test(SystemLogs::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_system_infrastructure(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(SystemShipping::class)
            ->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(SystemDeliveryTimes::class)
            ->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(SystemLogs::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_access_system_infrastructure(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Logistik',
            'last_name' => 'Mitarbeiter',
            'email' => 'logistik@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        // Shipping
        Livewire::actingAs($admin, 'admin')->test(SystemShipping::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(SystemShipping::class)->assertStatus(200);

        // Delivery Times
        Livewire::actingAs($admin, 'admin')->test(SystemDeliveryTimes::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(SystemDeliveryTimes::class)->assertStatus(200);

        // Logs: Admin-only
        Livewire::actingAs($admin, 'admin')->test(SystemLogs::class)->assertStatus(200);
        auth()->guard('admin')->logout();
        Livewire::actingAs($employee, 'employee')->test(SystemLogs::class)->assertStatus(403);
    }
}

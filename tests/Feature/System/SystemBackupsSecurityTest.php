<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemBackups;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemBackupsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_system_backups(): void
    {
        Livewire::test(SystemBackups::class)->assertStatus(403);
    }

    public function test_customers_and_employees_cannot_mount_system_backups(): void
    {
        $customer = Customer::factory()->create();
        Livewire::actingAs($customer, 'customer')
            ->test(SystemBackups::class)
            ->assertStatus(403);

        $employee = Employee::create([
            'first_name' => 'Support',
            'last_name' => 'Agent',
            'email' => 'employee.backup@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($employee, 'employee')
            ->test(SystemBackups::class)
            ->assertStatus(403);
    }

    public function test_admins_can_mount_system_backups(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(SystemBackups::class)
            ->assertStatus(200);
    }
}

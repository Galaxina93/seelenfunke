<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiRoleManager;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiRoleManagerSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_role_manager(): void
    {
        Livewire::test(AiRoleManager::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_role_manager(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(AiRoleManager::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_role_manager(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Role',
            'last_name' => 'Manager',
            'email' => 'employee.role@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiRoleManager::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(AiRoleManager::class)
            ->assertStatus(200);
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiCompanyStructure;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiCompanyStructureSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_company_structure(): void
    {
        Livewire::test(AiCompanyStructure::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_company_structure(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(AiCompanyStructure::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_company_structure(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Agent',
            'last_name' => 'Structure',
            'email' => 'employee.struct@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiCompanyStructure::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(AiCompanyStructure::class)
            ->assertStatus(200);
    }
}

<?php

namespace Tests\Feature\System;

use App\Livewire\Backend\System\SystemNeuralAnalysisIndex;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemNeuralAnalysisSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_neural_analysis(): void
    {
        Livewire::test(SystemNeuralAnalysisIndex::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_neural_analysis(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(SystemNeuralAnalysisIndex::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_neural_analysis(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Dev',
            'last_name' => 'Agent',
            'email' => 'employee.dev@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(SystemNeuralAnalysisIndex::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(SystemNeuralAnalysisIndex::class)
            ->assertStatus(200);
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiAgentEditor;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiAgentEditorAndMethodSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_agent_editor(): void
    {
        Livewire::test(AiAgentEditor::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_agent_editor(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(AiAgentEditor::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_agent_editor(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Agent',
            'last_name' => 'Editor',
            'email' => 'employee.editor@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')->test(AiAgentEditor::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(AiAgentEditor::class)->assertStatus(200);
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiKnowledgeBase;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiKnowledgeBaseSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_knowledge_base(): void
    {
        Livewire::test(AiKnowledgeBase::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_knowledge_base(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(AiKnowledgeBase::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_knowledge_base(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Wiki',
            'last_name' => 'Editor',
            'email' => 'employee.wiki@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiKnowledgeBase::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(AiKnowledgeBase::class)
            ->assertStatus(200);
    }
}

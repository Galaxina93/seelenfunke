<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiKnowledgeBaseCategories;
use App\Livewire\Shop\Ai\AiKnowledgeBaseTags;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiKnowledgeBaseTaxonomySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_kb_taxonomy(): void
    {
        Livewire::test(AiKnowledgeBaseCategories::class)->assertStatus(403);
        Livewire::test(AiKnowledgeBaseTags::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_kb_taxonomy(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(AiKnowledgeBaseCategories::class)
            ->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(AiKnowledgeBaseTags::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_kb_taxonomy(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Taxonomy',
            'last_name' => 'Agent',
            'email' => 'employee.taxo@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')->test(AiKnowledgeBaseCategories::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(AiKnowledgeBaseCategories::class)->assertStatus(200);

        Livewire::actingAs($admin, 'admin')->test(AiKnowledgeBaseTags::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(AiKnowledgeBaseTags::class)->assertStatus(200);
    }
}

<?php

namespace Tests\Feature\Management;

use App\Livewire\Backend\Management\ManagementLinktreeManager;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagementLinktreeSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_linktree_manager(): void
    {
        Livewire::test(ManagementLinktreeManager::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_linktree_manager(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(ManagementLinktreeManager::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_linktree_manager(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Marketing',
            'last_name' => 'Agent',
            'email' => 'employee.linktree@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ManagementLinktreeManager::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(ManagementLinktreeManager::class)
            ->assertStatus(200);
    }

    public function test_linktree_page_blocks_open_redirect_to_manipulated_url(): void
    {
        $link = \App\Models\Management\ManagementLinktree::create([
            'title' => 'Offizieller Shop',
            'url' => 'https://mein-seelenfunke.de/shop',
            'type' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Livewire::test(\App\Livewire\Frontend\Management\ManagementLinktreePage::class)
            ->call('trackAndRedirect', $link->id, 'https://evil-phishing.example.com')
            ->assertNoRedirect();

        $this->assertDatabaseHas('system_logs', [
            'type' => 'security',
            'action_id' => 'security:open_redirect_blocked',
        ]);
    }
}

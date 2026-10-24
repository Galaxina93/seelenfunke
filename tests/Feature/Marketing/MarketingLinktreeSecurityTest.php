<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Shop\Marketing\MarketingLinktree;
use App\Livewire\Shop\Marketing\MarketingTracking;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingLinktreeSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_marketing_linktree_or_tracking(): void
    {
        Livewire::test(MarketingLinktree::class)->assertStatus(403);
        Livewire::test(MarketingTracking::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_marketing_linktree_or_tracking(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(MarketingLinktree::class)
            ->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(MarketingTracking::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_marketing_linktree_and_tracking(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Marketing',
            'last_name' => 'Agent',
            'email' => 'employee.linktree@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingLinktree::class)
            ->assertStatus(200);

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingTracking::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(MarketingLinktree::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(MarketingTracking::class)
            ->assertStatus(200);
    }

    public function test_linktree_page_blocks_open_redirect_to_manipulated_url(): void
    {
        $link = \App\Models\Marketing\MarketingLinktree::create([
            'title' => 'Offizieller Shop',
            'url' => 'https://mein-seelenfunke.de/shop',
            'type' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Livewire::test(\App\Livewire\Frontend\Marketing\MarketingLinktreePage::class)
            ->call('trackAndRedirect', $link->id, 'https://evil-phishing.example.com')
            ->assertNoRedirect();

        $this->assertDatabaseHas('system_logs', [
            'type' => 'security',
            'action_id' => 'security:open_redirect_blocked',
        ]);
    }
}

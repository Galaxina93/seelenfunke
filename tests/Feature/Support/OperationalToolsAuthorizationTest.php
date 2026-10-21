<?php

namespace Tests\Feature\Support;

use App\Livewire\Auth\AuthProfileDropdown;
use App\Livewire\Shop\Ai\PartialsAiWorkspace\TabCronjobs;
use App\Livewire\Shop\Order\OrderAnalytics;
use App\Livewire\Shop\Order\OrderShoppingCarts;
use App\Livewire\Shop\Support\SupportContactFormComponent;
use App\Livewire\Shop\Support\SupportTelephony;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalToolsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_operational_tools(): void
    {
        Livewire::test(SupportTelephony::class)->assertStatus(403);
        Livewire::test(SupportContactFormComponent::class)->assertStatus(403);
        Livewire::test(TabCronjobs::class)->assertStatus(403);
        Livewire::test(AuthProfileDropdown::class)->assertStatus(403);
        Livewire::test(OrderAnalytics::class)->assertStatus(403);
        Livewire::test(OrderShoppingCarts::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_internal_operational_tools(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')->test(SupportTelephony::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(SupportContactFormComponent::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(TabCronjobs::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(OrderAnalytics::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(OrderShoppingCarts::class)->assertStatus(403);

        // Profile dropdown is permitted for authenticated customer
        Livewire::actingAs($customer, 'customer')->test(AuthProfileDropdown::class)->assertStatus(200);
    }

    public function test_admins_and_employees_access_operational_tools(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Agent',
            'last_name' => 'Mitarbeiter',
            'email' => 'agent@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        // Support & Orders: admin & employee allowed
        Livewire::actingAs($admin, 'admin')->test(SupportTelephony::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(SupportTelephony::class)->assertStatus(200);

        Livewire::actingAs($admin, 'admin')->test(SupportContactFormComponent::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(SupportContactFormComponent::class)->assertStatus(200);

        Livewire::actingAs($admin, 'admin')->test(OrderAnalytics::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(OrderAnalytics::class)->assertStatus(200);

        Livewire::actingAs($admin, 'admin')->test(OrderShoppingCarts::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(OrderShoppingCarts::class)->assertStatus(200);

        // Cronjobs: admin-only
        Livewire::actingAs($admin, 'admin')->test(TabCronjobs::class)->assertStatus(200);
        auth()->guard('admin')->logout();
        Livewire::actingAs($employee, 'employee')->test(TabCronjobs::class)->assertStatus(403);
    }
}

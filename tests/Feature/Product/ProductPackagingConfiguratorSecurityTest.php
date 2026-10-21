<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductPackagingConfigurator;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductPackagingConfiguratorSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_packaging_configurator(): void
    {
        Livewire::test(ProductPackagingConfigurator::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_packaging_configurator(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(ProductPackagingConfigurator::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_packaging_configurator(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Packaging',
            'last_name' => 'Agent',
            'email' => 'employee.pack@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ProductPackagingConfigurator::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(ProductPackagingConfigurator::class)
            ->assertStatus(200);
    }
}

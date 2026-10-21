<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductCreate;
use App\Livewire\Shop\Product\ProductFracture;
use App\Livewire\Shop\Product\ProductPackagingConfigurator;
use App\Livewire\Shop\Product\ProductSuppliers;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_product_management_components(): void
    {
        Livewire::test(ProductCreate::class)->assertStatus(403);
        Livewire::test(ProductSuppliers::class)->assertStatus(403);
        Livewire::test(ProductFracture::class)->assertStatus(403);
        Livewire::test(ProductPackagingConfigurator::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_product_management_components(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')->test(ProductCreate::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(ProductSuppliers::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(ProductFracture::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(ProductPackagingConfigurator::class)->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_product_management_components(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Mitarbeiter',
            'email' => 'employee-prod@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        // Admin
        Livewire::actingAs($admin, 'admin')->test(ProductCreate::class)->assertStatus(200);
        Livewire::actingAs($admin, 'admin')->test(ProductSuppliers::class)->assertStatus(200);
        Livewire::actingAs($admin, 'admin')->test(ProductFracture::class)->assertStatus(200);
        Livewire::actingAs($admin, 'admin')->test(ProductPackagingConfigurator::class)->assertStatus(200);

        // Employee
        Livewire::actingAs($employee, 'employee')->test(ProductCreate::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(ProductSuppliers::class)->assertStatus(200);
    }
}

<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductAttributes;
use App\Livewire\Shop\Product\ProductCategories;
use App\Livewire\Shop\Product\ProductCrawler;
use App\Livewire\Shop\Product\ProductTemplates;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogBackendAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Schlüsselanhänger',
            'slug' => 'schluesselanhaenger-' . Str::uuid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 1200,
        ], $attributes));
    }

    public function test_guests_cannot_mount_catalog_backend_components(): void
    {
        $product = $this->createProduct();

        Livewire::test(ProductAttributes::class, ['product' => $product])->assertStatus(403);
        Livewire::test(ProductCategories::class, ['product' => $product])->assertStatus(403);
        Livewire::test(ProductTemplates::class)->assertStatus(403);
        Livewire::test(ProductCrawler::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_catalog_backend_components(): void
    {
        $product = $this->createProduct();
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(ProductAttributes::class, ['product' => $product])
            ->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductCategories::class, ['product' => $product])
            ->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductTemplates::class)
            ->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductCrawler::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_catalog_backend_components(): void
    {
        $product = $this->createProduct();
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Tim',
            'last_name' => 'Mitarbeiter',
            'email' => 'tim@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ProductAttributes::class, ['product' => $product])
            ->assertStatus(200);

        Livewire::actingAs($admin, 'admin')
            ->test(ProductCategories::class, ['product' => $product])
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(ProductTemplates::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(ProductCrawler::class)
            ->assertStatus(200);
    }
}

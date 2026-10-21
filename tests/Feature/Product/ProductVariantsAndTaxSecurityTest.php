<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductTax;
use App\Livewire\Shop\Product\ProductVariants;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductVariantsAndTaxSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Armband',
            'slug' => 'armband-' . Str::uuid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2500,
        ], $attributes));
    }

    public function test_guests_cannot_mount_variants_and_tax_components(): void
    {
        $product = $this->createProduct();

        Livewire::test(ProductVariants::class, ['product' => $product])->assertStatus(403);
        Livewire::test(ProductTax::class, ['product' => $product])->assertStatus(403);
    }

    public function test_customers_cannot_mount_variants_and_tax_components(): void
    {
        $product = $this->createProduct();
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(ProductVariants::class, ['product' => $product])
            ->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductTax::class, ['product' => $product])
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_variants_and_tax_components(): void
    {
        $product = $this->createProduct();
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Sara',
            'last_name' => 'Mitarbeiterin',
            'email' => 'sara@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ProductVariants::class, ['product' => $product])
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(ProductTax::class, ['product' => $product])
            ->assertStatus(200);
    }

    public function test_variants_sanitizes_negative_price_and_stock(): void
    {
        $product = $this->createProduct();
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(ProductVariants::class, ['product' => $product])
            ->set('variants', [
                [
                    'sku' => 'SKU-TEST',
                    'name' => 'Test Variante',
                    'attributes' => ['Farbe' => 'Blau'],
                    'price' => '-15.50',
                    'stock' => '-10',
                ]
            ])
            ->call('saveVariants');

        $product->refresh();
        $savedVariants = $product->variants_data;
        $this->assertEquals(0, $savedVariants[0]['price']);
        $this->assertEquals(0, $savedVariants[0]['stock']);
    }
}

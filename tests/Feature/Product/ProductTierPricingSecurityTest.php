<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductTierPricing;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Product\Product;
use App\Models\Product\ProductTierPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductTierPricingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Kette',
            'slug' => 'kette-' . Str::uuid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3000,
        ], $attributes));
    }

    public function test_guests_and_customers_cannot_mount_tier_pricing(): void
    {
        $product = $this->createProduct();
        $customer = Customer::factory()->create();

        Livewire::test(ProductTierPricing::class, ['product' => $product])->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductTierPricing::class, ['product' => $product])
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_tier_pricing(): void
    {
        $product = $this->createProduct();
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Klaus',
            'last_name' => 'Mitarbeiter',
            'email' => 'klaus@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ProductTierPricing::class, ['product' => $product])
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(ProductTierPricing::class, ['product' => $product])
            ->assertStatus(200);
    }

    public function test_tier_cannot_delete_or_update_tier_from_different_product_idor(): void
    {
        $productA = $this->createProduct();
        $productB = $this->createProduct();

        $tierB = ProductTierPrice::create([
            'product_id' => $productB->id,
            'qty' => 5,
            'percent' => 10,
        ]);

        $admin = Admin::factory()->create();

        // Admin operates on Product A, attempts to delete tier of Product B
        Livewire::actingAs($admin, 'admin')
            ->test(ProductTierPricing::class, ['product' => $productA])
            ->call('removeTier', $tierB->id);

        // Tier B should still exist!
        $this->assertDatabaseHas('product_tier_prices', [
            'id' => $tierB->id,
            'product_id' => $productB->id,
        ]);
    }

    public function test_tier_percentage_is_clamped_and_qty_must_be_positive(): void
    {
        $product = $this->createProduct();
        $tier = ProductTierPrice::create([
            'product_id' => $product->id,
            'qty' => 5,
            'percent' => 10,
        ]);

        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(ProductTierPricing::class, ['product' => $product])
            ->set("tiers.{$tier->id}.percent", 150) // > 100%
            ->set("tiers.{$tier->id}.qty", -10) // negative qty
            ->call('updateTier', $tier->id);

        $tier->refresh();
        $this->assertEquals(100, $tier->percent);
        $this->assertEquals(1, $tier->qty);
    }
}

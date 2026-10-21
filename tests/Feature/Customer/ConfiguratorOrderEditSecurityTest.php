<?php

namespace Tests\Feature\Customer;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguratorOrderEditSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_edit_another_customers_order_item_in_configurator()
    {
        $customerA = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'victim@example.com',
            'first_name' => 'Alice',
            'last_name' => 'Victim',
            'password' => bcrypt('password123'),
        ]);

        $customerB = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'attacker@example.com',
            'first_name' => 'Bob',
            'last_name' => 'Attacker',
            'password' => bcrypt('password123'),
        ]);

        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Holzbrett',
            'slug' => 'holzbrett',
            'price' => 3000,
            'status' => 'active',
        ]);

        $orderA = OrderOrder::create([
            'id' => Str::uuid()->toString(),
            'order_number' => 'ORD-ALICE-1',
            'customer_id' => $customerA->id,
            'email' => $customerA->email,
            'status' => 'pending',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Alice', 'last_name' => 'Victim'],
            'subtotal_price' => 3000,
            'tax_amount' => 0,
            'total_price' => 3000,
        ]);

        $itemA = OrderOrderItem::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $orderA->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3000,
            'total_price' => 3000,
            'configuration' => ['notes' => 'Original Note Alice'],
        ]);

        // Attacker Bob attempts to mount the configurator with Alice's order item
        $this->actingAs($customerB, 'customer');

        Livewire::test(ProductConfigurator::class, [
            'product' => $product,
            'context' => 'order_edit',
            'cartItem' => $itemA,
        ])->assertStatus(403);
    }

    public function test_customer_can_edit_own_order_item_in_configurator()
    {
        $customerA = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'alice@example.com',
            'first_name' => 'Alice',
            'last_name' => 'User',
            'password' => bcrypt('password123'),
        ]);

        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Holzbrett',
            'slug' => 'holzbrett-2',
            'price' => 3000,
            'status' => 'active',
        ]);

        $orderA = OrderOrder::create([
            'id' => Str::uuid()->toString(),
            'order_number' => 'ORD-ALICE-2',
            'customer_id' => $customerA->id,
            'email' => $customerA->email,
            'status' => 'pending',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Alice', 'last_name' => 'User'],
            'subtotal_price' => 3000,
            'tax_amount' => 0,
            'total_price' => 3000,
        ]);

        $itemA = OrderOrderItem::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $orderA->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3000,
            'total_price' => 3000,
            'configuration' => ['notes' => 'Original Alice'],
        ]);

        $this->actingAs($customerA, 'customer');

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $product,
            'context' => 'order_edit',
            'cartItem' => $itemA,
        ]);

        $component->assertStatus(200);
    }
}

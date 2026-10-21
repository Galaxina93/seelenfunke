<?php

namespace Tests\Feature\Customer;

use App\Livewire\Customer\CustomerOrdersComponent;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerOrdersSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customerA;
    protected Customer $customerB;
    protected Product $product;
    protected OrderOrder $orderA;
    protected OrderOrder $orderB;
    protected OrderOrderItem $itemA;
    protected OrderOrderItem $itemB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerA = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'customer_a@example.com',
            'first_name' => 'Customer',
            'last_name' => 'A',
            'password' => bcrypt('secret123'),
        ]);

        $this->customerB = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'customer_b@example.com',
            'first_name' => 'Customer',
            'last_name' => 'B',
            'password' => bcrypt('secret123'),
        ]);

        $this->product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Produkt',
            'slug' => 'test-produkt-' . Str::random(5),
            'price' => 2500,
            'status' => 'active',
        ]);

        $this->orderA = OrderOrder::create([
            'id' => Str::uuid()->toString(),
            'order_number' => 'ORD-2026-AAAAAA',
            'customer_id' => $this->customerA->id,
            'email' => $this->customerA->email,
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal_price' => 2500,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 2500,
            'billing_address' => [
                'first_name' => 'Customer',
                'last_name' => 'A',
                'address' => 'Teststr. 1',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE'
            ],
            'shipping_address' => [
                'first_name' => 'Customer',
                'last_name' => 'A',
                'address' => 'Teststr. 1',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE'
            ],
        ]);

        $this->itemA = $this->orderA->items()->create([
            'id' => Str::uuid()->toString(),
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'tax_rate' => 19.0,
            'total_price' => 2500,
        ]);

        $this->orderB = OrderOrder::create([
            'id' => Str::uuid()->toString(),
            'order_number' => 'ORD-2026-BBBBBB',
            'customer_id' => $this->customerB->id,
            'email' => $this->customerB->email,
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal_price' => 2500,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 2500,
            'billing_address' => [
                'first_name' => 'Customer',
                'last_name' => 'B',
                'address' => 'Teststr. 2',
                'postal_code' => '12345',
                'city' => 'Hamburg',
                'country' => 'DE'
            ],
            'shipping_address' => [
                'first_name' => 'Customer',
                'last_name' => 'B',
                'address' => 'Teststr. 2',
                'postal_code' => '12345',
                'city' => 'Hamburg',
                'country' => 'DE'
            ],
        ]);

        $this->itemB = $this->orderB->items()->create([
            'id' => Str::uuid()->toString(),
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'tax_rate' => 19.0,
            'total_price' => 2500,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function customer_cannot_view_another_customers_order_via_idor()
    {
        Livewire::actingAs($this->customerA, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('showOrder', $this->orderB->id)
            ->assertSet('selectedOrderId', null)
            ->assertSee($this->orderA->order_number)
            ->assertDontSee($this->orderB->order_number);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function customer_can_view_their_own_order()
    {
        Livewire::actingAs($this->customerA, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('showOrder', $this->orderA->id)
            ->assertSet('selectedOrderId', $this->orderA->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function customer_cannot_preview_another_customers_order_item()
    {
        Livewire::actingAs($this->customerA, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('openPreview', $this->itemB->id)
            ->assertSet('previewItemId', null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function customer_cannot_edit_another_customers_order_item()
    {
        Livewire::actingAs($this->customerA, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('openEdit', $this->itemB->id)
            ->assertSet('editItemId', null);
    }
}

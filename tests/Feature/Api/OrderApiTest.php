<?php

namespace Tests\Feature\Api;

use App\Models\Order\OrderOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = \App\Models\Admin\Admin::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'email' => 'admin@test.de',
            'first_name' => 'Hans',
            'last_name' => 'Wurst',
            'password' => bcrypt('password')
        ]);

        $this->customer = \App\Models\Customer\Customer::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'email' => 'test@kunde.de',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'password' => bcrypt('password')
        ]);
    }

    private function createOrder($orderNumber, $email, $lastName, $status = 'pending', $isExpress = false)
    {
        return OrderOrder::create([
            'order_number' => $orderNumber,
            'email' => $email,
            'customer_id' => $this->customer->id,
            'status' => $status,
            'is_express' => $isExpress,
            'payment_status' => 'open',
            'payment_method' => 'bank_transfer',
            'total_price' => 100,
            'subtotal_price' => 100,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'billing_address' => [
                'first_name' => 'Max', 
                'last_name' => $lastName,
                'address' => 'Teststraße 1',
                'postal_code' => '12345',
                'city' => 'Musterstadt',
                'country' => 'DE'
            ],
            'shipping_address' => [
                'first_name' => 'Max', 
                'last_name' => $lastName,
                'address' => 'Teststraße 1',
                'postal_code' => '12345',
                'city' => 'Musterstadt',
                'country' => 'DE'
            ],
        ]);
    }

    public function test_api_requires_authentication()
    {
        $response = $this->getJson('/api/shop/orders');
        $response->assertStatus(401);
    }

    public function test_api_requires_admin_role()
    {
        $this->actingAs($this->customer);
        $response = $this->getJson('/api/shop/orders');
        $response->assertStatus(403);
    }

    public function test_api_returns_orders_with_default_workflow_sorting()
    {
        $this->actingAs($this->admin);

        // Create 3 orders
        // 1. Regular oldest (FIFO)
        $o1 = $this->createOrder('TEST-100', 'first@kunde.de', 'A-First', 'pending', false);
        $o1->created_at = now()->subDays(2);
        $o1->save();

        // 2. Completed oldest (should be at the bottom)
        $o2 = $this->createOrder('TEST-200', 'second@kunde.de', 'B-Second', 'completed', false);
        $o2->created_at = now()->subDays(3);
        $o2->save();

        // 3. Express newest (should be at the top of pending)
        $o3 = $this->createOrder('TEST-300', 'third@kunde.de', 'C-Third', 'pending', true);
        $o3->created_at = now()->subDay();
        $o3->save();

        $response = $this->getJson('/api/shop/orders');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);

        // Sorting: Open Express (o3) first, Open Standard (o1) second, Completed (o2) last.
        $this->assertEquals('TEST-300', $data[0]['order_number']);
        $this->assertEquals('TEST-100', $data[1]['order_number']);
        $this->assertEquals('TEST-200', $data[2]['order_number']);

        // Check priority_order key
        $priorityOrder = $response->json('priority_order');
        $this->assertNotNull($priorityOrder);
        // The priority order is the express one (TEST-300)
        $this->assertEquals('TEST-300', $priorityOrder['order_number']);
        $this->assertStringContainsString('Lagerbestand gesichert', $priorityOrder['priority_tip']);
    }

    public function test_api_can_search_by_order_number_and_billing_address()
    {
        $this->actingAs($this->admin);

        $this->createOrder('TEST-ALPHA', 'alpha@kunde.de', 'Kaiser');
        $this->createOrder('TEST-BETA', 'beta@kunde.de', 'Mueller');

        // Search for alpha
        $response = $this->getJson('/api/shop/orders?search=alpha');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('TEST-ALPHA', $data[0]['order_number']);

        // Search for last name
        $response = $this->getJson('/api/shop/orders?search=Mueller');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('TEST-BETA', $data[0]['order_number']);
    }

    public function test_api_can_filter_by_status()
    {
        $this->actingAs($this->admin);

        $this->createOrder('TEST-1', 'alpha@kunde.de', 'Kaiser', 'pending');
        $this->createOrder('TEST-2', 'beta@kunde.de', 'Mueller', 'completed');

        // Filter by completed
        $response = $this->getJson('/api/shop/orders?status=completed');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('TEST-2', $data[0]['order_number']);
        
        // Priority order (TEST-1) should still be returned in priority_order key!
        $priorityOrder = $response->json('priority_order');
        $this->assertNotNull($priorityOrder);
        $this->assertEquals('TEST-1', $priorityOrder['order_number']);
    }
}

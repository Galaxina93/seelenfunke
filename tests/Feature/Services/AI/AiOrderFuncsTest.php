<?php

namespace Tests\Feature\Services\AI;

use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Services\AI\Functions\AiOrderFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiOrderFuncsTest extends TestCase
{
    use RefreshDatabase, AiOrderFuncs;

    public function test_get_order_funcs_schema()
    {
        $schema = self::getAiOrderFuncsSchema();
        $this->assertIsArray($schema);
        
        $names = array_column($schema, 'name');
        $this->assertContains('order_get_details', $names);
    }

    public function test_execute_get_order_latest_fallback()
    {
        // Create two orders
        $o1 = OrderOrder::create([
            'order_number' => 'ORD-1',
            'email' => 'customer1@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'billing_address' => ['first_name' => 'Alice', 'last_name' => 'Smith', 'address' => 'Main St 1', 'postal_code' => '12345', 'city' => 'Berlin'],
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'total_price' => 1190,
        ]);
        $o1->created_at = now()->subDay();
        $o1->save();

        $o2 = OrderOrder::create([
            'order_number' => 'ORD-2',
            'email' => 'customer2@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Bob', 'last_name' => 'Jones', 'address' => 'Broadway 5', 'postal_code' => '54321', 'city' => 'Munich'],
            'subtotal_price' => 2000,
            'tax_amount' => 380,
            'total_price' => 2380,
        ]);
        $o2->created_at = now();
        $o2->save();

        $result = self::executeGetOrder([]);
        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['orders']);
        $this->assertEquals('ORD-2', $result['orders'][0]['order_number']);
    }

    public function test_execute_get_order_fuzzy_search_customer_name()
    {
        // Registered customer
        $customer = Customer::create([
            'first_name' => 'Philip',
            'last_name' => 'Goik',
            'email' => 'philip.goik@example.com',
            'password' => bcrypt('password'),
        ]);

        OrderOrder::create([
            'order_number' => 'ORD-2026-6NJZ5N',
            'customer_id' => $customer->id,
            'email' => 'philip.goik@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'billing_address' => ['first_name' => 'Philip', 'last_name' => 'Goik', 'address' => 'Musterweg 12', 'postal_code' => '80808', 'city' => 'München'],
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'total_price' => 5950,
        ]);

        // Guest customer (matches via billing_address name)
        OrderOrder::create([
            'order_number' => 'ORD-GUEST',
            'email' => 'guest@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'billing_address' => ['first_name' => 'John', 'last_name' => 'Goik', 'address' => 'Some St 10', 'postal_code' => '90909', 'city' => 'Nürnberg'],
            'subtotal_price' => 3000,
            'tax_amount' => 570,
            'total_price' => 3570,
        ]);

        // Unrelated order
        OrderOrder::create([
            'order_number' => 'ORD-OTHER',
            'email' => 'other@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'billing_address' => ['first_name' => 'Jane', 'last_name' => 'Smith', 'address' => 'Road 1', 'postal_code' => '11111', 'city' => 'Köln'],
            'subtotal_price' => 3000,
            'tax_amount' => 570,
            'total_price' => 3570,
        ]);

        // Query by 'Goik'
        $result = self::executeGetOrder(['customer_name' => 'Goik']);
        $this->assertEquals('success', $result['status']);
        $this->assertCount(2, $result['orders']);

        $numbers = array_column($result['orders'], 'order_number');
        $this->assertContains('ORD-2026-6NJZ5N', $numbers);
        $this->assertContains('ORD-GUEST', $numbers);
        $this->assertNotContains('ORD-OTHER', $numbers);
    }

    public function test_execute_get_order_total_price_range()
    {
        // Order with total 15.00 EUR (1500 cents)
        OrderOrder::create([
            'order_number' => 'ORD-LOW',
            'email' => 'low@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'billing_address' => ['first_name' => 'Low', 'last_name' => 'Price', 'address' => 'St 1', 'postal_code' => '12345', 'city' => 'Berlin'],
            'subtotal_price' => 1260,
            'tax_amount' => 240,
            'total_price' => 1500,
        ]);

        // Order with total 50.00 EUR (5000 cents)
        OrderOrder::create([
            'order_number' => 'ORD-MID',
            'email' => 'mid@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'billing_address' => ['first_name' => 'Mid', 'last_name' => 'Price', 'address' => 'St 2', 'postal_code' => '12345', 'city' => 'Berlin'],
            'subtotal_price' => 4200,
            'tax_amount' => 800,
            'total_price' => 5000,
        ]);

        // Order with total 120.00 EUR (12000 cents)
        OrderOrder::create([
            'order_number' => 'ORD-HIGH',
            'email' => 'high@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'billing_address' => ['first_name' => 'High', 'last_name' => 'Price', 'address' => 'St 3', 'postal_code' => '12345', 'city' => 'Berlin'],
            'subtotal_price' => 10080,
            'tax_amount' => 1920,
            'total_price' => 12000,
        ]);

        // Filter range 20.00 to 100.00 EUR
        $result = self::executeGetOrder(['min_total' => 20.00, 'max_total' => 100.00]);
        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['orders']);
        $this->assertEquals('ORD-MID', $result['orders'][0]['order_number']);
    }

    public function test_execute_get_order_status_filters()
    {
        // Order: processing / paid
        OrderOrder::create([
            'order_number' => 'ORD-1',
            'email' => 'c1@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'A', 'last_name' => 'B', 'address' => 'St 1', 'postal_code' => '123', 'city' => 'B'],
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'total_price' => 1190,
        ]);

        // Order: pending / unpaid
        OrderOrder::create([
            'order_number' => 'ORD-2',
            'email' => 'c2@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'billing_address' => ['first_name' => 'C', 'last_name' => 'D', 'address' => 'St 2', 'postal_code' => '123', 'city' => 'B'],
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'total_price' => 1190,
        ]);

        // Filter by status=processing
        $result = self::executeGetOrder(['status' => 'processing']);
        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['orders']);
        $this->assertEquals('ORD-1', $result['orders'][0]['order_number']);

        // Filter by payment_status=unpaid
        $resultPay = self::executeGetOrder(['payment_status' => 'unpaid']);
        $this->assertEquals('success', $resultPay['status']);
        $this->assertCount(1, $resultPay['orders']);
        $this->assertEquals('ORD-2', $resultPay['orders'][0]['order_number']);
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Services\AI\Functions\AiScoutFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiScoutFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_customers_enforces_minimum_length_and_escapes_wildcards()
    {
        $dummy = new class {
            use AiScoutFuncs;
        };

        // 1. Short query rejected
        $resShort = $dummy::executeSearchCustomers([
            'search_query' => 'a',
        ]);
        $this->assertEquals('error', $resShort['status']);
        $this->assertStringContainsString('mindestens 2 Zeichen', $resShort['message']);

        // 2. Wildcard query does not match all customers
        $c1 = Customer::create([
            'first_name' => 'Max_Mustermann',
            'last_name' => 'Schmidt',
            'email' => 'max_test@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $c1->updateAiDataConsent(true, 'test');

        Customer::create([
            'first_name' => 'MaxXMustermann',
            'last_name' => 'Schmidt',
            'email' => 'maxx_test@example.com',
            'password' => bcrypt('secret123'),
        ]);

        // '_' query search
        $res = $dummy::executeSearchCustomers([
            'search_query' => 'Max_Mustermann',
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertCount(1, $res['customers']);
        $this->assertEquals('Max_Mustermann Schmidt', $res['customers'][0]['name']);
    }

    public function test_search_customers_calculates_spent_with_total_price_without_crash()
    {
        $dummy = new class {
            use AiScoutFuncs;
        };

        $customer = Customer::create([
            'first_name' => 'Laura',
            'last_name' => 'Meier',
            'email' => 'laura@example.com',
            'password' => bcrypt('secret123'),
        ]);

        OrderOrder::create([
            'id' => Str::uuid(),
            'customer_id' => $customer->id,
            'order_number' => 'ORD-LAURA-01',
            'email' => 'laura@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Laura'],
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 5490,
        ]);

        $res = $dummy::executeSearchCustomers([
            'search_query' => 'Laura',
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertCount(1, $res['customers']);
        $this->assertEquals(1, $res['customers'][0]['total_orders']);
        $this->assertStringContainsString('54,90', $res['customers'][0]['total_spent']);
    }
}

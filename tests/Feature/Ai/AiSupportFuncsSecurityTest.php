<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Support\SupportTicket;
use App\Models\Order\OrderOrder;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Support\SupportCustomerChat;
use App\Services\AI\Functions\AiSupportFuncs;

class AiSupportFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $mockClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClass = new class {
            use AiSupportFuncs;
        };
    }

    public function test_get_ticket_status_works_without_ilike_syntax_error(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $ticket = SupportTicket::create([
            'ticket_number' => 'TCK-12345678',
            'customer_id' => $customer->id,
            'subject' => 'Mein Ticket',
            'category' => 'allgemein',
            'status' => 'open',
            'priority' => 'normal'
        ]);

        $res = $this->mockClass::executeGetTicketStatus([
            'ticket_number' => 'TCK-12345678'
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('TCK-12345678', $res['data']['ticket_number']);
    }

    public function test_order_status_escapes_wildcards(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $order = OrderOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-1001',
            'email' => 'customer@example.com',
            'status' => 'pending',
            'subtotal_price' => 5000,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 5000,
            'payment_status' => 'paid',
            'billing_address' => [],
            'shipping_address' => []
        ]);

        // Literal '%' search should not match ORD-1001
        $res = $this->mockClass::executeGetOrderStatus([
            'identifier' => '%'
        ]);

        $this->assertEquals('not_found', $res['status']);
    }

    public function test_product_fuzzy_search_limits_memory(): void
    {
        Product::create([
            'name' => 'Seelen Kristall Anhänger',
            'slug' => 'seelen-kristall-anhaenger',
            'price' => 2999,
            'status' => 'active'
        ]);

        // Search with typo / spacing
        $res = $this->mockClass::executeGetProductInfo([
            'search_term' => 'seelenkristall'
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertNotEmpty($res['data']);
        $this->assertEquals('Seelen Kristall Anhänger', $res['data'][0]['name']);
    }
}

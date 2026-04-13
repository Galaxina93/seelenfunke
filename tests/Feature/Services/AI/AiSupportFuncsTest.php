<?php

namespace Tests\Feature\Services\AI;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\AI\Functions\AiSupportFuncs;
use Illuminate\Support\Str;
use App\Models\Support\SupportCustomerChat;
use App\Models\Ai\AiAgent;
use App\Models\System\SystemLog;

// Dummy class to test the trait
class AiSupportFuncsDummy
{
    use AiSupportFuncs;
}

class AiSupportFuncsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function test_returns_correct_message_if_cart_is_empty()
    {
        $result = AiSupportFuncsDummy::executeAnalyzeCart([]);
        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('Der Warenkorb des Kunden ist aktuell komplett leer', $result['message']);
    }

    /** @test */
    public function test_returns_delivery_times_info()
    {
        $result = AiSupportFuncsDummy::executeGetDeliveryTimes([]);
        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('Standard-Produktionszeit', $result['message']);
    }

    /** @test */
    public function test_identifies_personalized_orders_for_returns_policy()
    {
        $customer = Customer::factory()->create();
        $order = OrderOrder::create([
            'order_number' => 'ORD-123456',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'pending',
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 0,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 0,
        ]);
        
        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => Product::create(['name' => 'Mock Product', 'slug' => 'mock-1-'.Str::uuid(), 'status' => 'active', 'type' => 'physical', 'price' => 1000])->id,
            'product_name' => 'Mock Product',
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'configuration' => ['engraving_text' => 'Hello World']
        ]);

        $result = AiSupportFuncsDummy::executeCheckReturnsPolicy(['order_number' => '123456']);
        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('Die Bestellung enthält PERSONALISIERTE Artikel', $result['message']);
        $this->assertStringContainsString('streng vom Widerrufsrecht ausgeschlossen', $result['message']);
    }

    /** @test */
    public function test_identifies_standard_orders_for_returns_policy()
    {
        $customer = Customer::factory()->create();
        $order = OrderOrder::create([
            'order_number' => 'ORD-789012',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'pending',
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 0,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 0,
        ]);
        
        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => Product::create(['name' => 'Mock Product', 'slug' => 'mock-2-'.Str::uuid(), 'status' => 'active', 'type' => 'physical', 'price' => 1000])->id,
            'product_name' => 'Mock Product',
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'configuration' => null
        ]);

        $result = AiSupportFuncsDummy::executeCheckReturnsPolicy(['order_number' => '789012']);
        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('besteht nur aus Standard-Artikeln', $result['message']);
        $this->assertStringContainsString('Zeige dem Kunden das Verhalten passend zum Frist-Status', $result['message']);
    }

    /** @test */
    public function test_fails_claim_ticket_if_customer_not_logged_in()
    {
        $result = AiSupportFuncsDummy::executeCreateClaimTicket([
            'order_number' => 'ORD-111',
            'reason_summary' => 'Defekt'
        ]);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Kunde muss sich erst einloggen', $result['message']);
    }

    /** @test */
    public function test_creates_claim_ticket_and_logs_snapshot()
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $order = OrderOrder::create([
            'order_number' => 'ORD-CLAIM-123',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'pending',
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 0,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 0,
        ]);

        $agent = AiAgent::create(['id' => Str::uuid(), 'name' => 'Support AI']);
        $chat = SupportCustomerChat::create([
            'id' => Str::uuid(),
            'status' => 'open'
        ]);

        $result = AiSupportFuncsDummy::executeCreateClaimTicket([
            'order_number' => 'CLAIM-123',
            'reason_summary' => 'Das Glas ist gebrochen angekommen.',
            '__chat_id' => $chat->id,
            '__agent_id' => $agent->id
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('Reklamationsticket wurde mit Prio Hoch', $result['message']);
        
        // Assert Ticket exists
        $this->assertDatabaseHas('support_tickets', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'reklamation',
            'priority' => 'high'
        ]);

        // Assert Log exists (Snapshot)
        $this->assertDatabaseHas('system_logs', [
            'ai_agent_id' => $agent->id,
            'action_id' => 'ai_claim_ticket',
            'status' => 'success'
        ]);
    }

    /** @test */
    public function test_prevents_address_change_if_order_not_pending()
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $order = OrderOrder::create([
            'order_number' => 'ORD-NOCHANGE',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'processing', // NOT pending
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 0,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 0,
        ]);

        $result = AiSupportFuncsDummy::executeModifyPendingOrder([
            'order_number' => 'NOCHANGE',
            'action_type' => 'change_address',
            'new_address_data' => ['street' => 'New Street 1']
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('ABSOLUTE SPERRE - Datenrettung nicht mehr möglich', $result['message']);
    }

    /** @test */
    public function test_changes_address_and_saves_snapshot_log_if_pending()
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $oldAddress = [
            'first_name' => 'Old',
            'last_name' => 'Name',
            'street' => 'Old Street',
            'house_number' => '1',
            'zipcode' => '12345',
            'city' => 'Old City'
        ];

        $order = OrderOrder::create([
            'order_number' => 'ORD-CHANGE-OK',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'pending',
            'billing_address' => [],
            'shipping_address' => $oldAddress,
            'subtotal_price' => 0,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 0,
        ]);

        $agent = AiAgent::create(['id' => Str::uuid(), 'name' => 'Address AI']);
        $chat = SupportCustomerChat::create([
            'id' => Str::uuid(),
            'status' => 'open'
        ]);

        $result = AiSupportFuncsDummy::executeModifyPendingOrder([
            'order_number' => 'CHANGE-OK',
            'action_type' => 'change_address',
            'new_address_data' => [
                'street' => 'New Street',
                'house_number' => '99',
                'zipcode' => '98765',
                'city' => 'New City'
            ],
            '__chat_id' => $chat->id,
            '__agent_id' => $agent->id
        ]);

        $this->assertEquals('success', $result['status']);

        // Assert Order updated
        $order->refresh();
        $address = $order->shipping_address;
        $this->assertEquals('New Street', $address['street']);
        $this->assertEquals('99', $address['house_number']);
        $this->assertEquals('Old', $address['first_name']); // Kept old value since it wasn't passed or fallback took it

        // Assert Log created
        $log = SystemLog::where('ai_agent_id', $agent->id)
            ->where('action_id', 'ai_order_modify')
            ->first();
            
        $this->assertNotNull($log);
        $payload = $log->payload;
        
        $this->assertEquals('Old Street', $payload['before']['shipping_address']['street']);
        $this->assertEquals('New Street', $payload['after']['shipping_address']['street']);
    }

    /** @test */
    public function test_cancels_order_if_pending()
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $order = OrderOrder::create([
            'order_number' => 'ORD-CANCEL-OK',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'pending',
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 0,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 0,
        ]);

        $result = AiSupportFuncsDummy::executeModifyPendingOrder([
            'order_number' => 'CANCEL-OK',
            'action_type' => 'cancel_order',
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('TECHNISCHE SPERRE. DU DARFST KEINE BESTELLUNGEN STORNIEREN!', $result['message']);
    }
}

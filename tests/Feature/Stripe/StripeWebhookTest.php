<?php

namespace Tests\Feature\Stripe;

use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Models\Customer\Customer;
use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Stripe\WebhookSignature;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $webhookSecret = 'whsec_test_secret_for_phpunit_12345';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook.secret' => $this->webhookSecret,
            'services.stripe.webhook_secret' => $this->webhookSecret,
            'services.stripe.secret' => 'sk_test_mock_secret',
        ]);

        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
    }

    protected function generateStripeHeader(string $payload, int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->webhookSecret);
        return "t={$timestamp},v1={$signature}";
    }

    protected function createTestOrder(array $attributes = []): OrderOrder
    {
        $customer = Customer::create([
            'email' => 'customer@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'password' => bcrypt('secret123'),
        ]);

        return OrderOrder::create(array_merge([
            'order_number' => 'ORD-2026-TEST01',
            'customer_id' => $customer->id,
            'email' => 'customer@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'company' => '',
                'address' => 'Testweg 1',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'company' => '',
                'address' => 'Testweg 1',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 595,
            'total_price' => 5950,
        ], $attributes));
    }

    #[Test]
    public function it_is_exempt_from_csrf_verification()
    {
        $response = $this->post('/stripe/webhook', [], [
            'Content-Type' => 'application/json',
        ]);

        // Must NOT return 419 Page Expired
        $this->assertNotEquals(419, $response->getStatusCode());
        $this->assertEquals(400, $response->getStatusCode());
    }

    #[Test]
    public function it_rejects_requests_with_invalid_signature()
    {
        $payload = json_encode(['type' => 'payment_intent.succeeded']);

        $response = $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=invalid_signature_hash',
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(400);
        $this->assertStringContainsString('Invalid Signature', $response->getContent());
    }

    #[Test]
    public function it_processes_checkout_session_completed_webhook()
    {
        Queue::fake();

        $product = Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Produkt',
            'slug' => 'test-produkt-' . uniqid(),
            'sku' => 'TP-' . uniqid(),
            'price' => 5000,
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $order = $this->createTestOrder();
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 2500,
            'tax_rate' => 19.0,
            'total_price' => 5000,
        ]);

        $payload = json_encode([
            'id' => 'evt_test_checkout_session',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'payment_intent' => 'pi_test_from_cs',
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ]);

        $signatureHeader = $this->generateStripeHeader($payload);

        $response = $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $signatureHeader,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('pi_test_from_cs', $order->stripe_payment_intent_id);

        $product->refresh();
        $this->assertEquals(8, $product->quantity); // 10 - 2

        Queue::assertPushed(ProcessOrderDocumentsAndMails::class);
    }

    #[Test]
    public function it_processes_payment_intent_succeeded_webhook_by_stripe_payment_intent_id()
    {
        Queue::fake();

        $product = Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Produkt 2',
            'slug' => 'test-produkt-2-' . uniqid(),
            'sku' => 'TP2-' . uniqid(),
            'price' => 3000,
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => true,
            'quantity' => 5,
        ]);

        $order = $this->createTestOrder([
            'stripe_payment_intent_id' => 'pi_test_element_direct_999',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3000,
            'tax_rate' => 19.0,
            'total_price' => 3000,
        ]);

        $payload = json_encode([
            'id' => 'evt_test_pi_succeeded',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_element_direct_999',
                    'metadata' => [],
                ],
            ],
        ]);

        $signatureHeader = $this->generateStripeHeader($payload);

        $response = $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $signatureHeader,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        $product->refresh();
        $this->assertEquals(4, $product->quantity); // 5 - 1

        Queue::assertPushed(ProcessOrderDocumentsAndMails::class);
    }

    #[Test]
    public function it_is_idempotent_when_payment_intent_succeeded_is_sent_multiple_times()
    {
        Queue::fake();

        $product = Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Produkt Idempotent',
            'slug' => 'test-produkt-idem-' . uniqid(),
            'sku' => 'TP-IDEM-' . uniqid(),
            'price' => 2000,
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $order = $this->createTestOrder([
            'stripe_payment_intent_id' => 'pi_test_idempotent_123',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 2000,
            'tax_rate' => 19.0,
            'total_price' => 4000,
        ]);

        $payload = json_encode([
            'id' => 'evt_test_pi_succeeded_idem',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_idempotent_123',
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ]);

        $signatureHeader = $this->generateStripeHeader($payload);

        // Call 1
        $res1 = $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signatureHeader,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
        $res1->assertStatus(200);

        // Call 2 (retry by Stripe)
        $res2 = $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signatureHeader,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
        $res2->assertStatus(200);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        $product->refresh();
        // Quantity must be reduced only ONCE (10 - 2 = 8, not 6!)
        $this->assertEquals(8, $product->quantity);

        // Queue job dispatched only ONCE
        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, 1);
    }

    #[Test]
    public function it_converts_quote_when_quote_number_is_in_webhook_metadata()
    {
        Queue::fake();

        $order = $this->createTestOrder([
            'stripe_payment_intent_id' => 'pi_test_quote_conv_123',
        ]);

        $quote = OrderQuoteRequest::create([
            'quote_number' => 'ANG-2026-99999',
            'customer_id' => $order->customer_id,
            'email' => $order->email,
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'status' => 'open',
            'net_total' => 5000,
            'tax_total' => 950,
            'gross_total' => 5950,
        ]);

        $payload = json_encode([
            'id' => 'evt_test_quote_conv',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_quote_conv_123',
                    'metadata' => [
                        'order_id' => $order->id,
                        'quote_number' => 'ANG-2026-99999',
                    ],
                ],
            ],
        ]);

        $signatureHeader = $this->generateStripeHeader($payload);

        $response = $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signatureHeader,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);

        $quote->refresh();
        $this->assertEquals('converted', $quote->status);
        $this->assertEquals($order->id, $quote->converted_order_id);
    }

    #[Test]
    public function it_handles_payment_failed_without_errors()
    {
        $payload = json_encode([
            'id' => 'evt_test_failed',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test_fail_123',
                    'last_payment_error' => [
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
        ]);

        $signatureHeader = $this->generateStripeHeader($payload);

        $response = $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signatureHeader,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);
    }
}

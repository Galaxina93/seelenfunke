<?php

namespace Tests\Feature\Order;

use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Customer\Customer;
use App\Models\Management\ManagementTask;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Product\Product;
use App\Models\System\SystemLog;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_stripe_webhook_controller_test_secret_2026';

    private string $tempLocalDiskRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempLocalDiskRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/sf_stripe_whk_local_' . Str::random(10);
        @mkdir($this->tempLocalDiskRoot, 0777, true);

        Queue::fake();
        Mail::fake();

        config([
            'filesystems.disks.local.root' => $this->tempLocalDiskRoot,
            'logging.default' => 'null',
            'services.stripe.webhook.secret' => $this->webhookSecret,
            'services.stripe.webhook_secret' => $this->webhookSecret,
            'services.stripe.secret' => 'sk_test_webhook_controller_mock',
        ]);
        Storage::forgetDisk('local');

        $this->seed(SystemSettingSeeder::class);
    }

    protected function tearDown(): void
    {
        if ($this->tempLocalDiskRoot !== '' && is_dir($this->tempLocalDiskRoot)) {
            app('files')->deleteDirectory($this->tempLocalDiskRoot);
        }

        parent::tearDown();
    }

    private function signStripePayload(string $payload, ?int $timestamp = null, ?string $secret = null): string
    {
        $ts = $timestamp ?? time();
        $key = $secret ?? $this->webhookSecret;
        $sig = hash_hmac('sha256', "{$ts}.{$payload}", $key);

        return "t={$ts},v1={$sig}";
    }

    private function postSignedWebhook(array $eventPayload, ?string $overrideSignatureHeader = null): TestResponse
    {
        $rawJson = json_encode($eventPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sigHeader = $overrideSignatureHeader ?? $this->signStripePayload($rawJson);

        return $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $sigHeader,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawJson
        );
    }

    private function createProduct(int $priceCents = 2500, int $stock = 10, bool $personalizable = false): Product
    {
        return Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Manufaktur Testartikel ' . Str::upper(Str::random(4)),
            'slug' => 'manufaktur-testartikel-' . Str::lower(Str::random(8)),
            'sku' => 'SKU-' . Str::upper(Str::random(6)),
            'price' => $priceCents,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => $personalizable,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => false,
            'quantity' => $stock,
            'weight' => 250,
        ]);
    }

    private function createOrder(array $overrides = []): OrderOrder
    {
        $customer = Customer::create([
            'email' => 'kunde-' . Str::lower(Str::random(6)) . '@example.com',
            'first_name' => 'Alina',
            'last_name' => 'Testkundin',
            'password' => bcrypt('Secret123!'),
        ]);

        return OrderOrder::create(array_merge([
            'order_number' => 'ORD-2026-' . Str::upper(Str::random(6)),
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'billing_address' => [
                'first_name' => 'Alina',
                'last_name' => 'Testkundin',
                'company' => '',
                'address' => 'Carl-Goerdeler-Ring 26',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Alina',
                'last_name' => 'Testkundin',
                'company' => '',
                'address' => 'Carl-Goerdeler-Ring 26',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 5490,
            'is_small_business' => false,
        ], $overrides));
    }

    #[Test]
    public function it_rejects_missing_invalid_or_expired_stripe_signatures_with_http_400(): void
    {
        // 1a. Fehlendes Stripe-Signature Header
        $missingHeaderResponse = $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => 'evt_no_sig', 'type' => 'payment_intent.succeeded'])
        );
        $missingHeaderResponse->assertStatus(400);
        $this->assertStringContainsString('Missing Signature', $missingHeaderResponse->getContent());

        // 1b. Manipulierte / falsche HMAC-Signatur -> HTTP 400 + Security-Log
        $tamperedResponse = $this->postSignedWebhook(
            [
                'id' => 'evt_bad_sig',
                'object' => 'event',
                'type' => 'payment_intent.succeeded',
                'data' => ['object' => ['id' => 'pi_bad_sig']],
            ],
            't=' . time() . ',v1=' . str_repeat('a', 64)
        );
        $tamperedResponse->assertStatus(400);
        $this->assertStringContainsString('Invalid Signature', $tamperedResponse->getContent());
        $this->assertDatabaseHas('system_logs', [
            'type' => 'security',
            'action_id' => 'security:webhook_signature_invalid',
        ]);

        // 1c. Replay-Versuch mit abgelaufenem Zeitstempel (> 5 Min.) -> HTTP 400
        $rawReplayJson = json_encode([
            'id' => 'evt_expired_sig',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_expired_sig']],
        ]);
        $expiredSig = $this->signStripePayload($rawReplayJson, time() - 3600);

        $expiredResponse = $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $expiredSig,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawReplayJson
        );
        $expiredResponse->assertStatus(400);
    }

    #[Test]
    public function it_marks_unpaid_order_as_paid_on_payment_intent_succeeded_and_is_strictly_idempotent(): void
    {
        $product = $this->createProduct(2500, 10);
        $order = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_live_idem_001',
            'subtotal_price' => 5000,
            'shipping_price' => 490,
            'total_price' => 5490,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 2500,
            'tax_rate' => 19.0,
            'total_price' => 5000,
        ]);

        $eventPayload = [
            'id' => 'evt_pi_succeeded_001',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_live_idem_001',
                    'object' => 'payment_intent',
                    'amount' => 5490,
                    'amount_received' => 5490,
                    'currency' => 'eur',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ],
            ],
        ];

        // Erster Webhook-Zustellversuch
        $this->postSignedWebhook($eventPayload)->assertStatus(200);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('pending', $order->status);
        $this->assertSame(8, $product->fresh()->quantity);
        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, 1);

        // Zweiter Webhook-Zustellversuch (Retry durch Stripe) -> Idempotenz!
        $this->postSignedWebhook($eventPayload)->assertStatus(200);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(8, $product->fresh()->quantity);
        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, 1);
    }

    #[Test]
    public function it_rejects_payment_intent_succeeded_with_underpaid_amount_or_wrong_currency(): void
    {
        $product = $this->createProduct(5000, 10);
        $order = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_tamper_test_002',
            'subtotal_price' => 5000,
            'shipping_price' => 490,
            'total_price' => 5490,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 5000,
            'tax_rate' => 19.0,
            'total_price' => 5000,
        ]);

        // 3a. Zu geringer Betrag (amount < total_price) -> HTTP 400, Order bleibt unpaid
        $underpaidPayload = [
            'id' => 'evt_pi_underpaid',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_tamper_test_002',
                    'object' => 'payment_intent',
                    'amount' => 5489,
                    'currency' => 'eur',
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ];

        $this->postSignedWebhook($underpaidPayload)
            ->assertStatus(400)
            ->assertSee('Amount mismatch');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(10, $product->fresh()->quantity);
        Queue::assertNothingPushed();

        // 3b. Falsche Währung (usd statt eur) -> HTTP 400, Order bleibt unpaid
        $wrongCurrencyPayload = [
            'id' => 'evt_pi_wrong_currency',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_tamper_test_002',
                    'object' => 'payment_intent',
                    'amount' => 5490,
                    'currency' => 'usd',
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ];

        $this->postSignedWebhook($wrongCurrencyPayload)
            ->assertStatus(400)
            ->assertSee('Currency mismatch');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(10, $product->fresh()->quantity);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_completes_checkout_session_and_converts_associated_order_quote_request(): void
    {
        $product = $this->createProduct(10000, 15);
        $order = $this->createOrder([
            'payment_method' => 'stripe_link',
            'payment_url' => 'https://checkout.stripe.com/c/pay/cs_test_quote_1',
            'subtotal_price' => 10000,
            'tax_amount' => 1597,
            'shipping_price' => 0,
            'total_price' => 10000,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 10000,
            'tax_rate' => 19.0,
            'total_price' => 10000,
        ]);

        $quote = OrderQuoteRequest::create([
            'quote_number' => 'ANG-2026-77701',
            'customer_id' => $order->customer_id,
            'email' => $order->email,
            'first_name' => 'Alina',
            'last_name' => 'Testkundin',
            'status' => 'open',
            'net_total' => 8403,
            'tax_total' => 1597,
            'gross_total' => 10000,
        ]);

        $sessionPayload = [
            'id' => 'evt_cs_quote_completed',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_quote_1',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'currency' => 'eur',
                    'amount_total' => 10000,
                    'payment_intent' => 'pi_from_cs_quote_1',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                        'quote_number' => 'ANG-2026-77701',
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($sessionPayload)->assertStatus(200);

        $order->refresh();
        $quote->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('pi_from_cs_quote_1', $order->stripe_payment_intent_id);
        $this->assertNull($order->payment_url);
        $this->assertSame(14, $product->fresh()->quantity);

        $this->assertSame('converted', $quote->status);
        $this->assertSame($order->id, $quote->converted_order_id);
        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, 1);
    }

    #[Test]
    public function it_handles_payment_intent_payment_failed_gracefully_with_http_200(): void
    {
        $order = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_failed_card_005',
        ]);

        $failedPayload = [
            'id' => 'evt_pi_failed_005',
            'object' => 'event',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed_card_005',
                    'object' => 'payment_intent',
                    'last_payment_error' => [
                        'code' => 'card_declined',
                        'message' => 'Your card has insufficient funds.',
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($failedPayload)->assertStatus(200);

        // Order bleibt unbezahlt/pending, damit der Kunde im Checkout ein anderes Zahlungsmittel wählen kann
        $order->refresh();
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pending', $order->status);
    }

    #[Test]
    public function it_cancels_unpaid_pending_order_on_payment_intent_canceled_but_never_touches_paid_orders(): void
    {
        // 6a. Unbezahlte pending Order -> wird sauber storniert
        $unpaidOrder = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_cancel_unpaid_006',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $cancelUnpaidPayload = [
            'id' => 'evt_pi_canceled_unpaid_006',
            'object' => 'event',
            'type' => 'payment_intent.canceled',
            'data' => [
                'object' => [
                    'id' => 'pi_cancel_unpaid_006',
                    'object' => 'payment_intent',
                ],
            ],
        ];

        $this->postSignedWebhook($cancelUnpaidPayload)->assertStatus(200);

        $unpaidOrder->refresh();
        $this->assertSame('cancelled', $unpaidOrder->status);
        $this->assertSame('unpaid', $unpaidOrder->payment_status);
        $this->assertNotEmpty($unpaidOrder->cancellation_reason);

        // 6b. Bereits bezahlte Order -> bleibt unangetastet!
        $product = $this->createProduct(3000, 5);
        $paidOrder = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_already_paid_006',
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);
        $paidOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3000,
            'tax_rate' => 19.0,
            'total_price' => 3000,
        ]);

        $cancelPaidPayload = [
            'id' => 'evt_pi_canceled_paid_006',
            'object' => 'event',
            'type' => 'payment_intent.canceled',
            'data' => [
                'object' => [
                    'id' => 'pi_already_paid_006',
                    'object' => 'payment_intent',
                    'metadata' => ['order_id' => (string) $paidOrder->id],
                ],
            ],
        ];

        $this->postSignedWebhook($cancelPaidPayload)->assertStatus(200);

        $paidOrder->refresh();
        $this->assertSame('paid', $paidOrder->payment_status);
        $this->assertSame('pending', $paidOrder->status);
        $this->assertSame(5, $product->fresh()->quantity);
    }

    #[Test]
    public function it_marks_paid_order_as_refunded_on_charge_refunded_with_gobd_credit_note_idempotently(): void
    {
        $standardProduct = $this->createProduct(4990, 8, false);
        $paidOrder = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_refund_paid_007',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 4990,
            'tax_amount' => 797,
            'shipping_price' => 0,
            'total_price' => 4990,
        ]);
        $paidOrder->items()->create([
            'product_id' => $standardProduct->id,
            'product_name' => $standardProduct->name,
            'quantity' => 1,
            'unit_price' => 4990,
            'tax_rate' => 19.0,
            'total_price' => 4990,
        ]);

        $refundPayload = [
            'id' => 'evt_charge_refunded_007',
            'object' => 'event',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_refund_007',
                    'object' => 'charge',
                    'payment_intent' => 'pi_refund_paid_007',
                    'amount' => 4990,
                    'amount_refunded' => 4990,
                    'refunded' => true,
                    'refunds' => [
                        'data' => [
                            ['id' => 're_full_007', 'amount' => 4990],
                        ],
                    ],
                ],
            ],
        ];

        // Erster Aufruf -> Order wird refunded + 1 GoBD-Gutschrift + unpersonalisierte Lagerware einmalig zurückgebucht
        $this->postSignedWebhook($refundPayload)->assertStatus(200);

        $paidOrder->refresh();
        $this->assertSame('refunded', $paidOrder->status);
        $this->assertSame('refunded', $paidOrder->payment_status);
        $this->assertSame(9, $standardProduct->fresh()->quantity);
        $this->assertSame(
            1,
            AccountingInvoice::where('order_id', $paidOrder->id)->where('type', 'credit_note')->count()
        );

        // Zweiter Aufruf (Retry durch Stripe) -> 100 % idempotent (keine 2. Gutschrift, keine doppelte Lagerrückbuchung!)
        $this->postSignedWebhook($refundPayload)->assertStatus(200);

        $paidOrder->refresh();
        $this->assertSame('refunded', $paidOrder->status);
        $this->assertSame('refunded', $paidOrder->payment_status);
        $this->assertSame(9, $standardProduct->fresh()->quantity);
        $this->assertSame(
            1,
            AccountingInvoice::where('order_id', $paidOrder->id)->where('type', 'credit_note')->count()
        );
    }

    #[Test]
    public function it_logs_security_warning_and_creates_priority_task_idempotently_on_charge_dispute_created(): void
    {
        $order = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_dispute_008',
            'status' => 'shipped',
            'payment_status' => 'paid',
            'total_price' => 5490,
        ]);

        $disputePayload = [
            'id' => 'evt_dispute_created_008',
            'object' => 'event',
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [
                    'id' => 'dp_test_008',
                    'object' => 'dispute',
                    'payment_intent' => 'pi_dispute_008',
                    'amount' => 5490,
                    'reason' => 'fraudulent',
                ],
            ],
        ];

        $this->postSignedWebhook($disputePayload)->assertStatus(200);
        $this->postSignedWebhook($disputePayload)->assertStatus(200);

        $this->assertSame(
            1,
            SystemLog::where('action_id', 'security:stripe_dispute_created:dp_test_008')->count()
        );
        $this->assertSame(
            1,
            SystemLog::where('action_id', 'stripe_webhook:dispute_created:dp_test_008')->count()
        );
        $this->assertSame(
            1,
            ManagementTask::where('title', 'like', '%' . $order->order_number . '%')->count()
        );
    }

    #[Test]
    public function it_guards_checkout_session_against_currency_or_amount_tampering_and_underpaid_quote_conversion(): void
    {
        $order = $this->createOrder([
            'subtotal_price' => 5000,
            'shipping_price' => 490,
            'total_price' => 5490,
        ]);

        // Falsche Währung in checkout.session.completed -> HTTP 400
        $this->postSignedWebhook([
            'id' => 'evt_cs_usd_009',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_usd_009',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'currency' => 'usd',
                    'amount_total' => 5490,
                    'payment_intent' => 'pi_cs_usd_009',
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ])->assertStatus(400)->assertSee('Currency mismatch');

        // Unterzahlung in checkout.session.completed -> HTTP 400
        $this->postSignedWebhook([
            'id' => 'evt_cs_underpaid_009',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_underpaid_009',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'currency' => 'eur',
                    'amount_total' => 1000,
                    'payment_intent' => 'pi_cs_underpaid_009',
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ])->assertStatus(400)->assertSee('Amount mismatch');

        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Manipulationsversuch: Kleine Order (54,90 EUR) versucht teures Angebot (250,00 EUR) zu konvertieren -> Angebot bleibt open!
        $expensiveQuote = OrderQuoteRequest::create([
            'quote_number' => 'ANG-2026-BIG999',
            'customer_id' => $order->customer_id,
            'email' => $order->email,
            'first_name' => 'Alina',
            'last_name' => 'Testkundin',
            'status' => 'open',
            'net_total' => 21008,
            'tax_total' => 3992,
            'gross_total' => 25000,
        ]);

        $this->postSignedWebhook([
            'id' => 'evt_cs_valid_order_but_big_quote_009',
            'object' => 'event',
            'type' => 'checkout.session.async_payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'cs_async_ok_009',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'currency' => 'eur',
                    'amount_total' => 5490,
                    'payment_intent' => 'pi_cs_async_ok_009',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                        'quote_number' => 'ANG-2026-BIG999',
                    ],
                ],
            ],
        ])->assertStatus(200);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('open', $expensiveQuote->fresh()->status);
        $this->assertNull($expensiveQuote->fresh()->converted_order_id);
    }

    #[Test]
    public function it_ignores_charge_refunded_for_unpaid_orders_without_creating_gobd_credit_note(): void
    {
        $unpaidOrder = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_never_paid_010',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => 5490,
        ]);

        $this->postSignedWebhook([
            'id' => 'evt_refund_unpaid_010',
            'object' => 'event',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_never_paid_010',
                    'object' => 'charge',
                    'payment_intent' => 'pi_never_paid_010',
                    'amount_refunded' => 5490,
                    'refunded' => true,
                ],
            ],
        ])->assertStatus(200);

        $unpaidOrder->refresh();
        $this->assertSame('unpaid', $unpaidOrder->payment_status);
        $this->assertSame('pending', $unpaidOrder->status);
        $this->assertSame(
            0,
            AccountingInvoice::where('order_id', $unpaidOrder->id)->where('type', 'credit_note')->count()
        );
    }
}

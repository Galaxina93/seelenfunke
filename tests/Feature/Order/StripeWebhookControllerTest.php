<?php

namespace Tests\Feature\Order;

use App\Http\Controllers\StripeWebhookController;
use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Livewire\Shop\Order\OrderCheckout\OrderCheckout;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Cart\Cart;
use App\Models\Customer\Customer;
use App\Models\Management\ManagementTask;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Product\Product;
use App\Models\System\SystemLog;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
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
        StripeWebhookController::$testingOrderLookupRetryHook = null;

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

    #[Test]
    public function it_caches_early_inline_payment_intent_succeeded_without_false_warning_and_reconciles_on_order_creation(): void
    {
        $product = $this->createProduct(2500, 10);
        $customer = Customer::create([
            'email' => 'alina-race@example.com',
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'password' => bcrypt('Secret123!'),
        ]);
        $customer->profile()->create([
            'street' => 'Carl-Goerdeler-Ring',
            'house_number' => '26',
            'postal' => '38518',
            'city' => 'Gifhorn',
            'country' => 'DE',
            'email_verified_at' => now(),
        ]);

        $cart = Cart::create(['customer_id' => $customer->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
        ]);

        // 1. Checkout mountet und erzeugt Vorab-PaymentIntent (OrderOrder existiert noch nicht in MySQL)
        $checkout = Livewire::actingAs($customer, 'customer')->test(OrderCheckout::class);
        $intentId = (string) $checkout->get('currentPaymentIntentId');
        $expectedTotalCents = (int) $checkout->get('totalAmount');

        $this->assertNotEmpty($intentId);
        $this->assertGreaterThan(0, $expectedTotalCents);
        $this->assertNull(OrderOrder::where('stripe_payment_intent_id', $intentId)->first());

        // Metadaten-Aufbau von HandlesStripePayment prüfen
        $componentInstance = $checkout->instance();
        $refMethod = new \ReflectionMethod($componentInstance, 'buildStripePaymentIntentMetadata');
        $refMethod->setAccessible(true);
        $builtMetadata = $refMethod->invoke($componentInstance, $cart, 'DE');

        $this->assertSame('inline_payment_element', $builtMetadata['checkout_flow'] ?? null);
        $this->assertSame((string) config('app.env'), $builtMetadata['app_env'] ?? null);
        $this->assertArrayHasKey('app_url', $builtMetadata);
        $this->assertArrayNotHasKey('order_id', $builtMetadata);

        // 2. Stripe feuert payment_intent.succeeded VOR dem Livewire-Order-Commit
        $earlyWebhookPayload = [
            'id' => 'evt_pi_race_early_011',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $intentId,
                    'object' => 'payment_intent',
                    'amount' => $expectedTotalCents,
                    'amount_received' => $expectedTotalCents,
                    'currency' => 'eur',
                    'metadata' => $builtMetadata,
                ],
            ],
        ];

        $this->postSignedWebhook($earlyWebhookPayload)->assertStatus(200);

        // Kein falscher Sentinel-Warnlog für regulären Vorab-Inline-Intent!
        $this->assertSame(
            0,
            SystemLog::where('message', 'like', "%Keine passende Order gefunden für PaymentIntent {$intentId}%")->count()
        );

        // Kurzlebiger verifizierter Marker liegt im Cache bereit
        $cacheKey = StripeWebhookController::WEBHOOK_PAID_INTENT_CACHE_PREFIX . $intentId;
        $cachedMarker = Cache::get($cacheKey);
        $this->assertIsArray($cachedMarker);
        $this->assertSame($intentId, $cachedMarker['intent_id']);
        $this->assertSame($expectedTotalCents, $cachedMarker['amount_received']);
        $this->assertSame('eur', $cachedMarker['currency']);

        // 3. Paralleler/verzögerter Livewire-Order-Commit legt OrderOrder an und wertet den Cache-Marker sofort aus
        $checkout
            ->set('address', 'Carl-Goerdeler-Ring 26')
            ->set('postal_code', '38518')
            ->set('city', 'Gifhorn')
            ->set('country', 'DE')
            ->set('terms_accepted', true)
            ->call('validateAndCreateOrder')
            ->assertHasNoErrors();

        $createdOrder = OrderOrder::where('stripe_payment_intent_id', $intentId)->first();
        $this->assertNotNull($createdOrder);
        $this->assertSame('paid', $createdOrder->payment_status);
        $this->assertNull(Cache::get($cacheKey));
        $this->assertSame(9, $product->fresh()->quantity);
        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, 1);

        // 4. Nachfolgender Browser-Aufruf von handlePaymentSuccess bleibt 100 % idempotent
        $checkout
            ->call('handlePaymentSuccess', (string) $createdOrder->id)
            ->assertHasNoErrors();

        $this->assertSame('paid', $createdOrder->fresh()->payment_status);
        $this->assertSame(9, $product->fresh()->quantity);
        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, 1);
    }

    #[Test]
    public function it_handles_lookup_retry_foreign_env_suppression_and_keeps_warning_for_missing_same_env_order_id(): void
    {
        // A) Paralleler Commit trifft während Nachprüfung #1 ein -> Order wird gefunden & bezahlt, 0 Warnungen
        $product = $this->createProduct(3000, 5);
        $retryAttempts = 0;

        StripeWebhookController::$testingOrderLookupRetryHook = function (int $attempt, ?string $intentId) use ($product, &$retryAttempts): void {
            $retryAttempts = $attempt;
            if ($attempt === 1 && $intentId === 'pi_parallel_commit_012') {
                $delayedOrder = $this->createOrder([
                    'stripe_payment_intent_id' => 'pi_parallel_commit_012',
                    'subtotal_price' => 3000,
                    'shipping_price' => 490,
                    'total_price' => 3490,
                ]);
                $delayedOrder->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => 1,
                    'unit_price' => 3000,
                    'tax_rate' => 19.0,
                    'total_price' => 3000,
                ]);
            }
        };

        $this->postSignedWebhook([
            'id' => 'evt_pi_parallel_012',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_parallel_commit_012',
                    'object' => 'payment_intent',
                    'amount' => 3490,
                    'amount_received' => 3490,
                    'currency' => 'eur',
                    'metadata' => [
                        'checkout_flow' => 'inline_payment_element',
                        'app_env' => (string) config('app.env'),
                    ],
                ],
            ],
        ])->assertStatus(200);

        StripeWebhookController::$testingOrderLookupRetryHook = null;

        $this->assertSame(1, $retryAttempts);
        $resolvedOrder = OrderOrder::where('stripe_payment_intent_id', 'pi_parallel_commit_012')->first();
        $this->assertNotNull($resolvedOrder);
        $this->assertSame('paid', $resolvedOrder->payment_status);
        $this->assertSame(4, $product->fresh()->quantity);
        $this->assertSame(
            0,
            SystemLog::where('message', 'like', '%pi_parallel_commit_012%')->count()
        );

        // B) PaymentIntent aus fremder Umgebung (z.B. local-Test im geteilten Stripe-Testkonto auf Stage):
        //    Kein Retry, kein fremder Order-Match über zufällig gleiche order_id, 0 Warnungen!
        $unrelatedSameIdCandidate = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_stage_own_intent_999',
            'total_price' => 5490,
        ]);
        $foreignRetries = 0;
        StripeWebhookController::$testingOrderLookupRetryHook = function () use (&$foreignRetries): void {
            $foreignRetries++;
        };

        $this->postSignedWebhook([
            'id' => 'evt_pi_foreign_env_012b',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_local_dev_intent_012b',
                    'object' => 'payment_intent',
                    'amount' => 5490,
                    'amount_received' => 5490,
                    'currency' => 'eur',
                    'metadata' => [
                        'checkout_flow' => 'inline_payment_element',
                        'app_env' => 'local_developer_box',
                        'app_url' => 'http://127.0.0.1:8080',
                        'order_id' => (string) $unrelatedSameIdCandidate->id,
                    ],
                ],
            ],
        ])->assertStatus(200);

        StripeWebhookController::$testingOrderLookupRetryHook = null;

        $this->assertSame(0, $foreignRetries);
        $this->assertSame('unpaid', $unrelatedSameIdCandidate->fresh()->payment_status);
        $this->assertNull(Cache::get(StripeWebhookController::WEBHOOK_PAID_INTENT_CACHE_PREFIX . 'pi_local_dev_intent_012b'));
        $this->assertSame(
            0,
            SystemLog::where('message', 'like', '%pi_local_dev_intent_012b%')->count()
        );

        // C) Explizite metadata.order_id für dieselbe Umgebung fehlt tatsächlich -> echtes Log::warning bleibt aktiv!
        $missingSameEnvOrderId = (string) Str::uuid();
        $this->postSignedWebhook([
            'id' => 'evt_pi_missing_same_env_012c',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_missing_same_env_012c',
                    'object' => 'payment_intent',
                    'amount' => 5490,
                    'amount_received' => 5490,
                    'currency' => 'eur',
                    'metadata' => [
                        'app_env' => (string) config('app.env'),
                        'order_id' => $missingSameEnvOrderId,
                    ],
                ],
            ],
        ])->assertStatus(200);

        $this->assertGreaterThanOrEqual(
            1,
            SystemLog::where('message', 'like', '%Keine passende Order gefunden für PaymentIntent pi_missing_same_env_012c%')->count()
        );

        // D) Unterzahlungs-Schutz beim Cache-Marker: Manipulierter/zu kleiner gecachter Betrag darf Order niemals bezahlen
        Cache::put(
            StripeWebhookController::WEBHOOK_PAID_INTENT_CACHE_PREFIX . 'pi_underpaid_cache_012d',
            [
                'intent_id' => 'pi_underpaid_cache_012d',
                'amount' => 1000,
                'amount_received' => 1000,
                'currency' => 'eur',
            ],
            now()->addMinutes(10)
        );
        $underpaidCandidate = $this->createOrder([
            'stripe_payment_intent_id' => 'pi_underpaid_cache_012d',
            'total_price' => 5490,
        ]);
        $checkoutDummy = new OrderCheckout();
        $reconcileRef = new \ReflectionMethod($checkoutDummy, 'reconcileCachedStripeWebhookPayment');
        $reconcileRef->setAccessible(true);
        $reconcileRef->invoke($checkoutDummy, $underpaidCandidate);

        $this->assertSame('unpaid', $underpaidCandidate->fresh()->payment_status);
    }
}

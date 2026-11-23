<?php

namespace Tests\Feature\Stripe;

use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiTool;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Logistics\LogisticsShippingRate;
use App\Models\Logistics\LogisticsShippingZone;
use App\Models\Management\ManagementTask;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderRevocation;
use App\Models\Order\OrderShipment;
use App\Models\Product\Product;
use App\Models\System\SystemLog;
use App\Models\System\SystemSetting;
use App\Services\AI\AIFunctionsRegistry;
use Database\Seeders\AiAgentSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutonomousStripeAgentAndAcpTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_autonomous_stripe_test_secret_2026';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        Queue::fake();

        config([
            'services.stripe.secret' => 'sk_test_autonomous_mock_key',
            'services.stripe.webhook.secret' => $this->webhookSecret,
            'services.stripe.webhook_secret' => $this->webhookSecret,
        ]);

        $this->seed(SystemSettingSeeder::class);

        app(\App\Services\StripeAgentExecutionService::class)->ensureAutonomyDefaultsAndTools();
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-stripe-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    private function setupGermanShippingZone(): LogisticsShippingZone
    {
        $zone = LogisticsShippingZone::create([
            'name' => 'Deutschland',
            'is_active' => true,
        ]);

        $zone->countries()->create([
            'country_code' => 'DE',
        ]);

        LogisticsShippingRate::create([
            'logistics_shipping_zone_id' => $zone->id,
            'name' => 'DHL Paket DE',
            'min_weight' => 0,
            'max_weight' => 31.5,
            'min_price' => 0,
            'price' => 490,
        ]);

        Cache::forget('global_shop_settings');

        return $zone;
    }

    private function createSampleProduct(string $name = 'Manufaktur Artikel', int $priceCents = 2990, int $qty = 10): Product
    {
        return Product::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'sku' => 'SKU-' . strtoupper(Str::random(6)),
            'price' => $priceCents,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => false,
            'quantity' => $qty,
            'weight' => 250,
        ]);
    }

    private function signWebhookPayload(string $payload): string
    {
        $ts = time();
        $sig = hash_hmac('sha256', "{$ts}.{$payload}", $this->webhookSecret);

        return "t={$ts},v1={$sig}";
    }

    public function test_migration_seeds_autonomy_settings_and_assigns_stripe_tools_exclusively_to_executive_roles(): void
    {
        $this->seed(AiAgentSeeder::class);

        app(\App\Services\StripeAgentExecutionService::class)->ensureAutonomyDefaultsAndTools();

        $this->assertSame('true', SystemSetting::get('stripe_agent_autonomy_enabled'));
        $this->assertSame('25000', SystemSetting::get('stripe_agent_auto_refund_limit_cents'));
        $this->assertSame('100000', SystemSetting::get('stripe_agent_daily_refund_cap_cents'));

        $expectedTools = [
            'stripe_create_checkout_link',
            'stripe_execute_refund',
            'stripe_charge_saved_mandate',
            'stripe_reconcile_payouts_fees',
            'stripe_defend_dispute',
            'stripe_get_live_telemetry',
        ];

        $falcone = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();
        $funkira = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();
        $funki = AiAgent::where('name', 'Funki')->with('role.tools')->firstOrFail();

        $falconeTools = $falcone->tools->pluck('identifier')->all();
        $funkiraTools = $funkira->tools->pluck('identifier')->all();
        $funkiTools = $funki->tools->pluck('identifier')->all();

        foreach ($expectedTools as $toolName) {
            $this->assertNotNull(AiTool::where('identifier', $toolName)->first(), "Missing AiTool {$toolName}");
            $this->assertContains($toolName, $falconeTools);
            $this->assertContains($toolName, $funkiraTools);
            $this->assertNotContains($toolName, $funkiTools);
        }
    }

    public function test_strict_air_gap_and_role_isolation_blocks_funki_guest_customer_employee_influencer_and_tax_advisor_on_all_stripe_tools(): void
    {
        $this->seed(AiAgentSeeder::class);
        app(\App\Services\StripeAgentExecutionService::class)->ensureAutonomyDefaultsAndTools();

        $falcone = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();
        $funki = AiAgent::where('name', 'Funki')->with('role.tools')->firstOrFail();
        $buchi = AiAgent::where('name', 'Buchi')->with('role.tools')->firstOrFail();

        $stripeToolCalls = [
            'stripe_create_checkout_link' => ['customer_email' => 'test@example.com'],
            'stripe_execute_refund' => ['order_number' => 'ORD-2026-NONE'],
            'stripe_charge_saved_mandate' => ['order_number' => 'ORD-2026-NONE', 'customer_id' => 'cus_1', 'payment_method_id' => 'pm_1'],
            'stripe_reconcile_payouts_fees' => [],
            'stripe_defend_dispute' => ['dispute_id' => 'dp_1'],
            'stripe_get_live_telemetry' => [],
        ];

        // 1. Unauthentifizierter Gast -> abgewiesen (sowohl via Registry als auch direkt)
        foreach ($stripeToolCalls as $tool => $args) {
            $res = AIFunctionsRegistry::execute($tool, $args, $falcone);
            $this->assertTrue($res['error'] ?? false, "Guest must be blocked on {$tool}");
            $this->assertStringContainsString('ROLE VIOLATION', (string) ($res['message'] ?? ''));
        }
        $directGuest = AIFunctionsRegistry::executeStripeGetLiveTelemetry([], $falcone);
        $this->assertTrue($directGuest['error'] ?? false);

        // 2. Authentifizierter Kunde (Customer) -> abgewiesen
        $customer = Customer::create([
            'email' => 'kunde-block@example.com',
            'first_name' => 'Klara',
            'last_name' => 'Kundin',
            'password' => bcrypt('Secret123!'),
        ]);
        Auth::guard('customer')->login($customer);
        foreach ($stripeToolCalls as $tool => $args) {
            $res = AIFunctionsRegistry::execute($tool, $args, $falcone);
            $this->assertTrue($res['error'] ?? false, "Customer must be blocked on {$tool}");
        }
        Auth::guard('customer')->logout();

        // 3. Interne Mitarbeiter, Influencer & Steuerberater -> abgewiesen
        foreach (['internal', 'influencer', 'tax_advisor'] as $empRole) {
            $emp = Employee::create([
                'first_name' => 'Role',
                'last_name' => $empRole,
                'email' => "{$empRole}-stripe@mein-seelenfunke.de",
                'role' => $empRole,
                'password' => bcrypt('Secret123!'),
            ]);
            Auth::guard('employee')->login($emp);

            foreach ($stripeToolCalls as $tool => $args) {
                $res = AIFunctionsRegistry::execute($tool, $args, $falcone);
                $this->assertTrue($res['error'] ?? false, "Employee role {$empRole} must be blocked on {$tool}");
                $this->assertStringContainsString('ROLE VIOLATION', (string) ($res['message'] ?? ''));
            }

            Auth::guard('employee')->logout();
        }

        // 4. Selbst bei eingeloggtem Admin: Funki (SANDBOX VIOLATION) & Nicht-Executive-Agenten (ROLE VIOLATION) -> hart gesperrt!
        $admin = $this->createAdmin();
        Auth::guard('admin')->login($admin);

        foreach ($stripeToolCalls as $tool => $args) {
            $funkiRes = AIFunctionsRegistry::execute($tool, $args, $funki);
            $this->assertTrue($funkiRes['error'] ?? false, "Funki must be air-gapped on {$tool}");
            $this->assertStringContainsString('SANDBOX VIOLATION', (string) ($funkiRes['message'] ?? ''));

            $buchiRes = AIFunctionsRegistry::execute($tool, $args, $buchi);
            $this->assertTrue($buchiRes['error'] ?? false, "Non-executive agent must be blocked on {$tool}");
            $this->assertStringContainsString('ROLE VIOLATION', (string) ($buchiRes['message'] ?? ''));
        }
    }

    public function test_falcone_executes_all_six_autonomous_stripe_tools_with_gobd_credit_note_and_refund_guardrails(): void
    {
        $this->seed(AiAgentSeeder::class);
        app(\App\Services\StripeAgentExecutionService::class)->ensureAutonomyDefaultsAndTools();

        $this->setupGermanShippingZone();

        $admin = $this->createAdmin();
        Auth::guard('admin')->login($admin);
        $falcone = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();

        $refundHttpCalls = 0;

        Http::fake(function (HttpClientRequest $request) use (&$refundHttpCalls) {
            $url = $request->url();
            $method = strtoupper($request->method());
            $data = $request->data();

            if ($method === 'POST' && str_ends_with($url, '/v1/checkout/sessions')) {
                $this->assertNotEmpty($request->header('Idempotency-Key'));
                return Http::response([
                    'id' => 'cs_test_autonomy_1001',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_autonomy_1001',
                    'payment_intent' => 'pi_test_autonomy_1001',
                ], 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/v1/refunds')) {
                $refundHttpCalls++;
                $this->assertNotEmpty($request->header('Idempotency-Key'));
                return Http::response([
                    'id' => 're_test_autonomy_' . $refundHttpCalls,
                    'status' => 'succeeded',
                    'amount' => (int) ($data['amount'] ?? 0),
                ], 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/v1/payment_intents')) {
                if (($data['payment_method'] ?? '') === 'pm_requires_sca') {
                    return Http::response([
                        'error' => [
                            'code' => 'authentication_required',
                            'message' => '3D Secure authentication required by issuer.',
                        ],
                    ], 402);
                }

                return Http::response([
                    'id' => 'pi_mandate_ok_9001',
                    'status' => 'succeeded',
                    'currency' => 'eur',
                    'amount' => (int) ($data['amount'] ?? 0),
                ], 200);
            }

            if ($method === 'GET' && str_contains($url, '/v1/payouts')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'po_fyrst_2026_01',
                            'amount' => 48500,
                            'currency' => 'eur',
                            'status' => 'paid',
                            'arrival_date' => now()->timestamp,
                        ],
                    ],
                ], 200);
            }

            if ($method === 'GET' && str_contains($url, '/v1/balance_transactions')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'txn_charge_01',
                            'type' => 'charge',
                            'amount' => 50000,
                            'fee' => 1500,
                            'net' => 48500,
                        ],
                    ],
                ], 200);
            }

            if ($method === 'POST' && str_contains($url, '/v1/disputes/dp_test_dispute_01')) {
                return Http::response([
                    'id' => 'dp_test_dispute_01',
                    'status' => 'under_review',
                ], 200);
            }

            if ($method === 'GET' && str_ends_with($url, '/v1/balance')) {
                return Http::response([
                    'available' => [['currency' => 'eur', 'amount' => 124500]],
                    'pending' => [['currency' => 'eur', 'amount' => 32900]],
                ], 200);
            }

            if ($method === 'GET' && str_contains($url, '/v1/payment_intents')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'pi_telemetry_1',
                            'amount' => 4990,
                            'currency' => 'eur',
                            'status' => 'succeeded',
                            'receipt_email' => 'privat@kunde.de',
                            'metadata' => [
                                'order_number' => 'ORD-2026-TELEM1',
                                'customer_name' => 'Erika Geheim',
                            ],
                        ],
                    ],
                ], 200);
            }

            if ($method === 'GET' && str_contains($url, '/v1/disputes')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'dp_open_01',
                            'amount' => 3990,
                            'reason' => 'product_not_received',
                            'status' => 'needs_response',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        // --- 1. stripe_create_checkout_link: Blockiert US (keine aktive Zone), erlaubt DE ---
        $blockedCountry = AIFunctionsRegistry::execute('stripe_create_checkout_link', [
            'customer_email' => 'b2b@firma.de',
            'shipping_country' => 'US',
            'items' => [['name' => 'Schieferplatte Gravur', 'quantity' => 2, 'unit_price_cents' => 2990]],
        ], $falcone);
        $this->assertTrue($blockedCountry['error'] ?? false);
        $this->assertSame('shipping_country_not_supported', $blockedCountry['code'] ?? null);

        $quoteProduct = $this->createSampleProduct('Kristall-Trophäe', 11900, 20);

        $quote = OrderQuoteRequest::create([
            'quote_number' => 'ANG-2026-50001',
            'email' => 'b2b@firma.de',
            'first_name' => 'Sabine',
            'last_name' => 'Meyer',
            'status' => 'open',
            'net_total' => 10000,
            'tax_total' => 1900,
            'gross_total' => 11900,
        ]);
        $quote->items()->create([
            'product_id' => $quoteProduct->id,
            'product_name' => '10x Kristall-Trophäe',
            'quantity' => 1,
            'unit_price' => 11900,
            'total_price' => 11900,
            'configuration' => ['quote_country' => 'DE'],
        ]);

        $linkRes = AIFunctionsRegistry::execute('stripe_create_checkout_link', [
            'quote_number' => 'ANG-2026-50001',
            'shipping_country' => 'DE',
            'shipping_cents' => 0,
            'send_email' => true,
        ], $falcone);

        $this->assertSame('success', $linkRes['status'] ?? null);
        $this->assertSame(11900, $linkRes['amount_total_cents']);
        $this->assertSame(1900, $linkRes['tax_cents']);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_autonomy_1001', $linkRes['checkout_url']);
        $this->assertTrue($linkRes['email_sent']);

        // --- 2a. stripe_execute_refund innerhalb Limit (11900 <= 25000 Cents) inkl. GoBD-Gutschrift & OrderRevocation ---
        $refundProduct = $this->createSampleProduct('Standard Geschenkbox', 11900, 10);
        $refundableOrder = OrderOrder::create([
            'order_number' => 'ORD-2026-REF001',
            'email' => 'widerruf@kunde.de',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => 'pi_paid_ref_001',
            'billing_address' => ['first_name' => 'Tom', 'last_name' => 'Test', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Tom', 'last_name' => 'Test', 'country' => 'DE'],
            'subtotal_price' => 11900,
            'tax_amount' => 1900,
            'shipping_price' => 0,
            'total_price' => 11900,
            'is_small_business' => false,
        ]);
        $refundableOrder->items()->create([
            'product_id' => $refundProduct->id,
            'product_name' => 'Standard Geschenkbox',
            'quantity' => 1,
            'unit_price' => 11900,
            'tax_rate' => 19.0,
            'total_price' => 11900,
        ]);
        $revocation = OrderRevocation::create([
            'name' => 'Tom Test',
            'email' => 'widerruf@kunde.de',
            'order_number' => 'ORD-2026-REF001',
            'status' => 'pending',
        ]);

        $refundOk = AIFunctionsRegistry::execute('stripe_execute_refund', [
            'order_number' => 'ORD-2026-REF001',
            'amount_cents' => 11900,
            'reason' => 'requested_by_customer',
        ], $falcone);

        $this->assertSame('success', $refundOk['status'] ?? null);
        $this->assertSame(1, $refundHttpCalls);
        $this->assertSame('refunded', $refundableOrder->fresh()->payment_status);
        $this->assertSame('processed', $revocation->fresh()->status);

        $creditNote = AccountingInvoice::findOrFail($refundOk['credit_note_id']);
        $this->assertSame('credit_note', $creditNote->type);
        $this->assertSame($refundableOrder->id, $creditNote->order_id);
        $this->assertSame(-11900, (int) $creditNote->total);
        $this->assertSame(-1900, (int) $creditNote->tax_amount);
        $this->assertTrue(Storage::disk('local')->exists($creditNote->pdf_storage_path));

        // --- 2b. stripe_execute_refund ÜBER Limit (> 25000 Cents -> Freigabe-Task statt Direkt-Refund!) ---
        $highValueOrder = OrderOrder::create([
            'order_number' => 'ORD-2026-HIGH99',
            'email' => 'vip@kunde.de',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => 'pi_paid_high_99',
            'billing_address' => ['first_name' => 'Eva', 'last_name' => 'Gross', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Eva', 'last_name' => 'Gross', 'country' => 'DE'],
            'subtotal_price' => 35000,
            'tax_amount' => 5588,
            'shipping_price' => 0,
            'total_price' => 35000,
        ]);
        $highValueOrder->items()->create([
            'product_id' => $refundProduct->id,
            'product_name' => 'Atelier Gold-Unikat',
            'quantity' => 1,
            'unit_price' => 35000,
            'tax_rate' => 19.0,
            'total_price' => 35000,
        ]);

        $refundBlocked = AIFunctionsRegistry::execute('stripe_execute_refund', [
            'order_number' => 'ORD-2026-HIGH99',
            'amount_cents' => 30000,
            'reason' => 'defective',
        ], $falcone);

        $this->assertSame('approval_required', $refundBlocked['status'] ?? null);
        $this->assertTrue($refundBlocked['blocked_by_guardrail'] ?? false);
        $this->assertSame('single_refund_limit_exceeded', $refundBlocked['violation_code'] ?? null);
        // Kein weiterer Stripe-Refund-API-Call!
        $this->assertSame(1, $refundHttpCalls);
        $this->assertSame('paid', $highValueOrder->fresh()->payment_status);
        $this->assertNotNull(ManagementTask::find($refundBlocked['task_id']));

        // --- 3. stripe_charge_saved_mandate (Off-Session Erfolg + PSD2/SCA Fallback) ---
        $mandateOrder = OrderOrder::create([
            'order_number' => 'ORD-2026-MANDATE1',
            'email' => 'stammkunde@firma.de',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'billing_address' => ['first_name' => 'Karl', 'last_name' => 'B2B', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Karl', 'last_name' => 'B2B', 'country' => 'DE'],
            'subtotal_price' => 5950,
            'tax_amount' => 950,
            'shipping_price' => 0,
            'total_price' => 5950,
        ]);
        $mandateOrder->items()->create([
            'product_id' => $refundProduct->id,
            'product_name' => 'Nachbestellung Schlüsselanhänger',
            'quantity' => 5,
            'unit_price' => 1190,
            'tax_rate' => 19.0,
            'total_price' => 5950,
        ]);

        $scaRes = AIFunctionsRegistry::execute('stripe_charge_saved_mandate', [
            'order_number' => 'ORD-2026-MANDATE1',
            'customer_id' => 'cus_b2b_1',
            'payment_method_id' => 'pm_requires_sca',
        ], $falcone);
        $this->assertSame('requires_action_fallback_link_sent', $scaRes['status'] ?? null);
        $this->assertTrue($scaRes['sca_required'] ?? false);
        $this->assertNotEmpty($scaRes['fallback_checkout_url']);

        $chargeOk = AIFunctionsRegistry::execute('stripe_charge_saved_mandate', [
            'order_number' => 'ORD-2026-MANDATE1',
            'customer_id' => 'cus_b2b_1',
            'payment_method_id' => 'pm_valid_sepa_card',
        ], $falcone);
        $this->assertSame('succeeded', $chargeOk['status'] ?? null);
        $this->assertSame('paid', $mandateOrder->fresh()->payment_status);

        // --- 4. stripe_reconcile_payouts_fees (Cent-Gebühren + Dublettenschutz) ---
        $reconcileFirst = AIFunctionsRegistry::execute('stripe_reconcile_payouts_fees', [
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ], $falcone);
        $this->assertSame('success', $reconcileFirst['status'] ?? null);
        $this->assertSame(50000, $reconcileFirst['gross_volume_cents']);
        $this->assertSame(1500, $reconcileFirst['stripe_fees_cents']);
        $this->assertSame(0, $reconcileFirst['skipped_duplicates_count']);

        $reconcileSecond = AIFunctionsRegistry::execute('stripe_reconcile_payouts_fees', [
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ], $falcone);
        $this->assertGreaterThanOrEqual(2, $reconcileSecond['skipped_duplicates_count']);

        // --- 5. stripe_defend_dispute (DHL-Tracking + Beweispaket + Dashboard-Task) ---
        OrderShipment::create([
            'order_id' => $highValueOrder->id,
            'tracking_number' => '00340434161094012345',
            'carrier' => 'DHL',
            'status' => 'delivered',
        ]);

        $disputeRes = AIFunctionsRegistry::execute('stripe_defend_dispute', [
            'dispute_id' => 'dp_test_dispute_01',
            'order_number' => 'ORD-2026-HIGH99',
            'submit_immediately' => true,
        ], $falcone);
        $this->assertSame('success', $disputeRes['status'] ?? null);
        $this->assertSame('00340434161094012345', $disputeRes['evidence_summary']['shipping_tracking_number'] ?? null);
        $this->assertNotNull(ManagementTask::find($disputeRes['task_id']));

        // --- 6. stripe_get_live_telemetry (DSGVO-PII-Maskierung ohne Einwilligung) ---
        $telemetry = AIFunctionsRegistry::execute('stripe_get_live_telemetry', ['limit' => 5], $falcone);
        $this->assertSame('success', $telemetry['status'] ?? null);
        $this->assertSame(124500, $telemetry['available_cents']);
        $this->assertSame(32900, $telemetry['pending_cents']);
        $this->assertSame(1, $telemetry['open_disputes_count']);
        $this->assertSame('Anonyme Person (Keine DSGVO-KI-Freigabe)', $telemetry['recent_payments'][0]['customer_name']);
        $this->assertSame('[E-Mail DSGVO-geschützt]', $telemetry['recent_payments'][0]['customer_email']);
    }

    public function test_acp_v1_enforces_live_and_maintenance_gate_rejects_raw_pan_and_completes_spt_checkout_idempotently(): void
    {
        $this->setupGermanShippingZone();

        $availableProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'der-seelen-kristall-acp',
            'sku' => 'SK-ACP-01',
            'price' => 2990,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => false,
            'quantity' => 5,
            'weight' => 350,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ausverkaufter Rohling',
            'slug' => 'ausverkauft-acp',
            'sku' => 'OUT-ACP-02',
            'price' => 1990,
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => false,
            'quantity' => 0,
        ]);

        // 1. Live-Sperre: shop_is_live = false -> strikt 503 auf allen ACP-Routen
        SystemSetting::set('shop_is_live', 'false');
        SystemSetting::set('maintenance_mode', 'false');
        Cache::forget('global_shop_settings');

        $this->getJson('/api/acp/v1/products')->assertStatus(503);
        $this->postJson('/api/acp/v1/checkout/sessions', [])->assertStatus(503);
        $this->postJson('/api/acp/v1/checkout/sessions/acp_sess_dummy1234/complete', [])->assertStatus(503);

        // 2. Wartungsmodus: shop_is_live = true, aber shop_maintenance_mode = true -> strikt 503
        SystemSetting::set('shop_is_live', 'true');
        SystemSetting::set('shop_maintenance_mode', 'true');
        Cache::forget('global_shop_settings');

        $this->getJson('/api/acp/v1/products')->assertStatus(503);

        // 3. Freigabe für Live-Betrieb
        SystemSetting::set('shop_maintenance_mode', 'false');
        SystemSetting::set('maintenance_mode', 'false');
        Cache::forget('global_shop_settings');

        $catalogRes = $this->getJson('/api/acp/v1/products');
        $catalogRes->assertStatus(200)
            ->assertJsonPath('protocol', 'stripe-acp-v1')
            ->assertJsonPath('active_shipping_countries', ['DE']);

        $productsList = $catalogRes->json('products');
        $this->assertCount(1, $productsList);
        $this->assertSame($availableProduct->id, $productsList[0]['id']);
        $this->assertSame(490, $productsList[0]['optional_addons'][0]['price_cents']);

        // 4. Lieferzonen-Sperre im ACP Checkout (US nicht aktiv -> 422)
        $this->postJson('/api/acp/v1/checkout/sessions', [
            'items' => [['product_id' => $availableProduct->id, 'quantity' => 1]],
            'buyer' => ['email' => 'agent-buyer@example.com', 'first_name' => 'Anna', 'last_name' => 'Käufer'],
            'shipping_address' => [
                'address' => '5th Ave 1',
                'postal_code' => '10001',
                'city' => 'New York',
                'country' => 'US',
            ],
        ])->assertStatus(422)
          ->assertJsonPath('code', 'shipping_country_not_supported');

        // 5. Gültige ACP Checkout-Session für DE inkl. Westpack-Geschenkbox (+490 Cent)
        $sessionRes = $this->postJson('/api/acp/v1/checkout/sessions', [
            'items' => [
                [
                    'product_id' => $availableProduct->id,
                    'quantity' => 1,
                    'gift_box_westpack' => true,
                    'personalization' => [
                        'text_lines' => ['Für Mama', '16.11.2026'],
                        'font' => 'Playfair Display',
                    ],
                ],
            ],
            'buyer' => [
                'email' => 'agent-buyer@example.com',
                'first_name' => 'Anna',
                'last_name' => 'Käufer',
            ],
            'shipping_address' => [
                'address' => 'Musterweg 12',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'copyright_cleared' => true,
        ]);

        $sessionRes->assertStatus(201);
        $sessionId = (string) $sessionRes->json('session_id');
        // 2990 (Produkt) + 490 (Westpack-Box) = 3480 Subtotal + 490 Versand (< 50 EUR) = 3970 Cent
        $this->assertSame(3480, $sessionRes->json('subtotal_cents'));
        $this->assertSame(490, $sessionRes->json('shipping_cents'));
        $this->assertSame(3970, $sessionRes->json('amount_total_cents'));

        // 6. Ablehnung roher Kartendaten (PAN / CVC / Nicht-spt_-Token) mit 422
        $this->postJson("/api/acp/v1/checkout/sessions/{$sessionId}/complete", [
            'card_number' => '4242424242424242',
            'cvc' => '123',
            'shared_payment_token' => 'spt_fake_123456',
        ])->assertStatus(422)
          ->assertJsonPath('code', 'raw_card_data_forbidden');

        $this->postJson("/api/acp/v1/checkout/sessions/{$sessionId}/complete", [
            'shared_payment_token' => 'pm_not_a_shared_payment_token',
        ])->assertStatus(422)
          ->assertJsonPath('code', 'invalid_shared_payment_token');

        // 7. Erfolgreicher Abschluss via spt_* Token + Idempotenz-Prüfung
        Http::fake([
            'https://api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_acp_spt_success_777',
                'status' => 'succeeded',
                'currency' => 'eur',
                'amount' => 3970,
            ], 200),
        ]);

        $completeRes = $this->postJson("/api/acp/v1/checkout/sessions/{$sessionId}/complete", [
            'shared_payment_token' => 'spt_live_agent_token_abcdef12345',
        ]);

        $completeRes->assertStatus(200)
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('amount_total_cents', 3970);

        $orderNumber = (string) $completeRes->json('order_number');
        $this->assertStringStartsWith('ORD-', $orderNumber);
        $this->assertSame(4, $availableProduct->fresh()->quantity);
        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, 1);

        // Zweiter Aufruf derselben Session -> 100 % idempotent (keine Doppelbestellung, kein zweiter Lagerabzug)
        $retryRes = $this->postJson("/api/acp/v1/checkout/sessions/{$sessionId}/complete", [
            'shared_payment_token' => 'spt_live_agent_token_abcdef12345',
        ]);
        $retryRes->assertStatus(200)
            ->assertJsonPath('order_number', $orderNumber)
            ->assertJsonPath('status', 'paid');
        $this->assertSame(4, $availableProduct->fresh()->quantity);
    }

    public function test_stripe_webhook_handles_charge_refunded_dispute_created_dispute_closed_and_payout_paid_idempotently(): void
    {
        $webhookProduct = $this->createSampleProduct('Herz-Schlüsselanhänger', 2380, 10);

        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-WHK01',
            'email' => 'webhook@kunde.de',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => 'pi_whk_sync_01',
            'billing_address' => ['first_name' => 'Lukas', 'last_name' => 'Webhook', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Lukas', 'last_name' => 'Webhook', 'country' => 'DE'],
            'subtotal_price' => 2380,
            'tax_amount' => 380,
            'shipping_price' => 0,
            'total_price' => 2380,
            'is_small_business' => false,
        ]);
        $order->items()->create([
            'product_id' => $webhookProduct->id,
            'product_name' => 'Herz-Schlüsselanhänger',
            'quantity' => 1,
            'unit_price' => 2380,
            'tax_rate' => 19.0,
            'total_price' => 2380,
        ]);

        // 1. charge.refunded (2x gesendet -> nur 1 GoBD-Gutschrift!)
        $refundPayload = json_encode([
            'id' => 'evt_whk_refund_01',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_whk_01',
                    'payment_intent' => 'pi_whk_sync_01',
                    'amount_refunded' => 2380,
                    'refunded' => true,
                    'refunds' => [
                        'data' => [
                            ['id' => 're_whk_01', 'amount' => 2380],
                        ],
                    ],
                ],
            ],
        ]);
        $sigRefund = $this->signWebhookPayload($refundPayload);

        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/stripe/webhook', [], [], [], [
                'HTTP_STRIPE_SIGNATURE' => $sigRefund,
                'CONTENT_TYPE' => 'application/json',
            ], $refundPayload)->assertStatus(200);
        }

        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(
            1,
            AccountingInvoice::where('order_id', $order->id)->where('type', 'credit_note')->count()
        );

        // 2. charge.dispute.created (2x gesendet -> idempotent 1 Task & 1 Log)
        $disputeCreatedPayload = json_encode([
            'id' => 'evt_whk_dp_created_01',
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [
                    'id' => 'dp_whk_01',
                    'payment_intent' => 'pi_whk_sync_01',
                    'amount' => 2380,
                    'reason' => 'fraudulent',
                ],
            ],
        ]);
        $sigDpCreated = $this->signWebhookPayload($disputeCreatedPayload);

        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/stripe/webhook', [], [], [], [
                'HTTP_STRIPE_SIGNATURE' => $sigDpCreated,
                'CONTENT_TYPE' => 'application/json',
            ], $disputeCreatedPayload)->assertStatus(200);
        }

        $this->assertSame(
            1,
            SystemLog::where('action_id', 'stripe_webhook:dispute_created:dp_whk_01')->count()
        );
        $this->assertSame(
            1,
            ManagementTask::where('title', 'like', '%ORD-2026-WHK01%')->count()
        );

        // 3. charge.dispute.closed (idempotent)
        $disputeClosedPayload = json_encode([
            'id' => 'evt_whk_dp_closed_01',
            'type' => 'charge.dispute.closed',
            'data' => [
                'object' => [
                    'id' => 'dp_whk_01',
                    'status' => 'won',
                    'amount' => 2380,
                ],
            ],
        ]);
        $sigDpClosed = $this->signWebhookPayload($disputeClosedPayload);

        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/stripe/webhook', [], [], [], [
                'HTTP_STRIPE_SIGNATURE' => $sigDpClosed,
                'CONTENT_TYPE' => 'application/json',
            ], $disputeClosedPayload)->assertStatus(200);
        }

        $this->assertSame(
            1,
            SystemLog::where('action_id', 'stripe_webhook:dispute_closed:dp_whk_01')->count()
        );

        // 4. payout.paid (2x gesendet -> idempotente Vormerkung für FYRST-Bankabgleich)
        $payoutPayload = json_encode([
            'id' => 'evt_whk_po_paid_01',
            'type' => 'payout.paid',
            'data' => [
                'object' => [
                    'id' => 'po_whk_fyrst_01',
                    'amount' => 95000,
                    'currency' => 'eur',
                    'arrival_date' => now()->timestamp,
                ],
            ],
        ]);
        $sigPayout = $this->signWebhookPayload($payoutPayload);

        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/stripe/webhook', [], [], [], [
                'HTTP_STRIPE_SIGNATURE' => $sigPayout,
                'CONTENT_TYPE' => 'application/json',
            ], $payoutPayload)->assertStatus(200);
        }

        $this->assertSame(
            1,
            SystemLog::where('action_id', 'stripe_webhook:payout_paid:po_whk_fyrst_01')->count()
        );
    }

    public function test_autonomous_webhook_self_healing_missed_event_sync_maintenance_passthrough_and_zero_token_mail_ticket(): void
    {
        $this->seed(AiAgentSeeder::class);
        app(\App\Services\StripeAgentExecutionService::class)->ensureAutonomyDefaultsAndTools();

        // 1. Alle Agenten bleiben strikt im AgentSkillBudgetGuard-Limit & Funki bleibt 100 % Air-Gapped
        foreach (AiAgent::where('is_active', true)->get() as $agentItem) {
            $budget = \App\Services\AI\AgentSkillBudgetGuard::evaluateAgentSkillBudget($agentItem);
            $this->assertLessThanOrEqual(
                $budget['max_tool_cap'],
                $budget['current_tool_count'],
                "Agent {$agentItem->name} überschreitet Skill-Budget!"
            );
        }

        // 2. Live-Wartungsmodus-Durchlass: Auf mein-seelenfunke.de (!shop_is_live) kein HTTP 503 mehr für /stripe/webhook,
        //    ungültige Signatur wird mit 400 abgewiesen, Sandbox-Event (livemode=false) wird ohne Live-DB-Manipulation mit 200 quittiert!
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        $this->call('POST', 'https://mein-seelenfunke.de/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=invalid',
            'CONTENT_TYPE' => 'application/json',
        ], '{}')->assertStatus(400);

        $sandboxOnLivePayload = json_encode([
            'id' => 'evt_sandbox_on_live_01',
            'livemode' => false,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_01',
                    'payment_status' => 'paid',
                    'amount_total' => 4990,
                    'currency' => 'eur',
                ],
            ],
        ]);
        $sigSandbox = $this->signWebhookPayload($sandboxOnLivePayload);
        $sandboxResp = $this->call('POST', 'https://mein-seelenfunke.de/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $sigSandbox,
            'CONTENT_TYPE' => 'application/json',
        ], $sandboxOnLivePayload);
        $sandboxResp->assertStatus(200);
        $this->assertStringContainsString('Ignored: Sandbox event on live host', $sandboxResp->getContent());

        // 3. Unbezahlte Bestellung anlegen, deren Webhook während des 503-Fensters verloren ging
        $unpaidOrder = OrderOrder::create([
            'order_number' => 'ORD-2026-RECOVER503',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'email' => 'alina-recover@example.com',
            'billing_address' => ['first_name' => 'Alina', 'last_name' => 'Test', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Alina', 'last_name' => 'Test', 'country' => 'DE'],
            'subtotal_price' => 4500,
            'tax_amount' => 0,
            'shipping_price' => 490,
            'total_price' => 4990,
        ]);

        $deletedMisroutedEndpoint = false;
        $updatedStageEvents = false;

        Http::fake(function (HttpClientRequest $req) use ($unpaidOrder, &$deletedMisroutedEndpoint, &$updatedStageEvents) {
            $url = $req->url();
            $method = strtoupper($req->method());

            if ($method === 'GET' && str_contains($url, '/v1/webhook_endpoints')) {
                return Http::response([
                    'object' => 'list',
                    'data' => [
                        [
                            'id' => 'we_stage_sandbox_ok',
                            'url' => 'https://stage.mein-seelenfunke.de/stripe/webhook',
                            'status' => 'enabled',
                            'livemode' => false,
                            'enabled_events' => ['checkout.session.completed', 'payment_intent.succeeded'],
                        ],
                        [
                            'id' => 'we_sandbox_wrong_live',
                            'url' => 'https://mein-seelenfunke.de/stripe/webhook',
                            'status' => 'enabled',
                            'livemode' => false,
                            'enabled_events' => ['checkout.session.completed'],
                        ],
                    ],
                ], 200);
            }

            if ($method === 'DELETE' && str_ends_with($url, '/v1/webhook_endpoints/we_sandbox_wrong_live')) {
                $deletedMisroutedEndpoint = true;
                return Http::response(['id' => 'we_sandbox_wrong_live', 'deleted' => true], 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/v1/webhook_endpoints/we_stage_sandbox_ok')) {
                $updatedStageEvents = true;
                return Http::response(['id' => 'we_stage_sandbox_ok', 'status' => 'enabled'], 200);
            }

            if ($method === 'GET' && str_contains($url, '/v1/events')) {
                if (str_contains($url, 'checkout.session.completed')) {
                    return Http::response([
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'evt_missed_503_01',
                                'type' => 'checkout.session.completed',
                                'livemode' => false,
                                'data' => [
                                    'object' => [
                                        'id' => 'cs_test_recover_01',
                                        'payment_status' => 'paid',
                                        'payment_intent' => 'pi_test_recover_503',
                                        'amount_total' => 4990,
                                        'currency' => 'eur',
                                        'metadata' => [
                                            'order_id' => (string) $unpaidOrder->id,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ], 200);
                }

                return Http::response(['object' => 'list', 'data' => []], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        // 4. SSRF-Schutz auf stripe_diagnose_and_manage_webhooks prüfen
        $admin = $this->createAdmin();
        Auth::guard('admin')->login($admin);
        $funkira = AiAgent::where('name', 'Funkira')->firstOrFail();

        $ssrfBlocked = AIFunctionsRegistry::execute('stripe_diagnose_and_manage_webhooks', [
            'action' => 'update_url',
            'endpoint_id' => 'we_stage_sandbox_ok',
            'target_url' => 'https://evil.example.com/webhook',
        ], $funkira);
        $this->assertTrue($ssrfBlocked['error'] ?? false);

        // 5. 0-Token E-Mail-Autopilot über MailAiSortService mit exakt Alinas Stripe-Sandbox-Warnmail testen
        $mailAccount = \App\Models\Management\Mail\MailAccount::create([
            'name' => 'Kontakt',
            'email' => 'kontakt@mein-seelenfunke.de',
            'imap_host' => '127.0.0.1',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => '127.0.0.1',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'username' => 'kontakt@mein-seelenfunke.de',
            'password' => 'secret',
        ]);

        $stripeMail = \App\Models\Management\Mail\MailMessage::create([
            'mail_account_id' => $mailAccount->id,
            'message_id' => '<stripe-sandbox-503-alert@stripe.com>',
            'folder' => 'INBOX',
            'from_email' => 'notifications@stripe.com',
            'from_name' => 'Stripe',
            'to_email' => 'kontakt@mein-seelenfunke.de',
            'subject' => '[Sandbox] Übertragungsprobleme bei Stripe-Webhook [Testmodus] für https://mein-seelenfunke.de',
            'body_plain' => '9 Anforderungen mit Zurücksendung von HTTP 503 an https://mein-seelenfunke.de/stripe/webhook.',
            'received_at' => now(),
            'is_read' => false,
        ]);

        app(\App\Services\Management\MailAiSortService::class)->sortAllMailsAndSubfolders();

        $this->assertTrue($deletedMisroutedEndpoint, 'Fehlgeleiteter Sandbox->Live-Webhook muss automatisch gelöscht werden.');
        $this->assertTrue($updatedStageEvents, 'Stage-Webhook muss fehlende Standard-Events automatisch ergänzen.');
        $this->assertSame('paid', $unpaidOrder->fresh()->payment_status, 'Verpasste Bestellung muss idempotent als bezahlt nachgebucht werden.');

        $ticket = \App\Models\Ai\AiUniverseTicket::where('title', 'like', '%Stripe-Meldung:%')->first();
        $this->assertNotNull($ticket, 'Gebündeltes Ticket muss in /admin/tickets erstellt werden.');
        $this->assertSame(\App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED, $ticket->status);
    }
}

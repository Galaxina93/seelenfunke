<?php

namespace App\Services;

use App\Models\Accounting\AccountingBankTransaction;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Customer\Customer;
use App\Models\Logistics\LogisticsShippingZone;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderRevocation;
use App\Models\Product\Product;
use App\Models\System\SystemLog;
use App\Models\System\SystemSetting;
use App\Services\AI\AiAuthHelper;
use App\Services\AI\Mails\AiAgentMessageMail;
use App\Services\Tax\TaxSettingService;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StripeAgentExecutionService
{
    private const STRIPE_API_BASE = 'https://api.stripe.com/v1';

    public const STAGE_WEBHOOK_URL = 'https://stage.mein-seelenfunke.de/stripe/webhook';
    public const LIVE_WEBHOOK_URL = 'https://mein-seelenfunke.de/stripe/webhook';

    public const ALLOWED_WEBHOOK_URLS = [
        self::STAGE_WEBHOOK_URL,
        self::LIVE_WEBHOOK_URL,
    ];

    public const CANONICAL_WEBHOOK_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'payment_intent.canceled',
        'charge.refunded',
        'charge.dispute.created',
        'charge.dispute.closed',
        'payout.paid',
    ];

    private static ?Closure $customTransport = null;

    public static function setTransportForTesting(?Closure $transport): void
    {
        self::$customTransport = $transport;
    }

    /**
     * Stellt idempotent sicher, dass alle Stripe-Autonomie-Einstellungen und Executive-Tools
     * (mit harter Supporter/Funki Air-Gap-Trennung) in der Datenbank registriert sind.
     */
    public function ensureAutonomyDefaultsAndTools(): void
    {
        if (Schema::hasTable('shop_settings')) {
            $defaultSettings = [
                'stripe_agent_autonomy_enabled' => 'true',
                'stripe_agent_auto_refund_limit_cents' => '25000',
                'stripe_agent_daily_refund_cap_cents' => '100000',
            ];

            foreach ($defaultSettings as $key => $value) {
                SystemSetting::firstOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }

            \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
        }

        if (Schema::hasTable('ai_tools') && class_exists(\App\Services\AI\AIFunctionsRegistry::class)) {
            $stripeSchemas = \App\Services\AI\AIFunctionsRegistry::getAiStripeFuncsSchema();
            $stripeToolIds = [];

            foreach ($stripeSchemas as $fn) {
                $identifier = (string) ($fn['name'] ?? '');
                if ($identifier === '') {
                    continue;
                }

                $tool = \App\Models\Ai\AiTool::updateOrCreate(
                    ['identifier' => $identifier],
                    [
                        'name' => ucwords(str_replace('_', ' ', $identifier)),
                        'description' => (string) ($fn['description'] ?? 'Autonomes Stripe-Werkzeug'),
                    ]
                );

                $stripeToolIds[] = $tool->id;
            }

            if (!empty($stripeToolIds) && Schema::hasTable('ai_roles') && Schema::hasTable('ai_role_tool')) {
                $executiveRoles = \App\Models\Ai\AiRole::whereIn('name', ['Teamleiter'])->get();
                foreach ($executiveRoles as $role) {
                    $role->tools()->syncWithoutDetaching($stripeToolIds);
                }

                $supporterRole = \App\Models\Ai\AiRole::where('name', 'Supporter')->first();
                if ($supporterRole) {
                    $supporterRole->tools()->detach($stripeToolIds);
                }
            }

            \Illuminate\Support\Facades\Cache::forget('ai_agent_capabilities_map');
        }
    }

    /**
     * Liest eine Systemeinstellung frisch aus shop_settings (mit Fallback auf shop_setting),
     * damit Änderungen an Sicherheitslimits sofort ohne Cache-Verzögerung greifen.
     */
    public function getSettingValue(string $key, mixed $default = null): mixed
    {
        try {
            if (Schema::hasTable('shop_settings')) {
                $dbVal = SystemSetting::query()->where('key', $key)->value('value');
                if ($dbVal !== null && $dbVal !== '') {
                    return $dbVal;
                }
            }
        } catch (\Throwable $ignore) {
        }

        return function_exists('shop_setting') ? shop_setting($key, $default) : $default;
    }

    public function isAutonomyEnabled(): bool
    {
        $raw = $this->getSettingValue('stripe_agent_autonomy_enabled', 'true');
        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    public function getSingleRefundLimitCents(): int
    {
        return max(0, (int) $this->getSettingValue('stripe_agent_auto_refund_limit_cents', 25000));
    }

    public function getDailyRefundCapCents(): int
    {
        return max(0, (int) $this->getSettingValue('stripe_agent_daily_refund_cap_cents', 100000));
    }

    /**
     * Summiert alle in den letzten 24 Stunden autonom ausgeführten Stripe-Erstattungen (in Cent).
     */
    public function getExecutedAutoRefundTotalLast24hCents(): int
    {
        if (!Schema::hasTable('system_logs')) {
            return 0;
        }

        $logs = SystemLog::query()
            ->where('action_id', 'stripe_agent:refund_executed')
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subHours(24))
            ->get(['payload']);

        $sum = 0;
        foreach ($logs as $log) {
            $payload = is_array($log->payload) ? $log->payload : [];
            $sum += max(0, (int) ($payload['amount_cents'] ?? 0));
        }

        return $sum;
    }

    /**
     * Liefert alle Ländercodes (ISO-2) aus aktiven Versandzonen (LogisticsShippingZone).
     *
     * @return array<int, string>
     */
    public function getActiveShippingCountryCodes(): array
    {
        if (
            Schema::hasTable('logistics_shipping_zones')
            && Schema::hasTable('logistics_shipping_zone_countries')
            && LogisticsShippingZone::withTrashed()->exists()
        ) {
            $zones = LogisticsShippingZone::query()
                ->where('is_active', true)
                ->with('countries')
                ->get();

            $codes = [];
            foreach ($zones as $zone) {
                foreach ($zone->countries as $country) {
                    $code = strtoupper(trim((string) $country->country_code));
                    if (strlen($code) === 2) {
                        $codes[] = $code;
                    }
                }
            }

            return array_values(array_unique($codes));
        }

        $rawActive = $this->getSettingValue('active_countries', null);
        if (is_string($rawActive) && $rawActive !== '') {
            $decoded = json_decode($rawActive, true);
            if (is_array($decoded) && !empty($decoded)) {
                $keys = array_keys($decoded);
                $codes = [];
                foreach ($keys as $k) {
                    $candidate = strtoupper(trim((string) $k));
                    if (strlen($candidate) === 2 && !is_numeric($candidate)) {
                        $codes[] = $candidate;
                    }
                }
                if (!empty($codes)) {
                    return array_values(array_unique($codes));
                }
            }
        }

        return ['DE'];
    }

    /**
     * Prüft strikt, ob ein Zielland durch eine aktive Versandzone abgedeckt ist.
     */
    public function isCountrySupportedByActiveZone(string $countryCode): bool
    {
        $code = strtoupper(trim($countryCode));
        if (strlen($code) !== 2) {
            return false;
        }

        if (
            Schema::hasTable('logistics_shipping_zones')
            && Schema::hasTable('logistics_shipping_zone_countries')
            && LogisticsShippingZone::withTrashed()->exists()
        ) {
            $directMatch = LogisticsShippingZone::query()
                ->where('is_active', true)
                ->whereHas('countries', fn ($q) => $q->where('country_code', $code))
                ->exists();

            if ($directMatch) {
                return true;
            }

            return LogisticsShippingZone::query()
                ->where('is_active', true)
                ->where('name', 'Weltweit')
                ->exists();
        }

        return in_array($code, $this->getActiveShippingCountryCodes(), true);
    }

    /**
     * Kapselt sämtliche Stripe-API-Aufrufe testbar über Laravels HTTP-Client (Http::fake-kompatibel)
     * oder einen optional injizierten Test-Transport.
     */
    public function requestStripeApi(
        string $method,
        string $endpoint,
        array $payload = [],
        ?string $idempotencyKey = null
    ): array {
        $normalizedMethod = strtoupper(trim($method));
        $cleanEndpoint = '/' . ltrim($endpoint, '/');
        $url = self::STRIPE_API_BASE . $cleanEndpoint;

        if (self::$customTransport !== null) {
            return (array) call_user_func(self::$customTransport, $normalizedMethod, $cleanEndpoint, $payload, $idempotencyKey);
        }

        $secret = (string) (config('services.stripe.secret') ?: $this->getSettingValue('stripe_secret_key', ''));
        if (trim($secret) === '') {
            return [
                'ok' => false,
                'http_status' => 500,
                'error_code' => 'stripe_secret_missing',
                'error_message' => 'Stripe Secret Key ist nicht konfiguriert.',
                'data' => [],
            ];
        }

        $headers = [
            'Accept' => 'application/json',
        ];
        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $headers['Idempotency-Key'] = trim($idempotencyKey);
        }

        $pendingRequest = Http::withToken($secret)
            ->timeout(20)
            ->withHeaders($headers);

        if ($normalizedMethod === 'GET') {
            $response = $pendingRequest->get($url, $payload);
        } else {
            $encodedBody = http_build_query($payload, '', '&');
            $response = $pendingRequest
                ->withBody($encodedBody, 'application/x-www-form-urlencoded')
                ->send($normalizedMethod, $url);
        }

        $json = $response->json();
        $data = is_array($json) ? $json : [];

        if ($response->successful()) {
            return [
                'ok' => true,
                'http_status' => $response->status(),
                'data' => $data,
            ];
        }

        $errObj = is_array($data['error'] ?? null) ? $data['error'] : [];

        return [
            'ok' => false,
            'http_status' => $response->status(),
            'error_code' => (string) ($errObj['code'] ?? 'stripe_api_error'),
            'error_message' => (string) ($errObj['message'] ?? ('Stripe HTTP ' . $response->status())),
            'payment_intent' => $errObj['payment_intent'] ?? null,
            'data' => $data,
        ];
    }

    /**
     * 1. Erstellt autonom einen Stripe-Checkout-Link für ein Angebot (OrderQuoteRequest),
     *    eine bestehende Bestellung oder Direktpositionen (inkl. Lieferzonen- & 19%-USt.-Prüfung).
     */
    public function createCheckoutLink(array $params, string $actorName = 'Falcone'): array
    {
        $shippingCountry = strtoupper(trim((string) ($params['shipping_country'] ?? 'DE')));
        if ($shippingCountry === '') {
            $shippingCountry = 'DE';
        }

        $quote = null;
        if (!empty($params['quote_number'])) {
            $quote = OrderQuoteRequest::with('items.product')
                ->where('quote_number', trim((string) $params['quote_number']))
                ->first();

            if (!$quote) {
                return [
                    'error' => true,
                    'status' => 'error',
                    'code' => 'quote_not_found',
                    'message' => 'Angebotsanfrage wurde nicht gefunden.',
                ];
            }

            if (empty($params['shipping_country'])) {
                $shippingCountry = strtoupper(trim((string) ($quote->country ?: 'DE')));
            }
        }

        $existingOrder = null;
        if (!empty($params['order_number'])) {
            $existingOrder = OrderOrder::with('items.product')
                ->where('order_number', trim((string) $params['order_number']))
                ->first();

            if (!$existingOrder) {
                return [
                    'error' => true,
                    'status' => 'error',
                    'code' => 'order_not_found',
                    'message' => 'Bestellung wurde nicht gefunden.',
                ];
            }

            if ($existingOrder->payment_status === 'paid') {
                return [
                    'error' => true,
                    'status' => 'error',
                    'code' => 'order_already_paid',
                    'message' => 'Diese Bestellung ist bereits bezahlt.',
                ];
            }

            if (empty($params['shipping_country'])) {
                $shipAddr = is_array($existingOrder->shipping_address) ? $existingOrder->shipping_address : [];
                $shippingCountry = strtoupper(trim((string) ($shipAddr['country'] ?? 'DE')));
            }
        }

        if (!$this->isCountrySupportedByActiveZone($shippingCountry)) {
            $this->logAudit(
                'stripe_agent:checkout_link_blocked_zone',
                'Stripe Checkout-Link wegen inaktiver Lieferzone abgelehnt',
                "Zielland '{$shippingCountry}' liegt in keiner aktiven Versandzone.",
                'warning',
                ['shipping_country' => $shippingCountry, 'actor' => $actorName]
            );

            return [
                'error' => true,
                'status' => 'error',
                'code' => 'shipping_country_not_supported',
                'message' => "Lieferland '{$shippingCountry}' ist in keiner aktiven Versandzone freigeschaltet.",
            ];
        }

        $isSmallBusiness = app(TaxSettingService::class)->isCurrentSmallBusiness();
        $defaultTaxRate = $isSmallBusiness ? 0.0 : (float) $this->getSettingValue('default_tax_rate', 19.0);

        $order = $existingOrder;
        if (!$order) {
            $customerEmail = trim((string) ($params['customer_email'] ?? $quote?->email ?? ''));
            if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'error' => true,
                    'status' => 'error',
                    'code' => 'invalid_customer_email',
                    'message' => 'Eine gültige Kunden-E-Mail-Adresse ist für den Checkout-Link erforderlich.',
                ];
            }

            $firstName = trim((string) ($params['customer_first_name'] ?? $quote?->first_name ?? 'Kunde'));
            $lastName = trim((string) ($params['customer_last_name'] ?? $quote?->last_name ?? 'Manufaktur'));
            $company = trim((string) ($params['company'] ?? $quote?->company ?? ''));

            $normalizedItems = [];
            $virtualShippingItems = [];
            $totalWeightKg = 0.0;

            if ($quote && $quote->items->isNotEmpty()) {
                foreach ($quote->items as $qItem) {
                    $qty = max(1, (int) $qItem->quantity);
                    $unitCents = max(1, (int) $qItem->unit_price);
                    $prod = $qItem->product;
                    $itemTax = $isSmallBusiness ? 0.0 : ($prod ? (float) $prod->tax_rate : $defaultTaxRate);

                    $normalizedItems[] = [
                        'product_id' => $qItem->product_id,
                        'product_name' => (string) ($qItem->product_name ?: ($prod?->name ?? 'Manufaktur-Position')),
                        'quantity' => $qty,
                        'unit_price_cents' => $unitCents,
                        'total_price_cents' => $unitCents * $qty,
                        'tax_rate' => $itemTax,
                        'configuration' => is_array($qItem->configuration) ? $qItem->configuration : [],
                    ];

                    if ($prod) {
                        $virtualShippingItems[] = (object) ['product' => $prod, 'configuration' => $qItem->configuration ?? []];
                        if ($prod->type === 'physical') {
                            $totalWeightKg += ((max(1, (int) ($prod->weight ?: 100)) * $qty) / 1000);
                        }
                    }
                }
            } else {
                $rawItems = is_array($params['items'] ?? null) ? $params['items'] : [];
                if (empty($rawItems)) {
                    return [
                        'error' => true,
                        'status' => 'error',
                        'code' => 'missing_items',
                        'message' => 'Es wurden weder eine Angebotsnummer noch gültige Positionen übergeben.',
                    ];
                }

                foreach ($rawItems as $rawItem) {
                    if (!is_array($rawItem)) {
                        continue;
                    }
                    $qty = max(1, (int) ($rawItem['quantity'] ?? 1));
                    $prod = !empty($rawItem['product_id']) ? Product::find($rawItem['product_id']) : null;
                    $unitCents = isset($rawItem['unit_price_cents'])
                        ? (int) $rawItem['unit_price_cents']
                        : ($prod ? (int) $prod->gross_price : 0);

                    if ($unitCents <= 0) {
                        return [
                            'error' => true,
                            'status' => 'error',
                            'code' => 'invalid_item_price',
                            'message' => 'Jede Position muss einen positiven Cent-Einzelpreis besitzen.',
                        ];
                    }

                    $name = trim((string) ($rawItem['name'] ?? $rawItem['product_name'] ?? ($prod?->name ?? 'Sonderanfertigung')));
                    $itemTax = $isSmallBusiness ? 0.0 : ($prod ? (float) $prod->tax_rate : $defaultTaxRate);

                    $normalizedItems[] = [
                        'product_id' => $prod?->id,
                        'product_name' => $name !== '' ? $name : 'Sonderanfertigung',
                        'quantity' => $qty,
                        'unit_price_cents' => $unitCents,
                        'total_price_cents' => $unitCents * $qty,
                        'tax_rate' => $itemTax,
                        'configuration' => is_array($rawItem['configuration'] ?? null) ? $rawItem['configuration'] : [],
                    ];

                    if ($prod) {
                        $virtualShippingItems[] = (object) ['product' => $prod, 'configuration' => $rawItem['configuration'] ?? []];
                        if ($prod->type === 'physical') {
                            $totalWeightKg += ((max(1, (int) ($prod->weight ?: 100)) * $qty) / 1000);
                        }
                    }
                }
            }

            if (empty($normalizedItems)) {
                return [
                    'error' => true,
                    'status' => 'error',
                    'code' => 'empty_items',
                    'message' => 'Keine abrechenbaren Positionen gefunden.',
                ];
            }

            $subtotalCents = 0;
            $taxCents = 0;
            foreach ($normalizedItems as $nItem) {
                $subtotalCents += (int) $nItem['total_price_cents'];
                if (!$isSmallBusiness && $nItem['tax_rate'] > 0) {
                    $divisor = 1 + (((float) $nItem['tax_rate']) / 100);
                    $taxCents += (int) round($nItem['total_price_cents'] - ($nItem['total_price_cents'] / $divisor));
                }
            }

            if (array_key_exists('shipping_cents', $params) && $params['shipping_cents'] !== null) {
                $shippingCents = max(0, (int) $params['shipping_cents']);
            } elseif ($quote && (int) $quote->gross_total > $subtotalCents) {
                $shippingCents = max(0, (int) $quote->gross_total - $subtotalCents);
            } elseif (!empty($virtualShippingItems)) {
                $shipCalc = app(ShippingCalculatorService::class)->calculateShippingCost(
                    $virtualShippingItems,
                    $subtotalCents,
                    max(0.1, $totalWeightKg),
                    $shippingCountry
                );
                $shippingCents = max(0, (int) ($shipCalc['cost'] ?? 0));
            } else {
                $shippingCents = 0;
            }

            if (!$isSmallBusiness && $shippingCents > 0 && $defaultTaxRate > 0) {
                $shipDivisor = 1 + ($defaultTaxRate / 100);
                $taxCents += (int) round($shippingCents - ($shippingCents / $shipDivisor));
            }

            $totalCents = $subtotalCents + $shippingCents;

            $addressPayload = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company' => $company,
                'address' => trim((string) ($params['address'] ?? ($quote?->shipping_address['address'] ?? ''))),
                'postal_code' => trim((string) ($params['postal_code'] ?? ($quote?->shipping_address['postal_code'] ?? ''))),
                'city' => trim((string) ($params['city'] ?? ($quote?->shipping_address['city'] ?? ''))),
                'country' => $shippingCountry,
            ];

            $customer = Customer::withTrashed()
                ->whereRaw('LOWER(email) = ?', [strtolower($customerEmail)])
                ->first();

            $order = DB::transaction(function () use (
                $customer,
                $customerEmail,
                $addressPayload,
                $subtotalCents,
                $taxCents,
                $shippingCents,
                $totalCents,
                $isSmallBusiness,
                $normalizedItems,
                $quote
            ) {
                $createdOrder = OrderOrder::create([
                    'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
                    'customer_id' => $customer?->id ?: $quote?->customer_id,
                    'email' => $customerEmail,
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'payment_method' => 'stripe',
                    'billing_address' => $addressPayload,
                    'shipping_address' => $addressPayload,
                    'subtotal_price' => $subtotalCents,
                    'tax_amount' => $taxCents,
                    'shipping_price' => $shippingCents,
                    'total_price' => $totalCents,
                    'is_small_business' => $isSmallBusiness,
                    'notes' => $quote ? ('Angebot #' . $quote->quote_number) : null,
                ]);

                foreach ($normalizedItems as $nItem) {
                    $resolvedProductId = !empty($nItem['product_id'])
                        ? (string) $nItem['product_id']
                        : $this->resolveFallbackCustomProductId();

                    $createdOrder->items()->create([
                        'product_id' => $resolvedProductId,
                        'product_name' => $nItem['product_name'],
                        'quantity' => $nItem['quantity'],
                        'unit_price' => $nItem['unit_price_cents'],
                        'tax_rate' => $nItem['tax_rate'],
                        'total_price' => $nItem['total_price_cents'],
                        'configuration' => $nItem['configuration'],
                    ]);
                }

                return $createdOrder->load('items.product');
            });
        }

        $lineItems = [];
        foreach ($order->items as $orderItem) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => (int) $orderItem->unit_price,
                    'product_data' => [
                        'name' => (string) $orderItem->product_name,
                    ],
                ],
                'quantity' => max(1, (int) $orderItem->quantity),
            ];
        }

        if ((int) $order->shipping_price > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => (int) $order->shipping_price,
                    'product_data' => [
                        'name' => 'Versand & Verpackung (' . $shippingCountry . ')',
                    ],
                ],
                'quantity' => 1,
            ];
        }

        $expiresInHours = max(1, min(720, (int) ($params['expires_in_hours'] ?? 24)));
        $expiresAtUnix = isset($params['expires_at']) && is_numeric($params['expires_at'])
            ? (int) $params['expires_at']
            : now()->addHours($expiresInHours)->timestamp;

        $metadata = [
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'shipping_country' => $shippingCountry,
            'created_by_agent' => $actorName,
        ];
        if ($quote && !empty($quote->quote_number)) {
            $metadata['quote_number'] = (string) $quote->quote_number;
        }

        $baseUrl = rtrim((string) config('app.url', 'https://mein-seelenfunke.de'), '/');
        $stripePayload = [
            'mode' => 'payment',
            'currency' => 'eur',
            'customer_email' => (string) $order->email,
            'line_items' => $lineItems,
            'metadata' => $metadata,
            'payment_intent_data' => [
                'metadata' => $metadata,
            ],
            'expires_at' => $expiresAtUnix,
            'success_url' => $baseUrl . '/checkout/success?order=' . urlencode((string) $order->order_number),
            'cancel_url' => $baseUrl . '/cart',
        ];

        $idempotencyKey = !empty($params['idempotency_key'])
            ? (string) $params['idempotency_key']
            : ('checkout_link:' . $order->id . ':' . (int) $order->total_price);

        $stripeRes = $this->requestStripeApi('POST', '/checkout/sessions', $stripePayload, $idempotencyKey);
        if (!$stripeRes['ok']) {
            $this->logAudit(
                'stripe_agent:checkout_link_failed',
                'Stripe Checkout-Link Erstellung fehlgeschlagen',
                $stripeRes['error_message'] ?? 'Unbekannter Stripe-Fehler',
                'error',
                ['order_number' => $order->order_number, 'actor' => $actorName]
            );

            return [
                'error' => true,
                'status' => 'error',
                'code' => $stripeRes['error_code'] ?? 'stripe_error',
                'message' => $stripeRes['error_message'] ?? 'Stripe Checkout-Link konnte nicht erstellt werden.',
            ];
        }

        $sessionData = $stripeRes['data'];
        $checkoutUrl = (string) ($sessionData['url'] ?? '');
        $sessionId = (string) ($sessionData['id'] ?? '');
        $intentId = is_array($sessionData['payment_intent'] ?? null)
            ? (string) ($sessionData['payment_intent']['id'] ?? '')
            : (string) ($sessionData['payment_intent'] ?? '');

        $orderUpdate = ['payment_url' => $checkoutUrl];
        if ($intentId !== '') {
            $orderUpdate['stripe_payment_intent_id'] = $intentId;
        }
        $order->update($orderUpdate);

        $emailSent = false;
        if (!empty($params['send_email']) && !empty($order->email) && filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
            try {
                $euroFormatted = number_format(((int) $order->total_price) / 100, 2, ',', '.') . ' EUR';
                $focusLabel = $order->getProductionFocusLabel();
                $mailBody = "Guten Tag,\n\n"
                    . "hier ist Ihr persönlicher Zahlungslink für Ihre Bestellung ({$focusLabel}) bei Mein Seelenfunke:\n\n"
                    . "Gesamtbetrag (inkl. gesetzlicher USt.): {$euroFormatted}\n"
                    . "Direkt zur sicheren Zahlung: {$checkoutUrl}\n\n"
                    . "Herzliche Grüße aus der Manufaktur Gifhorn,\n"
                    . "Alina Steinhauer · Mein Seelenfunke";

                Mail::to($order->email)->send(new AiAgentMessageMail(
                    'Ihr Zahlungslink von Mein Seelenfunke (' . $order->order_number . ')',
                    $mailBody,
                    $actorName
                ));
                $emailSent = true;
            } catch (\Throwable $mailEx) {
                Log::warning('Stripe Checkout-Link Mailversand fehlgeschlagen: ' . $mailEx->getMessage());
            }
        }

        $this->logAudit(
            'stripe_agent:checkout_link_created',
            'Autonomer Stripe Checkout-Link erstellt',
            "Checkout-Session {$sessionId} für {$order->getProductionFocusLabel()} ({$order->order_number}) erstellt.",
            'success',
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'quote_number' => $quote?->quote_number,
                'session_id' => $sessionId,
                'amount_total_cents' => (int) $order->total_price,
                'tax_cents' => (int) $order->tax_amount,
                'shipping_cents' => (int) $order->shipping_price,
                'shipping_country' => $shippingCountry,
                'idempotency_key' => $idempotencyKey,
                'email_sent' => $emailSent,
                'actor' => $actorName,
            ]
        );

        return [
            'status' => 'success',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'production_focus' => $order->getProductionFocusLabel(),
            'quote_number' => $quote?->quote_number,
            'session_id' => $sessionId,
            'checkout_url' => $checkoutUrl,
            'amount_total_cents' => (int) $order->total_price,
            'subtotal_cents' => (int) $order->subtotal_price,
            'shipping_cents' => (int) $order->shipping_price,
            'tax_cents' => (int) $order->tax_amount,
            'currency' => 'eur',
            'shipping_country' => $shippingCountry,
            'idempotency_key' => $idempotencyKey,
            'email_sent' => $emailSent,
        ];
    }

    /**
     * Berechnet den bisher für eine Bestellung gutgeschriebenen/erstatteten Betrag in Cent.
     */
    public function getAlreadyRefundedCentsForOrder(OrderOrder $order): int
    {
        $creditsSum = AccountingInvoice::query()
            ->where('order_id', $order->id)
            ->whereIn('type', ['credit_note', 'cancellation'])
            ->where('status', '!=', 'draft')
            ->sum('total');

        return abs((int) $creditsSum);
    }

    /**
     * 2. Führt eine autonome Voll- oder Teilerstattung inkl. GoBD-Gutschrift (type = credit_note)
     *    und Sicherheits-Leitplanken (Einzellimit & 24h-Tagesdeckel) aus.
     */
    public function executeRefund(
        array $params,
        string $actorName = 'Falcone',
        bool $bypassAutonomyGuardrail = false
    ): array {
        $order = $this->resolveOrderFromParams($params);
        if (!$order) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'order_not_found',
                'message' => 'Bestellung für die Rückerstattung wurde nicht gefunden.',
            ];
        }

        if (empty($order->stripe_payment_intent_id)) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'missing_payment_intent',
                'message' => 'Bestellung besitzt keine verknüpfte Stripe PaymentIntent-ID.',
            ];
        }

        if (!in_array($order->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'order_not_paid',
                'message' => 'Nur bezahlte Bestellungen können über Stripe erstattet werden.',
            ];
        }

        $alreadyRefundedCents = $this->getAlreadyRefundedCentsForOrder($order);
        $restRefundableCents = max(0, (int) $order->total_price - $alreadyRefundedCents);

        if ($restRefundableCents <= 0) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'already_fully_refunded',
                'message' => 'Diese Bestellung wurde bereits vollständig erstattet.',
            ];
        }

        $amountCents = isset($params['amount_cents']) && $params['amount_cents'] !== null
            ? (int) $params['amount_cents']
            : $restRefundableCents;

        if ($amountCents <= 0 || $amountCents > $restRefundableCents) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'exceeds_refundable_amount',
                'rest_refundable_cents' => $restRefundableCents,
                'message' => "Erstattungsbetrag ({$amountCents} Cent) überschreitet den erstattbaren Restbetrag ({$restRefundableCents} Cent).",
            ];
        }

        $rawReason = strtolower(trim((string) ($params['reason'] ?? 'requested_by_customer')));
        $stripeReason = match ($rawReason) {
            'duplicate' => 'duplicate',
            'fraudulent' => 'fraudulent',
            default => 'requested_by_customer',
        };

        // Autonomie-Leitplanken prüfen (Hauptschalter, Einzellimit, 24h-Tagesdeckel)
        if (!$bypassAutonomyGuardrail) {
            $autonomyEnabled = $this->isAutonomyEnabled();
            $singleLimitCents = $this->getSingleRefundLimitCents();
            $dailyCapCents = $this->getDailyRefundCapCents();
            $used24hCents = $this->getExecutedAutoRefundTotalLast24hCents();

            $violationCode = null;
            $violationText = null;

            if (!$autonomyEnabled) {
                $violationCode = 'autonomy_disabled';
                $violationText = 'Autonome Stripe-Ausführung ist in den Einstellungen deaktiviert.';
            } elseif ($amountCents > $singleLimitCents) {
                $violationCode = 'single_refund_limit_exceeded';
                $violationText = sprintf(
                    'Betrag (%s EUR) liegt über dem autonomen Einzellimit (%s EUR).',
                    number_format($amountCents / 100, 2, ',', '.'),
                    number_format($singleLimitCents / 100, 2, ',', '.')
                );
            } elseif (($used24hCents + $amountCents) > $dailyCapCents) {
                $violationCode = 'daily_refund_cap_exceeded';
                $violationText = sprintf(
                    '24h-Erstattungsdeckel (%s EUR) würde überschritten (bereits genutzt: %s EUR).',
                    number_format($dailyCapCents / 100, 2, ',', '.'),
                    number_format($used24hCents / 100, 2, ',', '.')
                );
            }

            if ($violationCode !== null) {
                $euroStr = number_format($amountCents / 100, 2, ',', '.') . ' EUR';
                $focus = $order->getProductionFocusLabel();
                $taskTitle = "Stripe-Erstattung freigeben: {$focus} ({$euroStr})";
                $taskPlan = "Bestellung: {$order->order_number}\n"
                    . "Betrag: {$euroStr} ({$amountCents} Cent)\n"
                    . "Grund: {$rawReason}\n"
                    . "Sicherheitsbremse: {$violationText}";

                $task = $this->createPriorityDashboardTask($taskTitle, $taskPlan, 'high');

                $this->logAudit(
                    'stripe_agent:refund_blocked_approval_required',
                    'Autonome Stripe-Erstattung gestoppt (Freigabe nötig)',
                    "{$violationText} Auftrag {$order->order_number}, angefragt durch {$actorName}.",
                    'warning',
                    [
                        'fingerprint' => 'refund_guardrail:' . $order->id . ':' . $amountCents . ':' . $violationCode,
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'amount_cents' => $amountCents,
                        'single_limit_cents' => $singleLimitCents,
                        'daily_cap_cents' => $dailyCapCents,
                        'used_24h_cents' => $used24hCents,
                        'violation_code' => $violationCode,
                        'task_id' => $task->id,
                        'actor' => $actorName,
                    ]
                );

                return [
                    'status' => 'approval_required',
                    'blocked_by_guardrail' => true,
                    'violation_code' => $violationCode,
                    'order_number' => $order->order_number,
                    'amount_cents' => $amountCents,
                    'single_limit_cents' => $singleLimitCents,
                    'daily_cap_cents' => $dailyCapCents,
                    'used_24h_cents' => $used24hCents,
                    'task_id' => $task->id,
                    'message' => "{$violationText} Eine Freigabe-Aufgabe wurde für Alina im Operativen Status angelegt.",
                ];
            }
        }

        $seq = AccountingInvoice::query()
            ->where('order_id', $order->id)
            ->whereIn('type', ['credit_note', 'cancellation'])
            ->count() + 1;

        $idempotencyKey = !empty($params['idempotency_key'])
            ? (string) $params['idempotency_key']
            : "refund:{$order->id}:{$amountCents}:{$seq}";

        $refundRes = $this->requestStripeApi('POST', '/refunds', [
            'payment_intent' => (string) $order->stripe_payment_intent_id,
            'amount' => $amountCents,
            'reason' => $stripeReason,
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
                'internal_reason' => $rawReason,
                'executed_by_agent' => $actorName,
            ],
        ], $idempotencyKey);

        if (!$refundRes['ok']) {
            $this->logAudit(
                'stripe_agent:refund_failed',
                'Stripe-Rückerstattung fehlgeschlagen',
                $refundRes['error_message'] ?? 'Stripe Refund API Fehler',
                'error',
                ['order_number' => $order->order_number, 'amount_cents' => $amountCents, 'actor' => $actorName]
            );

            return [
                'error' => true,
                'status' => 'error',
                'code' => $refundRes['error_code'] ?? 'stripe_refund_failed',
                'message' => $refundRes['error_message'] ?? 'Stripe-Rückerstattung fehlgeschlagen.',
            ];
        }

        $refundData = $refundRes['data'];
        $stripeRefundId = (string) ($refundData['id'] ?? ('re_local_' . Str::lower(Str::random(12))));
        $isFullRefund = ($alreadyRefundedCents + $amountCents) >= (int) $order->total_price;

        $creditNote = $this->finalizeOrderRefundAndCreditNote(
            $order,
            $amountCents,
            $stripeRefundId,
            $rawReason,
            $isFullRefund
        );

        $this->logAudit(
            'stripe_agent:refund_executed',
            'Autonome Stripe-Erstattung & GoBD-Gutschrift ausgeführt',
            "Erstattung {$stripeRefundId} über {$amountCents} Cent für {$order->order_number} (Gutschrift {$creditNote->invoice_number}) abgeschlossen.",
            'success',
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'stripe_refund_id' => $stripeRefundId,
                'amount_cents' => $amountCents,
                'is_full_refund' => $isFullRefund,
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->invoice_number,
                'idempotency_key' => $idempotencyKey,
                'actor' => $actorName,
            ]
        );

        return [
            'status' => 'success',
            'order_number' => $order->order_number,
            'stripe_refund_id' => $stripeRefundId,
            'amount_cents' => $amountCents,
            'is_full_refund' => $isFullRefund,
            'rest_refundable_cents' => max(0, $restRefundableCents - $amountCents),
            'credit_note_id' => $creditNote->id,
            'credit_note_number' => $creditNote->invoice_number,
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /**
     * Legt idempotent eine GoBD-Gutschrift (AccountingInvoice mit type = credit_note) über InvoiceService an
     * und synchronisiert OrderOrder sowie OrderRevocation.
     */
    public function finalizeOrderRefundAndCreditNote(
        OrderOrder $order,
        int $amountCents,
        string $stripeRefundId,
        string $reasonLabel = 'requested_by_customer',
        bool $isFullRefund = false
    ): AccountingInvoice {
        $marker = "[stripe_refund:{$stripeRefundId}]";

        $existingCredit = AccountingInvoice::query()
            ->where('order_id', $order->id)
            ->where('type', 'credit_note')
            ->where('notes', 'like', '%' . addcslashes($marker, '%_\\') . '%')
            ->first();

        if (!$existingCredit) {
            $isSmallBusiness = (bool) ($order->is_small_business ?? app(TaxSettingService::class)->isCurrentSmallBusiness());
            $taxRate = $isSmallBusiness ? 0.0 : (float) $this->getSettingValue('default_tax_rate', 19.0);
            $divisor = 1 + ($taxRate / 100);
            $netCents = $isSmallBusiness ? $amountCents : (int) round($amountCents / $divisor);
            $taxCents = $isSmallBusiness ? 0 : ($amountCents - $netCents);

            $focusLabel = $order->getProductionFocusLabel();
            $itemLabel = $isFullRefund
                ? "Rückerstattung zu Bestellung #{$order->order_number} ({$focusLabel})"
                : "Teilerstattung zu Bestellung #{$order->order_number} ({$focusLabel})";

            $creditData = [
                'customer_id' => $order->customer_id,
                'subject' => 'Gutschrift zu Bestellung #' . $order->order_number,
                'header_text' => 'Hiermit erhalten Sie eine GoBD-konforme Gutschrift zu Ihrer Bestellung.',
                'footer_text' => 'Der gutgeschriebene Betrag wurde auf Ihr ursprüngliches Stripe-Zahlungsmittel erstattet.',
                'notes' => "Stripe-Erstattung ({$reasonLabel}) zu Bestellung #{$order->order_number} {$marker}",
                'billing_address' => $order->billing_address ?? [],
                'shipping_address' => $order->shipping_address ?? [],
                'subtotal' => $amountCents,
                'tax_amount' => $taxCents,
                'total' => $amountCents,
                'is_small_business' => $isSmallBusiness,
                'custom_items' => [
                    [
                        'product_name' => $itemLabel,
                        'quantity' => 1,
                        'unit_price' => $amountCents,
                        'net_unit_price' => $netCents,
                        'tax_rate' => $taxRate,
                        'total_price' => $amountCents,
                        'configuration' => [],
                    ],
                ],
            ];

            $existingCredit = app(InvoiceService::class)->createCreditNote($creditData);
            $existingCredit->update(['order_id' => $order->id]);
        }

        if ($isFullRefund) {
            if ($order->payment_status !== 'refunded' || $order->status !== 'refunded') {
                $prevStatus = (string) $order->status;
                $prevPaymentStatus = (string) $order->payment_status;

                // Falls bereits eine Ausgangsrechnung existiert, auf cancelled markieren, damit der Order-Observer
                // nicht zusätzlich zur bereits erzeugten credit_note eine zweite Stornobuchung erzeugt.
                $order->invoices()
                    ->where('type', 'invoice')
                    ->whereIn('status', ['paid', 'open'])
                    ->update(['status' => 'cancelled']);

                if (!in_array($prevStatus, ['cancelled', 'refunded'], true) && $prevPaymentStatus !== 'refunded') {
                    $order->handleCancellation('Stripe Rückerstattung (' . $reasonLabel . ')', $prevStatus, $prevPaymentStatus);
                }

                $order->update([
                    'payment_status' => 'refunded',
                    'status' => 'refunded',
                    'cancellation_reason' => $order->cancellation_reason ?: ('Stripe Rückerstattung (' . $reasonLabel . ')'),
                ]);
            }

            if (Schema::hasTable('order_revocations')) {
                OrderRevocation::query()
                    ->where('order_number', $order->order_number)
                    ->where('status', '!=', 'processed')
                    ->update([
                        'status' => 'processed',
                        'customer_notified_at' => now(),
                    ]);
            }
        }

        return $existingCredit;
    }

    /**
     * 3. Bucht Folge-/B2B-Bestellungen über ein gespeichertes Off-Session-Mandat ab.
     *    Greift bei PSD2/SCA (authentication_required / requires_action) automatisch auf einen 1-Klick-Checkout-Link zurück.
     */
    public function chargeSavedMandate(array $params, string $actorName = 'Falcone'): array
    {
        $order = $this->resolveOrderFromParams($params);
        if (!$order) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'order_not_found',
                'message' => 'Bestellung für den Mandats-Einzug wurde nicht gefunden.',
            ];
        }

        if ($order->payment_status === 'paid') {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'order_already_paid',
                'message' => 'Diese Bestellung ist bereits bezahlt.',
            ];
        }

        $stripeCustomerId = trim((string) ($params['stripe_customer_id'] ?? $params['customer_id'] ?? ''));
        $paymentMethodId = trim((string) ($params['payment_method_id'] ?? ''));

        if ($stripeCustomerId === '' || $paymentMethodId === '') {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'missing_mandate_identifiers',
                'message' => 'Sowohl customer_id als auch payment_method_id sind für den Off-Session-Einzug erforderlich.',
            ];
        }

        $amountCents = isset($params['amount_cents']) && (int) $params['amount_cents'] > 0
            ? (int) $params['amount_cents']
            : (int) $order->total_price;

        $idempotencyKey = !empty($params['idempotency_key'])
            ? (string) $params['idempotency_key']
            : "mandate_charge:{$order->id}:{$amountCents}:{$paymentMethodId}";

        $intentRes = $this->requestStripeApi('POST', '/payment_intents', [
            'amount' => $amountCents,
            'currency' => 'eur',
            'customer' => $stripeCustomerId,
            'payment_method' => $paymentMethodId,
            'off_session' => 'true',
            'confirm' => 'true',
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
                'charged_by_agent' => $actorName,
            ],
        ], $idempotencyKey);

        $intentData = $intentRes['data'] ?? [];
        $intentStatus = (string) ($intentData['status'] ?? '');
        $errorCode = (string) ($intentRes['error_code'] ?? '');

        $requiresScaFallback = (!$intentRes['ok'] && in_array($errorCode, ['authentication_required', 'card_declined_authentication_required'], true))
            || in_array($intentStatus, ['requires_action', 'requires_confirmation'], true);

        if ($requiresScaFallback) {
            $shipAddr = is_array($order->shipping_address) ? $order->shipping_address : [];
            $fallbackLink = $this->createCheckoutLink([
                'order_number' => $order->order_number,
                'shipping_country' => (string) ($shipAddr['country'] ?? 'DE'),
                'send_email' => (bool) ($params['send_email'] ?? true),
                'idempotency_key' => 'sca_fallback:' . $order->id . ':' . $amountCents,
            ], $actorName);

            $this->logAudit(
                'stripe_agent:mandate_sca_fallback',
                'PSD2/SCA 3D-Secure angefordert: 1-Klick-Link erzeugt',
                "Bank forderte 3D-Secure für {$order->order_number}. Automatischer Checkout-Link bereitgestellt.",
                'info',
                [
                    'order_number' => $order->order_number,
                    'amount_cents' => $amountCents,
                    'checkout_url' => $fallbackLink['checkout_url'] ?? null,
                    'actor' => $actorName,
                ]
            );

            return [
                'status' => 'requires_action_fallback_link_sent',
                'sca_required' => true,
                'order_number' => $order->order_number,
                'amount_cents' => $amountCents,
                'fallback_checkout_url' => $fallbackLink['checkout_url'] ?? null,
                'fallback_session_id' => $fallbackLink['session_id'] ?? null,
                'email_sent' => $fallbackLink['email_sent'] ?? false,
            ];
        }

        if (!$intentRes['ok'] || !in_array($intentStatus, ['succeeded', 'processing'], true)) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => $errorCode !== '' ? $errorCode : 'mandate_charge_failed',
                'message' => $intentRes['error_message'] ?? 'Mandats-Einzug fehlgeschlagen.',
            ];
        }

        $intentId = (string) ($intentData['id'] ?? '');
        if ($intentStatus === 'succeeded') {
            $order->completePayment($intentId);
        } else {
            $order->update(['stripe_payment_intent_id' => $intentId]);
        }

        $this->logAudit(
            'stripe_agent:mandate_charged',
            'Autonomer Off-Session-Mandatseinzug erfolgreich',
            "Bestellung {$order->order_number} über {$amountCents} Cent erfolgreich abgebucht ({$intentId}).",
            'success',
            [
                'order_number' => $order->order_number,
                'payment_intent_id' => $intentId,
                'amount_cents' => $amountCents,
                'idempotency_key' => $idempotencyKey,
                'actor' => $actorName,
            ]
        );

        return [
            'status' => 'succeeded',
            'order_number' => $order->order_number,
            'payment_intent_id' => $intentId,
            'amount_cents' => $amountCents,
            'currency' => 'eur',
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /**
     * 4. Liest Stripe Payouts & BalanceTransactions aus, prüft Dubletten und liefert Cent-genaue
     *    Umsatz- und Gebührenaufschlüsselung für den FYRST-Bankabgleich.
     */
    public function reconcilePayoutsAndFees(array $params = [], string $actorName = 'Falcone'): array
    {
        $fromCarbon = !empty($params['date_from'])
            ? Carbon::parse((string) $params['date_from'])->startOfDay()
            : now()->subDays(30)->startOfDay();
        $toCarbon = !empty($params['date_to'])
            ? Carbon::parse((string) $params['date_to'])->endOfDay()
            : now()->endOfDay();

        $queryFilter = [
            'created' => [
                'gte' => $fromCarbon->timestamp,
                'lte' => $toCarbon->timestamp,
            ],
            'limit' => 100,
        ];

        $payoutsRes = $this->requestStripeApi('GET', '/payouts', $queryFilter);
        $txRes = $this->requestStripeApi('GET', '/balance_transactions', $queryFilter);

        if (!$payoutsRes['ok'] || !$txRes['ok']) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'stripe_reconcile_failed',
                'message' => $payoutsRes['error_message'] ?? $txRes['error_message'] ?? 'Stripe Abgleich fehlgeschlagen.',
            ];
        }

        $knownPayoutIds = $this->getAlreadyReconciledIds('reconciled_payout_ids');
        $knownBalanceTxIds = $this->getAlreadyReconciledIds('reconciled_balance_tx_ids');

        $grossVolumeCents = 0;
        $refundsCents = 0;
        $stripeFeesCents = 0;
        $netVolumeCents = 0;
        $newBalanceTxIds = [];
        $skippedDuplicateTx = 0;

        $txItems = is_array($txRes['data']['data'] ?? null) ? $txRes['data']['data'] : [];
        foreach ($txItems as $tx) {
            if (!is_array($tx)) {
                continue;
            }
            $txId = (string) ($tx['id'] ?? '');
            if ($txId === '') {
                continue;
            }
            if (in_array($txId, $knownBalanceTxIds, true)) {
                $skippedDuplicateTx++;
                continue;
            }

            $type = strtolower((string) ($tx['type'] ?? 'charge'));
            if ($type === 'payout') {
                continue;
            }

            $amount = (int) ($tx['amount'] ?? 0);
            $fee = abs((int) ($tx['fee'] ?? 0));
            $net = (int) ($tx['net'] ?? ($amount - $fee));

            if ($type === 'refund' || $amount < 0) {
                $refundsCents += abs($amount);
            } else {
                $grossVolumeCents += $amount;
            }

            $stripeFeesCents += $fee;
            $netVolumeCents += $net;
            $newBalanceTxIds[] = $txId;
        }

        $payoutSummaries = [];
        $newPayoutIds = [];
        $skippedDuplicatePayouts = 0;
        $payoutTotalCents = 0;

        $payoutItems = is_array($payoutsRes['data']['data'] ?? null) ? $payoutsRes['data']['data'] : [];
        foreach ($payoutItems as $po) {
            if (!is_array($po)) {
                continue;
            }
            $poId = (string) ($po['id'] ?? '');
            if ($poId === '') {
                continue;
            }
            $alreadyReconciled = in_array($poId, $knownPayoutIds, true);
            if ($alreadyReconciled) {
                $skippedDuplicatePayouts++;
            } else {
                $newPayoutIds[] = $poId;
            }

            $poAmount = (int) ($po['amount'] ?? 0);
            if (!$alreadyReconciled) {
                $payoutTotalCents += $poAmount;
            }

            $arrivalDate = isset($po['arrival_date']) && is_numeric($po['arrival_date'])
                ? Carbon::createFromTimestamp((int) $po['arrival_date'])->toDateString()
                : null;

            $matchedBankTxId = null;
            if (Schema::hasTable('accounting_bank_transactions') && $poAmount > 0) {
                $amountDecimal = round($poAmount / 100, 2);
                $bankTx = AccountingBankTransaction::query()
                    ->where('amount', $amountDecimal)
                    ->where(function ($q) use ($poId) {
                        $q->where('purpose', 'like', '%' . addcslashes($poId, '%_\\') . '%')
                            ->orWhere('counterpart_name', 'like', '%Stripe%');
                    })
                    ->first();
                $matchedBankTxId = $bankTx?->id;
            }

            $payoutSummaries[] = [
                'stripe_payout_id' => $poId,
                'amount_cents' => $poAmount,
                'currency' => strtolower((string) ($po['currency'] ?? 'eur')),
                'status' => (string) ($po['status'] ?? 'paid'),
                'arrival_date' => $arrivalDate,
                'already_reconciled' => $alreadyReconciled,
                'fyrst_bank_transaction_id' => $matchedBankTxId,
            ];
        }

        if (!empty($newPayoutIds) || !empty($newBalanceTxIds)) {
            $this->logAudit(
                'stripe_agent:payout_reconciled',
                'Stripe Auszahlungs- & Gebührenabgleich (FYRST)',
                sprintf(
                    'Zeitraum %s bis %s: %d neue Auszahlungen, %d Cent Stripe-Gebühren erfasst.',
                    $fromCarbon->toDateString(),
                    $toCarbon->toDateString(),
                    count($newPayoutIds),
                    $stripeFeesCents
                ),
                'success',
                [
                    'date_from' => $fromCarbon->toDateString(),
                    'date_to' => $toCarbon->toDateString(),
                    'gross_volume_cents' => $grossVolumeCents,
                    'refunds_cents' => $refundsCents,
                    'stripe_fees_cents' => $stripeFeesCents,
                    'net_volume_cents' => $netVolumeCents,
                    'payout_total_cents' => $payoutTotalCents,
                    'reconciled_payout_ids' => $newPayoutIds,
                    'reconciled_balance_tx_ids' => $newBalanceTxIds,
                    'actor' => $actorName,
                ]
            );
        }

        return [
            'status' => 'success',
            'date_from' => $fromCarbon->toDateString(),
            'date_to' => $toCarbon->toDateString(),
            'gross_volume_cents' => $grossVolumeCents,
            'refunds_cents' => $refundsCents,
            'stripe_fees_cents' => $stripeFeesCents,
            'net_volume_cents' => $netVolumeCents,
            'payout_total_cents' => $payoutTotalCents,
            'payouts' => $payoutSummaries,
            'new_balance_transactions_count' => count($newBalanceTxIds),
            'skipped_duplicates_count' => $skippedDuplicateTx + $skippedDuplicatePayouts,
        ];
    }

    /**
     * 5. Bündelt Rechnung, Gravur-Freigabe, AGB-Bestätigung und DHL-Sendungsnummer (OrderShipment)
     *    und übermittelt das Beweispaket autonom an Stripe + erstellt Prioritäts-Aufgabe im Dashboard.
     */
    public function defendDispute(array $params, string $actorName = 'Falcone'): array
    {
        $disputeId = trim((string) ($params['dispute_id'] ?? ''));
        $submitImmediately = filter_var($params['submit_immediately'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $order = $this->resolveOrderFromParams($params);

        if ($disputeId !== '' && !$order) {
            $disputeGet = $this->requestStripeApi('GET', '/disputes/' . urlencode($disputeId));
            if ($disputeGet['ok']) {
                $piId = (string) ($disputeGet['data']['payment_intent'] ?? '');
                if ($piId !== '') {
                    $order = OrderOrder::with(['items.product', 'invoices', 'shipments'])->where('stripe_payment_intent_id', $piId)->first();
                }
            }
        } elseif ($disputeId === '' && $order && !empty($order->stripe_payment_intent_id)) {
            $disputeList = $this->requestStripeApi('GET', '/disputes', [
                'payment_intent' => (string) $order->stripe_payment_intent_id,
                'limit' => 1,
            ]);
            if ($disputeList['ok'] && !empty($disputeList['data']['data'][0]['id'])) {
                $disputeId = (string) $disputeList['data']['data'][0]['id'];
            }
        }

        if ($disputeId === '') {
            return [
                'error' => true,
                'status' => 'error',
                'code' => 'dispute_not_found',
                'message' => 'Keine gültige Stripe Dispute-ID gefunden.',
            ];
        }

        if ($order) {
            $order->loadMissing(['items.product', 'invoices', 'shipments']);
        }

        $evidence = $this->buildDisputeEvidencePayload($order);
        $idempotencyKey = 'dispute_defend:' . $disputeId . ':' . ($submitImmediately ? 'submit' : 'stage');

        $updateRes = $this->requestStripeApi('POST', '/disputes/' . urlencode($disputeId), [
            'evidence' => $evidence,
            'submit' => $submitImmediately ? 'true' : 'false',
            'metadata' => [
                'order_number' => (string) ($order?->order_number ?? 'unknown'),
                'defended_by_agent' => $actorName,
            ],
        ], $idempotencyKey);

        if (!$updateRes['ok']) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => $updateRes['error_code'] ?? 'dispute_update_failed',
                'message' => $updateRes['error_message'] ?? 'Beweispaket konnte nicht an Stripe übermittelt werden.',
            ];
        }

        $orderLabel = $order ? ($order->getProductionFocusLabel() . ' (' . $order->order_number . ')') : $disputeId;
        $taskTitle = "Stripe-Konfliktfall prüfen: {$orderLabel}";
        $taskPlan = "Dispute-ID: {$disputeId}\n"
            . 'Status Beweispaket: ' . ($submitImmediately ? 'Direkt eingereicht' : 'Bei Stripe hinterlegt (Entwurf)') . "\n"
            . 'Sendungsnummer: ' . ($evidence['shipping_tracking_number'] ?? 'Keine hinterlegt');

        $task = $this->createPriorityDashboardTask($taskTitle, $taskPlan, 'high');

        $this->logAudit(
            'stripe_agent:dispute_defended',
            'Stripe-Konfliktfall Beweispaket übermittelt',
            "Beweispaket für Dispute {$disputeId} ({$orderLabel}) an Stripe übertragen.",
            'success',
            [
                'dispute_id' => $disputeId,
                'order_number' => $order?->order_number,
                'submitted_immediately' => $submitImmediately,
                'tracking_number' => $evidence['shipping_tracking_number'] ?? null,
                'task_id' => $task->id,
                'actor' => $actorName,
            ]
        );

        return [
            'status' => 'success',
            'dispute_id' => $disputeId,
            'order_number' => $order?->order_number,
            'submitted' => $submitImmediately,
            'evidence_summary' => $evidence,
            'task_id' => $task->id,
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /**
     * 6. Liefert DSGVO-konforme Echtzeit-Telemetrie (Guthaben available/pending, letzte Zahlungen & offene Disputes).
     */
    public function getLiveTelemetry(array $params = [], string $actorName = 'Falcone'): array
    {
        $limit = max(1, min(25, (int) ($params['limit'] ?? 10)));

        $balanceRes = $this->requestStripeApi('GET', '/balance');
        $intentsRes = $this->requestStripeApi('GET', '/payment_intents', ['limit' => $limit]);
        $disputesRes = $this->requestStripeApi('GET', '/disputes', ['limit' => $limit]);

        if (!$balanceRes['ok']) {
            return [
                'error' => true,
                'status' => 'error',
                'code' => $balanceRes['error_code'] ?? 'stripe_telemetry_failed',
                'message' => $balanceRes['error_message'] ?? 'Stripe-Guthaben konnte nicht abgerufen werden.',
            ];
        }

        $availableCents = 0;
        foreach ((array) ($balanceRes['data']['available'] ?? []) as $entry) {
            if (strtolower((string) ($entry['currency'] ?? 'eur')) === 'eur') {
                $availableCents += (int) ($entry['amount'] ?? 0);
            }
        }

        $pendingCents = 0;
        foreach ((array) ($balanceRes['data']['pending'] ?? []) as $entry) {
            if (strtolower((string) ($entry['currency'] ?? 'eur')) === 'eur') {
                $pendingCents += (int) ($entry['amount'] ?? 0);
            }
        }

        $recentPayments = [];
        $failedCount = 0;
        foreach ((array) ($intentsRes['data']['data'] ?? []) as $pi) {
            if (!is_array($pi)) {
                continue;
            }
            $piStatus = (string) ($pi['status'] ?? 'unknown');
            if (in_array($piStatus, ['requires_payment_method', 'canceled'], true) || !empty($pi['last_payment_error'])) {
                $failedCount++;
            }

            $rawEmail = (string) ($pi['receipt_email'] ?? $pi['metadata']['customer_email'] ?? '');
            $customerId = $pi['metadata']['customer_id'] ?? null;
            $hasConsent = AiAuthHelper::hasCustomerAiConsent(null, is_string($customerId) ? $customerId : null, $rawEmail !== '' ? $rawEmail : null);

            $rawName = (string) ($pi['metadata']['customer_name'] ?? '');
            $recentPayments[] = [
                'payment_intent_id' => (string) ($pi['id'] ?? ''),
                'amount_cents' => (int) ($pi['amount'] ?? 0),
                'currency' => strtolower((string) ($pi['currency'] ?? 'eur')),
                'status' => $piStatus,
                'order_number' => $pi['metadata']['order_number'] ?? null,
                'customer_name' => AiAuthHelper::sanitizeCustomerName($rawName, $hasConsent),
                'customer_email' => $rawEmail !== '' ? AiAuthHelper::sanitizeCustomerEmail($rawEmail, $hasConsent) : null,
            ];
        }

        $openDisputes = [];
        foreach ((array) ($disputesRes['data']['data'] ?? []) as $dp) {
            if (!is_array($dp)) {
                continue;
            }
            $dpStatus = (string) ($dp['status'] ?? '');
            if (in_array($dpStatus, ['needs_response', 'warning_needs_response', 'under_review'], true)) {
                $openDisputes[] = [
                    'dispute_id' => (string) ($dp['id'] ?? ''),
                    'amount_cents' => (int) ($dp['amount'] ?? 0),
                    'reason' => (string) ($dp['reason'] ?? 'general'),
                    'status' => $dpStatus,
                ];
            }
        }

        return [
            'status' => 'success',
            'currency' => 'eur',
            'available_cents' => $availableCents,
            'pending_cents' => $pendingCents,
            'recent_payments' => $recentPayments,
            'failed_payments_count' => $failedCount,
            'open_disputes_count' => count($openDisputes),
            'open_disputes' => $openDisputes,
            'autonomy_guardrails' => [
                'autonomy_enabled' => $this->isAutonomyEnabled(),
                'single_refund_limit_cents' => $this->getSingleRefundLimitCents(),
                'daily_refund_cap_cents' => $this->getDailyRefundCapCents(),
                'used_24h_refund_cents' => $this->getExecutedAutoRefundTotalLast24hCents(),
            ],
        ];
    }

    /**
     * Bestätigt für das Agentic Commerce Protocol (/api/acp/v1/*) einen PaymentIntent ausschließlich
     * über ein händlergebundenes Stripe Shared Payment Token (spt_*).
     */
    public function confirmSharedPaymentTokenIntent(array $params): array
    {
        $spt = trim((string) ($params['shared_payment_token'] ?? ''));
        if (!preg_match('/^spt_[A-Za-z0-9_]{6,120}$/', $spt)) {
            return [
                'ok' => false,
                'code' => 'invalid_shared_payment_token',
                'message' => 'Ungültiges Shared Payment Token (Präfix spt_ erforderlich).',
            ];
        }

        $amountCents = max(1, (int) ($params['amount_cents'] ?? 0));
        $sessionId = (string) ($params['session_id'] ?? Str::uuid());
        $idempotencyKey = 'acp_complete:' . $sessionId;

        $res = $this->requestStripeApi('POST', '/payment_intents', [
            'amount' => $amountCents,
            'currency' => 'eur',
            'shared_payment_token' => $spt,
            'confirm' => 'true',
            'metadata' => [
                'channel' => 'acp_v1',
                'acp_session_id' => $sessionId,
                'shipping_country' => (string) ($params['shipping_country'] ?? 'DE'),
            ],
        ], $idempotencyKey);

        if (!$res['ok']) {
            return [
                'ok' => false,
                'code' => $res['error_code'] ?? 'payment_failed',
                'message' => $res['error_message'] ?? 'Zahlung über Shared Payment Token abgelehnt.',
            ];
        }

        $data = $res['data'];
        $status = (string) ($data['status'] ?? '');
        $currency = strtolower((string) ($data['currency'] ?? 'eur'));
        $confirmedAmount = (int) ($data['amount'] ?? 0);

        if (!in_array($status, ['succeeded', 'processing'], true)) {
            return [
                'ok' => false,
                'code' => 'payment_not_succeeded',
                'message' => "Zahlung nicht abgeschlossen (Status: {$status}).",
            ];
        }

        if ($currency !== 'eur' || $confirmedAmount !== $amountCents) {
            return [
                'ok' => false,
                'code' => 'amount_or_currency_mismatch',
                'message' => 'Betrags- oder Währungsabweichung bei der Stripe-Bestätigung.',
            ];
        }

        return [
            'ok' => true,
            'payment_intent_id' => (string) ($data['id'] ?? ('pi_acp_' . Str::lower(Str::random(12)))),
            'payment_status' => $status,
            'amount_cents' => $confirmedAmount,
        ];
    }

    /**
     * Webhook-Sync für charge.refunded (idempotent für externe und interne Teil-/Vollerstattungen).
     */
    public function syncWebhookRefund(OrderOrder $order, object $charge, ?string $eventId = null): void
    {
        if (!in_array((string) $order->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) {
            Log::warning("syncWebhookRefund ignoriert: Order {$order->order_number} hat Status '{$order->payment_status}' (nicht bezahlt).");
            return;
        }

        $isFullRefund = (bool) ($charge->refunded ?? false);
        $totalChargeRefundedCents = isset($charge->amount_refunded)
            ? max(0, (int) $charge->amount_refunded)
            : ($isFullRefund ? (int) $order->total_price : 0);

        $latestRefundId = null;
        if (isset($charge->refunds->data[0]->id)) {
            $latestRefundId = (string) $charge->refunds->data[0]->id;
        }
        $refundRef = $latestRefundId ?: ($eventId ?: ('charge_' . ($charge->id ?? $order->id) . '_' . $totalChargeRefundedCents));

        $alreadyCredited = $this->getAlreadyRefundedCentsForOrder($order);
        $deltaCents = max(0, $totalChargeRefundedCents - $alreadyCredited);

        if ($deltaCents > 0) {
            $this->finalizeOrderRefundAndCreditNote(
                $order,
                $deltaCents,
                $refundRef,
                'webhook_charge_refunded',
                $isFullRefund || (($alreadyCredited + $deltaCents) >= (int) $order->total_price)
            );
        } elseif ($isFullRefund && ($order->payment_status !== 'refunded' || $order->status !== 'refunded')) {
            $prevStatus = (string) $order->status;
            $prevPaymentStatus = (string) $order->payment_status;
            $order->invoices()->where('type', 'invoice')->whereIn('status', ['paid', 'open'])->update(['status' => 'cancelled']);
            $order->handleCancellation('Stripe Webhook Rückerstattung', $prevStatus, $prevPaymentStatus);
            $order->update([
                'payment_status' => 'refunded',
                'status' => 'refunded',
            ]);
        }
    }

    /**
     * Webhook-Verarbeitung für charge.dispute.created (idempotent).
     */
    public function handleWebhookDisputeCreated(object $dispute, ?string $eventId = null): void
    {
        $disputeId = (string) ($dispute->id ?? '');
        if ($disputeId === '') {
            return;
        }

        $actionId = 'stripe_webhook:dispute_created:' . $disputeId;
        if (Schema::hasTable('system_logs') && SystemLog::query()->where('action_id', $actionId)->exists()) {
            return;
        }

        $intentId = is_object($dispute->payment_intent ?? null)
            ? (string) ($dispute->payment_intent->id ?? '')
            : (string) ($dispute->payment_intent ?? '');

        $order = $intentId !== ''
            ? OrderOrder::with(['items.product', 'invoices', 'shipments'])->where('stripe_payment_intent_id', $intentId)->first()
            : null;

        $amountCents = (int) ($dispute->amount ?? 0);
        $reason = (string) ($dispute->reason ?? 'general');
        $orderInfo = $order ? ($order->getProductionFocusLabel() . ' (' . $order->order_number . ')') : ($intentId ?: $disputeId);

        $taskTitle = "DRINGEND Stripe-Konfliktfall: {$orderInfo}";
        $taskPlan = "Dispute-ID: {$disputeId}\n"
            . 'Betrag: ' . number_format($amountCents / 100, 2, ',', '.') . " EUR\n"
            . "Grund: {$reason}\n"
            . 'Hinweis: Beweismittel über Falcone (stripe_defend_dispute) prüfen und einreichen.';

        $task = $this->createPriorityDashboardTask($taskTitle, $taskPlan, 'high');

        $this->logAudit(
            $actionId,
            'Stripe-Konfliktfall (Dispute) eingegangen',
            "Neuer Stripe-Konflikt {$disputeId} ({$reason}) über {$amountCents} Cent für {$orderInfo}.",
            'warning',
            [
                'fingerprint' => $actionId,
                'event_id' => $eventId,
                'dispute_id' => $disputeId,
                'order_number' => $order?->order_number,
                'amount_cents' => $amountCents,
                'reason' => $reason,
                'task_id' => $task->id,
                'evidence_preview' => $this->buildDisputeEvidencePayload($order),
            ]
        );
    }

    /**
     * Webhook-Verarbeitung für charge.dispute.closed (idempotent).
     */
    public function handleWebhookDisputeClosed(object $dispute, ?string $eventId = null): void
    {
        $disputeId = (string) ($dispute->id ?? '');
        $status = (string) ($dispute->status ?? 'closed');
        if ($disputeId === '') {
            return;
        }

        $actionId = 'stripe_webhook:dispute_closed:' . $disputeId;
        if (Schema::hasTable('system_logs') && SystemLog::query()->where('action_id', $actionId)->exists()) {
            return;
        }

        $this->logAudit(
            $actionId,
            'Stripe-Konfliktfall abgeschlossen',
            "Dispute {$disputeId} wurde mit Ergebnis '{$status}' geschlossen.",
            $status === 'won' ? 'success' : 'warning',
            [
                'fingerprint' => $actionId,
                'event_id' => $eventId,
                'dispute_id' => $disputeId,
                'dispute_status' => $status,
                'amount_cents' => (int) ($dispute->amount ?? 0),
            ]
        );
    }

    /**
     * Webhook-Verarbeitung für payout.paid (idempotente Vormerkung für den FYRST-Bankabgleich).
     */
    public function handleWebhookPayoutPaid(object $payout, ?string $eventId = null): void
    {
        $payoutId = (string) ($payout->id ?? '');
        if ($payoutId === '') {
            return;
        }

        $actionId = 'stripe_webhook:payout_paid:' . $payoutId;
        if (Schema::hasTable('system_logs') && SystemLog::query()->where('action_id', $actionId)->exists()) {
            return;
        }

        $amountCents = (int) ($payout->amount ?? 0);
        $arrivalDate = isset($payout->arrival_date) && is_numeric($payout->arrival_date)
            ? Carbon::createFromTimestamp((int) $payout->arrival_date)->toDateString()
            : now()->toDateString();

        $this->logAudit(
            $actionId,
            'Stripe-Auszahlung auf FYRST-Konto vorgemerkt',
            "Stripe-Auszahlung {$payoutId} über {$amountCents} Cent (Valuta {$arrivalDate}) für den FYRST-Bankabgleich vorgemerkt.",
            'success',
            [
                'event_id' => $eventId,
                'stripe_payout_id' => $payoutId,
                'reconciled_payout_ids' => [$payoutId],
                'amount_cents' => $amountCents,
                'currency' => strtolower((string) ($payout->currency ?? 'eur')),
                'arrival_date' => $arrivalDate,
            ]
        );
    }

    /**
     * Stellt alle nachweisrelevanten Belege einer Bestellung für Stripe Dispute Evidence zusammen.
     */
    public function buildDisputeEvidencePayload(?OrderOrder $order): array
    {
        if (!$order) {
            return [
                'product_description' => 'Manufaktur-Bestellung bei Mein Seelenfunke (Gifhorn)',
                'uncategorized_text' => 'Kaufvertrag gem. AGB von Mein Seelenfunke abgeschlossen.',
            ];
        }

        $billing = is_array($order->billing_address) ? $order->billing_address : [];
        $customerName = trim(((string) ($billing['first_name'] ?? '')) . ' ' . ((string) ($billing['last_name'] ?? '')));
        $invoiceNumber = (string) ($order->invoices->where('type', 'invoice')->first()?->invoice_number ?? '');
        $shipment = $order->shipments->sortByDesc('created_at')->first();
        $trackingNumber = (string) ($shipment?->tracking_number ?? '');
        $carrier = (string) ($shipment?->carrier ?? ($trackingNumber !== '' ? 'DHL' : ''));

        $itemDescriptions = [];
        $hasPersonalizedGoods = false;
        foreach ($order->items as $item) {
            $conf = is_array($item->configuration) ? $item->configuration : [];
            $isCustom = !empty($conf['texts']) || !empty($conf['text_lines']) || !empty($conf['custom_text']) || !empty($item->config_fingerprint);
            if ($isCustom) {
                $hasPersonalizedGoods = true;
            }
            $fingerprintInfo = !empty($item->config_fingerprint) ? (' [Gravur-Hash: ' . substr((string) $item->config_fingerprint, 0, 12) . ']') : '';
            $itemDescriptions[] = $item->quantity . 'x ' . $item->product_name . $fingerprintInfo;
        }

        $legalNotice = $hasPersonalizedGoods
            ? 'Kundenspezifische Laser-Einzelanfertigung nach § 312g Abs. 2 Nr. 1 BGB mit digitaler Konfigurator-Freigabe.'
            : 'Reguläre Bestellung mit bestätigten AGB und Widerrufsbelehrung im Checkout.';

        $evidence = [
            'customer_name' => $customerName !== '' ? $customerName : 'Kunde',
            'customer_email_address' => (string) ($order->email ?? ''),
            'product_description' => implode(', ', $itemDescriptions) ?: $order->getProductionFocusLabel(),
            'uncategorized_text' => sprintf(
                'Bestellung %s vom %s. Rechnung: %s. %s',
                $order->order_number,
                $order->created_at ? $order->created_at->format('d.m.Y H:i') : now()->format('d.m.Y H:i'),
                $invoiceNumber !== '' ? $invoiceNumber : 'in Erstellung',
                $legalNotice
            ),
        ];

        if ($trackingNumber !== '') {
            $evidence['shipping_tracking_number'] = $trackingNumber;
            $evidence['shipping_carrier'] = $carrier !== '' ? $carrier : 'DHL';
        }

        return $evidence;
    }

    private function resolveFallbackCustomProductId(): string
    {
        $existing = Product::withTrashed()
            ->where('slug', 'individuelle-sonderanfertigung')
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            return (string) $existing->id;
        }

        $created = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Individuelle Manufaktur-Sonderanfertigung',
            'slug' => 'individuelle-sonderanfertigung',
            'sku' => 'SF-CUSTOM-ORDER',
            'type' => 'physical',
            'status' => 'draft',
            'price' => 0,
            'track_quantity' => false,
            'continue_selling_when_out_of_stock' => true,
            'quantity' => 0,
        ]);

        return (string) $created->id;
    }

    private function resolveOrderFromParams(array $params): ?OrderOrder
    {
        if (!empty($params['order_id'])) {
            $byId = OrderOrder::with(['items.product', 'invoices', 'shipments'])->find($params['order_id']);
            if ($byId) {
                return $byId;
            }
        }

        if (!empty($params['order_number'])) {
            $raw = trim((string) $params['order_number']);
            $clean = ltrim($raw, '#');
            $byNumber = OrderOrder::with(['items.product', 'invoices', 'shipments'])
                ->where('order_number', $raw)
                ->orWhere('order_number', $clean)
                ->first();
            if ($byNumber) {
                return $byNumber;
            }
        }

        if (!empty($params['stripe_payment_intent_id'])) {
            return OrderOrder::with(['items.product', 'invoices', 'shipments'])
                ->where('stripe_payment_intent_id', trim((string) $params['stripe_payment_intent_id']))
                ->first();
        }

        return null;
    }

    /**
     * Erstellt idempotent eine Prioritäts-Aufgabe im Operativen Status (/admin/dashboard).
     */
    private function createPriorityDashboardTask(string $title, string $aiPlan, string $priority = 'high'): ManagementTask
    {
        $list = ManagementTaskList::query()
            ->where('is_archived', false)
            ->orderBy('position', 'asc')
            ->first();

        if (!$list) {
            $list = ManagementTaskList::create([
                'name' => 'Operativer Fokus',
                'icon' => 'bolt',
                'color' => 'amber',
                'position' => 1,
                'is_archived' => false,
            ]);
        }

        $existing = ManagementTask::query()
            ->where('task_list_id', $list->id)
            ->where('title', $title)
            ->where('is_completed', false)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ManagementTask::create([
            'task_list_id' => $list->id,
            'title' => $title,
            'is_completed' => false,
            'position' => 1,
            'priority' => $priority,
            'ai_plan' => $aiPlan,
            'is_archived' => false,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function getAlreadyReconciledIds(string $payloadKey): array
    {
        if (!Schema::hasTable('system_logs')) {
            return [];
        }

        $logs = SystemLog::query()
            ->where(function ($q) {
                $q->where('action_id', 'stripe_agent:payout_reconciled')
                    ->orWhere('action_id', 'like', 'stripe_webhook:payout_paid:%');
            })
            ->get(['payload']);

        $ids = [];
        foreach ($logs as $log) {
            $payload = is_array($log->payload) ? $log->payload : [];
            foreach ((array) ($payload[$payloadKey] ?? []) as $id) {
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 7. Prüft, repariert und verwaltet Stripe-Webhook-Endpunkte (Sandbox vs. Stage vs. Live) autonom über /v1/webhook_endpoints.
     */
    public function diagnoseAndManageWebhooks(array $params = [], string $actorName = 'Funkira'): array
    {
        $action = strtolower(trim((string) ($params['action'] ?? 'diagnose')));
        if (!in_array($action, ['diagnose', 'auto_heal', 'update_url', 'disable', 'delete'], true)) {
            return [
                'error' => true,
                'status' => 'error',
                'message' => "Ungültige Aktion '{$action}'. Erlaubt: diagnose, auto_heal, update_url, disable, delete.",
            ];
        }

        if (in_array($action, ['update_url', 'disable', 'delete'], true)) {
            $endpointId = trim((string) ($params['endpoint_id'] ?? ''));
            if ($endpointId === '' || !str_starts_with($endpointId, 'we_')) {
                return [
                    'error' => true,
                    'status' => 'error',
                    'message' => 'Für update_url, disable oder delete ist eine gültige endpoint_id (we_...) erforderlich.',
                ];
            }

            if ($action === 'update_url') {
                $targetUrl = trim((string) ($params['target_url'] ?? ''));
                if (!in_array($targetUrl, self::ALLOWED_WEBHOOK_URLS, true)) {
                    return [
                        'error' => true,
                        'status' => 'error',
                        'message' => 'Ziel-URL nicht in der Seelenfunke-Domain-Whitelist erlaubt.',
                    ];
                }

                $upd = $this->requestStripeApi('POST', "/webhook_endpoints/{$endpointId}", [
                    'url' => $targetUrl,
                    'enabled_events' => self::CANONICAL_WEBHOOK_EVENTS,
                ]);
                if (!$upd['ok']) {
                    return [
                        'error' => true,
                        'status' => 'error',
                        'message' => 'Stripe Webhook-Update fehlgeschlagen: ' . ($upd['error_message'] ?? 'Unbekannt'),
                    ];
                }

                $this->logAudit(
                    'stripe_agent:webhook_updated',
                    'Stripe-Webhook-URL aktualisiert',
                    "{$actorName} hat Webhook {$endpointId} auf {$targetUrl} umgestellt.",
                    'success',
                    ['endpoint_id' => $endpointId, 'target_url' => $targetUrl]
                );

                return [
                    'status' => 'success',
                    'action' => 'update_url',
                    'endpoint_id' => $endpointId,
                    'target_url' => $targetUrl,
                    'message' => "Webhook {$endpointId} wurde erfolgreich auf {$targetUrl} aktualisiert.",
                ];
            }

            if ($action === 'disable') {
                $dis = $this->requestStripeApi('POST', "/webhook_endpoints/{$endpointId}", [
                    'disabled' => 'true',
                ]);
                if (!$dis['ok']) {
                    return [
                        'error' => true,
                        'status' => 'error',
                        'message' => 'Stripe Webhook-Deaktivierung fehlgeschlagen: ' . ($dis['error_message'] ?? 'Unbekannt'),
                    ];
                }

                $this->logAudit(
                    'stripe_agent:webhook_disabled',
                    'Stripe-Webhook deaktiviert',
                    "{$actorName} hat Webhook {$endpointId} deaktiviert.",
                    'success',
                    ['endpoint_id' => $endpointId]
                );

                return [
                    'status' => 'success',
                    'action' => 'disable',
                    'endpoint_id' => $endpointId,
                    'message' => "Webhook {$endpointId} wurde bei Stripe deaktiviert.",
                ];
            }

            $del = $this->requestStripeApi('DELETE', "/webhook_endpoints/{$endpointId}");
            if (!$del['ok']) {
                return [
                    'error' => true,
                    'status' => 'error',
                    'message' => 'Stripe Webhook-Löschung fehlgeschlagen: ' . ($del['error_message'] ?? 'Unbekannt'),
                ];
            }

            $this->logAudit(
                'stripe_agent:webhook_deleted',
                'Fehlerhafter Stripe-Webhook gelöscht',
                "{$actorName} hat den überflüssigen/fehlgeleiteten Webhook {$endpointId} gelöscht.",
                'success',
                ['endpoint_id' => $endpointId]
            );

            return [
                'status' => 'success',
                'action' => 'delete',
                'endpoint_id' => $endpointId,
                'message' => "Webhook {$endpointId} wurde bei Stripe gelöscht.",
            ];
        }

        $listRes = $this->requestStripeApi('GET', '/webhook_endpoints', ['limit' => 30]);
        if (!$listRes['ok']) {
            return [
                'error' => true,
                'status' => 'error',
                'message' => 'Abruf der Stripe-Webhook-Endpunkte fehlgeschlagen: ' . ($listRes['error_message'] ?? 'Unbekannt'),
            ];
        }

        $rawEndpoints = (array) ($listRes['data']['data'] ?? []);
        $endpoints = [];
        $issues = [];
        $healedActions = [];

        $hasActiveStageSandbox = false;
        foreach ($rawEndpoints as $ep) {
            if (!is_array($ep)) {
                continue;
            }
            $epUrl = rtrim(strtolower(trim((string) ($ep['url'] ?? ''))), '/');
            $epLive = (bool) ($ep['livemode'] ?? false);
            $epStatus = strtolower((string) ($ep['status'] ?? 'unknown'));
            if (!$epLive && $epStatus === 'enabled' && $epUrl === strtolower(self::STAGE_WEBHOOK_URL)) {
                $hasActiveStageSandbox = true;
            }
        }

        foreach ($rawEndpoints as $ep) {
            if (!is_array($ep)) {
                continue;
            }

            $epId = (string) ($ep['id'] ?? '');
            $epUrl = trim((string) ($ep['url'] ?? ''));
            $epHost = strtolower((string) parse_url($epUrl, PHP_URL_HOST));
            $epLive = (bool) ($ep['livemode'] ?? false);
            $epStatus = strtolower((string) ($ep['status'] ?? 'unknown'));
            $enabledEvents = array_values(array_filter(array_map('strval', (array) ($ep['enabled_events'] ?? []))));
            $hasWildcard = in_array('*', $enabledEvents, true);
            $missingEvents = $hasWildcard ? [] : array_values(array_diff(self::CANONICAL_WEBHOOK_EVENTS, $enabledEvents));

            $issueCode = null;
            $issueDetail = null;

            if (!$epLive && in_array($epHost, ['mein-seelenfunke.de', 'www.mein-seelenfunke.de'], true) && $epStatus === 'enabled') {
                $issueCode = 'sandbox_pointing_to_live';
                $issueDetail = $hasActiveStageSandbox
                    ? "Sandbox-Endpunkt {$epId} sendet Test-Events auf die Live-Domain ({$epUrl}), obwohl Stage-Webhook aktiv ist."
                    : "Sandbox-Endpunkt {$epId} zeigt auf die Live-Domain ({$epUrl}) statt auf Stage (" . self::STAGE_WEBHOOK_URL . ').';
            } elseif ($epLive && $epHost === 'stage.mein-seelenfunke.de' && $epStatus === 'enabled') {
                $issueCode = 'live_pointing_to_stage';
                $issueDetail = "Kritisch: Echtes Live-Konto sendet Events über {$epId} an die Stage-Domain ({$epUrl}).";
            } elseif ($epStatus === 'enabled' && !empty($missingEvents)) {
                $issueCode = 'missing_canonical_events';
                $issueDetail = "Endpunkt {$epId} abonniert noch nicht alle Standard-Events (" . implode(', ', $missingEvents) . ').';
            }

            if ($issueCode !== null) {
                $issues[] = [
                    'endpoint_id' => $epId,
                    'livemode' => $epLive,
                    'url' => $epUrl,
                    'issue_code' => $issueCode,
                    'detail' => $issueDetail,
                ];
            }

            if ($action === 'auto_heal' && $epId !== '') {
                if ($issueCode === 'sandbox_pointing_to_live' && !$epLive) {
                    if ($hasActiveStageSandbox) {
                        $delRes = $this->requestStripeApi('DELETE', "/webhook_endpoints/{$epId}");
                        if ($delRes['ok']) {
                            $healedActions[] = "Doppelter Sandbox-auf-Live-Webhook {$epId} ({$epUrl}) gelöscht, da Stage-Webhook aktiv ist.";
                            $epStatus = 'deleted';
                        } else {
                            $disRes = $this->requestStripeApi('POST', "/webhook_endpoints/{$epId}", ['disabled' => 'true']);
                            if ($disRes['ok']) {
                                $healedActions[] = "Doppelter Sandbox-auf-Live-Webhook {$epId} ({$epUrl}) deaktiviert.";
                                $epStatus = 'disabled';
                            }
                        }
                    } else {
                        $updRes = $this->requestStripeApi('POST', "/webhook_endpoints/{$epId}", [
                            'url' => self::STAGE_WEBHOOK_URL,
                            'description' => 'Verarbeitet Zahlungseingänge für Bestellungen (Stage Sandbox)',
                            'enabled_events' => self::CANONICAL_WEBHOOK_EVENTS,
                        ]);
                        if ($updRes['ok']) {
                            $healedActions[] = "Sandbox-Webhook {$epId} von {$epUrl} auf " . self::STAGE_WEBHOOK_URL . ' umgestellt.';
                            $epUrl = self::STAGE_WEBHOOK_URL;
                            $missingEvents = [];
                            $hasActiveStageSandbox = true;
                        }
                    }
                } elseif (
                    !$epLive
                    && $epStatus === 'enabled'
                    && rtrim(strtolower($epUrl), '/') === strtolower(self::STAGE_WEBHOOK_URL)
                    && !empty($missingEvents)
                ) {
                    $syncEv = $this->requestStripeApi('POST', "/webhook_endpoints/{$epId}", [
                        'enabled_events' => self::CANONICAL_WEBHOOK_EVENTS,
                    ]);
                    if ($syncEv['ok']) {
                        $healedActions[] = "Stage-Sandbox-Webhook {$epId} um fehlende Events ergänzt (" . implode(', ', $missingEvents) . ').';
                        $missingEvents = [];
                    }
                }
            }

            $endpoints[] = [
                'id' => $epId,
                'url' => $epUrl,
                'status' => $epStatus,
                'livemode' => $epLive,
                'description' => (string) ($ep['description'] ?? ''),
                'missing_events' => $missingEvents,
                'issue_code' => $issueCode,
            ];
        }

        if (!empty($healedActions)) {
            $this->logAudit(
                'stripe_agent:webhook_auto_healed',
                'Stripe-Webhook-Konfiguration autonom geheilt',
                implode(' | ', $healedActions),
                'success',
                ['actor' => $actorName, 'actions' => $healedActions]
            );
        }

        return [
            'status' => 'success',
            'action' => $action,
            'endpoints_count' => count($endpoints),
            'endpoints' => $endpoints,
            'issues_found' => $issues,
            'healed_actions' => $healedActions,
            'message' => !empty($healedActions)
                ? ('Webhook-Heilung erfolgreich: ' . implode(' ', $healedActions))
                : (empty($issues)
                    ? 'Alle Stripe-Webhook-Endpunkte sind sauber konfiguriert.'
                    : (count($issues) . ' Webhook-Abweichung(en) erkannt.')),
        ];
    }

    /**
     * 8. Gleicht verpasste Stripe-Zahlungs- und Konflikt-Events (z. B. nach HTTP-503-Ausfällen) idempotent mit OrderOrder ab.
     */
    public function syncMissedEvents(array $params = [], string $actorName = 'Funkira'): array
    {
        $daysBack = max(1, min(30, (int) ($params['days_back'] ?? 10)));
        $dryRun = filter_var($params['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sinceUnix = Carbon::now()->subDays($daysBack)->timestamp;
        $isLiveEnvNow = class_exists(\App\Services\AI\FunkiraPresenceContext::class)
            && \App\Services\AI\FunkiraPresenceContext::resolveEnvironmentTier() === 'LIVE';

        $scannedEvents = 0;
        $alreadyPaidCount = 0;
        $recoveredOrders = [];
        $openDisputes = [];

        foreach (['checkout.session.completed', 'payment_intent.succeeded', 'charge.dispute.created'] as $eventType) {
            $res = $this->requestStripeApi('GET', '/events', [
                'type' => $eventType,
                'created' => ['gte' => $sinceUnix],
                'limit' => 100,
            ]);

            if (!$res['ok']) {
                return [
                    'error' => true,
                    'status' => 'error',
                    'message' => "Abruf der Stripe-Events ({$eventType}) fehlgeschlagen: " . ($res['error_message'] ?? 'Unbekannt'),
                ];
            }

            $items = (array) ($res['data']['data'] ?? []);
            foreach ($items as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                $scannedEvents++;
                $evLive = (bool) ($ev['livemode'] ?? false);
                if ($isLiveEnvNow && !$evLive) {
                    continue;
                }

                $obj = (array) ($ev['data']['object'] ?? []);
                $meta = (array) ($obj['metadata'] ?? []);

                if ($eventType === 'charge.dispute.created') {
                    $dpStatus = strtolower((string) ($obj['status'] ?? ''));
                    if (in_array($dpStatus, ['needs_response', 'warning_needs_response'], true)) {
                        $openDisputes[] = [
                            'dispute_id' => (string) ($obj['id'] ?? ''),
                            'amount_cents' => (int) ($obj['amount'] ?? 0),
                            'reason' => (string) ($obj['reason'] ?? 'unknown'),
                        ];
                    }
                    continue;
                }

                $metaAppUrl = rtrim(strtolower(trim((string) ($meta['app_url'] ?? ''))), '/');
                $currentAppUrl = rtrim(strtolower(trim((string) config('app.url', ''))), '/');
                if ($metaAppUrl !== '' && $currentAppUrl !== '' && $metaAppUrl !== $currentAppUrl) {
                    continue;
                }

                $orderId = trim((string) ($meta['order_id'] ?? ''));
                $intentId = '';
                if ($eventType === 'checkout.session.completed') {
                    if (strtolower((string) ($obj['payment_status'] ?? '')) !== 'paid') {
                        continue;
                    }
                    $rawPi = $obj['payment_intent'] ?? '';
                    $intentId = is_array($rawPi) ? trim((string) ($rawPi['id'] ?? '')) : trim((string) $rawPi);
                    $receivedCents = (int) ($obj['amount_total'] ?? 0);
                } else {
                    $intentId = trim((string) ($obj['id'] ?? ''));
                    $receivedCents = isset($obj['amount_received']) ? (int) $obj['amount_received'] : (int) ($obj['amount'] ?? 0);
                }

                $currency = strtolower(trim((string) ($obj['currency'] ?? 'eur')));
                if ($currency !== 'eur') {
                    continue;
                }

                $order = null;
                if ($orderId !== '') {
                    $order = OrderOrder::find($orderId);
                }
                if (!$order && $intentId !== '') {
                    $order = OrderOrder::where('stripe_payment_intent_id', $intentId)->first();
                }

                if (!$order) {
                    continue;
                }

                if (in_array($order->payment_status, ['paid', 'refunded'], true)) {
                    $alreadyPaidCount++;
                    continue;
                }

                if (in_array($order->status, ['cancelled', 'refunded'], true)) {
                    continue;
                }

                if ($receivedCents < (int) $order->total_price) {
                    continue;
                }

                if (!$dryRun) {
                    $order->completePayment($intentId !== '' ? $intentId : null);
                    $quoteNumber = trim((string) ($meta['quote_number'] ?? ''));
                    if ($quoteNumber !== '' && $order->payment_status === 'paid') {
                        $quote = OrderQuoteRequest::where('quote_number', $quoteNumber)->first();
                        if (
                            $quote
                            && $quote->status !== 'converted'
                            && empty($quote->converted_order_id)
                            && ((int) $order->total_price + (int) ($order->discount_amount ?? 0)) >= (int) $quote->gross_total
                        ) {
                            $quote->update(['status' => 'converted', 'converted_order_id' => $order->id]);
                        }
                    }
                }

                $recoveredOrders[] = (string) $order->order_number;
            }
        }

        $recoveredOrders = array_values(array_unique($recoveredOrders));

        $this->logAudit(
            'stripe_agent:missed_events_synced',
            'Stripe Event-Nachabgleich durchgeführt',
            "{$actorName} prüfte {$scannedEvents} Events ({$daysBack} Tage): " . count($recoveredOrders) . ' nachgebucht, ' . $alreadyPaidCount . ' bereits verbucht.',
            'success',
            [
                'days_back' => $daysBack,
                'dry_run' => $dryRun,
                'scanned_events' => $scannedEvents,
                'already_paid_orders' => $alreadyPaidCount,
                'recovered_orders' => $recoveredOrders,
                'open_disputes_count' => count($openDisputes),
            ]
        );

        return [
            'status' => 'success',
            'days_back' => $daysBack,
            'dry_run' => $dryRun,
            'scanned_events' => $scannedEvents,
            'already_paid_orders' => $alreadyPaidCount,
            'recovered_orders' => $recoveredOrders,
            'recovered_count' => count($recoveredOrders),
            'open_disputes' => $openDisputes,
            'message' => count($recoveredOrders) > 0
                ? ('Zahlungs-Nachabgleich abgeschlossen: ' . count($recoveredOrders) . ' Bestellung(en) nachgebucht (' . implode(', ', $recoveredOrders) . ').')
                : "Zahlungs-Nachabgleich über {$daysBack} Tage abgeschlossen: 0 verpasste Bestellungen ({$alreadyPaidCount} Zahlungen bereits regulär verbucht).",
        ];
    }

    /**
     * 0-Token E-Mail-Autopilot für offizielle Stripe-Systemmeldungen (@stripe.com):
     * Prüft Webhooks + verpasste Zahlungen rein deterministisch ohne LLM-Kosten und dokumentiert das Ergebnis als 1 gebündeltes Ticket.
     */
    public function runZeroTokenAlertMailAutopilot(\App\Models\Management\Mail\MailMessage $mail): array
    {
        $mailRef = trim((string) ($mail->message_id ?: $mail->id));
        $fingerprint = sha1('stripe_alert_mail:' . $mailRef);

        if (Schema::hasTable('ai_universe_tickets')) {
            $existingTicket = \App\Models\Ai\AiUniverseTicket::query()
                ->where('error_fingerprint', $fingerprint)
                ->first();
            if ($existingTicket) {
                return [
                    'status' => 'already_processed',
                    'ticket_number' => $existingTicket->ticket_number,
                    'ticket_status' => $existingTicket->status,
                ];
            }
        }

        $subj = trim((string) $mail->subject);
        $subjLower = mb_strtolower($subj);
        $bodyPlain = strip_tags((string) ($mail->body_plain ?: $mail->body_html));
        $bodyLower = mb_strtolower($bodyPlain);

        $isSandboxMail = str_contains($subjLower, 'sandbox')
            || str_contains($subjLower, 'testmodus')
            || str_contains($bodyLower, 'testmodus')
            || str_contains($bodyLower, 'sandbox');
        $isDisputeMail = str_contains($subjLower, 'dispute')
            || str_contains($subjLower, 'konfliktfall')
            || str_contains($subjLower, 'rückbuchung')
            || str_contains($subjLower, 'chargeback');

        $webhookRes = $this->diagnoseAndManageWebhooks(
            ['action' => $isSandboxMail ? 'auto_heal' : 'diagnose'],
            'Funkira (0-Token Autopilot)'
        );
        $syncRes = $this->syncMissedEvents(['days_back' => 10], 'Funkira (0-Token Autopilot)');

        $healedActions = (array) ($webhookRes['healed_actions'] ?? []);
        $issuesFound = (array) ($webhookRes['issues_found'] ?? []);
        $recoveredOrders = (array) ($syncRes['recovered_orders'] ?? []);
        $openDisputes = (array) ($syncRes['open_disputes'] ?? []);

        $needsAlinaAttention = $isDisputeMail
            || !empty($openDisputes)
            || (!$isSandboxMail && !empty($issuesFound));

        $targetStatus = $needsAlinaAttention
            ? \App\Models\Ai\AiUniverseTicket::STATUS_WAITING_FOR_ALINA
            : \App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED;

        $summaryLines = [];
        $summaryLines[] = '**0-Token Stripe-Autopilot zu E-Mail:** `' . Str::limit($subj, 110) . '`';
        if (!empty($healedActions)) {
            $summaryLines[] = '• **Webhook-Korrektur:** ' . implode(' ', $healedActions);
        } elseif (!empty($issuesFound)) {
            $summaryLines[] = '• **Webhook-Befund:** ' . implode(' ', array_column($issuesFound, 'detail'));
        } else {
            $summaryLines[] = '• **Webhook-Prüfung:** Alle aktiven Stripe-Endpunkte antworten sauber.';
        }

        $summaryLines[] = empty($recoveredOrders)
            ? '• **Zahlungs-Abgleich (10 Tage):** 0 verpasste Bestellungen (' . ((int) ($syncRes['already_paid_orders'] ?? 0)) . ' Zahlungen bereits regulär verbucht).'
            : '• **Zahlungs-Abgleich (10 Tage):** ' . count($recoveredOrders) . ' verpasste Bestellung(en) idempotent nachgebucht (' . implode(', ', $recoveredOrders) . ').';

        if (!empty($openDisputes)) {
            $summaryLines[] = '• **Offene Konfliktfälle:** ' . count($openDisputes) . ' Dispute(s) benötigen Prüfung.';
        }

        $summaryLines[] = $targetStatus === \App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED
            ? '**Status -> Erledigt:** Grund: Sandbox-Fehlroute und Zahlungsabgleich vollständig autonom bereinigt. Finale Aktion: E-Mail als erledigt protokolliert.'
            : '**Status -> Wartet auf Alina:** Grund: Live-Webhook-Abweichung oder offener Konfliktfall erkannt. Finale Aktion: Freigabe von Alina im Ticket angefordert.';

        $ticketNumber = null;
        if (Schema::hasTable('ai_universe_tickets') && Schema::hasTable('ai_universe_ticket_messages')) {
            $deptName = $isDisputeMail ? 'Bestellungen' : 'System';
            $dept = \App\Models\Ai\AiDepartment::where('name', $deptName)->first()
                ?? \App\Models\Ai\AiDepartment::orderBy('sort_order')->first();
            $funkira = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first();
            $envTier = class_exists(\App\Services\AI\FunkiraPresenceContext::class)
                ? \App\Services\AI\FunkiraPresenceContext::resolveEnvironmentTier()
                : 'LOCAL';

            $ticketNumber = \App\Models\Ai\AiUniverseTicket::generateNextTicketNumber();
            $ticket = \App\Models\Ai\AiUniverseTicket::create([
                'ticket_number' => $ticketNumber,
                'title' => Str::limit('Stripe-Meldung: ' . $subj, 140),
                'status' => $targetStatus,
                'priority' => $needsAlinaAttention ? 'high' : 'normal',
                'ticket_type' => 'system_incident',
                'ai_department_id' => $dept?->id,
                'assigned_agent_id' => $funkira?->id,
                'assigned_to_antigravity' => false,
                'source_channel' => 'system_sentinel',
                'environment_tier' => $envTier,
                'error_fingerprint' => $fingerprint,
                'occurrence_count' => 1,
                'messages_count' => 1,
                'action_payload' => [
                    'mail_id' => $mail->id,
                    'is_sandbox' => $isSandboxMail,
                    'healed_actions' => $healedActions,
                    'recovered_orders' => $recoveredOrders,
                ],
                'last_activity_at' => now(),
                'completed_at' => $targetStatus === \App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED ? now() : null,
            ]);

            \App\Models\Ai\AiUniverseTicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_role' => 'agent',
                'sender_name' => 'Funkira',
                'ai_agent_id' => $funkira?->id,
                'channel' => 'system_sentinel',
                'content' => implode("\n", $summaryLines),
                'executed_tools' => [
                    'stripe_diagnose_and_manage_webhooks',
                    'stripe_sync_missed_events',
                ],
            ]);
        }

        return [
            'status' => $targetStatus === \App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED ? 'auto_resolved' : 'awaiting_alina',
            'ticket_number' => $ticketNumber,
            'healed_actions' => $healedActions,
            'recovered_orders' => $recoveredOrders,
            'needs_alina_attention' => $needsAlinaAttention,
            'summary' => implode(' ', $summaryLines),
        ];
    }

    private function logAudit(
        string $actionId,
        string $title,
        string $message,
        string $status = 'info',
        array $payload = []
    ): void {
        try {
            if (!Schema::hasTable('system_logs')) {
                return;
            }

            SystemLog::create([
                'type' => 'stripe_autonomy',
                'action_id' => $actionId,
                'title' => $title,
                'message' => $message,
                'status' => $status,
                'payload' => $payload,
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('StripeAgentExecutionService Audit-Log Fehler: ' . $e->getMessage());
        }
    }
}

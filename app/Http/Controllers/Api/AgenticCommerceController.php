<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceShopMaintenanceMode;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\System\SystemLog;
use App\Models\System\SystemSetting;
use App\Services\ShippingCalculatorService;
use App\Services\StripeAgentExecutionService;
use App\Services\System\SystemSecurityService;
use App\Services\Tax\TaxSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AgenticCommerceController extends Controller
{
    private const WESTPACK_GIFT_BOX_PRICE_CENTS = 490;
    private const SESSION_TTL_MINUTES = 30;

    /**
     * GET /api/acp/v1/products
     * Liefert kaufbare aktive Artikel, Personalisierungsregeln, optionale schwarze Westpack-Geschenkbox (+490 Cent)
     * und aktive Lieferländer aus LogisticsShippingZone.
     */
    public function listProducts(Request $request, StripeAgentExecutionService $stripeAgentService): JsonResponse
    {
        if ($blocked = $this->guardShopLiveAndAvailable($request, $stripeAgentService)) {
            return $blocked;
        }

        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->filter(function (Product $product): bool {
                if ($product->type === 'digital' || $product->slug === 'geschenkgutschein') {
                    return true;
                }
                if (!$product->track_quantity || $product->continue_selling_when_out_of_stock) {
                    return true;
                }

                return (int) $product->quantity > 0;
            })
            ->values();

        $catalog = $products->map(function (Product $product): array {
            $isDigital = ($product->type === 'digital' || $product->slug === 'geschenkgutschein');
            $confSettings = is_array($product->configurator_settings) ? $product->configurator_settings : [];

            $addons = [];
            if (!$isDigital) {
                $addons[] = [
                    'id' => 'gift_box_westpack',
                    'name' => 'Schwarze Westpack-Geschenkbox',
                    'price_cents' => self::WESTPACK_GIFT_BOX_PRICE_CENTS,
                    'currency' => 'eur',
                ];
            }

            return [
                'id' => (string) $product->id,
                'slug' => (string) $product->slug,
                'sku' => (string) ($product->sku ?? ''),
                'name' => (string) $product->name,
                'type' => $isDigital ? 'digital' : (string) ($product->type ?: 'physical'),
                'unit_price_cents' => (int) $product->gross_price,
                'currency' => 'eur',
                'tax_rate' => (float) $product->tax_rate,
                'quantity_available' => $isDigital || !$product->track_quantity ? null : max(0, (int) $product->quantity),
                'continue_selling_when_out_of_stock' => (bool) $product->continue_selling_when_out_of_stock,
                'origin_label' => !$isDigital ? 'Handveredelt in Deutschland (Manufaktur Gifhorn)' : null,
                'tier_pricing' => is_array($product->tier_pricing) ? $product->tier_pricing : [],
                'personalization_rules' => [
                    'is_personalizable' => (bool) $product->is_personalizable,
                    'max_text_lines' => (int) ($confSettings['max_text_lines'] ?? ($product->is_personalizable ? 3 : 0)),
                    'allowed_fonts' => is_array($confSettings['allowed_fonts'] ?? null)
                        ? $confSettings['allowed_fonts']
                        : ['Playfair Display', 'Montserrat', 'Great Vibes'],
                    'requires_copyright_clearance' => true,
                    'prohibits_third_party_trademarks' => true,
                ],
                'optional_addons' => $addons,
            ];
        })->all();

        return response()->json([
            'protocol' => 'stripe-acp-v1',
            'currency' => 'eur',
            'accepted_payment_token_prefix' => 'spt_',
            'active_shipping_countries' => $stripeAgentService->getActiveShippingCountryCodes(),
            'products' => $catalog,
        ], 200);
    }

    /**
     * POST /api/acp/v1/checkout/sessions
     * Validiert Artikel, Menge und Lieferland gegen aktive LogisticsShippingZone, berechnet Versand & Gesamtbetrag
     * in int Cents und speichert einen kurzlebigen ACP-Checkout-Snapshot (TTL 30 Min).
     */
    public function createCheckoutSession(
        Request $request,
        StripeAgentExecutionService $stripeAgentService,
        ShippingCalculatorService $shippingCalculator
    ): JsonResponse {
        if ($blocked = $this->guardShopLiveAndAvailable($request, $stripeAgentService)) {
            return $blocked;
        }

        if ($panError = $this->rejectRawCardData($request)) {
            return $panError;
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'nullable|string|max:64',
            'items.*.slug' => 'nullable|string|max:190',
            'items.*.quantity' => 'required|integer|min:1|max:500',
            'items.*.gift_box_westpack' => 'nullable|boolean',
            'items.*.personalization' => 'nullable|array',
            'items.*.voucher_amount_cents' => 'nullable|integer|min:500|max:100000',
            'buyer' => 'required|array',
            'buyer.email' => 'required|email|max:190',
            'buyer.first_name' => 'required|string|max:120',
            'buyer.last_name' => 'required|string|max:120',
            'buyer.company' => 'nullable|string|max:150',
            'shipping_address' => 'required|array',
            'shipping_address.country' => 'required|string|size:2',
            'shipping_address.address' => 'nullable|string|max:190',
            'shipping_address.postal_code' => 'nullable|string|max:32',
            'shipping_address.city' => 'nullable|string|max:120',
            'copyright_cleared' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'validation_failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $countryCode = strtoupper(trim((string) $validated['shipping_address']['country']));

        if (!$stripeAgentService->isCountrySupportedByActiveZone($countryCode)) {
            return response()->json([
                'status' => 'error',
                'code' => 'shipping_country_not_supported',
                'message' => "Lieferungen nach '{$countryCode}' sind aktuell in keiner aktiven Versandzone freigeschaltet.",
                'active_shipping_countries' => $stripeAgentService->getActiveShippingCountryCodes(),
            ], 422);
        }

        $isSmallBusiness = app(TaxSettingService::class)->isCurrentSmallBusiness();
        $defaultTaxRate = $isSmallBusiness ? 0.0 : (float) $stripeAgentService->getSettingValue('default_tax_rate', 19.0);

        $snapshotItems = [];
        $virtualShippingItems = [];
        $totalWeightKg = 0.0;
        $hasPhysicalItems = false;

        foreach ($validated['items'] as $index => $rawItem) {
            $product = null;
            if (!empty($rawItem['product_id'])) {
                $product = Product::where('id', $rawItem['product_id'])->where('status', 'active')->first();
            } elseif (!empty($rawItem['slug'])) {
                $product = Product::where('slug', $rawItem['slug'])->where('status', 'active')->first();
            }

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'product_not_found',
                    'message' => "Produkt an Position {$index} existiert nicht oder ist nicht aktiv.",
                ], 422);
            }

            $qty = max(1, (int) $rawItem['quantity']);
            $isDigital = ($product->type === 'digital' || $product->slug === 'geschenkgutschein');

            if (!$isDigital) {
                $hasPhysicalItems = true;
                if ($product->track_quantity && !$product->continue_selling_when_out_of_stock && (int) $product->quantity < $qty) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'insufficient_stock',
                        'message' => "Artikel '{$product->name}' ist nicht in der angefragten Menge ({$qty}) auf Lager.",
                    ], 422);
                }
            }

            $personalization = is_array($rawItem['personalization'] ?? null) ? $rawItem['personalization'] : [];
            if (!empty($personalization) && array_key_exists('copyright_cleared', $validated) && $validated['copyright_cleared'] === false) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'copyright_clearance_required',
                    'message' => 'Für personalisierte Gravuren ist die Urheber- und Markenrechts-Freistellung erforderlich.',
                ], 422);
            }

            $wantsGiftBox = !$isDigital && (
                filter_var($rawItem['gift_box_westpack'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || filter_var($personalization['gift_box_westpack'] ?? false, FILTER_VALIDATE_BOOLEAN)
            );

            $baseUnitPriceCents = (int) $product->gross_price;
            $configPayload = [];

            if ($product->slug === 'geschenkgutschein' && !empty($rawItem['voucher_amount_cents'])) {
                $baseUnitPriceCents = max(500, min(100000, (int) $rawItem['voucher_amount_cents']));
                $configPayload['is_gift_voucher'] = true;
                $configPayload['amount_cents'] = $baseUnitPriceCents;
                $configPayload['delivery_method'] = 'email';
            }

            if (!empty($personalization['text_lines']) && is_array($personalization['text_lines'])) {
                $cleanLines = array_slice(
                    array_map(fn ($l) => mb_substr(trim((string) $l), 0, 80), $personalization['text_lines']),
                    0,
                    5
                );
                $configPayload['text_lines'] = $cleanLines;
            }
            if (!empty($personalization['font']) && is_string($personalization['font'])) {
                $configPayload['font'] = mb_substr(trim($personalization['font']), 0, 60);
            }

            if ($wantsGiftBox) {
                $baseUnitPriceCents += self::WESTPACK_GIFT_BOX_PRICE_CENTS;
                $configPayload['selected_addons'] = ['gift_box_westpack'];
                $configPayload['addons_summary'] = [
                    [
                        'id' => 'gift_box_westpack',
                        'name' => 'Schwarze Westpack-Geschenkbox',
                        'price_cents' => self::WESTPACK_GIFT_BOX_PRICE_CENTS,
                    ],
                ];
            }

            $itemTaxRate = $isSmallBusiness ? 0.0 : (float) $product->tax_rate;
            $lineTotalCents = $baseUnitPriceCents * $qty;

            $snapshotItems[] = [
                'product_id' => (string) $product->id,
                'product_name' => (string) $product->name,
                'quantity' => $qty,
                'unit_price_cents' => $baseUnitPriceCents,
                'total_price_cents' => $lineTotalCents,
                'tax_rate' => $itemTaxRate,
                'configuration' => $configPayload,
            ];

            $virtualShippingItems[] = (object) [
                'product' => $product,
                'configuration' => $configPayload,
            ];

            if (!$isDigital) {
                $totalWeightKg += ((max(1, (int) ($product->weight ?: 100)) * $qty) / 1000);
            }
        }

        if ($hasPhysicalItems) {
            if (
                trim((string) ($validated['shipping_address']['address'] ?? '')) === ''
                || trim((string) ($validated['shipping_address']['postal_code'] ?? '')) === ''
                || trim((string) ($validated['shipping_address']['city'] ?? '')) === ''
            ) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'incomplete_shipping_address',
                    'message' => 'Für physische Manufaktur-Artikel sind Straße, PLZ und Ort in shipping_address erforderlich.',
                ], 422);
            }
        }

        $subtotalCents = 0;
        $taxCents = 0;
        foreach ($snapshotItems as $sItem) {
            $subtotalCents += (int) $sItem['total_price_cents'];
            if (!$isSmallBusiness && $sItem['tax_rate'] > 0) {
                $divisor = 1 + (((float) $sItem['tax_rate']) / 100);
                $taxCents += (int) round($sItem['total_price_cents'] - ($sItem['total_price_cents'] / $divisor));
            }
        }

        $shippingResult = $shippingCalculator->calculateShippingCost(
            $virtualShippingItems,
            $subtotalCents,
            max(0.0, $totalWeightKg),
            $countryCode
        );
        $shippingCents = max(0, (int) ($shippingResult['cost'] ?? 0));

        if (!$isSmallBusiness && $shippingCents > 0 && $defaultTaxRate > 0) {
            $shipDivisor = 1 + ($defaultTaxRate / 100);
            $taxCents += (int) round($shippingCents - ($shippingCents / $shipDivisor));
        }

        $amountTotalCents = $subtotalCents + $shippingCents;
        $sessionId = 'acp_sess_' . Str::lower(Str::random(24));
        $expiresAt = now()->addMinutes(self::SESSION_TTL_MINUTES);

        $addressSnapshot = [
            'first_name' => trim((string) $validated['buyer']['first_name']),
            'last_name' => trim((string) $validated['buyer']['last_name']),
            'company' => trim((string) ($validated['buyer']['company'] ?? '')),
            'address' => trim((string) ($validated['shipping_address']['address'] ?? '')),
            'postal_code' => trim((string) ($validated['shipping_address']['postal_code'] ?? '')),
            'city' => trim((string) ($validated['shipping_address']['city'] ?? '')),
            'country' => $countryCode,
        ];

        $snapshot = [
            'session_id' => $sessionId,
            'buyer_email' => strtolower(trim((string) $validated['buyer']['email'])),
            'address' => $addressSnapshot,
            'items' => $snapshotItems,
            'subtotal_cents' => $subtotalCents,
            'shipping_cents' => $shippingCents,
            'tax_cents' => $taxCents,
            'amount_total_cents' => $amountTotalCents,
            'is_small_business' => $isSmallBusiness,
            'shipping_country' => $countryCode,
            'currency' => 'eur',
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        Cache::put('acp_checkout_session:' . $sessionId, $snapshot, $expiresAt);

        return response()->json([
            'session_id' => $sessionId,
            'status' => 'open',
            'currency' => 'eur',
            'subtotal_cents' => $subtotalCents,
            'shipping_cents' => $shippingCents,
            'tax_cents' => $taxCents,
            'amount_total_cents' => $amountTotalCents,
            'shipping_country' => $countryCode,
            'expires_at' => $expiresAt->toIso8601String(),
            'accepted_payment_token_prefix' => 'spt_',
        ], 201);
    }

    /**
     * POST /api/acp/v1/checkout/sessions/{sessionId}/complete
     * Akzeptiert ausschließlich ein Stripe Shared Payment Token (spt_*), lehnt rohe Kartendaten strikt mit 422 ab,
     * bestätigt den PaymentIntent und legt idempotent OrderOrder + OrderOrderItem an.
     */
    public function completeCheckoutSession(
        Request $request,
        string $sessionId,
        StripeAgentExecutionService $stripeAgentService
    ): JsonResponse {
        if ($blocked = $this->guardShopLiveAndAvailable($request, $stripeAgentService)) {
            return $blocked;
        }

        if ($panError = $this->rejectRawCardData($request)) {
            return $panError;
        }

        $rawToken = $request->input('shared_payment_token');
        if (!is_string($rawToken) || !preg_match('/^spt_[A-Za-z0-9_]{6,120}$/', trim($rawToken))) {
            SystemSecurityService::logEvent(
                'security:acp_invalid_payment_token',
                'Ungültiges ACP-Zahlungstoken abgewiesen',
                "Aufruf an /api/acp/v1/checkout/sessions/{$sessionId}/complete ohne gültiges spt_-Token abgelehnt.",
                'warning'
            );

            return response()->json([
                'status' => 'error',
                'code' => 'invalid_shared_payment_token',
                'message' => 'Es werden ausschließlich händlergebundene Stripe Shared Payment Tokens mit Präfix spt_ akzeptiert.',
            ], 422);
        }

        $sharedPaymentToken = trim($rawToken);

        // Idempotenz bei erneutem Aufruf derselben Session
        $alreadyCompleted = Cache::get('acp_checkout_completed:' . $sessionId);
        if (is_array($alreadyCompleted) && !empty($alreadyCompleted['order_number'])) {
            return response()->json($alreadyCompleted, 200);
        }

        $snapshot = Cache::get('acp_checkout_session:' . $sessionId);
        if (!is_array($snapshot) || empty($snapshot['amount_total_cents'])) {
            return response()->json([
                'status' => 'error',
                'code' => 'acp_session_not_found_or_expired',
                'message' => 'Die ACP-Checkout-Session existiert nicht oder ist abgelaufen.',
            ], 404);
        }

        $confirmRes = $stripeAgentService->confirmSharedPaymentTokenIntent([
            'shared_payment_token' => $sharedPaymentToken,
            'amount_cents' => (int) $snapshot['amount_total_cents'],
            'session_id' => $sessionId,
            'shipping_country' => (string) ($snapshot['shipping_country'] ?? 'DE'),
        ]);

        if (!$confirmRes['ok']) {
            return response()->json([
                'status' => 'error',
                'code' => $confirmRes['code'] ?? 'payment_failed',
                'message' => $confirmRes['message'] ?? 'Zahlungsbestätigung fehlgeschlagen.',
            ], 402);
        }

        $paymentIntentId = (string) $confirmRes['payment_intent_id'];

        $order = DB::transaction(function () use ($snapshot, $paymentIntentId, $sessionId) {
            $existingOrder = OrderOrder::where('stripe_payment_intent_id', $paymentIntentId)->first();
            if ($existingOrder) {
                return $existingOrder->load('items.product');
            }

            $email = (string) $snapshot['buyer_email'];
            $addr = (array) $snapshot['address'];

            $customer = Customer::withTrashed()
                ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->first();

            if ($customer && $customer->trashed()) {
                $customer->restore();
            }

            if (!$customer) {
                $customer = Customer::create([
                    'email' => $email,
                    'first_name' => (string) ($addr['first_name'] ?? 'Kunde'),
                    'last_name' => (string) ($addr['last_name'] ?? 'ACP'),
                    'password' => bcrypt(Str::random(32)),
                    'needs_password_change' => false,
                ]);
            }

            $createdOrder = OrderOrder::create([
                'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
                'customer_id' => $customer->id,
                'email' => $email,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => 'stripe',
                'stripe_payment_intent_id' => $paymentIntentId,
                'billing_address' => $addr,
                'shipping_address' => $addr,
                'subtotal_price' => (int) $snapshot['subtotal_cents'],
                'tax_amount' => (int) $snapshot['tax_cents'],
                'shipping_price' => (int) $snapshot['shipping_cents'],
                'total_price' => (int) $snapshot['amount_total_cents'],
                'is_small_business' => (bool) ($snapshot['is_small_business'] ?? false),
                'utm_source_first' => 'agentic_commerce',
                'utm_source_last' => 'agentic_commerce',
                'utm_medium_last' => 'acp_v1',
                'notes' => 'Agentic Commerce Checkout (' . $sessionId . ')',
            ]);

            foreach ((array) $snapshot['items'] as $sItem) {
                $conf = is_array($sItem['configuration'] ?? null) ? $sItem['configuration'] : [];
                $fingerprint = !empty($conf) ? hash('sha256', json_encode($conf)) : null;

                $createdOrder->items()->create([
                    'product_id' => $sItem['product_id'],
                    'product_name' => $sItem['product_name'],
                    'quantity' => (int) $sItem['quantity'],
                    'unit_price' => (int) $sItem['unit_price_cents'],
                    'tax_rate' => (float) $sItem['tax_rate'],
                    'total_price' => (int) $sItem['total_price_cents'],
                    'configuration' => $conf,
                    'config_fingerprint' => $fingerprint,
                ]);
            }

            $createdOrder->load('items.product');
            $createdOrder->completePayment($paymentIntentId);

            return $createdOrder->fresh(['items.product']);
        });

        $responsePayload = [
            'session_id' => $sessionId,
            'status' => 'paid',
            'payment_status' => (string) $order->payment_status,
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'production_focus' => $order->getProductionFocusLabel(),
            'payment_intent_id' => $paymentIntentId,
            'amount_total_cents' => (int) $order->total_price,
            'currency' => 'eur',
        ];

        Cache::put('acp_checkout_completed:' . $sessionId, $responsePayload, now()->addHours(24));
        Cache::forget('acp_checkout_session:' . $sessionId);

        if (Schema::hasTable('system_logs')) {
            SystemLog::create([
                'type' => 'stripe_autonomy',
                'action_id' => 'acp:order_completed',
                'title' => 'Agentic Commerce Bestellung bezahlt',
                'message' => "ACP-Auftrag {$order->getProductionFocusLabel()} ({$order->order_number}) über Shared Payment Token abgeschlossen.",
                'status' => 'success',
                'payload' => $responsePayload,
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        return response()->json($responsePayload, 200);
    }

    /**
     * Prüft zu Beginn jedes ACP-Aufrufs strikt den Wartungsmodus (maintenance_mode / shop_maintenance_mode)
     * sowie den Hauptschalter Live gehen (shop_is_live). Bei Sperre -> HTTP 503 JSON.
     */
    private function guardShopLiveAndAvailable(
        Request $request,
        StripeAgentExecutionService $stripeAgentService
    ): ?JsonResponse {
        $maintenanceActive = filter_var($stripeAgentService->getSettingValue('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN)
            || filter_var($stripeAgentService->getSettingValue('shop_maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);

        $rawShopIsLive = null;
        if (Schema::hasTable('shop_settings')) {
            $rawShopIsLive = SystemSetting::query()->where('key', 'shop_is_live')->value('value');
        }
        if ($rawShopIsLive === null || $rawShopIsLive === '') {
            $rawShopIsLive = function_exists('shop_setting') ? shop_setting('shop_is_live', null) : null;
        }

        $isShopLive = ($rawShopIsLive !== null && $rawShopIsLive !== '')
            ? filter_var($rawShopIsLive, FILTER_VALIDATE_BOOLEAN)
            : EnforceShopMaintenanceMode::isShopLive($request);

        if ($maintenanceActive || !$isShopLive) {
            return response()->json([
                'status' => 'unavailable',
                'code' => 'shop_not_live_or_maintenance',
                'message' => 'Agentic Commerce ist während des Wartungsmodus oder vor Freischaltung des Live-Verkaufs pausiert.',
            ], 503, [
                'Retry-After' => '3600',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        return null;
    }

    /**
     * PCI-DSS SAQ-A Schutz: Lehnt jegliche rohe Kreditkartendaten (PAN, CVC/CVV, Ablaufdatum) sofort mit HTTP 422 ab.
     */
    private function rejectRawCardData(Request $request): ?JsonResponse
    {
        $forbiddenKeys = [
            'card',
            'card_number',
            'pan',
            'primary_account_number',
            'cvc',
            'cvv',
            'security_code',
            'exp_month',
            'exp_year',
            'expiry',
        ];

        $payload = $request->all();
        $foundForbidden = $this->containsForbiddenCardKeysOrPan($payload, $forbiddenKeys);

        if ($foundForbidden) {
            SystemSecurityService::logEvent(
                'security:acp_raw_pan_rejected',
                'Rohe Kreditkartendaten an ACP-Endpunkt abgewiesen',
                'Ein Client versuchte rohe Kartendaten (PAN/CVC) an /api/acp/v1/* zu senden (PCI-DSS SAQ-A Schutz).',
                'error'
            );

            return response()->json([
                'status' => 'error',
                'code' => 'raw_card_data_forbidden',
                'message' => 'Rohe Kreditkartendaten (PAN/CVC) sind strikt untersagt. Übergeben Sie ausschließlich ein Stripe Shared Payment Token (spt_*).',
            ], 422);
        }

        return null;
    }

    private function containsForbiddenCardKeysOrPan(array $data, array $forbiddenKeys): bool
    {
        foreach ($data as $key => $value) {
            $normKey = strtolower(trim((string) $key));
            if (in_array($normKey, $forbiddenKeys, true)) {
                return true;
            }

            if (is_array($value)) {
                if ($this->containsForbiddenCardKeysOrPan($value, $forbiddenKeys)) {
                    return true;
                }
            } elseif (is_string($value) || is_numeric($value)) {
                if (in_array($normKey, ['shared_payment_token', 'payment_method', 'token', 'number'], true)) {
                    $digitsOnly = preg_replace('/[\s\-]+/', '', (string) $value);
                    if ($digitsOnly !== null && preg_match('/^\d{13,19}$/', $digitsOnly)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

<?php

namespace App\Livewire\Shop\Order\OrderCheckout\Traits;

use App\Models\Customer\Customer;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\System\SystemLog;

trait HandlesOrderCreation
{
    /**
     * Wird von Stripe Express Checkout (JS) aufgerufen, BEVOR das Wallet öffnet.
     * So verhindern wir, dass der Kunde Apple Pay nutzt, obwohl er z.B. die AGB nicht akzeptiert hat.
     */
    public function validateCheckoutData()
    {
        // Wir validieren hier NUR die rechtlichen Checkboxen.
        // Die Adresse kommt später direkt von Apple/Google Pay.
        $rules = [
            'terms_accepted' => 'accepted',
            'privacy_accepted' => 'accepted',
            'country' => 'required', // Land brauchen wir für die Versandkostenberechnung
        ];
        if (method_exists($this, 'hasDigitalOrIngameItems') && $this->hasDigitalOrIngameItems()) {
            $rules['digital_waiver_accepted'] = 'accepted';
        }
        $this->validate($rules, [
            'terms_accepted.accepted' => 'Bitte bestätige die AGB.',
            'privacy_accepted.accepted' => 'Bitte bestätige den Datenschutz.',
            'digital_waiver_accepted.accepted' => 'Bitte bestätige die sofortige Ausführung digitaler Inhalte (§ 356 Abs. 5 BGB).',
        ]);

        return true;
    }

    /**
     * Wird vom Frontend aufgerufen, BEVOR Stripe bestätigt wird.
     */
    public function validateAndCreateOrder()
    {
        if (shop_setting('maintenance_mode', false) && !Auth::guard('admin')->check()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cart' => 'Der Shop befindet sich aktuell im Wartungsmodus. Bestellungen sind vorübergehend pausiert.'
            ]);
        }

        if (!$this->has_separate_shipping) {
            $this->shipping_country = $this->country;
        }

        $this->validate();

        if (method_exists($this, 'syncPaymentIntentAmount')) {
            $this->syncPaymentIntentAmount();
        } elseif (method_exists($this, 'createPaymentIntent')) {
            $this->createPaymentIntent();
        }

        try {
            $orderId = $this->createOrderInDb();
            return $orderId;
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // Re-throw ValidationException to show directly in UI
        } catch (\Exception $e) {
            SystemLog::create([
                'type' => 'error',
                'status' => 'failed',
                'action_id' => 'checkout_order_creation',
                'title' => 'Kritischer Fehler bei der Order-Erstellung',
                'message' => 'Kritischer Fehler bei der Order-Erstellung: ' . $e->getMessage(),
                'payload' => json_encode([
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'intent' => $this->currentPaymentIntentId ?? null,
                    'email' => $this->email ?? null
                ])
            ]);

            throw new \Exception("Beim Erstellen deiner Bestellung ist ein Fehler aufgetreten. Bitte versuche es erneut oder kontaktiere den Support.");
        }
    }

    /**
     * Entfernt einen ungültigen Gutschein außerhalb einer DB-Transaktion (damit das Update nicht durch ValidationException zurückgerollt wird)
     * und aktualisiert sofort den Stripe PaymentIntent.
     */
    private function invalidateCartCoupon($cart, string $message): void
    {
        $cart->update(['coupon_code' => null]);
        if (method_exists($this, 'createPaymentIntent')) {
            $this->createPaymentIntent();
        }
        $this->dispatch('checkout-updated');

        throw \Illuminate\Validation\ValidationException::withMessages([
            'coupon' => $message
        ]);
    }

    /**
     * Private Helper: Die eigentliche Order-Erstellung inkl. User-Logik
     */
    private function createOrderInDb()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        if (!$cart || $cart->items()->count() === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cart' => 'Dein Warenkorb ist leer. Bitte füge vor dem Abschluss Artikel hinzu.'
            ]);
        }

        // [SICHERHEIT] Artikel-Verfügbarkeit & Lagerbestand vor Order-Erstellung prüfen (außerhalb der Transaktion)
        if (!session()->has('checkout_from_quote_id')) {
            foreach ($cart->items as $item) {
                $product = $item->product;
                if (!$product || $product->status !== 'active') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'cart' => 'Ein Artikel in deinem Warenkorb ist derzeit nicht mehr verfügbar.'
                    ]);
                }
                if ($product->type === 'physical' && $product->track_quantity && !$product->continue_selling_when_out_of_stock) {
                    $variantId = is_array($item->configuration) ? ($item->configuration['variant_id'] ?? null) : null;
                    $availableStock = $cartService->resolveAvailableStock($product, $variantId);
                    if ($availableStock < $item->quantity) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'cart' => "Der Artikel '{$product->name}' ist leider nicht mehr in der gewünschten Menge auf Lager."
                        ]);
                    }
                }
            }
        }

        // [SICHERHEITSCHECK FÜR GUTSCHEINE] Außerhalb der Transaktion prüfen, damit das Entfernen eines ungültigen Gutscheins nicht durch Rollback rückgängig gemacht wird
        if ($cart->coupon_code) {
            $coupon = \App\Models\Marketing\MarketingGiftVoucher::where('code', $cart->coupon_code)->first();
            if (!$coupon) {
                $coupon = MarketingVoucher::where('code', $cart->coupon_code)->first();
            }

            // Wenn Gutschein nicht existiert oder nicht mehr gültig ist (z.B. Limit erreicht)
            if (!$coupon || !$coupon->isValid()) {
                $this->invalidateCartCoupon($cart, 'Der verwendete Gutschein ist leider nicht mehr gültig oder aufgebraucht und wurde entfernt.');
            }

            // Referral-Sicherheitscheck gegen die eingegebene Checkout-E-Mail (Eigenwerbung & Neukundenschutz)
            $authCustomer = Auth::guard('customer')->check() ? Auth::guard('customer')->user() : null;
            $checkoutCustomerId = $authCustomer ? $authCustomer->id : null;
            $referralCheck = app(\App\Services\ReferralService::class)->validateReferralCode($cart->coupon_code, $this->email, $checkoutCustomerId);
            if ($referralCheck['is_referral'] && !$referralCheck['valid']) {
                $this->invalidateCartCoupon($cart, $referralCheck['message'] . ' Der Gutschein wurde aus deiner Bestellübersicht entfernt.');
            }

            // [SICHERHEIT] Persönliche Belohnungsgutscheine strikt an das verifizierte Kundenkonto UND die Checkout-E-Mail binden
            if ($coupon instanceof \App\Models\Marketing\MarketingGiftVoucher && !empty($coupon->recipient_email) && str_starts_with($coupon->code, 'DANKE-')) {
                $recipientTarget = strtolower(trim($coupon->recipient_email));
                if (
                    !$authCustomer
                    || !$authCustomer->hasVerifiedEmail()
                    || strtolower(trim((string) $authCustomer->email)) !== $recipientTarget
                    || strtolower(trim((string) $this->email)) !== $recipientTarget
                ) {
                    $this->invalidateCartCoupon($cart, 'Dieser persönliche Belohnungsgutschein ist an ein verifiziertes Kundenkonto gebunden und kann nur nach Anmeldung mit dieser E-Mail-Adresse eingelöst werden.');
                }
            }

            // [SICHERHEIT] Creator 100% Gratis-Muster (PR-Sample) strikt an das Creator-Konto & die hinterlegte Creator-E-Mail binden
            if ($coupon instanceof MarketingVoucher && $coupon->isInfluencerSample()) {
                if (!$cartService->isValidInfluencerSampleRedemption($coupon, (string) $this->email)) {
                    $this->invalidateCartCoupon($cart, 'Dieser Gratis-Muster-Code ist persönlich an dein Creator-Konto und deine Creator-E-Mail-Adresse gebunden.');
                }
            } elseif ($coupon instanceof MarketingVoucher && !empty($coupon->recipient_email)) {
                // [SICHERHEIT] Persönliche Belohnungsgutscheine (z.B. Gamification Meilensteine) strikt an das verifizierte Kundenkonto UND die Checkout-E-Mail binden
                $recipientTarget = strtolower(trim($coupon->recipient_email));
                $isFunkiCoupon = str_starts_with(strtoupper((string) $coupon->code), 'FUNKI-');
                $ownsFunkiCoupon = $isFunkiCoupon && $authCustomer && (
                    (!empty($coupon->customer_id) && (string) $coupon->customer_id === (string) $authCustomer->id)
                    || in_array($coupon->code, (array) (\App\Models\Customer\CustomerGamification::where('customer_id', $authCustomer->id)->where('is_active', true)->value('unlocked_coupons') ?? []), true)
                );
                if ($isFunkiCoupon && !empty($coupon->funki_game_id)) {
                    if (!$authCustomer || (string) ($authCustomer->profile?->funki_active_game ?? '') !== (string) $coupon->funki_game_id) {
                        $ownsFunkiCoupon = false;
                    }
                }

                if (
                    !$authCustomer
                    || (!$authCustomer->hasVerifiedEmail() && !$ownsFunkiCoupon)
                    || ($isFunkiCoupon && !$ownsFunkiCoupon)
                    || strtolower(trim((string) $authCustomer->email)) !== $recipientTarget
                    || strtolower(trim((string) $this->email)) !== $recipientTarget
                ) {
                    $this->invalidateCartCoupon($cart, 'Dieser persönliche Belohnungsgutschein ist an ein verifiziertes Kundenkonto gebunden und kann nur nach Anmeldung mit dieser E-Mail-Adresse eingelöst werden.');
                }
            }

            // [SICHERHEIT] Eigenbestellungs-Sperre für Influencer-Rabattcodes (auch bei Gast-Checkout mit Creator-E-Mail)
            if ($coupon instanceof MarketingVoucher && !empty($coupon->influencer_id)) {
                if ($cartService->isInfluencerSelfRedemption($coupon, (string) $this->email)) {
                    $this->invalidateCartCoupon($cart, 'Eigenbestellungen mit dem eigenen Creator-Rabattcode sind nicht zulässig. Der Gutschein wurde entfernt.');
                }
            }
        }

        return DB::transaction(function () use ($cartService, $cart) {
            $cart->refresh();

            $targetCountry = $this->has_separate_shipping ? $this->shipping_country : $this->country;
            $totals = $cartService->calculateTotals($cart, $targetCountry);

            if (($totals['subtotal_gross'] ?? 0) <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cart' => 'Ungültiger Warenwert. Bitte prüfe deinen Warenkorb.'
                ]);
            }

            $finalIntentId = $this->currentPaymentIntentId;

            if (empty($finalIntentId) && !empty($this->clientSecret)) {
                $parts = explode('_secret_', $this->clientSecret);
                $finalIntentId = $parts[0] ?? null;
            }

            if (($totals['total'] ?? 0) <= 0) {
                $finalIntentId = null;
                $this->currentPaymentIntentId = null;
                $this->clientSecret = null;
            }

            $customer = null;

            if (Auth::guard('customer')->check()) {
                $customer = Auth::guard('customer')->user();
                if ($customer && !Customer::where('id', $customer->id)->exists()) {
                    $customer = null;
                    Auth::guard('customer')->logout();
                }
            }

            if (!$customer) {
                $customer = Customer::where('email', $this->email)->first();
            }

            if (!$customer) {
                $tempPassword = Str::random(32);
                $isExistingEmployee = \App\Models\Employee\Employee::where('email', $this->email)->exists();
                $hasProductDigitalDocuments = $cart && $cart->items && $cart->items->contains(
                    fn ($ci) => $ci->product && method_exists($ci->product, 'hasDigitalDocuments') && $ci->product->hasDigitalDocuments()
                );
                $wantsAccount = !$isExistingEmployee && (bool) ($this->create_account || $this->hasDigitalOrIngameItems($cart) || $hasProductDigitalDocuments);
                $customer = Customer::create([
                    'email' => $this->email,
                    'first_name' => $this->first_name,
                    'last_name' => $this->last_name,
                    'password' => bcrypt($tempPassword),
                    'needs_password_change' => $wantsAccount,
                    'temporary_password' => null,
                ]);

                // Split street and house number
                $streetVal = trim($this->address);
                $houseNumVal = '';
                if (preg_match('#^(.*?)\s*(\d+[a-zA-Z]?(?:\s*[-/]\s*\d+[a-zA-Z]?)?)$#', $streetVal, $matches)) {
                    $streetVal = trim($matches[1]);
                    $houseNumVal = trim($matches[2]);
                }

                if ($customer->profile) {
                    $customer->profile->update([
                        'street' => $streetVal,
                        'house_number' => $houseNumVal,
                        'city' => $this->city,
                        'postal' => $this->postal_code,
                        'country' => $this->country,
                        'email_verified_at' => null,
                    ]);
                } else {
                    $customer->profile()->create([
                        'street' => $streetVal,
                        'house_number' => $houseNumVal,
                        'city' => $this->city,
                        'postal' => $this->postal_code,
                        'country' => $this->country,
                        'email_verified_at' => null,
                    ]);
                }
            }

            $customerId = $customer->id;

            $shipping_data = $this->has_separate_shipping ? [
                'first_name' => $this->shipping_first_name,
                'last_name'  => $this->shipping_last_name,
                'company'    => $this->shipping_company,
                'address'    => $this->shipping_address,
                'postal_code'=> $this->shipping_postal_code,
                'city'       => $this->shipping_city,
                'country'    => $this->shipping_country,
            ] : [
                'first_name' => $this->first_name,
                'last_name'  => $this->last_name,
                'company'    => $this->company,
                'address'    => $this->address,
                'postal_code'=> $this->postal_code,
                'city'       => $this->city,
                'country'    => $this->country,
            ];

            $isSmallBusiness = app(\App\Services\Tax\TaxSettingService::class)->isCurrentSmallBusiness();

            // DATEN FÜR DIE BESTELLUNG ZUSAMMENSTELLEN
            $orderData = [
                'customer_id' => $customerId,
                'email' => $this->email,
                'status' => 'pending',
                'is_express' => $cart->is_express,
                'payment_status' => 'unpaid',
                'payment_method' => 'stripe',
                'billing_address' => [
                    'first_name' => $this->first_name,
                    'last_name' => $this->last_name,
                    'company' => $this->company,
                    'address' => $this->address,
                    'postal_code' => $this->postal_code,
                    'city' => $this->city,
                    'country' => $this->country,
                    'digital_waiver_accepted' => !empty($this->digital_waiver_accepted),
                    'digital_waiver_accepted_at' => !empty($this->digital_waiver_accepted) ? now()->toIso8601String() : null,
                ],
                'shipping_address' => $shipping_data,
                'volume_discount' => $totals['volume_discount'] ?? 0,
                'coupon_code' => $totals['coupon_code'] ?? null,
                'discount_amount' => $totals['discount_amount'] ?? 0,
                'subtotal_price' => $totals['subtotal_gross'],
                'tax_amount' => $totals['tax'],
                'is_small_business' => $isSmallBusiness,
                'shipping_price' => $totals['shipping'],
                'express_price' => $totals['express'] ?? 0,
                'total_price' => $totals['total'],
            ];

            // UTM-, Referral- und Influencer-Tracking an der Bestellung festhalten
            $referrer = session('seelenfunke_referrer') ?: request()->cookie('seelenfunke_referrer');
            if ($referrer) {
                $orderData['utm_campaign_last'] = 'ref:' . substr($referrer, 0, 50);
            }
            $influencerId = session('seelenfunke_influencer_id') ?: request()->cookie('seelenfunke_influencer_id');
            $trackingLinkId = session('seelenfunke_tracking_link_id') ?: request()->cookie('seelenfunke_tracking_link_id');
            if ($influencerId && \Illuminate\Support\Facades\Schema::hasTable('marketing_influencers')) {
                $inf = \App\Models\Marketing\MarketingInfluencer::with('employee')->find($influencerId);
                $loggedInEmpId = \Illuminate\Support\Facades\Auth::guard('employee')->id();
                $buyerEmailLower = strtolower(trim((string) $this->email));
                $infEmailLower = strtolower(trim((string) ($inf?->employee?->email ?? '')));
                $infPaypalLower = strtolower(trim((string) ($inf?->paypal_email ?? '')));
                $isSelfCheckout = $inf && (
                    ($loggedInEmpId && (string) $loggedInEmpId === (string) $inf->employee_id)
                    || ($buyerEmailLower !== '' && ($buyerEmailLower === $infEmailLower || ($infPaypalLower !== '' && $buyerEmailLower === $infPaypalLower)))
                );

                if ($inf && $inf->is_active && !$inf->is_archived && !$isSelfCheckout) {
                    $affSlug = (string) $inf->slug;
                    if ($trackingLinkId && \Illuminate\Support\Facades\Schema::hasTable('marketing_tracking_links')) {
                        $trk = \App\Models\Marketing\MarketingTrackingLink::where('id', $trackingLinkId)
                            ->where('influencer_id', $inf->id)
                            ->first();
                        if ($trk && !empty($trk->slug)) {
                            $affSlug = (string) $trk->slug;
                        }
                    }
                    $orderData['utm_source_last'] = 'influencer';
                    $orderData['utm_campaign_last'] = 'aff:' . substr($affSlug, 0, 50);
                    $orderData['utm_medium_last'] = $inf->primary_channel ?: 'social';
                }
            }
            if (request()->hasCookie('seelenfunke_first_touch')) {
                $ft = json_decode(request()->cookie('seelenfunke_first_touch'), true);
                if (is_array($ft)) {
                    $orderData['utm_source_first'] = $ft['source'] ?? null;
                    $orderData['utm_campaign_first'] = $ft['campaign'] ?? null;
                    $orderData['utm_medium_first'] = $ft['medium'] ?? null;
                }
            }
            if (request()->hasCookie('seelenfunke_last_touch')) {
                $lt = json_decode(request()->cookie('seelenfunke_last_touch'), true);
                if (is_array($lt)) {
                    if (empty($orderData['utm_source_last'])) {
                        $orderData['utm_source_last'] = $lt['source'] ?? null;
                    }
                    if (empty($orderData['utm_campaign_last'])) {
                        $orderData['utm_campaign_last'] = $lt['campaign'] ?? null;
                    }
                    if (empty($orderData['utm_medium_last'])) {
                        $orderData['utm_medium_last'] = $lt['medium'] ?? null;
                    }
                }
            }

            // VERHINDERE DOPPELTE BESTELLUNGEN WENN DER USER MEHRFACH KLICKT
            $order = null;
            if (!empty($finalIntentId)) {
                $order = OrderOrder::where('stripe_payment_intent_id', $finalIntentId)
                    ->where('payment_status', 'unpaid')
                    ->first();
            }

            if ($order) {
                // Bestellung aktualisieren, falls sie schon existiert (z.B. nach einem fehlgeschlagenen Payment-Versuch)
                $order->update($orderData);
                $order->items()->delete(); // Alte Items verwerfen
            } else {
                // Neue Bestellung erstellen
                $orderData['order_number'] = 'ORD-' . date('Y'). '-' . strtoupper(Str::random(6));
                $orderData['stripe_payment_intent_id'] = $finalIntentId;
                $order = OrderOrder::create($orderData);
            }

            foreach($cart->items as $item) {
                $itemConfig = is_array($item->configuration) ? $item->configuration : [];
                if ($item->product) {
                    $itemConfig = app(\App\Services\CartService::class)->sanitizeConfiguration($item->product, $itemConfig) ?? [];
                }
                if (empty($itemConfig['purchased_image_path']) && $item->product) {
                    $resolvedPurchasedImg = $item->product->resolveConfigurationImagePath($itemConfig);
                    if ($resolvedPurchasedImg) {
                        $itemConfig['purchased_image_path'] = $resolvedPurchasedImg;
                    }
                }
                if (!empty($this->digital_waiver_accepted) && (($item->product->type ?? 'physical') === 'digital' || !empty($itemConfig['is_ingame_shop_item']))) {
                    $itemConfig['digital_waiver_accepted'] = true;
                    $itemConfig['digital_waiver_accepted_at'] = now()->toIso8601String();
                }
                if ($item->product && method_exists($item->product, 'hasDigitalDocuments') && $item->product->hasDigitalDocuments()) {
                    $itemConfig['digital_documents'] = $item->product->getDigitalDocuments();
                }
                if ($item->product && method_exists($item->product, 'requiresSupplierProcurement')) {
                    $variantIdForCheck = is_string($itemConfig['variant_id'] ?? null) ? $itemConfig['variant_id'] : null;
                    if ($item->product->requiresSupplierProcurement((int) $item->quantity, $variantIdForCheck)) {
                        $leadRange = $item->product->getSupplierLeadDaysRange();
                        $deliveryDetails = \App\Models\Delivery\DeliverySetting::getDeliveryDetailsForProduct(
                            $item->product,
                            (int) $item->quantity,
                            $variantIdForCheck
                        );
                        $supplierModel = $item->product->relationLoaded('supplier')
                            ? $item->product->supplier
                            : (!empty($item->product->product_supplier_id) ? $item->product->supplier()->first() : null);

                        $itemConfig['supplier_procurement'] = [
                            'required' => true,
                            'missing_quantity' => $item->product->getMissingStockQuantity((int) $item->quantity, $variantIdForCheck),
                            'ordered_from_supplier' => false,
                            'ordered_at' => null,
                            'supplier_id' => $item->product->product_supplier_id,
                            'supplier_name' => $supplierModel?->name,
                            'reorder_url' => $item->product->reorder_url ?: ($supplierModel?->website ?: null),
                            'lead_days_min' => (int) ($leadRange['min'] ?? 4),
                            'lead_days_max' => (int) ($leadRange['max'] ?? 6),
                            'estimated_delivery_text' => $deliveryDetails['full_text'] ?? null,
                        ];
                    }
                }

                $finalConfiguration = !empty($itemConfig) ? $itemConfig : $item->configuration;
                $configFingerprint = !empty($finalConfiguration)
                    ? hash('sha256', json_encode($finalConfiguration))
                    : null;

                $productTax = $item->product->tax_rate;
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => !empty($finalConfiguration['ingame_item_name']) ? $finalConfiguration['ingame_item_name'] : $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $isSmallBusiness ? 0.0 : ($productTax !== null ? (float) $productTax : (float) shop_setting('default_tax_rate', 19.0)),
                    'total_price' => $item->unit_price * $item->quantity,
                    'configuration' => $finalConfiguration,
                    'config_fingerprint' => $configFingerprint
                ]);
            }

            session(['checkout_order_id' => $order->id]);

            if ($order->total_price == 0 && $order->subtotal_price > 0 && $order->discount_amount >= $order->subtotal_price) {
                try {
                    $order->completePayment(null);
                } catch (\RuntimeException $e) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'coupon' => $e->getMessage()
                    ]);
                }
            }

            return $order->id;
        });
    }
}

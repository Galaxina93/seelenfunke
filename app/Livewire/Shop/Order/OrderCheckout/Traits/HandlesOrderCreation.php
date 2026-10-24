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
        $this->validate([
            'terms_accepted' => 'accepted',
            'privacy_accepted' => 'accepted',
            'country' => 'required', // Land brauchen wir für die Versandkostenberechnung
        ], [
            'terms_accepted.accepted' => 'Bitte bestätige die AGB.',
            'privacy_accepted.accepted' => 'Bitte bestätige den Datenschutz.',
        ]);

        return true;
    }

    /**
     * Wird vom Frontend aufgerufen, BEVOR Stripe bestätigt wird.
     */
    public function validateAndCreateOrder()
    {
        $this->validate();

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
     * Private Helper: Die eigentliche Order-Erstellung inkl. User-Logik
     */
    private function createOrderInDb()
    {
        return DB::transaction(function () {
            $cartService = app(CartService::class);
            $cart = $cartService->getCart();

            if (!$cart || $cart->items()->count() === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cart' => 'Dein Warenkorb ist leer. Bitte füge vor dem Abschluss Artikel hinzu.'
                ]);
            }

            // [SICHERHEIT] Artikel-Verfügbarkeit & Lagerbestand vor Order-Erstellung prüfen
            if (!session()->has('checkout_from_quote_id')) {
                foreach ($cart->items as $item) {
                    $product = $item->product;
                    if (!$product || $product->status !== 'active') {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'cart' => 'Ein Artikel in deinem Warenkorb ist derzeit nicht mehr verfügbar.'
                        ]);
                    }
                    if ($product->type === 'physical' && $product->track_quantity && !$product->continue_selling_when_out_of_stock && $product->quantity > 0 && $product->quantity < $item->quantity) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'cart' => "Der Artikel '{$product->name}' ist leider nicht mehr in der gewünschten Menge auf Lager."
                        ]);
                    }
                }
            }

            // [NEU] SICHERHEITSCHECK FÜR GUTSCHEINE
            if ($cart->coupon_code) {
                $coupon = \App\Models\Marketing\MarketingGiftVoucher::where('code', $cart->coupon_code)->first();
                if (!$coupon) {
                    $coupon = MarketingVoucher::where('code', $cart->coupon_code)->first();
                }

                // Wenn Gutschein nicht existiert oder nicht mehr gültig ist (z.B. Limit erreicht)
                if (!$coupon || !$coupon->isValid()) {
                    // Gutschein aus Cart entfernen
                    $cart->update(['coupon_code' => null]);

                    // Exception werfen, damit der Checkout abbricht und Livewire den Fehler anzeigt
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'coupon' => 'Der verwendete Gutschein ist leider nicht mehr gültig oder aufgebraucht.'
                    ]);
                }

                // Referral-Sicherheitscheck gegen die eingegebene Checkout-E-Mail (Eigenwerbung & Neukundenschutz)
                $authCustomer = Auth::guard('customer')->check() ? Auth::guard('customer')->user() : null;
                $checkoutCustomerId = $authCustomer ? $authCustomer->id : null;
                $referralCheck = app(\App\Services\ReferralService::class)->validateReferralCode($cart->coupon_code, $this->email, $checkoutCustomerId);
                if ($referralCheck['is_referral'] && !$referralCheck['valid']) {
                    $cart->update(['coupon_code' => null]);
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'coupon' => $referralCheck['message']
                    ]);
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
                        $cart->update(['coupon_code' => null]);
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'coupon' => 'Dieser persönliche Belohnungsgutschein ist an ein verifiziertes Kundenkonto gebunden und kann nur nach Anmeldung mit dieser E-Mail-Adresse eingelöst werden.'
                        ]);
                    }
                }

                // [SICHERHEIT] Persönliche Belohnungsgutscheine (z.B. Gamification Meilensteine) strikt an das verifizierte Kundenkonto UND die Checkout-E-Mail binden
                if ($coupon instanceof MarketingVoucher && !empty($coupon->recipient_email)) {
                    $recipientTarget = strtolower(trim($coupon->recipient_email));
                    if (
                        !$authCustomer
                        || !$authCustomer->hasVerifiedEmail()
                        || strtolower(trim((string) $authCustomer->email)) !== $recipientTarget
                        || strtolower(trim((string) $this->email)) !== $recipientTarget
                    ) {
                        $cart->update(['coupon_code' => null]);
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'coupon' => 'Dieser persönliche Belohnungsgutschein ist an ein verifiziertes Kundenkonto gebunden und kann nur nach Anmeldung mit dieser E-Mail-Adresse eingelöst werden.'
                        ]);
                    }
                }
            }

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
                $tempPassword = Str::random(10);
                $customer = Customer::create([
                    'email' => $this->email,
                    'first_name' => $this->first_name,
                    'last_name' => $this->last_name,
                    'password' => bcrypt($tempPassword),
                    'needs_password_change' => true,
                    'temporary_password' => $tempPassword,
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
                ],
                'shipping_address' => $shipping_data,
                'volume_discount' => $totals['volume_discount'] ?? 0,
                'coupon_code' => $totals['coupon_code'] ?? null,
                'discount_amount' => $totals['discount_amount'] ?? 0,
                'subtotal_price' => $totals['subtotal_gross'],
                'tax_amount' => $totals['tax'],
                'is_small_business' => app(\App\Services\Tax\TaxSettingService::class)->isCurrentSmallBusiness(),
                'shipping_price' => $totals['shipping'],
                'express_price' => $totals['express'] ?? 0,
                'total_price' => $totals['total'],
            ];

            // UTM- und Referral-Tracking an der Bestellung festhalten
            $referrer = session('seelenfunke_referrer') ?: request()->cookie('seelenfunke_referrer');
            if ($referrer) {
                $orderData['utm_campaign_last'] = 'ref:' . substr($referrer, 0, 50);
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
                    $orderData['utm_source_last'] = $lt['source'] ?? null;
                    if (empty($orderData['utm_campaign_last'])) {
                        $orderData['utm_campaign_last'] = $lt['campaign'] ?? null;
                    }
                    $orderData['utm_medium_last'] = $lt['medium'] ?? null;
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

                $configFingerprint = !empty($item->configuration)
                    ? hash('sha256', json_encode($item->configuration))
                    : null;

                $productTax = $item->product->tax_rate;
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $productTax !== null ? $productTax : shop_setting('default_tax_rate', 19.0),
                    'total_price' => $item->unit_price * $item->quantity,
                    'configuration' => $item->configuration,
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

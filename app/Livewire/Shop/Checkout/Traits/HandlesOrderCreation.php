<?php

namespace App\Livewire\Shop\Checkout\Traits;

use App\Models\Customer\Customer;
use App\Models\Funki\FunkiVoucher;
use App\Models\Order\Order;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
        $orderId = $this->createOrderInDb();
        return $orderId;
    }

    /**
     * Private Helper: Die eigentliche Order-Erstellung inkl. User-Logik
     */
    private function createOrderInDb()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        // [NEU] SICHERHEITSCHECK FÜR GUTSCHEINE
        if ($cart->coupon_code) {
            $coupon = FunkiVoucher::where('code', $cart->coupon_code)->first();

            // Wenn Gutschein nicht existiert oder nicht mehr gültig ist (z.B. Limit erreicht)
            if (!$coupon || !$coupon->isValid()) {
                // Gutschein aus Cart entfernen
                $cart->update(['coupon_code' => null]);

                // Exception werfen, damit der Checkout abbricht und Livewire den Fehler anzeigt
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'coupon' => 'Der verwendete Gutschein ist leider nicht mehr gültig oder aufgebraucht.'
                ]);
            }
        }

        $targetCountry = $this->has_separate_shipping ? $this->shipping_country : $this->country;
        $totals = $cartService->calculateTotals($cart, $targetCountry);

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
            $customer = Customer::create([
                'email' => $this->email,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'password' => bcrypt(Str::random(16)),
            ]);

            $customer->profile()->create([
                'street' => $this->address,
                'city' => $this->city,
                'postal' => $this->postal_code,
                'country' => $this->country,
            ]);
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
            'deadline' => $cart->deadline,
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
            'shipping_price' => $totals['shipping'],
            'total_price' => $totals['total'],
        ];

        // VERHINDERE DOPPELTE BESTELLUNGEN WENN DER USER MEHRFACH KLICKT
        $order = Order::where('stripe_payment_intent_id', $finalIntentId)
            ->where('payment_status', 'unpaid')
            ->first();

        if ($order) {
            // Bestellung aktualisieren, falls sie schon existiert (z.B. nach einem fehlgeschlagenen Payment-Versuch)
            $order->update($orderData);
            $order->items()->delete(); // Alte Items verwerfen
        } else {
            // Neue Bestellung erstellen
            $orderData['order_number'] = 'ORD-' . date('Y'). '-' . strtoupper(Str::random(6));
            $orderData['stripe_payment_intent_id'] = $finalIntentId;
            $order = Order::create($orderData);
        }

        foreach($cart->items as $item) {

            $configFingerprint = !empty($item->configuration)
                ? hash('sha256', json_encode($item->configuration))
                : null;

            $order->items()->create([
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->unit_price * $item->quantity,
                'configuration' => $item->configuration,
                'config_fingerprint' => $configFingerprint
            ]);
        }

        // FEHLERBEHEBUNG:
        // Der Warenkorb darf hier NICHT gelöscht werden ($cart->delete() wurde entfernt).
        // Das geschieht erst, wenn Stripe den erfolgreichen Kauf zurückmeldet.

        return $order->id;
    }
}

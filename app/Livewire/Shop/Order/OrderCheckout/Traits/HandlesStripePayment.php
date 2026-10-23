<?php

namespace App\Livewire\Shop\Order\OrderCheckout\Traits;

use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Services\CartService;
use App\Services\System\SystemSecurityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Stripe\PaymentIntent;
use Stripe\Stripe;

trait HandlesStripePayment
{
    /**
     * Erstellt oder aktualisiert den PaymentIntent bei Stripe
     */
    public function createPaymentIntent()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        // WICHTIG: Versandkosten basieren IMMER auf dem Lieferland
        $targetCountry = $this->has_separate_shipping ? $this->shipping_country : $this->country;
        $totals = $cartService->calculateTotals($cart, $targetCountry);

        $amount = $totals['total']; // Betrag in Cent
        $this->totalAmount = $amount;

        if ($amount > 0) {
            $stripeSecret = config('services.stripe.secret');
            if ($stripeSecret) {
                Stripe::setApiKey($stripeSecret);

                // Metadaten vorbereiten
                $metadata = [
                    'cart_id' => $cart->id,
                    'session_id' => Session::getId(),
                    'customer_email' => $this->email,
                    'shipping_country' => $targetCountry
                ];

                $intentData = [
                    'amount' => $amount,
                    'currency' => 'eur',
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => $metadata
                ];

                // Falls bereits ein Intent für diesen Checkout existiert, aktualisieren wir ihn, um verwaiste Intents zu vermeiden
                $intent = null;
                if (!empty($this->currentPaymentIntentId)) {
                    try {
                        $intent = PaymentIntent::update($this->currentPaymentIntentId, [
                            'amount' => $amount,
                            'metadata' => $metadata
                        ]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::info('Konnte bestehenden Stripe PaymentIntent nicht aktualisieren, erstelle neuen: ' . $e->getMessage());
                        $intent = null;
                    }
                }

                if (!$intent) {
                    $intent = PaymentIntent::create($intentData);
                }

                $this->clientSecret = $intent->client_secret;

                // Hier speichern wir die ID sofort in die Variable
                $this->currentPaymentIntentId = $intent->id;

                return $this->clientSecret;
            }
        }
        return null;
    }

    /**
     * Wird vom Frontend aufgerufen, wenn Stripe die Zahlung bestätigt hat.
     */
    public function handlePaymentSuccess($orderId = null)
    {
        // 1. Die richtige Order finden – strikt an den aktuellen PaymentIntent der Session gebunden
        $order = null;
        if (!empty($this->currentPaymentIntentId)) {
            $order = OrderOrder::with('items.product')
                ->where('stripe_payment_intent_id', $this->currentPaymentIntentId)
                ->latest()
                ->first();
        }

        // Falls eine orderId übergeben wird, darf diese nur akzeptiert werden,
        // wenn sie zum aktuellen PaymentIntent dieser Session gehört (IDOR / Injection Schutz)
        if ($orderId) {
            $candidate = OrderOrder::with('items.product')->find($orderId);
            if (!$candidate) {
                $this->addError('payment', 'Bestellung nicht gefunden.');
                return;
            }

            // Session Ownership Check (IDOR Protection)
            $isOwner = false;
            if (Auth::guard('customer')->check() && $candidate->customer_id === Auth::guard('customer')->id()) {
                $isOwner = true;
            } elseif (!empty($this->email) && strtolower($candidate->email) === strtolower($this->email)) {
                $isOwner = true;
            }

            if (!$isOwner) {
                Log::warning("Unberechtigter handlePaymentSuccess Aufruf: Order {$orderId} gehört nicht zum aktuellen Kunden/Session.");
                SystemSecurityService::logEvent(
                    'security:checkout_idor_attempt',
                    'ERROR',
                    'Unberechtigter Zahlungsabschluss-Aufruf für fremde Bestellung blockiert.',
                    ['order_id' => $orderId, 'current_email' => $this->email],
                    'idor_integrity'
                );
                $this->addError('payment', 'Ungültige Bestellsitzung.');
                return;
            }

            // Wenn die Bestellung kostenpflichtig ist (> 0 €), muss ein Stripe Intent existieren und übereinstimmen
            if ($candidate->total_price > 0) {
                if (empty($this->currentPaymentIntentId) || $candidate->stripe_payment_intent_id !== $this->currentPaymentIntentId) {
                    Log::warning("Verdächtiger handlePaymentSuccess Aufruf: Order {$orderId} stimmt nicht mit Intent {$this->currentPaymentIntentId} überein.");
                    SystemSecurityService::logEvent(
                        'security:checkout_intent_mismatch',
                        'ERROR',
                        'Manipulationsversuch im Checkout: Stripe PaymentIntent stimmt nicht mit Bestellung überein.',
                        ['order_id' => $orderId, 'session_intent' => $this->currentPaymentIntentId, 'order_intent' => $candidate->stripe_payment_intent_id],
                        'idor_integrity'
                    );
                    $this->addError('payment', 'Ungültige Bestellsitzung.');
                    return;
                }
            } else {
                // Bei 0 € Bestellungen (vollständig durch Gutschein gedeckt) darf total_price nicht manipuliert sein
                if ($candidate->total_price != 0) {
                    $this->addError('payment', 'Ungültiger Zahlungsstatus.');
                    return;
                }
            }

            $order = $candidate;
        }

        if ($order) {
            // 2. Serverseitige Verifikation über die Stripe API für kostenpflichtige Bestellungen
            if ($order->total_price > 0) {
                if (empty($order->stripe_payment_intent_id) && !app()->environment('testing')) {
                    $this->addError('payment', 'Keine gültige Zahlungsinformation vorhanden.');
                    return;
                }

                $stripeSecret = config('services.stripe.secret');
                if (!app()->environment('testing') && $stripeSecret && !empty($order->stripe_payment_intent_id)) {
                    Stripe::setApiKey($stripeSecret);
                    try {
                        $intent = PaymentIntent::retrieve($order->stripe_payment_intent_id);

                        if ($intent->status !== 'succeeded') {
                            Log::warning("Zahlungsverifikation fehlgeschlagen für Order {$order->order_number}: Status ist '{$intent->status}' (erwartet: 'succeeded').");
                            $this->addError('payment', 'Die Zahlung wurde von Stripe noch nicht freigegeben.');
                            return;
                        }

                        if ((int)$intent->amount !== (int)$order->total_price) {
                            Log::error("Betrags-Mismatch bei Order {$order->order_number}: Stripe={$intent->amount}, Order={$order->total_price}");
                            $this->addError('payment', 'Der bezahlte Betrag stimmt nicht mit der Auftragssumme überein.');
                            return;
                        }
                    } catch (\Exception $e) {
                        Log::error("Stripe API Fehler bei Verifikation von Order {$order->order_number}: " . $e->getMessage());
                        $this->addError('payment', 'Zahlung konnte serverseitig nicht verifiziert werden.');
                        return;
                    }
                }
            }

            // Fulfill the order using the unified, idempotent completePayment method
            $order->completePayment($order->stripe_payment_intent_id ?: $this->currentPaymentIntentId);

            $this->finalOrderNumber = $order->order_number;

            try {
                $ref = app(\App\Services\ReferralService::class)->getOrCreateReferralForCustomer(
                    $order->email,
                    $order->billing_address['first_name'] ?? ($this->first_name ?? ''),
                    $order->billing_address['last_name'] ?? ($this->last_name ?? ''),
                    $order->customer_id
                );
                $this->referralCode = $ref->code;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Referral code resolution error: ' . $e->getMessage());
            }

            // --- VERKNÜPFUNG ZUM ANGEBOT (QUOTE) ---
            if (Session::has('checkout_from_quote_id')) {
                $quoteId = Session::get('checkout_from_quote_id');
                $quote = OrderQuoteRequest::find($quoteId);

                if ($quote) {
                    $quote->update([
                        'status' => 'converted',
                        'converted_order_id' => $order->id
                    ]);

                    Session::forget('checkout_from_quote_id');

                    # \Illuminate\Support\Facades\Log::info("Angebot {$quote->quote_number} wurde erfolgreich in Order {$order->order_number} umgewandelt.");
                }
            }
        }

        // 3. UI umschalten & Cleanup
        $this->isFinished = true;

        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }

        $this->dispatch('cart-updated');
        $this->dispatch('payment-processed');
    }
}

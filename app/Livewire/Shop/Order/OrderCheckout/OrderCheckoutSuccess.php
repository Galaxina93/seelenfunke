<?php

namespace App\Livewire\Shop\Order\OrderCheckout;

use App\Models\Cart\Cart;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class OrderCheckoutSuccess extends Component
{
    #[\Livewire\Attributes\Locked]
    public $finalOrderNumber = '';

    #[\Livewire\Attributes\Locked]
    public $email = '';

    #[\Livewire\Attributes\Locked]
    public $referralCode = '';

    public function mount()
    {
        $intentId = request()->query('payment_intent');

        // Falls kein Intent in der URL ist, prüfen ob Customer eingeloggt ist für Fallback
        if (!$intentId) {
            if (Auth::guard('customer')->check()) {
                $order = OrderOrder::where('customer_id', Auth::guard('customer')->id())->latest()->first();
                if ($order) {
                    $this->finalOrderNumber = $order->order_number;
                    $this->email = $order->email ?: ($order->billing_address['email'] ?? ($order->customer->email ?? ''));
                    $this->resolveReferralCode($order);
                }
            }
            return;
        }

        Log::info("CheckoutSuccess: Verarbeite Redirect-Erfolg für Intent: $intentId");

        $order = OrderOrder::where('stripe_payment_intent_id', $intentId)->first();

        if (!$order) {
            Log::error("CheckoutSuccess: OrderOrder nicht gefunden für $intentId");
            return;
        }

        if (Auth::guard('customer')->check() && $order->customer_id && (string) $order->customer_id !== (string) Auth::guard('customer')->id()) {
            Log::warning("CheckoutSuccess: Unberechtigter Zugriffsversuch auf fremde Order {$order->id}");
            return;
        }

        $this->finalOrderNumber = $order->order_number;
        $this->email = $order->email ?: ($order->billing_address['email'] ?? ($order->customer->email ?? ''));
        $this->resolveReferralCode($order);

        $stripeSecret = config('services.stripe.secret');
        if (!$stripeSecret) return;

        try {
            Stripe::setApiKey($stripeSecret);
            $intent = PaymentIntent::retrieve($intentId);

            $clientSecretParam = request()->query('payment_intent_client_secret');
            if ($clientSecretParam && isset($intent->client_secret) && !hash_equals((string) $intent->client_secret, (string) $clientSecretParam)) {
                Log::warning("CheckoutSuccess: Ungültiges payment_intent_client_secret für Intent {$intentId}");
                return;
            }

            if ($intent->status === 'succeeded') {

                // Falls die Order noch nicht als bezahlt markiert wurde (Redirect-Fall)
                if ($order->payment_status !== 'paid') {
                    $order->completePayment($intentId);
                }

                // Clean Up (Falls via Redirect zurückgekommen)
                if (Session::has('checkout_from_quote_id')) {
                    $quote = OrderQuoteRequest::find(Session::get('checkout_from_quote_id'));
                    if ($quote) $quote->update(['status' => 'converted', 'converted_order_id' => $order->id]);
                    Session::forget('checkout_from_quote_id');
                }

                if (Auth::guard('customer')->check()) {
                    Cart::where('customer_id', Auth::guard('customer')->id())->delete();
                } else {
                    Cart::where('session_id', Session::getId())->delete();
                }

                $this->dispatch('cart-updated');
            }
        } catch (\Exception $e) {
            Log::error('Stripe Success Page Error: ' . $e->getMessage());
        }
    }

    private function resolveReferralCode(OrderOrder $order): void
    {
        try {
            $firstName = $order->billing_address['first_name'] ?? ($order->customer->first_name ?? '');
            $lastName = $order->billing_address['last_name'] ?? ($order->customer->last_name ?? '');
            $ref = app(\App\Services\ReferralService::class)->getOrCreateReferralForCustomer(
                $this->email,
                $firstName,
                $lastName,
                $order->customer_id
            );
            $this->referralCode = $ref->code;
        } catch (\Exception $e) {
            Log::error('CheckoutSuccess referral resolution error: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.shop.order.order-checkout.order-checkout-success')
            ->layout('components.layouts.frontend_layout');
    }
}

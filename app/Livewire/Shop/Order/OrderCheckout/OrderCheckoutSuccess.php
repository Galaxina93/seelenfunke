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
    public $finalOrderId = '';

    #[\Livewire\Attributes\Locked]
    public $finalOrderNumber = '';

    #[\Livewire\Attributes\Locked]
    public $email = '';

    #[\Livewire\Attributes\Locked]
    public $referralCode = '';

    #[\Livewire\Attributes\Locked]
    public array $instantDownloads = [];

    #[\Livewire\Attributes\Locked]
    public int $earnedSparks = 0;

    public function mount()
    {
        $intentId = request()->query('payment_intent');
        $sessionId = request()->query('session_id');

        // 0. Redirect aus Stripe Hosted Checkout Session (z. B. Zahlungslink aus Angebot)
        if (!$intentId && is_string($sessionId) && str_starts_with(trim($sessionId), 'cs_')) {
            $stripeSecret = config('services.stripe.secret') ?: shop_setting('stripe_secret_key');
            if ($stripeSecret) {
                try {
                    Stripe::setApiKey($stripeSecret);
                    $checkoutSession = \Stripe\Checkout\Session::retrieve(trim($sessionId));
                    $resolvedOrderId = $checkoutSession->metadata->order_id ?? null;
                    $resolvedPiId = is_object($checkoutSession->payment_intent ?? null)
                        ? ($checkoutSession->payment_intent->id ?? null)
                        : ($checkoutSession->payment_intent ?? null);

                    $order = $resolvedOrderId
                        ? OrderOrder::with('items.product')->find($resolvedOrderId)
                        : ($resolvedPiId ? OrderOrder::with('items.product')->where('stripe_payment_intent_id', $resolvedPiId)->first() : null);

                    if (
                        $order
                        && ($checkoutSession->payment_status ?? '') === 'paid'
                        && strtolower((string) ($checkoutSession->currency ?? 'eur')) === 'eur'
                        && (!isset($checkoutSession->amount_total) || (int) $checkoutSession->amount_total >= (int) $order->total_price)
                        && !in_array($order->status, ['cancelled', 'refunded'], true)
                        && $order->payment_status !== 'refunded'
                    ) {
                        if ($order->payment_status !== 'paid') {
                            $order->completePayment($resolvedPiId);
                            $order->refresh();
                        }

                        $this->finalOrderId = (string) $order->id;
                        $this->finalOrderNumber = $order->order_number;
                        $this->email = $order->email ?: ($order->billing_address['email'] ?? ($order->customer->email ?? ''));
                        $this->instantDownloads = $order->getInstantDownloads();
                        $this->resolveReferralCode($order);

                        $quoteNumber = $checkoutSession->metadata->quote_number ?? null;
                        if ($quoteNumber && $order->payment_status === 'paid') {
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
                } catch (\Exception $e) {
                    Log::error('Stripe Checkout Session Success Error: ' . $e->getMessage());
                }
                return;
            }
        }

        // Falls kein Intent in der URL ist, prüfen ob Customer eingeloggt ist für Fallback (ausschließlich bezahlte, aktive Bestellungen)
        if (!$intentId) {
            if (Auth::guard('customer')->check()) {
                $order = OrderOrder::with('items.product')
                    ->where('customer_id', Auth::guard('customer')->id())
                    ->where('payment_status', 'paid')
                    ->whereNotIn('status', ['cancelled', 'refunded'])
                    ->where('created_at', '>=', now()->subHours(2))
                    ->latest()
                    ->first();
                if ($order) {
                    $this->finalOrderId = (string) $order->id;
                    $this->finalOrderNumber = $order->order_number;
                    $this->email = $order->email ?: ($order->billing_address['email'] ?? ($order->customer->email ?? ''));
                    $this->instantDownloads = $order->getInstantDownloads();
                    $this->resolveReferralCode($order);
                }
            }
            return;
        }

        Log::info("CheckoutSuccess: Verarbeite Redirect-Erfolg für Intent: $intentId");

        $order = OrderOrder::with('items.product')->where('stripe_payment_intent_id', $intentId)->first();

        if (!$order) {
            Log::error("CheckoutSuccess: OrderOrder nicht gefunden für $intentId");
            return;
        }

        $isOwner = Auth::guard('customer')->check()
            && $order->customer_id
            && (string) $order->customer_id === (string) Auth::guard('customer')->id();

        if (Auth::guard('customer')->check() && $order->customer_id && !$isOwner) {
            Log::warning("CheckoutSuccess: Unberechtigter Zugriffsversuch auf fremde Order {$order->id}");
            return;
        }

        $clientSecretParam = request()->query('payment_intent_client_secret');
        if (!$isOwner && (!is_string($clientSecretParam) || trim($clientSecretParam) === '')) {
            Log::warning("CheckoutSuccess: Zugriff auf Order {$order->id} ohne payment_intent_client_secret verweigert.");
            return;
        }

        $stripeSecret = config('services.stripe.secret') ?: shop_setting('stripe_secret_key');
        if (!$stripeSecret) {
            if ($isOwner && $order->payment_status === 'paid' && !in_array($order->status, ['cancelled', 'refunded'], true)) {
                $this->finalOrderId = (string) $order->id;
                $this->finalOrderNumber = $order->order_number;
                $this->email = $order->email ?: ($order->billing_address['email'] ?? ($order->customer->email ?? ''));
                $this->instantDownloads = $order->getInstantDownloads();
                $this->resolveReferralCode($order);
            }
            return;
        }

        try {
            Stripe::setApiKey($stripeSecret);
            $intent = PaymentIntent::retrieve($intentId);

            if ((!$isOwner || $clientSecretParam) && (!isset($intent->client_secret) || !hash_equals((string) $intent->client_secret, (string) $clientSecretParam))) {
                Log::warning("CheckoutSuccess: Ungültiges payment_intent_client_secret für Intent {$intentId}");
                return;
            }

            if ($intent->status === 'succeeded') {
                if (isset($intent->currency) && strtolower((string) $intent->currency) !== 'eur') {
                    Log::error("CheckoutSuccess: Währungsabweichung für Order {$order->order_number} (Intent: {$intent->currency}).");
                    return;
                }

                if (isset($intent->amount) && (int) $intent->amount < (int) $order->total_price) {
                    Log::error("CheckoutSuccess: Betragsabweichung für Order {$order->order_number} (Intent: {$intent->amount}, Order: {$order->total_price}).");
                    return;
                }

                if (in_array($order->status, ['cancelled', 'refunded'], true) || $order->payment_status === 'refunded') {
                    Log::warning("CheckoutSuccess: Order {$order->order_number} ist bereits storniert oder erstattet.");
                    return;
                }

                // Falls die Order noch nicht als bezahlt markiert wurde (Redirect-Fall)
                if ($order->payment_status !== 'paid') {
                    $order->completePayment($intentId);
                    $order->refresh();
                }

                $this->finalOrderId = (string) $order->id;
                $this->finalOrderNumber = $order->order_number;
                $this->email = $order->email ?: ($order->billing_address['email'] ?? ($order->customer->email ?? ''));
                $this->instantDownloads = $order->getInstantDownloads();
                $this->resolveReferralCode($order);

                // Clean Up (Falls via Redirect zurückgekommen)
                if (Session::has('checkout_from_quote_id')) {
                    $quote = OrderQuoteRequest::find(Session::get('checkout_from_quote_id'));
                    if (
                        $quote
                        && $quote->status !== 'converted'
                        && empty($quote->converted_order_id)
                        && $order->payment_status === 'paid'
                        && ((int) $order->total_price + (int) ($order->discount_amount ?? 0)) >= (int) $quote->gross_total
                    ) {
                        $quote->update(['status' => 'converted', 'converted_order_id' => $order->id]);
                    }
                    Session::forget('checkout_from_quote_id');
                }

                $cartsToClean = Auth::guard('customer')->check()
                    ? Cart::where('customer_id', Auth::guard('customer')->id())->orWhere('session_id', Session::getId())->get()
                    : Cart::where('session_id', Session::getId())->get();

                foreach ($cartsToClean as $c) {
                    $c->items()->delete();
                    $c->delete();
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
            $this->earnedSparks = app(\App\Services\Gamification\GamificationService::class)->calculateOrderSparks($order);
        } catch (\Throwable $e) {
            $this->earnedSparks = 0;
        }

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

<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\Cart;
use App\Models\QuoteRequest;
use App\Services\InvoiceService; // NEU
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class CheckoutSuccess extends Component
{
    public function mount()
    {
        $intentId = request()->query('payment_intent');

        if ($intentId) {
            $order = Order::where('stripe_payment_intent_id', $intentId)->first();
            $stripeSecret = config('services.stripe.secret');

            if (!$stripeSecret) {
                Log::error('CRITICAL: Stripe Secret Key is missing in config!');
                return;
            }

            try {
                Stripe::setApiKey($stripeSecret);
                $intent = PaymentIntent::retrieve($intentId);

                if ($intent->status === 'succeeded') {

                    // --- SCHRITT 1: Bestellung verarbeiten ---\
                    if ($order && $order->payment_status !== 'paid') {
                        $order->update(['payment_status' => 'paid']);

                        // 1.1: RECHNUNG SOFORT ERSTELLEN (NEU)
                        try {
                            $invoiceService = new InvoiceService();
                            $invoiceService->createFromOrder($order);
                        } catch (\Exception $e) {
                            Log::error('Auto-Invoice Error: ' . $e->getMessage());
                        }

                        // 1.2 Mail senden
                        try {
                            Mail::to($order->email)->send(new OrderConfirmation($order));
                        } catch (\Exception $e) {
                            Log::error('Mail Error: ' . $e->getMessage());
                        }
                    }

                    // --- SCHRITT 2: Quote Status update (Falls aus Angebot) ---
                    if (Session::has('checkout_from_quote_id')) {
                        $quoteId = Session::get('checkout_from_quote_id');
                        $quote = QuoteRequest::find($quoteId);

                        if ($quote) {
                            $quote->update([
                                'status' => 'converted',
                                'converted_order_id' => $order ? $order->id : null
                            ]);
                        }
                        Session::forget('checkout_from_quote_id');
                    }

                    // --- SCHRITT 3: Warenkorb leeren ---
                    if (isset($intent->metadata->cart_id)) {
                        Cart::where('id', $intent->metadata->cart_id)->delete();
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
    }

    public function render()
    {
        return view('livewire.shop.checkout-success');
    }
}

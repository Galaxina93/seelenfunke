<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\Cart;
use App\Models\QuoteRequest;
use App\Services\InvoiceService;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log; // Logging
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
            Log::info("CheckoutSuccess: Processing Intent ID: $intentId");

            // Bestellung suchen
            $order = Order::where('stripe_payment_intent_id', $intentId)->first();

            if (!$order) {
                Log::error("CheckoutSuccess: CRITICAL - Order NOT FOUND for Intent ID: $intentId");
                // Hier könnten wir versuchen, über die Session oder E-Mail zu matchen, aber das ist riskant.
                return;
            }

            Log::info("CheckoutSuccess: Order found: " . $order->order_number);

            $stripeSecret = config('services.stripe.secret');

            if (!$stripeSecret) {
                Log::error('CRITICAL: Stripe Secret Key missing!');
                return;
            }

            try {
                Stripe::setApiKey($stripeSecret);
                $intent = PaymentIntent::retrieve($intentId);

                // Check auf status
                if ($intent->status === 'succeeded' || $intent->status === 'processing') {

                    if ($order->payment_status !== 'paid') {
                        $order->update(['payment_status' => 'paid']);
                        Log::info("Order updated to PAID.");

                        // 1. Rechnung
                        try {
                            $invoiceService = new InvoiceService();
                            $invoiceService->createFromOrder($order);
                            Log::info("Invoice created.");
                        } catch (\Exception $e) {
                            Log::error('Invoice Error: ' . $e->getMessage());
                        }

                        // 2. Mail
                        try {
                            Mail::to($order->email)->send(new OrderConfirmation($order));
                            Log::info("Mail sent to " . $order->email);
                        } catch (\Exception $e) {
                            Log::error('Mail Error: ' . $e->getMessage());
                        }
                    }

                    // --- Clean Up ---
                    if (Session::has('checkout_from_quote_id')) {
                        $quoteId = Session::get('checkout_from_quote_id');
                        $quote = QuoteRequest::find($quoteId);
                        if ($quote) {
                            $quote->update(['status' => 'converted', 'converted_order_id' => $order->id]);
                        }
                        Session::forget('checkout_from_quote_id');
                    }

                    if (Auth::guard('customer')->check()) {
                        Cart::where('customer_id', Auth::guard('customer')->id())->delete();
                    } else {
                        Cart::where('session_id', Session::getId())->delete();
                    }

                    $this->dispatch('cart-updated');
                } else {
                    Log::warning("Stripe Intent status is: " . $intent->status);
                }
            } catch (\Exception $e) {
                Log::error('Stripe Success Page Error: ' . $e->getMessage());
            }
        } else {
            Log::warning("CheckoutSuccess: No payment_intent in URL.");
        }
    }

    public function render()
    {
        return view('livewire.shop.checkout-success');
    }
}

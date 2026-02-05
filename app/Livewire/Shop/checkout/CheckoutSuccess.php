<?php

namespace App\Livewire\Shop\checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\QuoteRequest;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class CheckoutSuccess extends Component
{
    public function mount()
    {
        $intentId = request()->query('payment_intent');

        // Falls kein Intent in der URL ist, wurde der Erfolg wahrscheinlich
        // schon via JavaScript/handlePaymentSuccess verarbeitet.
        if (!$intentId) {
            return;
        }

        Log::info("CheckoutSuccess: Verarbeite Redirect-Erfolg für Intent: $intentId");

        $order = Order::where('stripe_payment_intent_id', $intentId)->first();

        if (!$order) {
            Log::error("CheckoutSuccess: Order nicht gefunden für $intentId");
            return;
        }

        $stripeSecret = config('services.stripe.secret');
        if (!$stripeSecret) return;

        try {
            Stripe::setApiKey($stripeSecret);
            $intent = PaymentIntent::retrieve($intentId);

            if ($intent->status === 'succeeded') {

                // Falls die Order noch nicht als bezahlt markiert wurde (Redirect-Fall)
                if ($order->payment_status !== 'paid') {
                    $order->update(['payment_status' => 'paid', 'status' => 'pending']);

                    // Rechnung erstellen (Falls noch nicht geschehen)
                    try {
                        $invoiceService = new InvoiceService();
                        if ($order->invoices()->count() === 0) {
                            $invoiceService->createFromOrder($order);
                        }
                    } catch (\Exception $e) {
                        Log::error('Invoice Error: ' . $e->getMessage());
                    }

                    // HINWEIS: Mail-Versand hier entfernen wir bewusst,
                    // da handlePaymentSuccess in Checkout.php das bereits erledigt hat
                    // oder wir es dort zentral steuern.
                }

                // Clean Up (Falls via Redirect zurückgekommen)
                if (Session::has('checkout_from_quote_id')) {
                    $quote = QuoteRequest::find(Session::get('checkout_from_quote_id'));
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

    public function render()
    {
        return view('livewire.shop.checkout.checkout-success');
    }
}

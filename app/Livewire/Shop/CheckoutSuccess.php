<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\Cart;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class CheckoutSuccess extends Component
{
    public function mount()
    {
        $intentId = request()->query('payment_intent');

        if ($intentId) {
            // Bestellung anhand der Stripe-ID suchen (die wir vorher gespeichert haben müssen!)
            // Falls du die ID im Checkout.php noch nicht speicherst, mach es dort oder hier über Session-Order-ID
            $order = Order::where('stripe_payment_intent_id', $intentId)->first();

            // Fallback: Falls Order noch nicht mit Stripe-ID verknüpft ist (Race Condition),
            // könnte man die letzte Order des Users/Session suchen.
            // Aber besser: In Checkout.php (JS Teil) sicherstellen, dass die ID gespeichert wird.

            if ($order && $order->payment_status !== 'paid') {

                Stripe::setApiKey(env('STRIPE_SECRET'));
                $intent = PaymentIntent::retrieve($intentId);

                if ($intent->status === 'succeeded') {
                    // 1. Status updaten
                    $order->update(['payment_status' => 'paid']);

                    // 2. E-Mail senden
                    try {
                        Mail::to($order->email)->send(new OrderConfirmation($order));
                    } catch (\Exception $e) {
                        \Log::error('Mail Error: ' . $e->getMessage());
                    }

                    // 3. Warenkorb leeren
                    $sessionId = Session::getId();

                    // Lösche Cart basierend auf Session
                    Cart::where('session_id', $sessionId)->delete();

                    // Lösche Cart basierend auf Customer ID (wenn eingeloggt)
                    if(Auth::guard('customer')->check()) {
                        Cart::where('customer_id', Auth::guard('customer')->id())->delete();
                    }

                    // Event feuern
                    $this->dispatch('cart-updated');
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.shop.checkout-success');
    }
}

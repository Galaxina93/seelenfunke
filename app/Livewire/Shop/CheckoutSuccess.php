<?php

namespace App\Livewire\Shop;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class CheckoutSuccess extends Component
{
    public $order;

    public function mount()
    {
        // Parameter von Stripe URL
        $paymentIntentId = request()->query('payment_intent');

        if (!$paymentIntentId) {
            return redirect()->route('home');
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));
        $intent = PaymentIntent::retrieve($paymentIntentId);

        if ($intent->status === 'succeeded') {
            // Warenkorb leeren
            // Achtung: Hier suchen wir die Order idealerweise über Metadata oder Session
            // Da wir im Checkout Controller die Order noch nicht persistiert hatten für den Redirect,
            // ist der sicherste Weg hier, den Cart zu löschen.

            // In einer perfekten Welt würde der Webhook die Order auf "paid" setzen.
            // Hier simulieren wir den Abschluss für das Frontend.

            $cartId = $intent->metadata->cart_id ?? null;
            if ($cartId) {
                Cart::where('id', $cartId)->delete(); // Oder leeren
                Session::forget('cart_id'); // Falls in Session
                Session::regenerate(); // Session ID erneuern für neuen Cart
            }

            // Optional: Order laden um "Danke Alina!" anzuzeigen.
            // Das geht am besten, wenn wir die Order ID in den confirmParams als return_url parameter mitgeben würden.
        }
    }

    public function render()
    {
        return view('livewire.shop.checkout-success');
    }
}

<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class Checkout extends Component
{
    // Rechnungsadresse
    public $email;
    public $first_name;
    public $last_name;
    public $company;
    public $address;
    public $apartment;
    public $city;
    public $country = 'DE'; // Standard Deutschland
    public $postal_code;
    public $phone;

    // Versandadresse (Optional, hier erstmal gleich Rechnungsadresse für MVP)
    public $ship_to_different_address = false;

    // Rechtliches
    public $terms_accepted = false;
    public $privacy_accepted = false;

    // Stripe
    public $clientSecret;

    protected $rules = [
        'email' => 'required|email',
        'first_name' => 'required|string|min:2',
        'last_name' => 'required|string|min:2',
        'address' => 'required|string|min:5',
        'city' => 'required|string|min:2',
        'postal_code' => 'required|string|min:4',
        'country' => 'required|in:DE,AT,CH', // Anpassbar
        'terms_accepted' => 'accepted',
        'privacy_accepted' => 'accepted',
    ];

    protected $messages = [
        'email.required' => 'Bitte gib deine E-Mail-Adresse an.',
        'terms_accepted.accepted' => 'Du musst den AGB zustimmen, um fortzufahren.',
        'privacy_accepted.accepted' => 'Bitte akzeptiere die Datenschutzerklärung.',
    ];

    public function mount(CartService $cartService)
    {
        $cart = $cartService->getCart();

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart');
        }

        // Falls eingeloggt, Daten vorbefüllen
        if (Auth::check()) {
            $user = Auth::user();
            $this->email = $user->email;
            $this->first_name = $user->name; // Oder splitten falls Name ein Feld ist
            // Weitere Felder hier mappen, falls im User Model vorhanden
        }

        // Stripe Intent erstellen
        $this->createPaymentIntent($cartService);
    }

    public function createPaymentIntent(CartService $cartService)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $totals = $cartService->getTotals();

        // Stripe erwartet Beträge in Cent (Integer)
        $amount = $totals['total'];

        $paymentIntent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => [
                'cart_id' => $cartService->getCart()->id,
                // 'customer_id' => Auth::id() // Optional
            ],
        ]);

        $this->clientSecret = $paymentIntent->client_secret;
    }

    /**
     * Diese Methode wird vom Frontend JS aufgerufen, BEVOR die Zahlung an Stripe geht.
     * Sie validiert die Formulardaten und erstellt die Order in der DB (Pending).
     */
    public function validateAndCreateOrder(CartService $cartService)
    {
        $this->validate();

        // Transaction, damit Order und Items atomar angelegt werden
        $order = DB::transaction(function () use ($cartService) {
            $cart = $cartService->getCart();
            $totals = $cartService->getTotals();

            // Adresse als JSON Array
            $billingAddress = [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'company' => $this->company,
                'address' => $this->address,
                'apartment' => $this->apartment,
                'city' => $this->city,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
                'phone' => $this->phone,
            ];

            // Order anlegen
            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()), // Besser: Eigene Nummernkreis-Logik
                'customer_id' => Auth::id(), // Null bei Gast
                'email' => $this->email,
                'status' => 'pending', // Wartet auf Zahlung
                'payment_status' => 'unpaid',
                'billing_address' => $billingAddress,
                'shipping_address' => $billingAddress, // Vorerst identisch
                'subtotal_price' => $totals['subtotal_gross'],
                'tax_amount' => $totals['tax'],
                'shipping_price' => $totals['shipping'],
                'total_price' => $totals['total'],
            ]);

            // Items übertragen
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->unit_price * $item->quantity,
                    'configuration' => $item->configuration,
                ]);
            }

            return $order;
        });

        // Wir geben die Order ID zurück, damit das Frontend sie an Stripe übergeben kann (für Webhooks)
        return $order->id;
    }

    public function render(CartService $cartService)
    {
        return view('livewire.shop.checkout', [
            'totals' => $cartService->getTotals(),
            'cart' => $cartService->getCart()
        ]);
    }
}

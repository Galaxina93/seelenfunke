<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\Cart;
use App\Models\QuoteRequest;
use App\Models\Customer;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Component;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class Checkout extends Component
{
    // --- BESTELLDATEN ---
    public $email;
    public $first_name;
    public $last_name;
    public $company;
    public $address;
    public $city;
    public $country = 'DE';
    public $postal_code;

    // --- RECHTLICHES ---
    public $terms_accepted = false;
    public $privacy_accepted = false;

    // --- LOGIN ---
    public $loginEmail = '';
    public $loginPassword = '';
    public $loginError = '';

    // --- STRIPE ---
    public $clientSecret;

    // --- REGELN ---
    protected $rules = [
        'email' => 'required|email',
        'first_name' => 'required|string|min:2',
        'last_name' => 'required|string|min:2',
        'address' => 'required|string|min:5',
        'city' => 'required|string|min:2',
        'postal_code' => 'required|string|min:4',
        'country' => 'required|in:DE,AT,CH',
        'terms_accepted' => 'accepted',
        'privacy_accepted' => 'accepted',
    ];

    protected $messages = [
        'email.required' => 'Bitte gib deine E-Mail-Adresse an.',
        'terms_accepted.accepted' => 'Du musst den AGB zustimmen.',
        'privacy_accepted.accepted' => 'Bitte akzeptiere die Datenschutzerklärung.',
    ];

    public function mount()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop');
        }

        // 1. Daten laden: Eingeloggter Kunde
        if (Auth::guard('customer')->check()) {
            $user = Auth::guard('customer')->user();
            $this->email = $user->email;
            $this->first_name = $user->first_name;
            $this->last_name = $user->last_name;

            if ($user->profile) {
                $this->address = $user->profile->street . ' ' . $user->profile->house_number;
                $this->city = $user->profile->city;
                $this->postal_code = $user->profile->postal;
            }
        }
        // 2. Daten laden: Aus Angebot (Quote)
        elseif (Session::has('checkout_from_quote_id')) {
            $quoteId = Session::get('checkout_from_quote_id');
            $quote = QuoteRequest::find($quoteId);

            if ($quote) {
                $this->email = $quote->email;
                $this->first_name = $quote->first_name;
                $this->last_name = $quote->last_name;
                $this->company = $quote->company;
            }
        }

        // Stripe Intent erstellen
        $this->createPaymentIntent($cart);
    }

    public function createPaymentIntent($cart)
    {
        $totals = app(CartService::class)->calculateTotals($cart);
        $amount = $totals['total']; // Betrag in Cent

        if ($amount > 0) {
            $stripeSecret = config('services.stripe.secret');

            if($stripeSecret) {
                Stripe::setApiKey($stripeSecret);

                $intent = PaymentIntent::create([
                    'amount' => $amount,
                    'currency' => 'eur',
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => [
                        'cart_id' => $cart->id,
                        'session_id' => Session::getId(),
                        'customer_email' => $this->email
                    ]
                ]);

                $this->clientSecret = $intent->client_secret;
            }
        }
    }

    /**
     * WICHTIG: Login mit Warenkorb-Mitnahme (Merge)
     * Heißt jetzt "loginUser", so wie du es in der Blade genannt hast.
     */
    public function loginUser()
    {
        $this->validate([
            'loginEmail' => 'required|email',
            'loginPassword' => 'required',
        ]);

        // 1. GAST-CART SICHERN (Bevor Login die Session ID ändert!)
        $guestCart = app(CartService::class)->getCart();

        if (Auth::guard('customer')->attempt(['email' => $this->loginEmail, 'password' => $this->loginPassword])) {

            $user = Auth::guard('customer')->user();

            // 2. USER-CART HOLEN
            $userCart = Cart::firstOrCreate(
                ['customer_id' => $user->id],
                ['session_id' => Session::getId()]
            );

            // 3. MERGE: Items vom Gast-Cart in den User-Cart schieben
            if ($guestCart && $guestCart->id !== $userCart->id) {
                foreach ($guestCart->items as $item) {
                    $item->cart_id = $userCart->id;
                    $item->save();
                }

                if ($guestCart->coupon_code && !$userCart->coupon_code) {
                    $userCart->coupon_code = $guestCart->coupon_code;
                    $userCart->save();
                }

                // Leeren Gast-Cart löschen
                if ($guestCart->items()->count() == 0) {
                    $guestCart->delete();
                }
            }

            // Seite neu laden
            return redirect(request()->header('Referer'));

        } else {
            $this->loginError = 'Zugangsdaten nicht korrekt.';
        }
    }

    public function validateAndCreateOrder()
    {
        $this->validate();
        $orderId = $this->createOrder();

        if ($this->clientSecret) {
            $parts = explode('_secret_', $this->clientSecret);
            if(count($parts) > 0) {
                $intentId = $parts[0];
                $order = Order::find($orderId);
                if($order) {
                    $order->stripe_payment_intent_id = $intentId;
                    $order->save();
                }
            }
        }

        return $orderId;
    }

    private function createOrder()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();
        $totals = $cartService->calculateTotals($cart);

        // --- KUNDEN-LOGIK (Fehler 1452 Fix) ---
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
                'country' => $this->country
            ]);
        }

        $customerId = $customer->id;
        // ---------------------------

        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
            'customer_id' => $customerId,
            'email' => $this->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',

            'billing_address' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'company' => $this->company,
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'city' => $this->city,
                'country' => $this->country,
            ],
            'shipping_address' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'company' => $this->company,
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'city' => $this->city,
                'country' => $this->country,
            ],

            'volume_discount' => $totals['volume_discount'] ?? 0,
            'coupon_code' => $totals['coupon_code'] ?? null,
            'discount_amount' => $totals['discount_amount'] ?? 0,

            'subtotal_price' => $totals['subtotal_gross'],
            'tax_amount' => $totals['tax'],
            'shipping_price' => $totals['shipping'],
            'total_price' => $totals['total'],
        ]);

        foreach($cart->items as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->unit_price * $item->quantity,
                'configuration' => $item->configuration
            ]);
        }

        return $order->id;
    }

    public function render()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        return view('livewire.shop.checkout', [
            'cart' => $cart,
            'totals' => $cartService->calculateTotals($cart)
        ]);
    }
}

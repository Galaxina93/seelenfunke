<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\Cart as CartModel;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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
        'terms_accepted.accepted' => 'Du musst den AGB zustimmen, um fortzufahren.',
        'privacy_accepted.accepted' => 'Bitte akzeptiere die Datenschutzerklärung.',
    ];

    public function mount()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop');
        }

        if (Auth::guard('customer')->check()) {
            $this->fillUserData();
        }

        $this->createPaymentIntent($cartService);
    }

    public function loginUser()
    {
        $this->validate([
            'loginEmail' => 'required|email',
            'loginPassword' => 'required',
        ]);

        // 1. Session retten
        $previousSessionId = Session::getId();

        if (Auth::guard('customer')->attempt(['email' => $this->loginEmail, 'password' => $this->loginPassword])) {
            $this->loginError = '';

            // 2. Session ID aktualisiert
            $currentSessionId = Session::getId();
            $customer = Auth::guard('customer')->user();

            // 3. Warenkorb migrieren
            $guestCart = CartModel::where('session_id', $previousSessionId)->first();

            if ($guestCart) {
                $existingCustomerCart = CartModel::where('customer_id', $customer->id)->first();

                if ($existingCustomerCart) {
                    foreach ($guestCart->items as $item) {
                        $item->update(['cart_id' => $existingCustomerCart->id]);
                    }
                    $guestCart->delete();
                    $existingCustomerCart->update(['session_id' => $currentSessionId]);
                } else {
                    $guestCart->update([
                        'customer_id' => $customer->id,
                        'session_id' => $currentSessionId
                    ]);
                }
            }

            $this->fillUserData();

            // FIX: Validierungsfehler entfernen, da Daten jetzt da sind
            $this->resetValidation();

            $this->createPaymentIntent(app(CartService::class));

            session()->flash('message', 'Erfolgreich angemeldet!');
        } else {
            $this->loginError = 'Die Zugangsdaten sind nicht korrekt.';
        }
    }

    public function fillUserData()
    {
        $user = Auth::guard('customer')->user();
        $profile = $user->profile;

        $this->email = $user->email;
        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;

        if ($profile) {
            $this->address = $profile->street ?? '';
            if(!empty($profile->house_number)) {
                $this->address .= ' ' . $profile->house_number;
            }
            $this->postal_code = $profile->postal ?? '';
            $this->city = $profile->city ?? '';
        }

        // FIX: Auch hier sicherheitshalber Fehler löschen
        $this->resetValidation();
    }

    public function createPaymentIntent(CartService $cartService)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $totals = $cartService->getTotals();
        $amount = max(50, $totals['total']);

        $paymentIntent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'cart_id' => $cartService->getCart()->id,
            ],
        ]);

        $this->clientSecret = $paymentIntent->client_secret;
    }

    public function validateAndCreateOrder()
    {
        $this->validate();

        $cartService = app(CartService::class);
        $cart = $cartService->getCart();
        $totals = $cartService->getTotals();

        if($cart->items->isEmpty()) return null;

        $stripeId = null;
        if($this->clientSecret) {
            $parts = explode('_secret_', $this->clientSecret);
            $stripeId = $parts[0] ?? null;
        }

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => Auth::guard('customer')->id(),
            'email' => $this->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'stripe_payment_intent_id' => $stripeId,
            'billing_address' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'company' => $this->company,
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'city' => $this->city,
                'country' => $this->country,
            ],
            'subtotal_price' => $totals['subtotal_gross'],
            'tax_amount' => $totals['tax'],
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
        return view('livewire.shop.checkout', [
            'totals' => $cartService->getTotals(),
            'cart' => $cartService->getCart()
        ]);
    }
}

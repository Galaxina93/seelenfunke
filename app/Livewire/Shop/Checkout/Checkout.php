<?php

namespace App\Livewire\Shop\Checkout;

use App\Livewire\Shop\Checkout\Traits\HandlesOrderCreation;
use App\Livewire\Shop\Checkout\Traits\HandlesStripePayment;
use App\Models\Cart\Cart;
use App\Models\Quote\QuoteRequest;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class Checkout extends Component
{
    use HandlesStripePayment, HandlesOrderCreation;

    // --- BESTELLDATEN ---
    public $email;
    public $first_name;
    public $last_name;
    public $company;
    public $address;
    public $city;
    public $country = 'DE'; // Standardland
    public $postal_code;

    // --- LIEFERDATEN ---
    public $has_separate_shipping = false;
    public $shipping_first_name;
    public $shipping_last_name;
    public $shipping_company;
    public $shipping_address;
    public $shipping_city;
    public $shipping_postal_code;
    public $shipping_country = 'DE';

    // --- RECHTLICHES ---
    public $terms_accepted = false;
    public $privacy_accepted = false;

    // --- LOGIN ---
    public $loginEmail = '';
    public $loginPassword = '';
    public $loginError = '';

    // --- STRIPE & CONFIG ---
    public $clientSecret;
    public $stripeKey;
    public $currentPaymentIntentId;

    // --- UI STATE ---
    public $isFinished = false;
    public $finalOrderNumber = '';

    /**
     * Schöne Namen für die Felder definieren
     */
    protected function validationAttributes()
    {
        return [
            'email' => 'E-Mail-Adresse',
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'address' => 'Straße und Hausnummer',
            'city' => 'Stadt',
            'postal_code' => 'Postleitzahl',
            'country' => 'Land',
            'shipping_first_name' => 'Vorname (Lieferadresse)',
            'shipping_last_name' => 'Nachname (Lieferadresse)',
            'shipping_address' => 'Straße (Lieferadresse)',
            'shipping_city' => 'Stadt (Lieferadresse)',
            'shipping_postal_code' => 'PLZ (Lieferadresse)',
            'shipping_country' => 'Land (Lieferadresse)',
        ];
    }

    // --- REGELN ---
    protected function rules()
    {
        // Wir holen die erlaubten Länder dynamisch aus der Config, falls vorhanden
        $allowedCountries = implode(',', array_keys(shop_setting('active_countries', ['DE' => 'Deutschland'])));

        $rules = [
            'email' => 'required|email',
            'first_name' => 'required|string|min:2',
            'last_name' => 'required|string|min:2',
            'address' => 'required|string|min:5',
            'city' => 'required|string|min:2',
            'postal_code' => 'required|string|min:4',
            'country' => 'required|in:' . $allowedCountries,
            'terms_accepted' => 'accepted',
            'privacy_accepted' => 'accepted',
        ];

        // Falls abweichende Lieferadresse aktiv ist, diese Felder validieren
        if ($this->has_separate_shipping) {
            $rules['shipping_first_name'] = 'required|string|min:2';
            $rules['shipping_last_name'] = 'required|string|min:2';
            $rules['shipping_address'] = 'required|string|min:5';
            $rules['shipping_city'] = 'required|string|min:2';
            $rules['shipping_postal_code'] = 'required|string|min:4';
            $rules['shipping_country'] = 'required|in:' . $allowedCountries;
        }

        return $rules;
    }

    protected $messages = [
        // Rechnungsadresse
        'email.required' => 'Bitte gib deine E-Mail-Adresse für die Bestellbestätigung an.',
        'email.email' => 'Die eingegebene E-Mail-Adresse ist nicht gültig.',
        'first_name.required' => 'Dein Vorname wird für die Rechnung benötigt.',
        'last_name.required' => 'Dein Nachname wird für die Rechnung benötigt.',
        'address.required' => 'Bitte gib deine vollständige Anschrift (Straße/Nr.) an.',
        'city.required' => 'Die Angabe deiner Stadt fehlt.',
        'postal_code.required' => 'Bitte gib deine Postleitzahl an.',
        'country.required' => 'Bitte wähle ein Land aus.',
        'country.in' => 'Wir liefern leider nicht in das ausgewählte Land.',

        // Lieferadresse
        'shipping_first_name.required' => 'Bitte gib den Vornamen des Empfängers an.',
        'shipping_last_name.required' => 'Bitte gib den Nachnamen des Empfängers an.',
        'shipping_address.required' => 'Die Lieferanschrift (Straße/Nr.) wird benötigt.',
        'shipping_city.required' => 'Die Angabe der Stadt für die Lieferung fehlt.',
        'shipping_postal_code.required' => 'Bitte gib die Postleitzahl für den Versand an.',
        'shipping_country.in' => 'Versand in dieses Land ist aktuell nicht möglich.',

        // Rechtliches
        'terms_accepted.accepted' => 'Bitte bestätige, dass du die AGB gelesen hast.',
        'privacy_accepted.accepted' => 'Deine Zustimmung zur Datenschutzerklärung ist erforderlich.',
    ];

    public function mount()
    {
        $this->stripeKey = config('services.stripe.key');

        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        // Leeren Warenkorb abfangen
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

                $activeCountries = shop_setting('active_countries', ['DE' => 'Deutschland']);

                // Sicherer Zugriff: Wir prüfen, ob 'country' im Profil existiert UND in den Shop-Settings aktiv ist
                if (!empty($user->profile->country) && array_key_exists($user->profile->country, $activeCountries)) {
                    $this->country = $user->profile->country;
                } else {
                    $this->country = array_key_exists('DE', $activeCountries) ? 'DE' : array_key_first($activeCountries);
                }
            }
        }

        // 2. Daten laden: Aus Angebot (Quote) falls vorhanden
        elseif (Session::has('checkout_from_quote_id')) {
            $quoteId = Session::get('checkout_from_quote_id');
            $quote = QuoteRequest::find($quoteId);

            if ($quote) {
                $this->email = $quote->email;
                // Namen trennen falls nötig oder direkt übernehmen
                if($quote->customer) {
                    $this->first_name = $quote->customer->first_name;
                    $this->last_name = $quote->customer->last_name;
                }
                $this->company = $quote->company;
            }
        }

        // Standardmäßig Lieferland an Rechnungsland angleichen
        $this->shipping_country = $this->country;

        // Stripe Intent sofort erstellen, damit das Payment Element laden kann
        $this->createPaymentIntent();
    }

    /**
     * WIRD AUFGERUFEN, WENN SICH DAS LAND ÄNDERT (wire:model.live="country")
     * Aktualisiert die Versandkosten und den Stripe Intent.
     */
    public function updated($propertyName)
    {
        // Diese Methode wird bei jeder Änderung gefeuert.
        // Wir validieren nur das geänderte Feld für Echtzeit-Feedback
        $this->validateOnly($propertyName);

        // Wir benachrichtigen das Frontend, dass sich Daten geändert haben könnten
        $this->dispatch('checkout-updated');
    }

    public function updatedCountry()
    {
        // Falls keine separate Lieferadresse, muss das Lieferland dem Rechnungsland folgen (für Versandkosten)
        if (!$this->has_separate_shipping) {
            $this->shipping_country = $this->country;
        }

        // 1. Stripe Intent aktualisieren (wegen neuem Gesamtbetrag durch Versand)
        $this->createPaymentIntent();
        $this->dispatch('checkout-updated');
    }

    public function updatedShippingCountry()
    {
        // Wenn sich das Lieferland ändert, müssen die Versandkosten neu berechnet werden
        $this->createPaymentIntent();
        $this->dispatch('checkout-updated');
    }

    public function updatedHasSeparateShipping($value)
    {
        // Wenn deaktiviert, Lieferland wieder auf Rechnungsland setzen
        if (!$value) {
            $this->shipping_country = $this->country;
            $this->createPaymentIntent();
            $this->dispatch('checkout-updated');
        }
    }

    /**
     * Login direkt im Checkout
     */
    public function loginUser()
    {
        $this->validate([
            'loginEmail' => 'required|email',
            'loginPassword' => 'required',
        ]);

        $cartService = app(CartService::class);
        $guestCart = $cartService->getCart();
        $wasExpress = $guestCart->is_express;

        if (Auth::guard('customer')->attempt(['email' => $this->loginEmail, 'password' => $this->loginPassword])) {

            $user = Auth::guard('customer')->user();

            $userCart = Cart::firstOrCreate(
                ['customer_id' => $user->id]
            );

            $userCart->update(['session_id' => Session::getId()]);

            if ($guestCart && $guestCart->id !== $userCart->id) {
                foreach ($guestCart->items as $item) {
                    $item->update(['cart_id' => $userCart->id]);
                }

                if ($guestCart->coupon_code && !$userCart->coupon_code) {
                    $userCart->update(['coupon_code' => $guestCart->coupon_code]);
                }

                $guestCart->delete();
            }

            if ($wasExpress) {
                $userCart->update(['is_express' => true]);
            }

            $this->mount();
            $this->loginError = '';

            $this->dispatch('checkout-updated');

        } else {
            $this->loginError = 'Zugangsdaten nicht korrekt.';
        }
    }

    public function render()
    {
        if ($this->isFinished) {
            return view('livewire.shop.checkout.checkout-success');
        }

        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        $targetCountry = $this->has_separate_shipping ? $this->shipping_country : $this->country;
        $totals = $cartService->calculateTotals($cart, $targetCountry);

        return view('livewire.shop.checkout.checkout', [
            'cart' => $cart,
            'totals' => $totals,
            'countries' => shop_setting('active_countries', ['DE' => 'Deutschland'])
        ])->layout('components.layouts.frontend_layout');
    }
}

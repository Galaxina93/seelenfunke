<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\Cart;
use App\Models\QuoteRequest;
use App\Models\Customer;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log; // [NEU] Wichtig für Debugging
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
    public $country = 'DE'; // Standardland
    public $postal_code;

    // --- LIEFERDATEN (NEU) ---
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

    // --- LOGIN (für Gäste die sich im Checkout einloggen wollen) ---
    public $loginEmail = '';
    public $loginPassword = '';
    public $loginError = '';

    // --- STRIPE & CONFIG ---
    public $clientSecret;
    public $stripeKey;

    // [FIX] Neue Variable, um die ID (pi_...) explizit zu speichern
    public $currentPaymentIntentId;

    // [NEU] State für die Erfolgsmeldung
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
                    /** * Fallback-Strategie:
                     * 1. Deutschland, falls du es belieferst (da Seelenfunke in DE sitzt)
                     * 2. Sonst einfach das erste Land deiner aktiven Liste
                     */
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
                } else {
                    // Fallback falls Name im Quote Objekt anders gespeichert ist
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
     * Erstellt oder aktualisiert den PaymentIntent bei Stripe
     */
    public function createPaymentIntent()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        // WICHTIG: Versandkosten basieren IMMER auf dem Lieferland
        $targetCountry = $this->has_separate_shipping ? $this->shipping_country : $this->country;
        $totals = $cartService->calculateTotals($cart, $targetCountry);

        $amount = $totals['total']; // Betrag in Cent

        if ($amount > 0) {
            $stripeSecret = config('services.stripe.secret');
            if($stripeSecret) {
                Stripe::setApiKey($stripeSecret);

                // Metadaten vorbereiten
                $metadata = [
                    'cart_id' => $cart->id,
                    'session_id' => Session::getId(),
                    'customer_email' => $this->email,
                    'shipping_country' => $targetCountry
                ];

                $intentData = [
                    'amount' => $amount,
                    'currency' => 'eur',
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => $metadata
                ];

                // OPTIONAL: Wenn wir schon einen Intent haben, könnten wir ihn updaten.
                // Der Einfachheit halber erstellen wir hier einen neuen.
                $intent = PaymentIntent::create($intentData);

                $this->clientSecret = $intent->client_secret;

                // [FIX] Hier speichern wir die ID sofort in die Variable
                $this->currentPaymentIntentId = $intent->id;

                return $this->clientSecret;
            }
        }
        return null;
    }

    /**
     * Wird vom Frontend aufgerufen, wenn Stripe die Zahlung bestätigt hat.
     */
    public function handlePaymentSuccess($orderId = null)
    {
        // 1. Die richtige Order finden
        $order = $orderId ? Order::with('items.product')->find($orderId) : null;

        if (!$order) {
            $order = Order::with('items.product')
                ->where('stripe_payment_intent_id', $this->currentPaymentIntentId)
                ->where('status', 'pending')
                ->latest()
                ->first();
        }

        if ($order) {
            // 2. Status & Zahlung der Bestellung aktualisieren
            $order->update([
                'payment_status' => 'paid',
                'status' => 'pending'
            ]);

            // --- LAGERBESTAND REDUZIEREN ---
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->reduceStock($item->quantity);
                }
            }

            $this->finalOrderNumber = $order->order_number;

            // --- [NEU] VERKNÜPFUNG ZUM ANGEBOT (QUOTE) ---
            if (Session::has('checkout_from_quote_id')) {
                $quoteId = Session::get('checkout_from_quote_id');
                $quote = QuoteRequest::find($quoteId);

                if ($quote) {
                    $quote->update([
                        'status' => 'converted',
                        'converted_order_id' => $order->id
                    ]);

                    Session::forget('checkout_from_quote_id');

                    \Illuminate\Support\Facades\Log::info("Angebot {$quote->quote_number} wurde erfolgreich in Order {$order->order_number} umgewandelt.");
                }
            }

            // 3. Rechnung & PDF zentral erstellen
            $pdfPath = null;
            try {
                $invoiceService = app(\App\Services\InvoiceService::class);
                $invoice = $invoiceService->createFromOrder($order);
                $pdfPath = storage_path("app/public/invoices/{$invoice->invoice_number}.pdf");

                if ($invoice && !file_exists($pdfPath)) {
                    $pdf = $invoiceService->generatePdf($invoice);
                    if (!file_exists(dirname($pdfPath))) {
                        mkdir(dirname($pdfPath), 0755, true);
                    }
                    file_put_contents($pdfPath, $pdf->output());
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Rechnungserstellung fehlgeschlagen für {$order->order_number}: " . $e->getMessage());
            }

            // 4. Mails versenden mit zentralisierten Daten
            try {
                $mailData = $order->toFormattedArray();

                // A) Bestätigung an Kunden (JETZT MIT PDF ANHANG)
                \Illuminate\Support\Facades\Mail::to($order->email)
                    ->send(new \App\Mail\OrderMailToCustomer($mailData, $pdfPath));

                // B) Arbeits-Anfrage an Admin (Dich)
                \Illuminate\Support\Facades\Mail::to('kontakt@mein-seelenfunke.de')
                    ->send(new \App\Mail\OrderMailToAdmin($mailData, $pdfPath));

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Checkout Mail Fehler für {$order->order_number}: " . $e->getMessage());
            }
        }

        // 5. UI umschalten & Cleanup
        $this->isFinished = true;

        $cartService = app(CartService::class);
        $cart = $cartService->getCart();
        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }

        $this->dispatch('cart-updated');
        $this->dispatch('payment-processed');
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

    /**
     * Wird vom Frontend aufgerufen, BEVOR Stripe bestätigt wird.
     */
    public function validateAndCreateOrder()
    {
        $this->validate();
        $orderId = $this->createOrderInDb();
        return $orderId;
    }

    /**
     * Private Helper: Die eigentliche Order-Erstellung inkl. User-Logik
     */
    private function createOrderInDb()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        $targetCountry = $this->has_separate_shipping ? $this->shipping_country : $this->country;
        $totals = $cartService->calculateTotals($cart, $targetCountry);

        $finalIntentId = $this->currentPaymentIntentId;

        if (empty($finalIntentId) && !empty($this->clientSecret)) {
            $parts = explode('_secret_', $this->clientSecret);
            $finalIntentId = $parts[0] ?? null;
        }

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
                'country' => $this->country,
            ]);
        }

        $customerId = $customer->id;

        $shipping_data = $this->has_separate_shipping ? [
            'first_name' => $this->shipping_first_name,
            'last_name'  => $this->shipping_last_name,
            'company'    => $this->shipping_company,
            'address'    => $this->shipping_address,
            'postal_code'=> $this->shipping_postal_code,
            'city'       => $this->shipping_city,
            'country'    => $this->shipping_country,
        ] : [
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'company'    => $this->company,
            'address'    => $this->address,
            'postal_code'=> $this->postal_code,
            'city'       => $this->city,
            'country'    => $this->country,
        ];

        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
            'customer_id' => $customerId,
            'email' => $this->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',

            'stripe_payment_intent_id' => $finalIntentId,

            'billing_address' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'company' => $this->company,
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'city' => $this->city,
                'country' => $this->country,
            ],
            'shipping_address' => $shipping_data,

            'volume_discount' => $totals['volume_discount'] ?? 0,
            'coupon_code' => $totals['coupon_code'] ?? null,
            'discount_amount' => $totals['discount_amount'] ?? 0,

            'subtotal_price' => $totals['subtotal_gross'],
            'tax_amount' => $totals['tax'],
            'shipping_price' => $totals['shipping'],
            'total_price' => $totals['total'],
        ]);

        foreach($cart->items as $item) {

            $configFingerprint = !empty($item->configuration)
                ? hash('sha256', json_encode($item->configuration))
                : null;

            $order->items()->create([
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->unit_price * $item->quantity,
                'configuration' => $item->configuration,
                'config_fingerprint' => $configFingerprint
            ]);
        }

        $cart->items()->delete();
        $cart->delete();

        return $order->id;
    }

    public function render()
    {
        if ($this->isFinished) {
            return view('livewire.shop.checkout-success');
        }

        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        $targetCountry = $this->has_separate_shipping ? $this->shipping_country : $this->country;
        $totals = $cartService->calculateTotals($cart, $targetCountry);

        return view('livewire.shop.checkout', [
            'cart' => $cart,
            'totals' => $totals,
            'countries' => shop_setting('active_countries', ['DE' => 'Deutschland'])
        ]);
    }
}

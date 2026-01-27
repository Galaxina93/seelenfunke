<?php

namespace App\Livewire\Shop;

use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\QuoteRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class QuoteAcceptance extends Component
{
    public $token;
    public $quote;

    // UI States
    public $viewState = 'dashboard'; // 'dashboard', 'success_accepted', 'success_rejected', 'error'
    public $errorMessage = '';

    public function mount($token)
    {
        $this->token = $token;
        $this->quote = QuoteRequest::where('token', $token)->with('items.product')->first();

        if (!$this->quote) {
            $this->viewState = 'error';
            $this->errorMessage = 'Angebot nicht gefunden.';
            return;
        }

        // Grundlegende Checks (nur Warnungen, Blockade erst bei Aktion)
        if ($this->quote->expires_at->isPast() && $this->quote->status === 'open') {
            $this->errorMessage = 'Dieses Angebot ist leider abgelaufen.';
            $this->viewState = 'error';
        }
    }

    /**
     * Angebot annehmen -> Bestellung erstellen
     */
    public function acceptQuote()
    {
        if (!$this->isValidAction()) return;

        // 1. Kunde finden oder erstellen
        $customer = Customer::firstOrCreate(
            ['email' => $this->quote->email],
            [
                'first_name' => $this->quote->first_name,
                'last_name' => $this->quote->last_name,
                'password' => bcrypt(Str::random(16)),
            ]
        );

        $customer->profile()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['phone_number' => $this->quote->phone]
        );

        // 2. Order erstellen
        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'email' => $this->quote->email,
            'status' => 'processing',
            'payment_status' => 'unpaid',
            'billing_address' => [
                'first_name' => $this->quote->first_name,
                'last_name' => $this->quote->last_name,
                'company' => $this->quote->company,
                'address' => 'Adresse folgt',
                'postal_code' => '',
                'city' => '',
                'country' => 'DE'
            ],
            'subtotal_price' => $this->quote->net_total,
            'tax_amount' => $this->quote->tax_total,
            'total_price' => $this->quote->gross_total,
            'notes' => 'Automatisch erstellt durch Angebotsannahme (' . $this->quote->quote_number . ').',
        ]);

        // 3. Items übertragen
        foreach($this->quote->items as $qItem) {
            $order->items()->create([
                'product_id' => $qItem->product_id,
                'product_name' => $qItem->product_name,
                'quantity' => $qItem->quantity,
                'unit_price' => $qItem->unit_price,
                'total_price' => $qItem->total_price,
                'configuration' => $qItem->configuration,
            ]);
        }

        // 4. Update Quote
        $this->quote->update([
            'status' => 'converted',
            'converted_order_id' => $order->id
        ]);

        // 5. Mail
        try {
            Mail::to($order->email)->send(new OrderConfirmation($order));
        } catch (\Exception $e) {}

        $this->viewState = 'success_accepted';
    }

    /**
     * Angebot ablehnen
     */
    public function rejectQuote()
    {
        if ($this->quote->status !== 'open') return;

        $this->quote->update(['status' => 'rejected']);
        $this->viewState = 'success_rejected';
    }

    /**
     * Angebot bearbeiten -> Lädt Daten in den Calculator
     */
    public function editQuote()
    {
        // Wir dürfen auch abgelaufene oder abgelehnte Angebote als Vorlage zum Bearbeiten nutzen!

        $cartItems = [];

        foreach($this->quote->items as $item) {
            // Wir müssen die Struktur exakt so nachbauen, wie der Calculator sie erwartet
            $product = $item->product; // Relation

            // Wenn Produkt gelöscht wurde, überspringen wir es sicherheitshalber
            if (!$product) continue;

            // Preis-Rekonstruktion
            // Wir nehmen hier die aktuellen Preise aus der DB oder die alten aus dem Angebot?
            // Besser: Wir nehmen das Produkt neu aus der DB, damit Preise aktuell sind.
            // ABER: Die Konfiguration wird übernommen.

            $cartItems[] = [
                'row_id' => Str::uuid()->toString(),
                'product_id' => $item->product_id,
                'name' => $product->name, // Name frisch aus DB
                'image_ref' => !empty($product->media_gallery[0]['path']) ? 'storage/'.$product->media_gallery[0]['path'] : null,
                'qty' => $item->quantity,
                'text' => $item->configuration['text'] ?? '',
                'configuration' => $item->configuration, // Das ist der wichtige Teil!
                'preview_ref' => $product->preview_image_path ? 'storage/'.$product->preview_image_path : null,

                // Preise werden vom Calculator beim Mounten eh neu berechnet anhand der Quantity
                'calculated_single_price' => 0,
                'calculated_total' => 0
            ];
        }

        // Formulardaten wiederherstellen
        $formData = [
            'vorname' => $this->quote->first_name,
            'nachname' => $this->quote->last_name,
            'firma' => $this->quote->company,
            'email' => $this->quote->email,
            'telefon' => $this->quote->phone,
            'anmerkung' => $this->quote->admin_notes // Oder leer lassen, je nach Wunsch
        ];

        // Session füllen
        session()->put('calc_cart', $cartItems);
        session()->put('calc_form', $formData);

        // Weiterleitung zum Calculator
        return redirect()->route('calculator');
    }

    private function isValidAction()
    {
        if ($this->quote->status === 'converted') {
            $this->errorMessage = 'Dieses Angebot wurde bereits angenommen.';
            $this->viewState = 'error';
            return false;
        }
        if ($this->quote->status === 'rejected') {
            $this->errorMessage = 'Dieses Angebot wurde abgelehnt.';
            $this->viewState = 'error';
            return false;
        }
        if ($this->quote->expires_at->isPast()) {
            $this->errorMessage = 'Das Angebot ist abgelaufen.';
            $this->viewState = 'error';
            return false;
        }
        return true;
    }

    public function render()
    {
        // Layout explizit setzen, um Fehler zu vermeiden
        return view('livewire.shop.quote-acceptance')
            ->layout('components.layouts.frontend_layout');
    }
}

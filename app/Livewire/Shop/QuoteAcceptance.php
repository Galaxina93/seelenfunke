<?php

namespace App\Livewire\Shop;

use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\QuoteRequest;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
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
     * Leitet den Kunden zum Checkout weiter, um direkt zu bezahlen.
     */
    public function proceedToCheckout(CartService $cartService)
    {
        if (!$this->isValidAction()) return;

        // 1. Aktuellen Warenkorb holen und leeren
        $cart = $cartService->getCart();
        $cart->items()->delete();

        // 2. Items aus dem Angebot in den Warenkorb übertragen
        foreach($this->quote->items as $qItem) {
            if($qItem->product) {
                // addItem Methode nutzen, damit Preise/Steuern aktuell berechnet werden
                $cartService->addItem(
                    $qItem->product,
                    $qItem->quantity,
                    $qItem->configuration
                );
            }
        }

        // 3. Merken, dass wir aus einem Angebot kommen
        // Das ist wichtig für Checkout (Adressen) und Success (Status-Update)
        Session::put('checkout_from_quote_id', $this->quote->id);

        // 4. Weiterleitung zum Checkout
        return redirect()->route('checkout');
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
        $cartItems = [];

        foreach($this->quote->items as $item) {
            $product = $item->product;

            // Falls Produkt gelöscht, überspringen
            if (!$product) continue;

            // Struktur für den Calculator vorbereiten
            $cartItems[] = [
                'row_id' => Str::uuid()->toString(),
                'product_id' => $item->product_id,
                'name' => $product->name,
                'image_ref' => !empty($product->media_gallery[0]['path']) ? 'storage/'.$product->media_gallery[0]['path'] : null,
                'qty' => $item->quantity,
                'text' => $item->configuration['text'] ?? '',
                'configuration' => $item->configuration,
                'preview_ref' => $product->preview_image_path ? 'storage/'.$product->preview_image_path : null,
                // Preise werden vom Calculator neu berechnet
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
            'anmerkung' => $this->quote->admin_notes
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

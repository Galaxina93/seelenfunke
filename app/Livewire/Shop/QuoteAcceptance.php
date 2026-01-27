<?php

namespace App\Livewire\Shop;

use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestItem;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\On;

class QuoteAcceptance extends Component
{
    public $token;
    public $quote;

    // UI States
    public $viewState = 'dashboard'; // 'dashboard', 'editor', 'success_rejected', 'error'
    public $errorMessage = '';

    // Editing State
    public $editingItem = null; // Das QuoteRequestItem Model

    public function mount($token)
    {
        $this->token = $token;
        $this->refreshQuote();

        if (!$this->quote) {
            $this->viewState = 'error';
            $this->errorMessage = 'Angebot nicht gefunden.';
            return;
        }

        // Warnung bei Ablauf, aber Zugriff erlauben solange Status 'open'
        if ($this->quote->expires_at->isPast() && $this->quote->status === 'open') {
            $this->errorMessage = 'Dieses Angebot ist leider abgelaufen.';
            $this->viewState = 'error';
        }
    }

    public function refreshQuote()
    {
        $this->quote = QuoteRequest::where('token', $this->token)
            ->with(['items.product'])
            ->first();
    }

    /**
     * Startet den Editor für ein spezifisches Item
     */
    public function editItem($itemId)
    {
        if (!$this->quote->isValid()) return;

        $this->editingItem = $this->quote->items->find($itemId);

        if ($this->editingItem && $this->editingItem->product) {
            $this->viewState = 'editor';
        }
    }

    /**
     * Bricht das Bearbeiten ab
     */
    public function cancelEdit()
    {
        $this->editingItem = null;
        $this->viewState = 'dashboard';
    }

    /**
     * Speichert Änderungen aus dem Configurator
     * Wird per Event vom Configurator aufgerufen (context="calculator")
     */
    #[On('calculator-save')]
    public function saveItem($data)
    {
        if (!$this->editingItem) return;

        // 1. Konfiguration & Menge updaten
        $this->editingItem->quantity = $data['qty'];
        $this->editingItem->configuration = $data; // Speichert Text, Pfade, Positionen etc.

        // 2. Einzelpreis neu berechnen (Staffelpreise beachten!)
        $product = $this->editingItem->product;
        $unitPriceCents = $this->calculateTierPrice($product, $data['qty']);

        $this->editingItem->unit_price = $unitPriceCents;
        $this->editingItem->total_price = $unitPriceCents * $data['qty'];
        $this->editingItem->save();

        // 3. Gesamtangebot neu berechnen
        $this->recalculateQuoteTotals();

        // 4. Zurück zum Dashboard
        $this->refreshQuote(); // Daten neu laden
        $this->viewState = 'dashboard';
        $this->editingItem = null;

        session()->flash('success', 'Position erfolgreich aktualisiert.');
    }

    /**
     * Hilfsfunktion: Staffelpreis berechnen (Kopie der Logik aus Calculator/Product)
     */
    private function calculateTierPrice($product, $qty)
    {
        $basePrice = $product->price; // Cents
        $tiers = $product->tier_pricing ?? [];

        if (!empty($tiers) && is_array($tiers)) {
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']);
            foreach ($tiers as $tier) {
                if ($qty >= $tier['qty']) {
                    $discount = $basePrice * ($tier['percent'] / 100);
                    return (int) round($basePrice - $discount);
                }
            }
        }
        return $basePrice;
    }

    /**
     * Hilfsfunktion: Summen des Angebots neu berechnen
     */
    private function recalculateQuoteTotals()
    {
        $netTotal = 0;
        $taxTotal = 0;

        foreach($this->quote->items as $item) {
            $product = $item->product;
            if(!$product) continue;

            $lineTotal = $item->total_price; // Ist bereits berechnet (Menge * Einzel)

            // Steuer herausrechnen
            $rate = $product->tax_rate ?? 19.0;

            if ($product->tax_included) {
                $lineNet = $lineTotal / (1 + ($rate / 100));
                $lineTax = $lineTotal - $lineNet;
            } else {
                $lineNet = $lineTotal;
                $lineTax = $lineNet * ($rate / 100);
            }

            $netTotal += $lineNet;
            $taxTotal += $lineTax;
        }

        // Express
        if ($this->quote->is_express) {
            $expressNet = 2500;
            $expressTax = $expressNet * 0.19;
            $netTotal += $expressNet;
            $taxTotal += $expressTax;
        }

        $grossTotal = $netTotal + $taxTotal;

        $this->quote->update([
            'net_total' => (int) round($netTotal),
            'tax_total' => (int) round($taxTotal),
            'gross_total' => (int) round($grossTotal),
        ]);
    }

    /**
     * Checkout Logik (Unverändert)
     */
    public function proceedToCheckout(CartService $cartService)
    {
        if (!$this->isValidAction()) return;

        $cart = $cartService->getCart();
        $cart->items()->delete();

        foreach($this->quote->items as $qItem) {
            if($qItem->product) {
                $cartService->addItem(
                    $qItem->product,
                    $qItem->quantity,
                    $qItem->configuration
                );
            }
        }

        Session::put('checkout_from_quote_id', $this->quote->id);
        return redirect()->route('checkout');
    }

    public function rejectQuote()
    {
        if ($this->quote->status !== 'open') return;
        $this->quote->update(['status' => 'rejected']);
        $this->viewState = 'success_rejected'; // Fehlerstatus nutzen oder neuen ViewState
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
        return view('livewire.shop.quote-acceptance')
            ->layout('components.layouts.frontend_layout');
    }
}

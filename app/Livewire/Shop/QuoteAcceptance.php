<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestItem;
use App\Models\ShippingZone; // Wichtig für Versandberechnung
use App\Services\CartService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
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
    public $editingItem = null; // Das QuoteRequestItem Model, das gerade bearbeitet wird

    public function mount($token)
    {
        $this->token = $token;
        $this->refreshQuote();

        if (!$this->quote) {
            $this->viewState = 'error';
            $this->errorMessage = 'Angebot nicht gefunden.';
            return;
        }

        // Warnung bei Ablauf
        if ($this->quote->expires_at->isPast() && $this->quote->status === 'open') {
            // Wir lassen den User drauf, aber sperren Buttons (handled in view/isValidAction)
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
     */
    #[On('calculator-save')]
    public function saveItem($data)
    {
        if (!$this->editingItem) return;

        // 1. Konfiguration & Menge updaten
        $this->editingItem->quantity = $data['qty'];
        $this->editingItem->configuration = $data;

        // 2. Einzelpreis neu berechnen (Staffelpreise beachten!)
        $product = $this->editingItem->product;
        $unitPriceCents = $this->calculateTierPrice($product, $data['qty']);

        $this->editingItem->unit_price = $unitPriceCents;
        $this->editingItem->total_price = $unitPriceCents * $data['qty'];
        $this->editingItem->save();

        // 3. Gesamtangebot neu berechnen (INKL. VERSAND & STEUERN)
        $this->recalculateQuoteTotals();

        // 4. Zurück zum Dashboard
        $this->refreshQuote();
        $this->viewState = 'dashboard';
        $this->editingItem = null;

        session()->flash('success', 'Position erfolgreich aktualisiert und Angebot neu berechnet.');
    }

    /**
     * Hilfsfunktion: Staffelpreis berechnen
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
     * HAUPTLOGIK: Summen des Angebots neu berechnen
     * Behebt das Problem, dass Versandkosten verschwinden.
     */
    private function recalculateQuoteTotals()
    {
        $netTotalProducts = 0; // Nur Produkte Netto
        $taxTotalProducts = 0; // Nur Produkte Steuer
        $grossTotalProducts = 0; // Nur Produkte Brutto (für Versandfreigrenze)

        $totalWeight = 0; // Gesamtgewicht für Versandberechnung

        // Shop-Einstellungen laden
        $isSmallBusiness = (bool) shop_setting('is_small_business', false);
        $defaultTaxRate = (float) shop_setting('default_tax_rate', 19.0);

        // 1. Produkte durchgehen
        foreach($this->quote->items as $item) {
            $product = $item->product;
            if(!$product) continue;

            // Gewicht summieren
            $weight = $product->weight ?? 0;
            $totalWeight += ($weight * $item->quantity);

            $lineTotal = $item->total_price;
            $rate = $product->tax_rate ?? $defaultTaxRate;

            if ($product->tax_included) {
                // Brutto -> Netto
                $lineGross = $lineTotal;
                $lineNet = $lineTotal / (1 + ($rate / 100));
                $lineTax = $lineTotal - $lineNet;
            } else {
                // Netto -> Brutto
                $lineNet = $lineTotal;
                $lineTax = $lineNet * ($rate / 100);
                $lineGross = $lineNet + $lineTax;
            }

            $netTotalProducts += $lineNet;
            $taxTotalProducts += $lineTax;
            $grossTotalProducts += $lineGross;
        }

        // 2. Versandberechnung
        // Wir nutzen die neue Adress-Struktur (Lieferadresse vor Rechnungsadresse)
        $countryCode = $this->quote->shipping_address['country'] ?? ($this->quote->billing_address['country'] ?? 'DE');

        $shippingCostCents = 0;

        // Logik analog zum Calculator
        if ($countryCode === 'DE') {
            // Regel: Kostenfrei ab 50,00 € (Brutto-Warenwert), sonst 4,90 €
            if (($grossTotalProducts / 100) >= 50.00 || $this->quote->items->isEmpty()) {
                $shippingCostCents = 0;
            } else {
                $shippingCostCents = 490;
            }
        } else {
            // Ausland: Zone suchen
            $zone = ShippingZone::whereHas('countries', function($q) use ($countryCode) {
                $q->where('country_code', $countryCode);
            })->with('rates')->first();

            if (!$zone) {
                $zone = ShippingZone::where('name', 'Weltweit')->with('rates')->first();
            }

            if ($zone && !$this->quote->items->isEmpty()) {
                $shippingRate = $zone->rates()
                    ->where(function($q) use ($totalWeight) {
                        $q->where('min_weight', '<=', $totalWeight)
                            ->where(function($sub) use ($totalWeight) {
                                $sub->where('max_weight', '>=', $totalWeight)
                                    ->orWhereNull('max_weight');
                            });
                    })
                    ->orderBy('price', 'asc')
                    ->first();

                if ($shippingRate) {
                    $shippingCostCents = $shippingRate->price;
                } else {
                    $shippingCostCents = 2990; // Fallback
                }
            } else {
                $shippingCostCents = 2990; // Fallback
            }
        }

        // Steuer auf Versand berechnen
        $euCountries = ['DE', 'AT', 'FR', 'NL', 'BE', 'IT', 'ES', 'PL', 'CZ', 'DK', 'SE', 'FI', 'GR', 'PT', 'IE', 'LU', 'HU', 'SI', 'SK', 'EE', 'LV', 'LT', 'CY', 'MT', 'HR', 'BG', 'RO'];

        $shippingNet = 0;
        $shippingTax = 0;

        if ($shippingCostCents > 0) {
            if (in_array($countryCode, $euCountries) && !$isSmallBusiness) {
                $shippingNet = $shippingCostCents / (1 + ($defaultTaxRate / 100));
                $shippingTax = $shippingCostCents - $shippingNet;
            } else {
                $shippingNet = $shippingCostCents;
                $shippingTax = 0;
            }
        }

        // 3. Express
        $expressNet = 0;
        $expressTax = 0;

        if ($this->quote->is_express) {
            $expressGross = (int) shop_setting('express_surcharge', 2500);
            if (in_array($countryCode, $euCountries) && !$isSmallBusiness) {
                $expressNet = $expressGross / (1 + ($defaultTaxRate / 100));
                $expressTax = $expressGross - $expressNet;
            } else {
                $expressNet = $expressGross;
                $expressTax = 0;
            }
        }

        // 4. Alles Zusammenrechnen
        if ($isSmallBusiness) {
            $finalTax = 0;
            $finalNet = $netTotalProducts + $shippingNet + $expressNet;
            $finalGross = $finalNet;
        } else {
            $finalNet = $netTotalProducts + $shippingNet + $expressNet;
            $finalTax = $taxTotalProducts + $shippingTax + $expressTax;
            $finalGross = $finalNet + $finalTax;
        }

        // 5. Update Database
        $this->quote->update([
            'net_total' => (int) round($finalNet),
            'tax_total' => (int) round($finalTax),
            'gross_total' => (int) round($finalGross),
            'shipping_price' => (int) round($shippingCostCents),
        ]);

        // Speichern der Versandkosten als temporäres Attribut für die View
        $this->quote->shipping_cost_calculated = $shippingCostCents;
    }

    /**
     * Checkout Logik: Überträgt Items und Express-Status in den Warenkorb
     */
    public function proceedToCheckout(CartService $cartService)
    {
        if (!$this->isValidAction()) return;

        $cart = $cartService->getCart();
        $cart->items()->delete();

        // Express-Status und Adress-Vorauswahl in das Cart-Model übernehmen
        $cart->update([
            'is_express' => $this->quote->is_express,
            // Wir können hier auch die Adressdaten für den Checkout vor-reservieren falls gewünscht
        ]);

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
        $this->viewState = 'success_rejected';
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

<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected PriceCalculator $calculator;

    public function __construct(PriceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Ruft den aktuellen Warenkorb ab oder erstellt einen neuen.
     */
    public function getCart(): Cart
    {
        $user = Auth::user();

        if ($user) {
            // customer_id muss in der carts Tabelle existieren
            return Cart::firstOrCreate(['customer_id' => $user->id], [
                'session_id' => Session::getId()
            ]);
        }

        $sessionId = Session::getId();
        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }

    /**
     * Fügt ein Produkt hinzu.
     * Hier berücksichtigen wir "tax_included".
     */
    public function addItem(Product $product, int $quantity = 1, array $configuration = null): void
    {
        $cart = $this->getCart();

        // Prüfen, ob Artikel mit exakt dieser Konfiguration schon existiert
        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->get()
            ->first(function ($item) use ($configuration) {
                // Einfacher Array-Vergleich
                return $item->configuration == $configuration;
            });

        if ($existingItem) {
            $existingItem->increment('quantity', $quantity);
        } else {
            // --- PREIS-LOGIK START ---
            $unitPrice = $product->price;

            // Falls Netto-Preis (B2B), Steuer aufschlagen für Warenkorb (Brutto-Anzeige)
            if ($product->tax_included === false) {
                $taxRate = (float) ($product->tax_rate ?? 19.0);
                $unitPrice = (int) round($unitPrice * (1 + ($taxRate / 100)));
            }
            // --- PREIS-LOGIK ENDE ---

            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice, // Brutto in Cent
                'configuration' => $configuration
            ]);
        }

        $this->refreshTotals($cart);
    }

    /** Info
     * Aktualisiert Konfiguration und Menge eines existierenden Items.
     * (Diese Methode fehlte wahrscheinlich)
     */
    public function updateItem(string $itemId, int $quantity, array $configuration): void
    {
        $item = CartItem::where('id', $itemId)->first();
        if (!$item) return;

        $product = $item->product;

        // 1. Preis neu berechnen (Basispreis)
        $unitPrice = $product->price;

        // 2. Staffelpreise prüfen und anwenden
        if (!empty($product->tier_pricing) && is_array($product->tier_pricing)) {
            $tiers = $product->tier_pricing;
            // Sortieren nach Menge absteigend
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']);

            foreach ($tiers as $tier) {
                if ($quantity >= $tier['qty']) {
                    $discount = $unitPrice * ($tier['percent'] / 100);
                    $unitPrice -= $discount;
                    break;
                }
            }
        }

        // 3. Steuer-Logik anwenden (Netto -> Brutto für Warenkorb)
        if ($product->tax_included === false) {
            $taxRate = (float) ($product->tax_rate ?? 19.0);
            $unitPrice = (int) round($unitPrice * (1 + ($taxRate / 100)));
        }

        // 4. Item in DB aktualisieren
        $item->update([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'configuration' => $configuration
        ]);

        $this->refreshTotals($item->cart);
    }

    /**
     * Ändert nur die Menge (z.B. im Warenkorb +/- Buttons).
     */
    public function updateQuantity(string $itemId, int $quantity): void
    {
        $item = CartItem::where('id', $itemId)->first();
        if (!$item) return;

        if ($quantity <= 0) {
            $item->delete();
        } else {
            // Hinweis: Hier könnte man theoretisch auch Staffelpreise neu berechnen,
            // wenn sich der Einzelpreis durch die Menge ändert.
            // Der Einfachheit halber lassen wir den unit_price hier oft gleich,
            // oder rufen updateItem auf, wenn Staffelpreise relevant sind.
            // Für volle Konsistenz wäre updateItem() besser, aber updateQuantity ist oft schneller.
            $item->update(['quantity' => $quantity]);
        }

        $this->refreshTotals($item->cart);
    }

    /**
     * Entfernt einen Artikel.
     */
    public function removeItem(string $itemId): void
    {
        $item = CartItem::where('id', $itemId)->first();
        if ($item) {
            $cart = $item->cart;
            $item->delete();
            $this->refreshTotals($cart);
        }
    }

    /**
     * Berechnet Summen.
     */
    public function getTotals(): array
    {
        $cart = $this->getCart();
        $items = $cart->items()->with('product')->get();

        $totalNet = 0;
        $totalGross = 0;
        $taxesBreakdown = [];
        $itemCount = 0;

        foreach ($items as $item) {
            $product = $item->product;
            if (!$product) continue;

            $qty = $item->quantity;
            $itemCount += $qty;

            $taxRate = (float) ($product->tax_rate ?? 19.0);

            // unit_price ist bereits Brutto (in Cent)
            $lineGross = $item->unit_price * $qty;

            // Netto berechnen
            if(method_exists($this->calculator, 'getNetFromGross')) {
                $lineNet = $this->calculator->getNetFromGross($lineGross, $taxRate);
            } else {
                $lineNet = (int) round($lineGross / (1 + ($taxRate / 100)));
            }

            $lineTax = $lineGross - $lineNet;

            // Aggregieren
            $totalNet += $lineNet;
            $totalGross += $lineGross;

            // Steuer gruppieren
            $strRate = number_format($taxRate, 0);
            if (!isset($taxesBreakdown[$strRate])) {
                $taxesBreakdown[$strRate] = 0;
            }
            $taxesBreakdown[$strRate] += $lineTax;
        }

        // Versandkosten (Beispiel: 0)
        $shippingGross = 0;

        $finalTotalGross = $totalGross + $shippingGross;
        $finalTotalNet = $totalNet;
        $finalTotalTax = $finalTotalGross - $finalTotalNet;

        return [
            'subtotal_net' => $totalNet,
            'subtotal_gross' => $totalGross,
            'tax' => $finalTotalTax,
            'taxes_breakdown' => $taxesBreakdown,
            'shipping' => $shippingGross,
            'total' => $finalTotalGross,
            'item_count' => $itemCount
        ];
    }

    private function refreshTotals(Cart $cart) {
        if($cart) {
            $cart->touch();
        }
    }
}

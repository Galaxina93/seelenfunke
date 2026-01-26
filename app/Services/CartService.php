<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Coupon; // Wichtig: Coupon Model importieren
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
     */
    public function addItem(Product $product, int $quantity = 1, array $configuration = null): void
    {
        $cart = $this->getCart();

        // Prüfen, ob Artikel mit exakt dieser Konfiguration schon existiert
        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->get()
            ->first(function ($item) use ($configuration) {
                return $item->configuration == $configuration;
            });

        if ($existingItem) {
            $existingItem->increment('quantity', $quantity);
        } else {
            // Preis berechnen
            $unitPrice = $product->price;

            // Falls Netto-Preis (B2B), Steuer aufschlagen für Warenkorb (Brutto-Anzeige)
            if ($product->tax_included === false) {
                // Accessor getTaxRateAttribute() aus Product Model nutzen
                $taxRate = (float) ($product->tax_rate ?? 19.0);
                $unitPrice = (int) round($unitPrice * (1 + ($taxRate / 100)));
            }

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

    /**
     * Aktualisiert Konfiguration und Menge eines existierenden Items.
     */
    public function updateItem(string $itemId, int $quantity, array $configuration): void
    {
        $item = CartItem::where('id', $itemId)->first();
        if (!$item) return;

        $product = $item->product;

        // 1. Basispreis
        $unitPrice = $product->price;

        // 2. Staffelpreise
        if (!empty($product->tier_pricing) && is_array($product->tier_pricing)) {
            $tiers = $product->tier_pricing;
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']);

            foreach ($tiers as $tier) {
                if ($quantity >= $tier['qty']) {
                    $discount = $unitPrice * ($tier['percent'] / 100);
                    $unitPrice -= $discount;
                    break;
                }
            }
        }

        // 3. Steuer-Logik (Netto -> Brutto)
        if ($product->tax_included === false) {
            $taxRate = (float) ($product->tax_rate ?? 19.0);
            $unitPrice = (int) round($unitPrice * (1 + ($taxRate / 100)));
        }

        // 4. Update
        $item->update([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'configuration' => $configuration
        ]);

        $this->refreshTotals($item->cart);
    }

    /**
     * Ändert nur die Menge.
     */
    public function updateQuantity(string $itemId, int $quantity): void
    {
        $item = CartItem::where('id', $itemId)->first();
        if (!$item) return;

        if ($quantity <= 0) {
            $item->delete();
        } else {
            // Hinweis: Um Staffelpreise bei reiner Mengenänderung im Warenkorb zu unterstützen,
            // müsste man hier eigentlich auch updateItem() Logik nutzen.
            // Der Einfachheit halber lassen wir den Einzelpreis hier fix.
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
     * NEU: Gutschein anwenden
     */
    public function applyCoupon(string $code): array
    {
        $coupon = Coupon::where('code', $code)->first();

        // 1. Existenz und generelle Gültigkeit prüfen
        if (!$coupon || !$coupon->isValid()) {
            return ['success' => false, 'message' => 'Gutschein ist ungültig oder abgelaufen.'];
        }

        // 2. Mindestbestellwert prüfen
        $cart = $this->getCart();
        // Summe aller Items berechnen
        $subtotal = $cart->items->sum(fn($item) => $item->unit_price * $item->quantity);

        if ($coupon->min_order_value && $subtotal < $coupon->min_order_value) {
            return [
                'success' => false,
                'message' => 'Mindestbestellwert von ' . number_format($coupon->min_order_value / 100, 2, ',', '.') . '€ nicht erreicht.'
            ];
        }

        // 3. Speichern
        $cart->update(['coupon_code' => $coupon->code]);

        return ['success' => true, 'message' => 'Gutschein erfolgreich eingelöst!'];
    }

    /**
     * NEU: Gutschein entfernen
     */
    public function removeCoupon(): void
    {
        $cart = $this->getCart();
        $cart->update(['coupon_code' => null]);
    }

    /**
     * Berechnet Summen, Steuern und Rabatte.
     */
    public function getTotals(): array
    {
        $cart = $this->getCart();
        $items = $cart->items()->with('product')->get();

        $totalNet = 0;
        $totalGross = 0;
        $taxesBreakdown = [];
        $itemCount = 0;

        // --- 1. Warenkorb Basis-Summen berechnen ---
        foreach ($items as $item) {
            $product = $item->product;
            if (!$product) continue;

            $qty = $item->quantity;
            $itemCount += $qty;

            // Accessor nutzen für Tax Rate
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

        // --- 2. Rabatt berechnen ---
        $discountAmount = 0;
        $couponCode = $cart->coupon_code;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();

            // Prüfen ob noch gültig (könnte seit dem Hinzufügen abgelaufen sein)
            if ($coupon && $coupon->isValid()) {

                // Prüfen ob Mindestbestellwert noch erfüllt (falls Artikel gelöscht wurden)
                if ($coupon->min_order_value && $totalGross < $coupon->min_order_value) {
                    // Gutschein nicht anwenden, aber Code vllt. nicht löschen, damit Kunde sieht warum?
                    // Besser: Ignorieren oder entfernen. Hier ignorieren wir ihn für die Berechnung.
                    $couponCode = null; // Für die Rückgabe nullen
                    // Optional: $cart->update(['coupon_code' => null]);
                } else {
                    if ($coupon->type === 'fixed') {
                        $discountAmount = $coupon->value;
                    } elseif ($coupon->type === 'percent') {
                        $discountAmount = (int) round($totalGross * ($coupon->value / 100));
                    }

                    // Rabatt darf nicht höher als Warenwert sein
                    $discountAmount = min($discountAmount, $totalGross);
                }

            } else {
                // Gutschein ungültig -> aus Cart entfernen
                $cart->update(['coupon_code' => null]);
                $couponCode = null;
            }
        }

        // --- 3. Endsummen berechnen ---
        $shippingGross = 0; // Hier Logik für Versandkosten einfügen

        // Summe nach Rabatt
        $totalAfterDiscount = $totalGross - $discountAmount;
        $finalTotalGross = $totalAfterDiscount + $shippingGross;

        // Hinweis: Um die Steuer korrekt auszuweisen, wenn ein Rabatt auf den gesamten Warenkorb
        // angewendet wird, muss der Steueranteil proportional reduziert werden.
        // Verhältnis: (Bezahlter Betrag) / (Ursprünglicher Betrag)
        $discountRatio = $totalGross > 0 ? ($totalAfterDiscount / $totalGross) : 1;

        // Steuern proportional anpassen (für die Anzeige)
        foreach($taxesBreakdown as $key => $val) {
            $taxesBreakdown[$key] = (int) round($val * $discountRatio);
        }

        // Netto proportional anpassen
        // (Alternativ: finalTotalGross - neue SteuerSumme)
        $finalTotalNet = (int) round($totalNet * $discountRatio);
        $finalTotalTax = $finalTotalGross - $finalTotalNet;

        return [
            'subtotal_net' => $totalNet,
            'subtotal_gross' => $totalGross,
            'discount_amount' => $discountAmount, // NEU: Rabattbetrag
            'coupon_code' => $couponCode,         // NEU: Angewendeter Code
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

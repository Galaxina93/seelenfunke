<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Coupon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    /**
     * Ruft den aktuellen Warenkorb ab oder erstellt einen neuen.
     */
    public function getCart(): Cart
    {
        // 1. Prüfen ob Kunde eingeloggt ist (Guard 'customer' explizit nutzen!)
        if (Auth::guard('customer')->check()) {
            $user = Auth::guard('customer')->user();

            // Da 'customer_id' jetzt fillable ist, funktioniert das hier sicher:
            // Wir übergeben session_id im zweiten Array, damit es beim ERSTELLEN gesetzt wird.
            $cart = Cart::firstOrCreate(
                ['customer_id' => $user->id],
                ['session_id' => Session::getId()]
            );

            // Session ID aktuell halten (wichtig bei Gerätewechsel)
            if ($cart->session_id !== Session::getId()) {
                $cart->update(['session_id' => Session::getId()]);
            }
            return $cart;
        }

        // 2. Gast-Warenkorb basierend auf Session ID
        $sessionId = Session::getId();
        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }

    /**
     * Fügt ein Produkt hinzu (inkl. Staffelpreis-Berechnung).
     * Prüft genau, ob die Konfiguration identisch ist.
     */
    public function addItem(Product $product, int $quantity = 1, array $configuration = null): void
    {
        $cart = $this->getCart();

        // Prüfen, ob Artikel mit exakt dieser Konfiguration schon existiert
        // Wir laden alle Items dieses Produkts und vergleichen das Array in PHP
        $existingItems = $cart->items()->where('product_id', $product->id)->get();

        $existingItem = $existingItems->first(function ($item) use ($configuration) {
            // Array Vergleich: Ist die gespeicherte Config gleich der neuen?
            // Wir nutzen '==' damit die Reihenfolge der Keys keine Rolle spielt, aber Werte stimmen müssen
            return $item->configuration == $configuration;
        });

        // Neue Gesamtmenge ermitteln (für Staffelpreis)
        $newQty = $existingItem ? $existingItem->quantity + $quantity : $quantity;

        // Preis basierend auf NEUER Menge berechnen
        $unitPrice = $this->calculateTierPrice($product, $newQty);

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $newQty,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $newQty
            ]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $quantity,
                'configuration' => $configuration
            ]);
        }

        $this->refreshTotals($cart);
    }

    /**
     * Aktualisiert ein Item (z.B. aus dem Warenkorb heraus).
     */
    public function updateItem(string $itemId, int $quantity, array $configuration = null): void
    {
        $item = CartItem::find($itemId);
        if (!$item) return;

        // Preis neu berechnen (könnte sich durch Menge geändert haben)
        $unitPrice = $this->calculateTierPrice($item->product, $quantity);

        $data = [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity
        ];

        if ($configuration !== null) {
            $data['configuration'] = $configuration;
        }

        $item->update($data);
        $this->refreshTotals($item->cart);
    }

    /**
     * Entfernt ein Item.
     */
    public function removeItem(string $itemId): void
    {
        $item = CartItem::find($itemId);
        if ($item) {
            $cart = $item->cart;
            $item->delete();
            $this->refreshTotals($cart);
        }
    }

    /**
     * Berechnet den Einzelpreis (Staffel + Steuer).
     * Gibt IMMER den Brutto-Preis in Cent zurück (da B2C Shop meist Brutto anzeigt).
     */
    public function calculateTierPrice(Product $product, int $qty): int
    {
        $price = $product->price;
        $tiers = $product->tier_pricing;

        if (!empty($tiers) && is_array($tiers)) {
            // Sortieren: Höchste Menge zuerst
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']);
            foreach ($tiers as $tier) {
                if ($qty >= $tier['qty']) {
                    $discount = $price * ($tier['percent'] / 100);
                    $price -= $discount;
                    break;
                }
            }
        }

        // Wenn Produkt netto in DB (tax_included = false), Steuer draufschlagen für Brutto-Preis
        if ($product->tax_included === false) {
            $taxRate = (float) ($product->tax_rate ?? 19.0);
            $price = (int) round($price * (1 + ($taxRate / 100)));
        }

        return (int) round($price);
    }

    /**
     * Gutschein anwenden.
     */
    public function applyCoupon(string $code): array
    {
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon || !$coupon->isValid()) {
            return ['success' => false, 'message' => 'Gutschein ist ungültig oder abgelaufen.'];
        }

        $cart = $this->getCart();

        // Wir müssen hier kurz vorrechnen, um Mindestbestellwert zu prüfen
        $totals = $this->calculateTotals($cart);
        $subtotal = $totals['subtotal_gross'];

        if ($coupon->min_order_value && $subtotal < $coupon->min_order_value) {
            return [
                'success' => false,
                'message' => 'Mindestbestellwert von ' . number_format($coupon->min_order_value / 100, 2, ',', '.') . '€ nicht erreicht.'
            ];
        }

        $cart->update(['coupon_code' => $coupon->code]);
        return ['success' => true, 'message' => 'Gutschein erfolgreich eingelöst!'];
    }

    public function removeCoupon(): void
    {
        $this->getCart()->update(['coupon_code' => null]);
    }

    /**
     * Die Haupt-Rechenmaschine.
     * Berechnet alle Summen für Warenkorb, Checkout und Angebote.
     * * WICHTIG: Umbenannt von getTotals zu calculateTotals, damit Checkout.php funktioniert!
     */
    public function calculateTotals(Cart $cart): array
    {
        // Items & Produkte laden
        $cart->load('items.product');

        $subtotalGross = 0;      // Tatsächliche Summe (mit Mengenrabatt)
        $originalSubtotal = 0;   // Summe ohne Mengenrabatt (Streichpreis)
        $taxesBreakdown = [];    // Steuer-Aufschlüsselung
        $itemCount = 0;

        foreach ($cart->items as $item) {
            $product = $item->product;
            if (!$product) continue;

            $qty = $item->quantity;
            $itemCount += $qty;

            // 1. Zeilensumme (Ist bereits inkl. Mengenrabatt durch unit_price Logik)
            $lineGross = $item->unit_price * $qty;
            $subtotalGross += $lineGross;

            // 2. Originalpreis berechnen (für "Sie sparen X Euro")
            $basePrice = $product->price;
            if ($product->tax_included === false) {
                $basePrice = (int) round($basePrice * (1 + (($product->tax_rate ?? 19.0) / 100)));
            }
            $originalSubtotal += ($basePrice * $qty);

            // 3. Steueranteil der Zeile berechnen
            $taxRate = (float) ($product->tax_rate ?? 19.0);

            // Rückrechnung aus Brutto: Brutto / 1.19 = Netto
            $lineNet = (int) round($lineGross / (1 + ($taxRate / 100)));
            $lineTax = $lineGross - $lineNet;

            // Sammeln für Breakdown
            $strRate = number_format($taxRate, 0); // Key als String "19" oder "7"
            if (!isset($taxesBreakdown[$strRate])) $taxesBreakdown[$strRate] = 0;
            $taxesBreakdown[$strRate] += $lineTax;
        }

        // Mengenrabatt in Euro (Differenz Listenpreis vs. tatsächlicher Preis)
        $volumeDiscount = max(0, $originalSubtotal - $subtotalGross);

        // --- GUTSCHEIN ---
        $discountAmount = 0;
        $couponCode = $cart->coupon_code;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();

            // Validierung im Kontext der Summe
            if ($coupon && $coupon->isValid()) {
                if ($coupon->min_order_value && $subtotalGross < $coupon->min_order_value) {
                    // Falls durch Löschen von Items der Wert unterschritten wurde
                    $couponCode = null;
                    $cart->update(['coupon_code' => null]);
                } else {
                    if ($coupon->type === 'fixed') {
                        $discountAmount = $coupon->value;
                    } elseif ($coupon->type === 'percent') {
                        $discountAmount = (int) round($subtotalGross * ($coupon->value / 100));
                    }
                    // Rabatt darf nicht höher als Warenwert sein
                    $discountAmount = min($discountAmount, $subtotalGross);
                }
            } else {
                $cart->update(['coupon_code' => null]);
                $couponCode = null;
            }
        }

        // --- VERSAND ---
        // Beispiel-Logik: Frei ab 150€ (nach Rabatt), sonst 4,90€
        $valueForShipping = $subtotalGross - $discountAmount;
        $shippingGross = ($valueForShipping >= 15000 || $itemCount === 0) ? 0 : 490;

        // --- ENDSUMME ---
        $totalAfterDiscount = $subtotalGross - $discountAmount;
        $finalTotalGross = $totalAfterDiscount + $shippingGross;

        // --- STEUER KORREKTUR NACH RABATT ---
        // Wenn ein Gutschein den Gesamtpreis senkt, sinkt auch die enthaltene Steuer.
        // Wir verteilen den Rabatt proportional auf die Steuersätze.
        $discountRatio = $subtotalGross > 0 ? ($totalAfterDiscount / $subtotalGross) : 1;

        foreach($taxesBreakdown as $key => $val) {
            $taxesBreakdown[$key] = (int) round($val * $discountRatio);
        }
        $finalTotalTax = array_sum($taxesBreakdown);

        return [
            'subtotal_original' => $originalSubtotal,
            'subtotal_gross' => $subtotalGross,
            'volume_discount' => $volumeDiscount,
            'discount_amount' => $discountAmount,
            'coupon_code' => $couponCode,
            'tax' => $finalTotalTax,
            'taxes_breakdown' => $taxesBreakdown,
            'shipping' => $shippingGross,
            'total' => max(0, $finalTotalGross), // Darf nicht negativ sein
            'item_count' => $itemCount
        ];
    }

    /**
     * Aktualisiert den Timestamp des Warenkorbs.
     */
    private function refreshTotals(Cart $cart) {
        if($cart) $cart->touch();
    }
}

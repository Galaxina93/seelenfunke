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

            $cart = Cart::firstOrCreate(
                ['customer_id' => $user->id],
                ['session_id' => Session::getId()]
            );

            // Session ID aktuell halten
            if ($cart->session_id !== Session::getId()) {
                $cart->update(['session_id' => Session::getId()]);
            }
            return $cart;
        }

        // 2. Gast-Warenkorb
        return Cart::firstOrCreate(['session_id' => Session::getId()]);
    }

    /**
     * Fügt ein Produkt hinzu.
     */
    public function addItem(Product $product, int $quantity = 1, array $configuration = null): void
    {
        $cart = $this->getCart();

        // Prüfen, ob Artikel mit exakt dieser Konfiguration schon existiert
        $existingItems = $cart->items()->where('product_id', $product->id)->get();

        $existingItem = $existingItems->first(function ($item) use ($configuration) {
            return $item->configuration == $configuration;
        });

        // Neue Menge berechnen
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
     * Aktualisiert ein Item komplett.
     */
    public function updateItem(string $itemId, int $quantity, array $configuration = null): void
    {
        $item = CartItem::find($itemId);
        if (!$item) return;

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
     * Aktualisiert NUR die Menge eines Items.
     */
    public function updateQuantity(string $itemId, int $quantity): void
    {
        $item = CartItem::find($itemId);
        if (!$item) return;

        if ($quantity <= 0) {
            $this->removeItem($itemId);
            return;
        }

        $unitPrice = $this->calculateTierPrice($item->product, $quantity);

        $item->update([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity
        ]);

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
     * Leert den Warenkorb.
     */
    public function emptyCart(): void
    {
        $cart = $this->getCart();
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);
        $this->refreshTotals($cart);
    }

    /**
     * Berechnet den Einzelpreis (Staffel + Steuer).
     */
    public function calculateTierPrice(Product $product, int $qty): int
    {
        $price = $product->price;
        $tiers = $product->tier_pricing;

        if (!empty($tiers) && is_array($tiers)) {
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']);
            foreach ($tiers as $tier) {
                if ($qty >= $tier['qty']) {
                    $discount = $price * ($tier['percent'] / 100);
                    $price -= $discount;
                    break;
                }
            }
        }

        if ($product->tax_included === false) {
            $taxRate = (float) ($product->tax_rate ?? 19.0);
            $price = (int) round($price * (1 + ($taxRate / 100)));
        }

        return (int) round($price);
    }

    public function applyCoupon(string $code): array
    {
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon || !$coupon->isValid()) {
            return ['success' => false, 'message' => 'Gutschein ist ungültig oder abgelaufen.'];
        }

        $cart = $this->getCart();
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
     * Wrapper für Kompatibilität mit alten Komponenten.
     */
    public function getTotals(): array
    {
        return $this->calculateTotals($this->getCart());
    }

    /**
     * Die Haupt-Rechenmaschine.
     */
    public function calculateTotals(Cart $cart): array
    {
        $cart->load('items.product');

        $subtotalGross = 0;
        $originalSubtotal = 0;
        $taxesBreakdown = []; // Sammelt Produktsteuern (19%, 7%, 0% aus DB)
        $itemCount = 0;

        // 1. ARTIKEL DURCHLAUFEN & STEUERN SAMMELN
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (!$product) continue;

            $qty = $item->quantity;
            $itemCount += $qty;

            // Zeilensumme
            $lineGross = $item->unit_price * $qty;
            $subtotalGross += $lineGross;

            // Originalpreis (für Streichpreis-Berechnung)
            $basePrice = $product->price;
            // Falls Netto-Preise gepflegt werden, hier Brutto errechnen
            if ($product->tax_included === false) {
                $basePrice = (int) round($basePrice * (1 + (($product->tax_rate ?? 19.0) / 100)));
            }
            $originalSubtotal += ($basePrice * $qty);

            // Steueranteil pro Zeile berechnen (Respektiert Produkt-Einstellung: 19, 7 oder 0)
            $taxRate = (float) ($product->tax_rate ?? 19.0);

            // Rückwärtsrechnung aus Brutto: Betrag / 1.19
            $lineNet = (int) round($lineGross / (1 + ($taxRate / 100)));
            $lineTax = $lineGross - $lineNet;

            $strRate = number_format($taxRate, 0);
            if (!isset($taxesBreakdown[$strRate])) $taxesBreakdown[$strRate] = 0;
            $taxesBreakdown[$strRate] += $lineTax;
        }

        $volumeDiscount = max(0, $originalSubtotal - $subtotalGross);

        // 2. GUTSCHEIN BERECHNUNG
        $discountAmount = 0;
        $couponCode = $cart->coupon_code;

        if ($couponCode) {
            $coupon = \App\Models\Coupon::where('code', $couponCode)->first(); // Model Pfad ggf. anpassen

            if ($coupon && $coupon->isValid()) {
                if ($coupon->min_order_value && $subtotalGross < $coupon->min_order_value) {
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

        // Zwischensumme nach Rabatt (Basis für Versandfrei-Grenze)
        $totalAfterDiscount = max(0, $subtotalGross - $discountAmount);

        // 3. VERSANDKOSTEN (NEU)
        // Werte aus Config holen oder Default setzen
        $shippingConfigCost = config('shop.shipping.cost', 490);
        $shippingThreshold = config('shop.shipping.free_threshold', 5000);
        $shippingTaxRate = config('shop.shipping.tax_rate', 19);

        $shippingGross = $shippingConfigCost;
        $isFreeShipping = false;
        $missingForFreeShipping = 0;

        // Prüfen ob Schwellwert erreicht
        if ($totalAfterDiscount >= $shippingThreshold) {
            $shippingGross = 0;
            $isFreeShipping = true;
        } else {
            $missingForFreeShipping = $shippingThreshold - $totalAfterDiscount;
        }

        // 4. STEUER KORREKTUR (Rabatt auf Produktsteuern verteilen)
        // Wir reduzieren die gesammelten Produktsteuern anteilig um den Rabatt
        $discountRatio = $subtotalGross > 0 ? ($totalAfterDiscount / $subtotalGross) : 1;

        foreach($taxesBreakdown as $key => $val) {
            $taxesBreakdown[$key] = (int) round($val * $discountRatio);
        }

        // 5. VERSANDSTEUER HINZUFÜGEN
        // Versand ist eine Dienstleistung und muss versteuert werden (meist 19%)
        // Diese kommt NACH dem Rabatt dazu (Rabatte gelten meist nicht auf Versand)
        $shippingTaxAmount = 0;
        if ($shippingGross > 0) {
            $shippingNet = (int) round($shippingGross / (1 + ($shippingTaxRate / 100)));
            $shippingTaxAmount = $shippingGross - $shippingNet;

            // Zur Breakdown hinzufügen
            $strShipRate = number_format($shippingTaxRate, 0);
            if (!isset($taxesBreakdown[$strShipRate])) $taxesBreakdown[$strShipRate] = 0;
            $taxesBreakdown[$strShipRate] += $shippingTaxAmount;
        }

        // Gesamtsummen finalisieren
        $finalTotalGross = $totalAfterDiscount + $shippingGross;
        $finalTotalTax = array_sum($taxesBreakdown);

        return [
            'subtotal_original' => $originalSubtotal, // Vor Rabatten (Streichpreis Summe)
            'subtotal_gross' => $subtotalGross,       // Aktueller Warenwert
            'volume_discount' => $volumeDiscount,
            'discount_amount' => $discountAmount,
            'coupon_code' => $couponCode,

            // Steuer Infos
            'tax' => $finalTotalTax,
            'taxes_breakdown' => $taxesBreakdown, // Enthält jetzt Produktsteuern (anteilig rabattiert) + Versandsteuer

            // Versand Infos
            'shipping' => $shippingGross,
            'shipping_tax' => $shippingTaxAmount,
            'is_free_shipping' => $isFreeShipping,
            'missing_for_free_shipping' => $missingForFreeShipping,

            // Endsumme
            'total' => max(0, $finalTotalGross),
            'item_count' => $itemCount
        ];
    }

    private function refreshTotals(Cart $cart) {
        if($cart) $cart->touch();
    }
}

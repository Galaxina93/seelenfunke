<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\ShippingZone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    /**
     * Ruft den aktuellen Warenkorb ab oder erstellt einen neuen.
     */
    public function getCart(): Cart
    {
        if (Auth::guard('customer')->check()) {
            $user = Auth::guard('customer')->user();

            $cart = Cart::firstOrCreate(
                ['customer_id' => $user->id],
                ['session_id' => Session::getId()]
            );

            if ($cart->session_id !== Session::getId()) {
                $cart->update(['session_id' => Session::getId()]);
            }
            return $cart;
        }

        return Cart::firstOrCreate(['session_id' => Session::getId()]);
    }

    /**
     * Fügt ein Produkt hinzu.
     */
    public function addItem(Product $product, int $quantity = 1, array $configuration = null): void
    {
        $cart = $this->getCart();

        // Suche nach existierendem Item mit gleicher Konfiguration
        $existingItems = $cart->items()->where('product_id', $product->id)->get();
        $existingItem = $existingItems->first(function ($item) use ($configuration) {
            return $item->configuration == $configuration;
        });

        $newQty = $existingItem ? $existingItem->quantity + $quantity : $quantity;

        // Preis berechnen
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

    public function removeItem(string $itemId): void
    {
        $item = CartItem::find($itemId);
        if ($item) {
            $cart = $item->cart;
            $item->delete();
            $this->refreshTotals($cart);
        }
    }

    public function emptyCart(): void
    {
        $cart = $this->getCart();
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);
        $this->refreshTotals($cart);
    }

    /**
     * Berechnet den Einzelpreis (Staffel + Steuer).
     * Gibt immer den Brutto-Preis in Cent zurück.
     */
    public function calculateTierPrice(Product $product, int $qty): int
    {
        $price = $product->price;
        $tiers = $product->tier_pricing;

        // Staffelpreise prüfen
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

        // Da wir prices_entered_gross global nutzen, ist der Preis hier schon Brutto.
        // Falls du Netto-Preise nutzt (via Config switch), müsste hier die Steuer drauf.
        // Der Accessor im Product Model regelt das aber meist schon.

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

    public function getTotals(): array
    {
        return $this->calculateTotals($this->getCart());
    }

    /**
     * Die Haupt-Rechenmaschine.
     * Aktualisiert jetzt auch veraltete Preise in der DB!
     */
    public function calculateTotals(Cart $cart, string $countryCode = 'DE'): array
    {
        $cart->load('items.product');

        $subtotalGross = 0;
        $originalSubtotal = 0;
        $taxesBreakdown = [];
        $itemCount = 0;
        $totalWeight = 0;

        // 1. ARTIKEL DURCHLAUFEN
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (!$product) continue;

            $qty = $item->quantity;
            $itemCount += $qty;

            // Gewicht summieren
            $weight = (int)($product->weight ?? 0);
            $totalWeight += ($weight * $qty);

            // --- FIX: Frischen Preis berechnen! ---
            // Wenn sich im Backend die Steuer/Preis geändert hat, ist $item->unit_price veraltet.
            $freshUnitPrice = $this->calculateTierPrice($product, $qty);

            // Wenn der berechnete Preis abweicht, aktualisieren wir das Item in der DB
            if ($freshUnitPrice !== $item->unit_price) {
                $item->update([
                    'unit_price' => $freshUnitPrice,
                    'total_price' => $freshUnitPrice * $qty
                ]);
            }
            // Wir rechnen mit dem frischen Preis weiter
            $lineGross = $freshUnitPrice * $qty;
            $subtotalGross += $lineGross;

            // Originalpreis (für Streichpreis-Logik, falls gewünscht)
            $basePrice = $product->price;
            $originalSubtotal += ($basePrice * $qty);

            // Steueranteil herausrechnen (vom Brutto-Preis)
            $taxRate = (float) ($product->tax_rate ?? 19.0);
            $lineNet = (int) round($lineGross / (1 + ($taxRate / 100)));
            $lineTax = $lineGross - $lineNet;

            $strRate = number_format($taxRate, 0);
            if (!isset($taxesBreakdown[$strRate])) $taxesBreakdown[$strRate] = 0;
            $taxesBreakdown[$strRate] += $lineTax;
        }

        $volumeDiscount = max(0, $originalSubtotal - $subtotalGross);

        // 2. GUTSCHEIN
        $discountAmount = 0;
        $couponCode = $cart->coupon_code;

        if ($couponCode) {
            $coupon = \App\Models\Coupon::where('code', $couponCode)->first();

            if ($coupon && $coupon->isValid()) {
                if ($coupon->min_order_value && $subtotalGross < $coupon->min_order_value) {
                    $cart->update(['coupon_code' => null]);
                    $couponCode = null;
                } else {
                    if ($coupon->type === 'fixed') {
                        $discountAmount = $coupon->value;
                    } elseif ($coupon->type === 'percent') {
                        $discountAmount = (int) round($subtotalGross * ($coupon->value / 100));
                    }
                    $discountAmount = min($discountAmount, $subtotalGross);
                }
            } else {
                $cart->update(['coupon_code' => null]);
                $couponCode = null;
            }
        }

        // Zwischensumme nach Rabatten
        $totalAfterDiscount = max(0, $subtotalGross - $discountAmount);

        // 3. VERSANDKOSTEN
        $shippingResult = $this->determineShippingCost($totalAfterDiscount, $totalWeight, $countryCode);
        $shippingGross = $shippingResult['cost'];
        $isFreeShipping = $shippingResult['is_free'];
        $missingForFreeShipping = $shippingResult['missing'];

        // 4. STEUER KORREKTUR (Rabatte auf Steuer umlegen)
        $discountRatio = $subtotalGross > 0 ? ($totalAfterDiscount / $subtotalGross) : 1;
        foreach($taxesBreakdown as $key => $val) {
            $taxesBreakdown[$key] = (int) round($val * $discountRatio);
        }

        // 5. VERSANDSTEUER (Nur EU)
        $shippingTaxAmount = 0;
        if ($shippingGross > 0) {
            $euCountries = ['DE', 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];
            $isEU = in_array($countryCode, $euCountries);

            // Standardmäßig 19% auf Versand in DE, oder 0% Export
            $shippingTaxRate = $isEU ? 19.0 : 0.0;

            $shippingNet = (int) round($shippingGross / (1 + ($shippingTaxRate / 100)));
            $shippingTaxAmount = $shippingGross - $shippingNet;

            if ($shippingTaxRate > 0) {
                $strShipRate = number_format($shippingTaxRate, 0);
                if (!isset($taxesBreakdown[$strShipRate])) $taxesBreakdown[$strShipRate] = 0;
                $taxesBreakdown[$strShipRate] += $shippingTaxAmount;
            }
        }

        $finalTotalGross = $totalAfterDiscount + $shippingGross;
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
            'shipping_tax' => $shippingTaxAmount,
            'is_free_shipping' => $isFreeShipping,
            'missing_for_free_shipping' => $missingForFreeShipping,
            'total' => max(0, $finalTotalGross),
            'item_count' => $itemCount,
            'weight' => $totalWeight,
            'country' => $countryCode
        ];
    }

    /**
     * Ermittelt die Versandkosten.
     */
    private function determineShippingCost(int $cartValueCents, float $totalWeight, string $countryCode): array
    {
        $result = ['cost' => 0, 'is_free' => false, 'missing' => 0];

        // 1. DEUTSCHLAND
        if ($countryCode === 'DE') {
            $threshold = 5000; // 50,00 Euro
            if ($cartValueCents >= $threshold) {
                $result['is_free'] = true;
                $result['cost'] = 0;
            } else {
                $result['cost'] = 490; // 4,90 Euro
                $result['missing'] = $threshold - $cartValueCents;
            }
            return $result;
        }

        // 2. INTERNATIONAL (Datenbank)
        $zone = ShippingZone::whereHas('countries', fn($q) => $q->where('country_code', $countryCode))
            ->with('rates')
            ->first();

        // Fallback "Weltweit"
        if (!$zone) {
            $zone = ShippingZone::where('name', 'Weltweit')->with('rates')->first();
        }

        if (!$zone) {
            $result['cost'] = 2990; // Absoluter Fallback
            return $result;
        }

        // Rate nach Gewicht
        $rate = $zone->rates()
            ->where(function($q) use ($totalWeight) {
                $q->where('max_weight', '>=', $totalWeight)
                    ->orWhereNull('max_weight');
            })
            ->where('min_weight', '<=', $totalWeight)
            ->orderBy('price', 'asc')
            ->first();

        if ($rate) {
            $result['cost'] = $rate->price;
        } else {
            $result['cost'] = 5000; // Fallback wenn zu schwer
        }

        return $result;
    }

    private function refreshTotals(Cart $cart) {
        if($cart) $cart->touch();
    }
}

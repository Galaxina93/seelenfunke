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

            // Session ID aktuell halten
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
        // Wir nutzen json_encode zum Vergleich der Arrays
        $existingItems = $cart->items()->where('product_id', $product->id)->get();
        $existingItem = $existingItems->first(function ($item) use ($configuration) {
            return json_encode($item->configuration) === json_encode($configuration);
        });

        $newQty = $existingItem ? $existingItem->quantity + $quantity : $quantity;

        // Preis berechnen (inkl. Staffelung)
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
        $cart->update(['coupon_code' => null, 'is_express' => false]);
        $this->refreshTotals($cart);
    }

    /**
     * Berechnet den Einzelpreis (Staffel).
     */
    public function calculateTierPrice(Product $product, int $qty): int
    {
        $price = $product->price;
        // Wir nutzen die Relation tierPrices (Eloquent) statt tier_pricing (JSON),
        // da deine Seeder die Relation füllen.
        $tiers = $product->tierPrices;

        if ($tiers && $tiers->count() > 0) {
            // Sortieren nach Menge absteigend
            $tier = $tiers->where('qty', '<=', $qty)->sortByDesc('qty')->first();

            if ($tier) {
                $discount = $price * ($tier->percent / 100);
                $price -= $discount;
            }
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
        $totals = $this->getTotals(); // Rekursion vermeiden: Wir rufen Totals ohne Coupon ab

        // Wir müssen hier aufpassen: getTotals ruft calculateTotals auf.
        // Wenn wir den Coupon noch nicht gesetzt haben, ist das OK.
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

    public function getTotals(string $countryCode = 'DE'): array
    {
        return $this->calculateTotals($this->getCart(), $countryCode);
    }

    /**
     * Die Haupt-Rechenmaschine.
     */
    public function calculateTotals(Cart $cart, string $countryCode = 'DE'): array
    {
        $cart->load(['items.product.tierPrices']);

        $subtotalGross = 0;
        $originalSubtotal = 0;
        $taxesBreakdown = [];
        $itemCount = 0;
        $totalWeight = 0;

        // Globale Einstellungen aus der 'shop-settings' Tabelle laden
        $isSmallBusiness = (bool)shop_setting('is_small_business', false);
        $defaultTaxRate = (float)shop_setting('default_tax_rate', 19.0);

        // 1. ARTIKEL DURCHLAUFEN
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (!$product) continue;

            $qty = $item->quantity;
            $itemCount += $qty;

            // Gewicht summieren
            $weight = (int)($product->weight ?? 0);
            $totalWeight += ($weight * $qty);

            // Frischen Preis berechnen (Selbstheilung)
            $freshUnitPrice = $this->calculateTierPrice($product, $qty);

            if ($freshUnitPrice !== $item->unit_price) {
                $item->unit_price = $freshUnitPrice;
                $item->saveQuietly(); // Events vermeiden
            }

            $lineGross = $freshUnitPrice * $qty;
            $subtotalGross += $lineGross;

            // Originalpreis (für Streichpreise)
            $basePrice = $product->price;
            $originalSubtotal += ($basePrice * $qty);

            // Steueranteil herausrechnen
            // Nutzt jetzt shop_setting statt config
            $taxRate = $isSmallBusiness ? 0.0 : (float) ($product->tax_rate ?? $defaultTaxRate);

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
            $coupon = Coupon::where('code', $couponCode)->first();

            if ($coupon && $coupon->isValid()) {
                if ($coupon->min_order_value && $subtotalGross < $coupon->min_order_value) {
                    // Ignorieren, aber Code behalten (User könnte noch was kaufen)
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

        // 3. VERSANDKOSTEN (Rein Datenbank-basiert)
        $shippingResult = $this->determineShippingCost($totalAfterDiscount, $totalWeight, $countryCode);
        $shippingGross = $shippingResult['cost'];
        $isFreeShipping = $shippingResult['is_free'];
        $missingForFreeShipping = $shippingResult['missing'];

        // 4. STEUER KORREKTUR (Rabatte proportional auf Steuer umlegen)
        $discountRatio = $subtotalGross > 0 ? ($totalAfterDiscount / $subtotalGross) : 1;
        foreach($taxesBreakdown as $key => $val) {
            $taxesBreakdown[$key] = (int) round($val * $discountRatio);
        }

        // 5. VERSANDSTEUER (EU-Logik)
        $shippingTaxAmount = 0;
        $euCountries = ['DE', 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];
        $isEU = in_array($countryCode, $euCountries);

        if ($shippingGross > 0) {
            // Steuersatz bestimmen: In EU den Standard-Satz aus shop-settings, sonst 0% (Export).
            $shippingTaxRate = ($isEU && !$isSmallBusiness) ? $defaultTaxRate : 0.0;

            // Berechnung mit dynamischem Divisor aus Datenbank-Einstellung
            $shippingNet = (int) round($shippingGross / (1 + ($shippingTaxRate / 100)));
            $shippingTaxAmount = $shippingGross - $shippingNet;

            if ($shippingTaxRate > 0) {
                $strShipRate = number_format($shippingTaxRate, 0);
                if (!isset($taxesBreakdown[$strShipRate])) {
                    $taxesBreakdown[$strShipRate] = 0;
                }
                $taxesBreakdown[$strShipRate] += $shippingTaxAmount;
            }
        }

        // 6. EXPRESS-LOGIK (WICHTIG: Aus dem Cart-Model lesen & Settings nutzen)
        $expressGross = 0;
        $expressTaxAmount = 0;

        if ($cart->is_express) {
            // Express-Zuschlag aus shop-settings laden (Fallback 2500 Cent)
            $expressGross = (int) shop_setting('express_surcharge', 2500);
            $expressTaxRate = ($isEU && !$isSmallBusiness) ? $defaultTaxRate : 0.0;

            $expressNet = (int) round($expressGross / (1 + ($expressTaxRate / 100)));
            $expressTaxAmount = $expressGross - $expressNet;

            if ($expressTaxRate > 0) {
                $strExpRate = number_format($expressTaxRate, 0);
                if (!isset($taxesBreakdown[$strExpRate])) $taxesBreakdown[$strExpRate] = 0;
                $taxesBreakdown[$strExpRate] += $expressTaxAmount;
            }
        }

        $finalTotalGross = $totalAfterDiscount + $shippingGross + $expressGross;
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
            'express' => $expressGross,
            'express_tax' => $expressTaxAmount,
            'is_express' => $cart->is_express,
            'is_free_shipping' => $isFreeShipping,
            'missing_for_free_shipping' => $missingForFreeShipping,
            'total' => max(0, $finalTotalGross),
            'item_count' => $itemCount,
            'weight' => $totalWeight,
            'country' => $countryCode
        ];
    }

    /**
     * Ermittelt die Versandkosten (Datenbank statt Hardcoded!)
     */
    private function determineShippingCost(int $cartValueCents, float $totalWeight, string $countryCode): array
    {
        // 1. Zone finden (Auch DE wird jetzt hier gefunden!)
        $zone = ShippingZone::whereHas('countries', fn($q) => $q->where('country_code', $countryCode))
            ->with('rates')
            ->first();

        // Fallback "Weltweit"
        if (!$zone) {
            $zone = ShippingZone::where('name', 'Weltweit')->with('rates')->first();
        }

        // Absoluter Fallback
        if (!$zone) {
            return ['cost' => 490, 'is_free' => false, 'missing' => 0];
        }

        // 2. Passende Rate finden (Gewicht & Mindestbestellwert)
        // Wir suchen ALLE Rates, die vom Gewicht her passen.
        $validRates = $zone->rates()
            ->where(function($q) use ($totalWeight) {
                $q->where('min_weight', '<=', $totalWeight)
                    ->where(fn($sub) => $sub->where('max_weight', '>=', $totalWeight)->orWhereNull('max_weight'));
            })
            // UND: Der Warenkorb muss den Mindestwert für diesen Tarif haben (z.B. min_price 5000 für Kostenlos)
            ->where('min_price', '<=', $cartValueCents)
            ->orderBy('price', 'asc') // Wir nehmen den günstigsten (z.B. 0€ wenn verfügbar)
            ->get();

        $bestRate = $validRates->first();

        // 3. "Noch X Euro bis versandkostenfrei" berechnen
        $freeShippingRate = $zone->rates()
            ->where('price', 0)
            ->where('min_price', '>', $cartValueCents)
            ->where('min_weight', '<=', $totalWeight)
            ->orderBy('min_price', 'asc')
            ->first();

        $missing = 0;
        if ($freeShippingRate) {
            $missing = $freeShippingRate->min_price - $cartValueCents;
        }

        if ($bestRate) {
            return [
                'cost' => $bestRate->price,
                'is_free' => $bestRate->price === 0,
                'missing' => $missing
            ];
        }

        // Fallback wenn zu schwer oder kein Tarif passt
        return ['cost' => 2990, 'is_free' => false, 'missing' => 0];
    }

    private function refreshTotals(Cart $cart) {
        if($cart) $cart->touch();
    }
}

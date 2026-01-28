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
        $taxesBreakdown = [];
        $itemCount = 0;

        foreach ($cart->items as $item) {
            $product = $item->product;
            if (!$product) continue;

            $qty = $item->quantity;
            $itemCount += $qty;

            // 1. Zeilensumme
            $lineGross = $item->unit_price * $qty;
            $subtotalGross += $lineGross;

            // 2. Originalpreis
            $basePrice = $product->price;
            if ($product->tax_included === false) {
                $basePrice = (int) round($basePrice * (1 + (($product->tax_rate ?? 19.0) / 100)));
            }
            $originalSubtotal += ($basePrice * $qty);

            // 3. Steueranteil
            $taxRate = (float) ($product->tax_rate ?? 19.0);
            $lineNet = (int) round($lineGross / (1 + ($taxRate / 100)));
            $lineTax = $lineGross - $lineNet;

            $strRate = number_format($taxRate, 0);
            if (!isset($taxesBreakdown[$strRate])) $taxesBreakdown[$strRate] = 0;
            $taxesBreakdown[$strRate] += $lineTax;
        }

        $volumeDiscount = max(0, $originalSubtotal - $subtotalGross);

        // --- GUTSCHEIN ---
        $discountAmount = 0;
        $couponCode = $cart->coupon_code;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();

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
                    $discountAmount = min($discountAmount, $subtotalGross);
                }
            } else {
                $cart->update(['coupon_code' => null]);
                $couponCode = null;
            }
        }

        // --- VERSAND (KORRIGIERT: Immer kostenlos) ---
        $shippingGross = 0;

        // --- ENDSUMME ---
        $totalAfterDiscount = $subtotalGross - $discountAmount;
        $finalTotalGross = $totalAfterDiscount + $shippingGross;

        // --- STEUER KORREKTUR ---
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
            'total' => max(0, $finalTotalGross),
            'item_count' => $itemCount
        ];
    }

    private function refreshTotals(Cart $cart) {
        if($cart) $cart->touch();
    }
}

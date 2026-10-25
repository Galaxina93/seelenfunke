<?php

namespace App\Services;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Product\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected $shippingService;

    // Dependency Injection im Constructor (Best Practice)
    public function __construct(ShippingCalculatorService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    /**
     * Ruft den aktuellen Warenkorb ab oder erstellt einen neuen.
     */
    public function getCart(bool $createIfNotExists = true): ?Cart
    {
        $cart = null;

        if (Auth::guard('customer')->check()) {
            $user = Auth::guard('customer')->user();

            if (!$createIfNotExists) {
                $cart = Cart::where('customer_id', $user->id)->first();
                if ($cart && $cart->session_id !== Session::getId()) {
                    $cart->update(['session_id' => Session::getId()]);
                }
            } else {
                $cart = Cart::firstOrCreate(
                    ['customer_id' => $user->id],
                    ['session_id' => Session::getId()]
                );

                // Session ID aktuell halten
                if ($cart->session_id !== Session::getId()) {
                    $cart->update(['session_id' => Session::getId()]);
                }
            }
        } else {
            $carts = Cart::where('session_id', Session::getId())->get();
            if ($carts->count() > 0) {
                $cart = $carts->first();
                
                // Lösche eventuelle leere Duplikate, die durch asynchrone Race Conditions parallel entstanden sind
                if ($carts->count() > 1) {
                    foreach ($carts->skip(1) as $duplicate) {
                        if ($duplicate->items()->count() === 0) {
                            $duplicate->delete();
                        }
                    }
                }
            } elseif ($createIfNotExists) {
                $cart = Cart::create(['session_id' => Session::getId()]);
            }
        }

        if ($cart) {
            $this->checkAndApplyPendingCoupon($cart);
        }

        return $cart;
    }

    protected function checkAndApplyPendingCoupon(Cart $cart): void
    {
        if (empty($cart->coupon_code)) {
            $pendingCoupon = session('seelenfunke_coupon') ?: request()->cookie('seelenfunke_coupon');
            if ($pendingCoupon) {
                $customer = Auth::guard('customer')->check() ? Auth::guard('customer')->user() : null;
                $buyerEmail = $customer ? $customer->email : null;
                $buyerCustomerId = $customer ? $customer->id : null;
                $referralCheck = app(\App\Services\ReferralService::class)->validateReferralCode($pendingCoupon, $buyerEmail, $buyerCustomerId);
                if ($referralCheck['is_referral'] && !$referralCheck['valid']) {
                    session()->forget('seelenfunke_coupon');
                    \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('seelenfunke_coupon'));
                    return; // Nicht auto-aktivieren bei Eigenwerbung oder Bestandskunden & Cookie/Session leeren
                }

                $coupon = MarketingVoucher::where('code', $pendingCoupon)->where('is_active', true)->first();
                $personalCouponAllowed = true;
                if ($coupon && !empty($coupon->recipient_email)) {
                    $personalCouponAllowed = $customer
                        && $customer->hasVerifiedEmail()
                        && strtolower(trim((string) $buyerEmail)) === strtolower(trim($coupon->recipient_email));
                }
                if ($coupon && $coupon->isValid() && $personalCouponAllowed) {
                    $cart->update(['coupon_code' => $coupon->code]);
                    $cart->coupon_code = $coupon->code;
                } else {
                    session()->forget('seelenfunke_coupon');
                    \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('seelenfunke_coupon'));
                }
            }
        }
    }

    /**
     * Fügt ein Produkt hinzu.
     */
    public function addItem(Product $product, int $quantity = 1, ?array $configuration = null): void
    {
        if ($product->status !== 'active' && !auth()->guard('admin')->check() && !auth()->guard('employee')->check()) {
            return;
        }

        $quantity = max(1, min(100, $quantity));
        $configuration = $this->sanitizeConfiguration($product, $configuration);
        $cart = $this->getCart();

        // Suche nach existierendem Item mit gleicher Konfiguration
        // Wir nutzen json_encode zum Vergleich der Arrays
        $existingItems = $cart->items()->where('product_id', $product->id)->get();
        $existingItem = $existingItems->first(function ($item) use ($configuration) {
            return json_encode($item->configuration) === json_encode($configuration);
        });

        $newQty = $existingItem ? min(100, $existingItem->quantity + $quantity) : $quantity;

        // FIX: Konfiguration an Preisberechnung übergeben!
        $unitPrice = $this->resolveItemUnitPrice($product, $newQty, $configuration);

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

        // Express sync wird nun von refreshTotals übernommen

        $this->refreshTotals($cart);
    }

    public function updateItem(string $itemId, int $quantity, ?array $configuration = null): void
    {
        $item = CartItem::find($itemId);
        if (!$item) return;

        // IDOR-Schutz: Kunden dürfen nur Artikel aus ihrem eigenen aktiven Warenkorb modifizieren
        if (!auth()->guard('admin')->check() && !auth()->guard('employee')->check()) {
            $currentCart = $this->getCart();
            if ($item->cart_id !== $currentCart->id) {
                \Illuminate\Support\Facades\Log::warning("Unberechtigter Zugriffsversuch auf fremdes CartItem {$itemId} aus Cart {$currentCart->id}");
                \App\Services\System\SystemSecurityService::logEvent(
                    'security:cart_idor_attempt',
                    'Warenkorb-IDOR-Versuch blockiert',
                    "Versuch, fremdes CartItem {$itemId} zu modifizieren.",
                    'warning',
                    ['item_id' => $itemId, 'cart_id' => $currentCart->id]
                );
                return;
            }
        }

        if ($quantity <= 0) {
            $this->removeItem($itemId);
            return;
        }

        $quantity = min(100, $quantity);
        if ($configuration !== null && $item->product) {
            $configuration = $this->sanitizeConfiguration($item->product, $configuration);
        }

        // FIX: Konfiguration an Preisberechnung übergeben!
        $unitPrice = $this->resolveItemUnitPrice($item->product, $quantity, $configuration ?? $item->configuration);

        $data = [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity
        ];

        if ($configuration !== null) {
            $data['configuration'] = $configuration;
        }

        $item->update($data);

        // Express sync wird nun von refreshTotals übernommen

        $this->refreshTotals($item->cart);
    }

    public function updateQuantity(string $itemId, int $quantity): void
    {
        $item = CartItem::find($itemId);
        if (!$item) return;

        // IDOR-Schutz: Kunden dürfen nur Artikel aus ihrem eigenen aktiven Warenkorb modifizieren
        if (!auth()->guard('admin')->check() && !auth()->guard('employee')->check()) {
            $currentCart = $this->getCart();
            if ($item->cart_id !== $currentCart->id) {
                \Illuminate\Support\Facades\Log::warning("Unberechtigter Mengen-Änderungsversuch auf fremdes CartItem {$itemId} aus Cart {$currentCart->id}");
                \App\Services\System\SystemSecurityService::logEvent(
                    'security:cart_idor_attempt',
                    'Warenkorb-IDOR-Versuch blockiert',
                    "Versuch, Menge von fremdem CartItem {$itemId} zu ändern.",
                    'warning',
                    ['item_id' => $itemId, 'cart_id' => $currentCart->id]
                );
                return;
            }
        }

        if ($quantity <= 0) {
            $this->removeItem($itemId);
            return;
        }

        $quantity = min(100, $quantity);

        // FIX: Konfiguration an Preisberechnung übergeben!
        $unitPrice = $this->resolveItemUnitPrice($item->product, $quantity, $item->configuration);

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
            // IDOR-Schutz: Kunden dürfen nur Artikel aus ihrem eigenen aktiven Warenkorb entfernen
            if (!auth()->guard('admin')->check() && !auth()->guard('employee')->check()) {
                $currentCart = $this->getCart();
                if ($item->cart_id !== $currentCart->id) {
                    \Illuminate\Support\Facades\Log::warning("Unberechtigter Löschversuch auf fremdes CartItem {$itemId} aus Cart {$currentCart->id}");
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:cart_idor_attempt',
                        'Warenkorb-IDOR-Versuch blockiert',
                        "Versuch, fremdes CartItem {$itemId} zu löschen.",
                        'warning',
                        ['item_id' => $itemId, 'cart_id' => $currentCart->id]
                    );
                    return;
                }
            }

            $cart = $item->cart;

            // Sofortiges Bereinigen verknüpfter Uploads und Snapshots (Lösung B)
            try {
                app(CartFileCleanupService::class)->cleanupCartItemFiles($item);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Fehler beim Bereinigen von Cart-Dateien für Item {$itemId}: " . $e->getMessage());
            }

            $item->delete();
            $this->refreshTotals($cart);
        }
    }

    public function emptyCart(): void
    {
        $cart = $this->getCart();
        $cleanupService = app(CartFileCleanupService::class);
        foreach ($cart->items as $item) {
            try {
                $cleanupService->cleanupCartItemFiles($item);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Fehler beim Bereinigen von Cart-Dateien beim Leeren des Warenkorbs: " . $e->getMessage());
            }
            $item->delete();
        }
        $cart->update(['coupon_code' => null, 'is_express' => false]);
        $this->refreshTotals($cart);
    }

    /**
     * Resolves unit price, using quote snapshot price if checking out from a quote.
     */
    public function resolveItemUnitPrice(Product $product, int $qty, ?array $configuration = null): int
    {
        $quoteId = session('checkout_from_quote_id');
        if ($quoteId) {
            $quote = \App\Models\Order\OrderQuoteRequest::find($quoteId);
            if (!$quote || !$quote->isValid()) {
                session()->forget('checkout_from_quote_id');
                return $this->calculateTierPrice($product, $qty, $configuration);
            }

            $quoteItems = \App\Models\Order\OrderQuoteRequestItem::where('quote_request_id', $quoteId)
                ->where('product_id', $product->id)
                ->get();
            
            $matchingQuoteItem = $quoteItems->first(function ($qItem) use ($configuration) {
                return json_encode($qItem->configuration) === json_encode($configuration);
            });

            if ($matchingQuoteItem && $qty >= max(1, (int) $matchingQuoteItem->quantity)) {
                return $matchingQuoteItem->unit_price;
            }
        }

        return $this->calculateTierPrice($product, $qty, $configuration);
    }

    /**
     * Berechnet den Einzelpreis (Staffel & Variante).
     */
    public function calculateTierPrice(Product $product, int $qty, ?array $configuration = null): int
    {
        if ($product->slug === 'geschenkgutschein' && $configuration && isset($configuration['amount_cents'])) {
            return max(500, min(100000, (int) $configuration['amount_cents']));
        }

        $price = $product->price;

        // 1. NEU: Varianten-Preis überschreiben, falls vorhanden
        if ($configuration && isset($configuration['variant_id'])) {
            $variants = $product->variants_data ?? [];
            $variant = collect($variants)->firstWhere('id', $configuration['variant_id']);

            // Wenn die Variante gefunden wurde UND einen abweichenden Preis hat
            if ($variant && !empty($variant['price'])) {
                $price = (int) round((float) $variant['price'] * 100);
            }
        }

        // 2. Mengenrabatt anwenden
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

        return max(0, (int) round($price));
    }

    /**
     * Bereinigt und validiert Artikelkonfigurationen gegen Preis-, Gutschein- und Pfad-Manipulation.
     */
    public function sanitizeConfiguration(Product $product, ?array $configuration): ?array
    {
        if ($configuration === null) {
            return null;
        }

        if ($product->slug !== 'geschenkgutschein') {
            if (isset($configuration['is_gift_voucher']) || isset($configuration['amount_cents'])) {
                \App\Services\System\SystemSecurityService::logEvent(
                    'security:price_tampering_attempt',
                    'Gutschein-Parameter-Manipulation blockiert',
                    "Versuch, Gutschein-Konfigurationswerte (is_gift_voucher / amount_cents) an Produkt '{$product->slug}' zu übergeben.",
                    'error',
                    ['product_id' => $product->id, 'slug' => $product->slug]
                );
                unset($configuration['is_gift_voucher'], $configuration['amount_cents']);
            }
        } else {
            $rawCents = isset($configuration['amount_cents']) ? (int) $configuration['amount_cents'] : ((int) $product->price ?: 2500);
            if ($rawCents < 500 || $rawCents > 100000) {
                \App\Services\System\SystemSecurityService::logEvent(
                    'security:price_tampering_attempt',
                    'Ungültiger Gutscheinbetrag abgefangen',
                    "Manipulierter Geschenkgutschein-Betrag ({$rawCents} Cents) wurde auf erlaubte Grenzen (500–100000 Cents) korrigiert.",
                    'error',
                    ['raw_amount_cents' => $rawCents]
                );
            }
            $configuration['amount_cents'] = max(500, min(100000, $rawCents));
            $configuration['is_gift_voucher'] = true;
        }

        // Pfad-Validierung gegen Path Traversal in Konfigurator-Dateien
        if (isset($configuration['files']) && is_array($configuration['files'])) {
            $configuration['files'] = array_values(array_filter($configuration['files'], function ($f) {
                return is_string($f) && !str_contains($f, '..') && !str_contains($f, "\0") && !str_starts_with($f, '/') && !str_starts_with($f, '\\');
            }));
        }

        foreach (['logos', 'logos_back'] as $logoKey) {
            if (isset($configuration[$logoKey]) && is_array($configuration[$logoKey])) {
                $configuration[$logoKey] = array_values(array_filter($configuration[$logoKey], function ($logo) {
                    if (!is_array($logo) || !isset($logo['value']) || !is_string($logo['value'])) {
                        return false;
                    }
                    $v = $logo['value'];
                    return !str_contains($v, '..') && !str_contains($v, "\0") && !str_starts_with($v, '/') && !str_starts_with($v, '\\');
                }));
            }
        }

        foreach (['logo_path', 'snapshot_path', 'snapshot_back_path', 'purchased_image_path'] as $pathKey) {
            if (isset($configuration[$pathKey]) && is_string($configuration[$pathKey])) {
                if (str_contains($configuration[$pathKey], '..') || str_contains($configuration[$pathKey], "\0")) {
                    unset($configuration[$pathKey]);
                }
            }
        }

        return $configuration;
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', trim($email));
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $maskedLocal = mb_substr($local, 0, min(2, mb_strlen($local))) . '***';
        return $maskedLocal . '@' . $domain;
    }

    public function applyCoupon(string $code): array
    {
        // 1. Zuerst prüfen, ob es sich um einen Wertgutschein handelt
        $giftVoucher = \App\Models\Marketing\MarketingGiftVoucher::where('code', $code)->where('is_active', true)->first();
        if ($giftVoucher) {
            if ($giftVoucher->current_balance <= 0) {
                return ['success' => false, 'message' => 'Dieser Gutschein ist bereits aufgebraucht.'];
            }
            if ($giftVoucher->valid_until && now()->gt($giftVoucher->valid_until)) {
                return ['success' => false, 'message' => 'Dieser Gutschein ist abgelaufen.'];
            }

            // [SICHERHEIT] Persönliche Belohnungsgutscheine an das verifizierte Kundenkonto binden
            $currentBuyerCustomer = Auth::guard('customer')->check() ? Auth::guard('customer')->user() : null;
            $currentBuyerEmail = ($currentBuyerCustomer && $currentBuyerCustomer->hasVerifiedEmail()) ? $currentBuyerCustomer->email : null;
            if (!empty($giftVoucher->recipient_email) && str_starts_with($giftVoucher->code, 'DANKE-')) {
                if (!$currentBuyerEmail || strtolower(trim($currentBuyerEmail)) !== strtolower(trim($giftVoucher->recipient_email))) {
                    return [
                        'success' => false,
                        'message' => 'Dieser Belohnungsgutschein ist persönlich an das Kundenkonto (' . $this->maskEmail($giftVoucher->recipient_email) . ') gebunden. Bitte melde dich an, um ihn einzulösen.'
                    ];
                }
            }

            $cart = $this->getCart();

            // Kein Gutschein-für-Gutschein-Kauf (UStG / Geldwäsche- & Gültigkeitsverlängerungs-Schutz)
            $nonVoucherSubtotal = 0;
            foreach ($cart->items as $item) {
                $product = $item->product;
                if (!$product) continue;
                $isGiftVoucher = ($product->slug === 'geschenkgutschein') || (!empty($item->configuration['is_gift_voucher']));
                if (!$isGiftVoucher) {
                    $nonVoucherSubtotal += $item->unit_price * $item->quantity;
                }
            }

            if ($nonVoucherSubtotal <= 0) {
                return [
                    'success' => false,
                    'message' => 'Gutscheine können nicht für den Kauf weiterer Geschenkgutscheine eingelöst werden.'
                ];
            }

            $cart->update(['coupon_code' => $giftVoucher->code]);
            return ['success' => true, 'message' => 'Geschenkgutschein erfolgreich angewendet!'];
        }

        // 2. Prüfen auf Empfehlungscode (Eigenmissbrauch- & Neukunden-Schutz)
        $customer = Auth::guard('customer')->check() ? Auth::guard('customer')->user() : null;
        $buyerEmail = $customer ? $customer->email : null;
        $buyerCustomerId = $customer ? $customer->id : null;
        $referralService = app(\App\Services\ReferralService::class);
        $referralCheck = $referralService->validateReferralCode($code, $buyerEmail, $buyerCustomerId);
        if ($referralCheck['is_referral'] && !$referralCheck['valid']) {
            return ['success' => false, 'message' => $referralCheck['message']];
        }

        $coupon = MarketingVoucher::where('code', $code)->first();

        if (!$coupon || !$coupon->isValid()) {
            return ['success' => false, 'message' => 'Gutschein ist ungültig oder abgelaufen.'];
        }

        // [SICHERHEIT] Persönliche Belohnungsgutscheine an das verifizierte Kundenkonto binden
        if (!empty($coupon->recipient_email)) {
            if (!$customer || !$customer->hasVerifiedEmail() || !$buyerEmail || strtolower(trim($buyerEmail)) !== strtolower(trim($coupon->recipient_email))) {
                return [
                    'success' => false,
                    'message' => 'Dieser Belohnungsgutschein ist persönlich an das Kundenkonto (' . $this->maskEmail($coupon->recipient_email) . ') gebunden. Bitte melde dich an, um ihn einzulösen.'
                ];
            }
        }

        $cart = $this->getCart();

        // Rabattfähigen Warenwert berechnen (ohne Geschenkgutscheine & ohne bereits im Autopilot-Sale reduzierte Artikel)
        $discountableSubtotal = 0;
        $hasAutopilotSaleItems = false;
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (!$product) continue;
            $isGiftVoucher = ($product->slug === 'geschenkgutschein') || (!empty($item->configuration['is_gift_voucher']));
            $isAutopilotSale = !empty($product->configurator_settings['autopilot_base_price']) && (int) $product->compare_at_price > (int) $product->price;
            if ($isAutopilotSale) {
                $hasAutopilotSaleItems = true;
                continue;
            }
            if (!$isGiftVoucher) {
                $discountableSubtotal += $item->unit_price * $item->quantity;
            }
        }

        if ($discountableSubtotal <= 0) {
            if ($hasAutopilotSaleItems) {
                return [
                    'success' => false,
                    'message' => 'Bereits reduzierte Feiertags- & Aktions-Unikate sind nicht mit zusätzlichen Rabattcodes kombinierbar.'
                ];
            }
            return [
                'success' => false,
                'message' => 'Rabattcodes können nicht auf den Kauf von Geschenkgutscheinen angewendet werden.'
            ];
        }

        if ($coupon->min_order_value && $discountableSubtotal < $coupon->min_order_value) {
            return [
                'success' => false,
                'message' => 'Mindestbestellwert von ' . number_format($coupon->min_order_value / 100, 2, ',', '.') . ' € für rabattfähige Artikel nicht erreicht.'
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
        $cart = $this->getCart(false);
        if (!$cart) {
            return [
                'subtotal_original' => 0,
                'subtotal_gross' => 0,
                'volume_discount' => 0,
                'discount_amount' => 0,
                'coupon_code' => null,
                'tax' => 0,
                'taxes_breakdown' => [],
                'shipping' => 0,
                'shipping_tax' => 0,
                'express' => 0,
                'express_tax' => 0,
                'is_express' => false,
                'is_free_shipping' => false,
                'missing_for_free_shipping' => 0,
                'total' => 0,
                'item_count' => 0,
                'weight' => 0,
                'country' => $countryCode
            ];
        }
        return $this->calculateTotals($cart, $countryCode);
    }

    /**
     * Die Haupt-Rechenmaschine.
     */
    public function calculateTotals(Cart $cart, string $countryCode = 'DE'): array
    {
        $cart->load(['items.product.tierPrices']);

        $subtotalGross = 0;
        $originalSubtotal = 0;
        $physicalSubtotalGross = 0;
        $taxesBreakdown = [];
        $itemCount = 0;
        $totalWeight = 0;

        // Globale Einstellungen aus der 'shop-settings' Tabelle laden
        $isSmallBusiness = (bool)shop_setting('is_small_business', false);
        $defaultTaxRate = (float)shop_setting('default_tax_rate', 19.0);
        $maxTaxRate = 0.0;

        // 1. ARTIKEL DURCHLAUFEN
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (!$product) continue;

            $qty = $item->quantity;
            $itemCount += $qty;

            // Gewicht summieren
            $weight = (int)($product->weight ?? 0);
            $totalWeight += ($weight * $qty);

            // FIX: Frischen Preis berechnen (mit Variante)
            $freshUnitPrice = $this->resolveItemUnitPrice($product, $qty, $item->configuration);

            if ($freshUnitPrice !== $item->unit_price) {
                $item->unit_price = $freshUnitPrice;
                $item->saveQuietly(); // Events vermeiden um Endlosschleifen zu verhindern
            }

            $lineGross = $freshUnitPrice * $qty;
            $subtotalGross += $lineGross;

            // Wir addieren den Betrag nur zur physischen Summe, wenn es Versand erfordert
            if ($product->type === 'physical') {
                $physicalSubtotalGross += $lineGross;
            }

            // Originalpreis (für Streichpreise/Mengenrabatt-Anzeige)
            // Auch hier muss die Variante greifen, sonst wird der falsche Basispreis zum Abziehen genommen!
            $basePrice = $this->calculateTierPrice($product, 1, $item->configuration);
            $originalSubtotal += ($basePrice * $qty);

            // Steueranteil herausrechnen
            $productTaxRate = $product->tax_rate !== null ? (float)$product->tax_rate : $defaultTaxRate;
            if ($productTaxRate > $maxTaxRate) {
                $maxTaxRate = $productTaxRate;
            }
            
            $taxRate = $isSmallBusiness ? 0.0 : $productTaxRate;

            $lineNet = (int) round($lineGross / (1 + ($taxRate / 100)));
            $lineTax = $lineGross - $lineNet;

            // Ensure 0% breakdown array starts at 0 if no item has > 0 tax yet
            $strRate = number_format($taxRate, 0);
            if (!isset($taxesBreakdown[$strRate])) $taxesBreakdown[$strRate] = 0;
            $taxesBreakdown[$strRate] += $lineTax;
        }
        
        if ($maxTaxRate === 0.0 && $itemCount === 0) {
            $maxTaxRate = $defaultTaxRate;
        }

        $volumeDiscount = max(0, $originalSubtotal - $subtotalGross);

        // 2. VERSANDKOSTEN & EXPRESS-ZUSCHLAG VORAB BERECHNEN (für Wertgutschein-Verrechnung)
        $shippingResult = $this->shippingService->calculateShippingCost(
            $cart->items,               // Die Items für die "Digital Check" Logik
            $physicalSubtotalGross,     // Warenwert VOR Rabatt für Freigrenze
            $totalWeight,               // Gewicht für Zonen
            $countryCode                // Land für Zonen
        );

        $shippingGross = $shippingResult['cost'];
        $isFreeShipping = $shippingResult['is_free'];
        $missingForFreeShipping = $shippingResult['missing'];

        $expressGross = 0;
        if ($cart->is_express && $this->shippingService->needsShipping($cart->items)) {
            $expressPercent = (float) shop_setting('express_surcharge_percent', 20.0);
            $expressMin = (int) shop_setting('express_surcharge_min', 500);
            $calculatedExpress = (int) round($subtotalGross * ($expressPercent / 100));
            $expressGross = max($expressMin, $calculatedExpress);
        }

        $totalBeforeDiscount = $subtotalGross + $shippingGross + $expressGross;

        // 3. GUTSCHEIN-BERECHNUNG
        $discountAmount = 0;
        $discountProducts = 0;
        $discountShipping = 0;
        $couponCode = $cart->coupon_code;

        if ($couponCode) {
            $activeCustomer = Auth::guard('customer')->check() ? Auth::guard('customer')->user() : null;
            $verifiedBuyerEmail = ($activeCustomer && $activeCustomer->hasVerifiedEmail()) ? strtolower(trim((string) $activeCustomer->email)) : null;

            // A) Zuerst prüfen, ob es sich um einen Wertgutschein handelt
            $giftVoucher = \App\Models\Marketing\MarketingGiftVoucher::where('code', $couponCode)->where('is_active', true)->first();
            $personalGiftAllowed = true;
            if ($giftVoucher && !empty($giftVoucher->recipient_email) && str_starts_with($giftVoucher->code, 'DANKE-')) {
                $personalGiftAllowed = $verifiedBuyerEmail !== null && $verifiedBuyerEmail === strtolower(trim($giftVoucher->recipient_email));
            }

            if ($giftVoucher && $giftVoucher->isValid() && $personalGiftAllowed) {
                $nonVoucherSubtotal = 0;
                foreach ($cart->items as $item) {
                    $product = $item->product;
                    if (!$product) continue;
                    $isGiftVoucher = ($product->slug === 'geschenkgutschein') || (!empty($item->configuration['is_gift_voucher']));
                    if (!$isGiftVoucher) {
                        $nonVoucherSubtotal += $item->unit_price * $item->quantity;
                    }
                }

                if ($nonVoucherSubtotal <= 0) {
                    $cart->update(['coupon_code' => null]);
                    $couponCode = null;
                } else {
                    // Wertgutschein deckt das gesamte nicht-Gutschein-Order-Total (inklusive Versand/Express) ab!
                    $redeemableCap = $nonVoucherSubtotal + $shippingGross + $expressGross;
                    $discountAmount = min($giftVoucher->current_balance, $redeemableCap);
                    $discountProducts = min($discountAmount, $nonVoucherSubtotal);
                    $discountShipping = $discountAmount - $discountProducts;
                }
            } elseif ($giftVoucher && !$personalGiftAllowed) {
                $cart->update(['coupon_code' => null]);
                $couponCode = null;
            } else {
                // B) Sonst normaler Marketing-Gutschein (Rabattcode)
                $coupon = MarketingVoucher::where('code', $couponCode)->first();
                $personalCouponAllowed = true;
                if ($coupon && !empty($coupon->recipient_email)) {
                    $personalCouponAllowed = $verifiedBuyerEmail !== null && $verifiedBuyerEmail === strtolower(trim($coupon->recipient_email));
                }

                if ($coupon && $coupon->isValid() && $personalCouponAllowed) {
                    // Rabattfähigen Warenwert berechnen (ohne Geschenkgutscheine & ohne bereits im Autopilot-Sale reduzierte Artikel)
                    $discountableSubtotal = 0;
                    foreach ($cart->items as $item) {
                        $product = $item->product;
                        if (!$product) continue;
                        $isGiftVoucher = ($product->slug === 'geschenkgutschein') || (!empty($item->configuration['is_gift_voucher']));
                        $isAutopilotSale = !empty($product->configurator_settings['autopilot_base_price']) && (int) $product->compare_at_price > (int) $product->price;
                        if (!$isGiftVoucher && !$isAutopilotSale) {
                            $discountableSubtotal += $item->unit_price * $item->quantity;
                        }
                    }

                    if ($discountableSubtotal <= 0) {
                        $cart->update(['coupon_code' => null]);
                        $couponCode = null;
                    } elseif ($coupon->min_order_value && $discountableSubtotal < $coupon->min_order_value) {
                        // Gutschein bleibt im cart hinterlegt, wird aber nicht vom Preis abgezogen
                    } else {
                        if ($coupon->type === 'fixed') {
                            $discountAmount = $coupon->value;
                        } elseif ($coupon->type === 'percent') {
                            $discountAmount = (int) round($discountableSubtotal * ($coupon->value / 100));
                        }
                        $discountAmount = min($discountAmount, $discountableSubtotal);
                        $discountProducts = $discountAmount;
                    }
                } else {
                    $cart->update(['coupon_code' => null]);
                    $couponCode = null;
                }
            }
        }

        // Zwischensumme nach Rabatten (Zahlbetrag für Produkte)
        $totalAfterDiscount = max(0, $subtotalGross - $discountProducts);

        // 4. STEUER KORREKTUR (Rabatte proportional auf Produktsteuer umlegen)
        $discountRatio = $subtotalGross > 0 ? ($totalAfterDiscount / $subtotalGross) : 1;
        foreach($taxesBreakdown as $key => $val) {
            $taxesBreakdown[$key] = (int) round($val * $discountRatio);
        }

        // 5. VERSANDSTEUER (MAXIMALER STEUERSATZ EU-Logik)
        $shippingTaxAmount = 0;
        $euCountries = ['DE', 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];
        $isEU = in_array($countryCode, $euCountries);

        if ($shippingGross > 0) {
            // Versandkosten um den darauf entfallenden Gutscheinanteil reduzieren
            $remainingShipping = max(0, $shippingGross - $discountShipping);
            $shippingTaxRate = ($isEU && !$isSmallBusiness) ? $maxTaxRate : 0.0;
            
            // Steuer berechnen basierend auf den verbleibenden effektiven Versandkosten
            $shippingNet = (int) round($remainingShipping / (1 + ($shippingTaxRate / 100)));
            $shippingTaxAmount = $remainingShipping - $shippingNet;

            if ($shippingTaxRate > 0 || floatval($shippingTaxRate) == 0.0) {
                $strShipRate = number_format($shippingTaxRate, 0);
                if (!isset($taxesBreakdown[$strShipRate])) {
                    $taxesBreakdown[$strShipRate] = 0;
                }
                $taxesBreakdown[$strShipRate] += $shippingTaxAmount;
            }
        }

        // 6. EXPRESS-LOGIK (Steuerberechnung)
        $expressTaxAmount = 0;
        if ($expressGross > 0) {
            // Falls der Gutscheinwert sogar den Versand übersteigt, zieht er auch vom Express ab
            $remainingExpress = max(0, $expressGross - max(0, $discountShipping - $shippingGross));
            $expressTaxRate = ($isEU && !$isSmallBusiness) ? $maxTaxRate : 0.0;

            $expressNet = (int) round($remainingExpress / (1 + ($expressTaxRate / 100)));
            $expressTaxAmount = $remainingExpress - $expressNet;

            if ($expressTaxRate > 0 || floatval($expressTaxRate) == 0.0) {
                $strExpRate = number_format($expressTaxRate, 0);
                if (!isset($taxesBreakdown[$strExpRate])) $taxesBreakdown[$strExpRate] = 0;
                $taxesBreakdown[$strExpRate] += $expressTaxAmount;
            }
        }

        $finalTotalGross = max(0, $totalBeforeDiscount - $discountAmount);
        $finalTotalTax = array_sum($taxesBreakdown);

        $hasPostalVoucher = collect($cart->items)->contains(function ($ci) {
            $cfg = is_array($ci->configuration) ? $ci->configuration : [];
            return !empty($cfg['is_gift_voucher']) && ($cfg['delivery_method'] ?? 'email') === 'post';
        });

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
            'shipping_reason' => $shippingResult['reason'] ?? null,
            'has_postal_voucher' => $hasPostalVoucher,
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
        // 0. Globale Einstellungen aus der Shop-Konfiguration laden
        $globalThreshold = (int) shop_setting('shipping_free_threshold', 5000);
        $globalDefaultCost = (int) shop_setting('shipping_cost', 490);

        // 1. Zone finden (DE oder Ausland)
        $zone = \App\Models\Logistics\LogisticsShippingZone::where('is_active', true)
            ->whereHas('countries', fn($q) => $q->where('country_code', $countryCode))
            ->with('rates')
            ->first();

        // Fallback "Weltweit"
        if (!$zone) {
            $zone = \App\Models\Logistics\LogisticsShippingZone::where('is_active', true)->where('name', 'Weltweit')->with('rates')->first();
        }

        // Initialisierung der Status-Variablen für DE (Prioritäts-Logik)
        $isFreeByGlobalSettings = ($countryCode === 'DE' && $cartValueCents >= $globalThreshold);

        // Absoluter Fallback (Falls gar keine Zone/Rate existiert)
        if (!$zone) {
            $missing = $isFreeByGlobalSettings ? 0 : max(0, $globalThreshold - $cartValueCents);
            return [
                'cost' => $isFreeByGlobalSettings ? 0 : $globalDefaultCost,
                'is_free' => $isFreeByGlobalSettings,
                'missing' => ($countryCode === 'DE') ? $missing : 0
            ];
        }

        // 2. Passende Rate in der Zone finden (Gewicht & Mindestbestellwert)
        $validRates = $zone->rates()
            ->where(function($q) use ($totalWeight) {
                $q->where('min_weight', '<=', $totalWeight)
                    ->where(fn($sub) => $sub->where('max_weight', '>=', $totalWeight)->orWhereNull('max_weight'));
            })
            ->where('min_price', '<=', $cartValueCents)
            ->orderBy('price', 'asc')
            ->get();

        $bestRate = $validRates->first();

        // 3. "Noch X Euro bis versandkostenfrei" berechnen
        $freeShippingRateFromZone = $zone->rates()
            ->where('price', 0)
            ->where('min_price', '>', $cartValueCents)
            ->where('min_weight', '<=', $totalWeight)
            ->orderBy('min_price', 'asc')
            ->first();

        $missing = 0;

        // A: Logik für Deutschland (Priorität auf Globale Schwelle)
        if ($countryCode === 'DE') {
            if (!$isFreeByGlobalSettings) {
                $missing = max(0, $globalThreshold - $cartValueCents);

                if ($freeShippingRateFromZone) {
                    $zoneMissing = max(0, $freeShippingRateFromZone->min_price - $cartValueCents);
                    $missing = min($missing, $zoneMissing);
                }
            }
        }
        // B: Logik für Ausland
        else {
            if ($freeShippingRateFromZone) {
                $missing = max(0, $freeShippingRateFromZone->min_price - $cartValueCents);
            }
        }

        // 4. Finales Resultat ermitteln

        // FALL 1: DEUTSCHLAND & Globale Schwelle erreicht
        if ($isFreeByGlobalSettings) {
            return [
                'cost' => 0,
                'is_free' => true,
                'missing' => 0
            ];
        }

        // FALL 2: Tarif in Zone gefunden
        if ($bestRate) {
            $finalCost = $bestRate->price;
            $isFree = ($finalCost === 0);

            // Sicherheits-Check für DE
            if ($countryCode === 'DE' && $finalCost > 0) {
                $finalCost = $globalDefaultCost;
            }

            return [
                'cost' => $finalCost,
                'is_free' => $isFree,
                'missing' => $isFree ? 0 : $missing
            ];
        }

        // FALL 3: Absoluter Fallback
        return [
            'cost' => ($countryCode === 'DE') ? $globalDefaultCost : 2990,
            'is_free' => false,
            'missing' => $missing
        ];
    }

    private function refreshTotals(Cart $cart) {
        if($cart) {
            $hasExpress = $cart->items()->where('configuration->is_express', true)->exists();
            if ($cart->is_express !== $hasExpress) {
                $cart->is_express = $hasExpress;
            }
            $cart->touch(); // touch calls save internally if modified
            $cart->save();
        }
    }
}

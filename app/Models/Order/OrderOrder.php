<?php

namespace App\Models\Order;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Customer\Customer; // GEÄNDERT: Customer statt User
use App\Traits\FormatsECommerceData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderOrder extends Model
{
    use HasFactory, SoftDeletes, HasUuids, FormatsECommerceData;

    protected $table = 'order_orders';

    protected $fillable = [
        'order_number',
        'customer_id',
        'email',
        'status',
        'payment_status',
        'payment_method',
        'payment_url',
        'stripe_payment_intent_id',
        'billing_address',
        'shipping_address',
        'volume_discount',
        'coupon_code',
        'discount_amount',
        'subtotal_price',
        'tax_amount',
        'shipping_price',
        'total_price',
        'notes',
        'cancellation_reason',
        'is_express',
        'express_price',
        'expires_at',
        'token',
        'is_small_business',
        'utm_source_first',
        'utm_campaign_first',
        'utm_medium_first',
        'utm_source_last',
        'utm_campaign_last',
        'utm_medium_last',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal_price' => 'integer',
        'tax_amount' => 'integer',
        'shipping_price' => 'integer',
        'total_price' => 'integer',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_express' => 'boolean',
        'is_small_business' => 'boolean',
        'cancellation_reason' => 'string',
    ];

    protected static function booted()
    {
        static::updated(function ($order) {
            // 1. Synchronize "paid" status
            if ($order->isDirty('payment_status') && $order->payment_status === 'paid') {
                $invoices = $order->invoices()->where('type', 'invoice')->whereIn('status', ['open', 'draft'])->get();
                foreach ($invoices as $invoice) {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
                    try {
                        app(\App\Services\InvoiceService::class)->storePdf($invoice);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to store PDF for invoice {$invoice->invoice_number} on order status sync: " . $e->getMessage());
                    }
                }

                try {
                    app(\App\Services\Marketing\InfluencerService::class)->processOrderCommission($order);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Influencer commission processing error on status sync: ' . $e->getMessage());
                }
            }

            // 1b. Synchronize "shipped" / "completed" -> approve pending influencer commission
            if ($order->isDirty('status') && in_array($order->status, ['shipped', 'completed'], true) && $order->payment_status === 'paid') {
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('marketing_influencer_commissions')) {
                        \App\Models\Marketing\MarketingInfluencerCommission::where('order_id', $order->id)
                            ->where('status', 'pending')
                            ->whereNull('payout_id')
                            ->update([
                                'status' => 'approved',
                                'approved_at' => now(),
                            ]);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Influencer commission approval error: ' . $e->getMessage());
                }
            }

            // 2. Synchronize "cancelled" or "refunded" status
            if (
                ($order->isDirty('payment_status') && $order->payment_status === 'refunded') ||
                ($order->isDirty('status') && in_array($order->status, ['cancelled', 'refunded']))
            ) {
                $invoices = $order->invoices()->where('type', 'invoice')->whereIn('status', ['paid', 'open'])->get();
                foreach ($invoices as $invoice) {
                    try {
                        app(\App\Services\InvoiceService::class)->cancelInvoice($invoice);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to cancel invoice {$invoice->invoice_number} on order status sync: " . $e->getMessage());
                    }
                }

                try {
                    app(\App\Services\Marketing\InfluencerService::class)->reverseOrderCommission($order, (string) ($order->cancellation_reason ?: 'Stornierung/Erstattung'));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Influencer commission reversal error on status sync: ' . $e->getMessage());
                }
            }
        });
    }

    public function items()
    {
        return $this->hasMany(OrderOrderItem::class, 'order_id');
    }

    public function shipments()
    {
        return $this->hasMany(OrderShipment::class, 'order_id');
    }

    public function getTrackingNumberAttribute()
    {
        $shipment = $this->shipments()->first();
        return $shipment ? $shipment->tracking_number : null;
    }

    public function getShippingLabelPathAttribute()
    {
        $shipment = $this->shipments()->latest()->first();
        return $shipment ? $shipment->shipping_label_path : null;
    }

    // Zeigt nun auf Customer::class (inkl. SoftDeletes/archivierte Accounts für GoBD-Konformität)
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => '#FF7D4F',
            'processing' => '#3b82f6',
            'shipped' => '#a855f7',
            'completed' => '#10b981',
            'cancelled' => '#ef4444',
            'refunded' => '#6b7280',
            default => '#6b7280',
        };
    }

    public function getPaymentStatusColorAttribute()
    {
        return match($this->payment_status) {
            'paid' => '#10b981',
            'unpaid' => '#ef4444',
            'pending' => '#FF7D4F',
            'refunded' => '#6b7280',
            default => '#6b7280',
        };
    }

    public function getCustomerNameAttribute()
    {
        if (isset($this->billing_address['first_name'])) {
            return $this->billing_address['first_name'] . ' ' . $this->billing_address['last_name'];
        }
        return 'Gast';
    }

    public function getShippingTaxAmountAttribute()
    {
        if ($this->shipping_price <= 0 || shop_setting('is_small_business', false)) {
            return 0;
        }
        $taxRate = (float)shop_setting('default_tax_rate', 19.00);
        $divisor = 1 + ($taxRate / 100);
        return (int)round($this->shipping_price - ($this->shipping_price / $divisor));
    }

    public function getShippingNetPriceAttribute()
    {
        return $this->shipping_price - $this->shipping_tax_amount;
    }

    public function getIsFreeShippingAttribute()
    {
        return $this->shipping_price === 0;
    }

    public function cancel(string $reason): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason
        ]);
    }

    public function invoices()
    {
        return $this->hasMany(AccountingInvoice::class, 'order_id');
    }

    /**
     * Check if the order contains only digital products.
     */
    public function isOnlyDigital(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }
        return $this->items->every(function ($item) {
            $product = $item->product;
            if (!$product) return false;
            
            // Check if it is a postal gift voucher
            $config = $item->configuration ?? [];
            if (!empty($config['is_gift_voucher']) && ($config['delivery_method'] ?? 'email') === 'post') {
                return false;
            }
            
            return $product->type === 'digital';
        });
    }

    /**
     * Get DHL weight details for the order including item breakdown and recommendation.
     */
    public function getDhlWeightDetails(int $packageCount = 1, string $packagingType = 'box'): array
    {
        $packageCount = max(1, $packageCount);
        $itemsList = [];
        $totalProductWeightGrams = 0;
        $remainingProductWeightGrams = 0;
        $maxTaraWeight = 0;
        $maxSingleItemWeight = 0;
        $totalItemsCount = 0;
        $hasFallbackWeight = false;

        foreach ($this->items as $item) {
            $product = $item->product;
            // Digital-Prüfung: rein digitale Produkte nicht physisch ins Paket wiegen
            $isDigital = $product && $product->type === 'digital';
            $config = $item->configuration ?? [];
            if (!empty($config['is_gift_voucher']) && ($config['delivery_method'] ?? 'email') === 'email') {
                $isDigital = true;
            }

            if ($product && !$isDigital) {
                $isFallback = empty($product->weight) || $product->weight <= 0;
                if ($isFallback) {
                    $hasFallbackWeight = true;
                }
                $itemWeight = !$isFallback ? (int) $product->weight : 100;
                $lineTotalWeight = $itemWeight * $item->quantity;
                $totalProductWeightGrams += $lineTotalWeight;

                if ($itemWeight > $maxSingleItemWeight) {
                    $maxSingleItemWeight = $itemWeight;
                }

                $remainingQty = max(0, $item->quantity - ($item->completed_quantity ?? 0));
                $remainingProductWeightGrams += ($itemWeight * $remainingQty);

                if ($product->packaging_weight && $product->packaging_weight > $maxTaraWeight) {
                    $maxTaraWeight = (int) $product->packaging_weight;
                }

                $totalItemsCount += $item->quantity;

                $itemsList[] = [
                    'id'                  => $item->id,
                    'product_id'          => $item->product_id,
                    'name'                => $item->product_name ?: ($product->name ?? 'Unbekanntes Produkt'),
                    'quantity'            => $item->quantity,
                    'remaining_quantity'  => $remainingQty,
                    'single_weight_grams' => $itemWeight,
                    'total_weight_grams'  => $lineTotalWeight,
                    'is_fallback'         => $isFallback,
                ];
            }
        }

        $weightToUse = $remainingProductWeightGrams > 0 ? $remainingProductWeightGrams : $totalProductWeightGrams;

        if ($weightToUse == 0 && $totalItemsCount > 0) {
            $weightToUse = $totalItemsCount * 100;
        }

        // Automatische Eignungsprüfung für Luftpolsterumschlag:
        // Physische Artikel vorhanden, Gesamtreingewicht <= 250g, Einzelschwerstes Teil <= 100g, keine schwere Sonderverpackung
        $canFitInEnvelope = $totalItemsCount > 0 
            && $weightToUse <= 250 
            && $maxSingleItemWeight <= 100 
            && $maxTaraWeight <= 50;

        $suggestedPackagingType = $canFitInEnvelope ? 'envelope' : 'box';

        // Tara & Puffer je nach gewählter Verpackungsart
        if ($packagingType === 'envelope') {
            $packagingName = 'Luftpolsterumschlag';
            $packagingWeightGrams = (int)shop_setting('packaging_weight_envelope_grams', 30);
            $bufferGrams = $weightToUse > 0 ? (int)shop_setting('shipping_buffer_envelope_grams', 10) : 0;
        } else {
            $packagingName = 'Karton';
            $packagingWeightGrams = $maxTaraWeight > 0 ? $maxTaraWeight : (int)shop_setting('packaging_weight_grams', 350);
            $bufferGrams = $weightToUse > 0 ? (int)shop_setting('shipping_buffer_grams', 50) : 0;
        }

        $totalGrams = $weightToUse + ($packageCount * $packagingWeightGrams) + ($packageCount * $bufferGrams);
        $weightPerPackageKg = round(($totalGrams / 1000) / $packageCount, 2);
        $weightPerPackageKg = max(0.01, $weightPerPackageKg);

        // DHL Tarifklasse & Schwellenwert-Prüfung
        $dhlClass = 'Paket bis 2 kg';
        $dhlBadgeColor = 'emerald';
        $isNearLimit = false;

        if ($weightPerPackageKg <= 2.00) {
            $dhlClass = 'Paket bis 2 kg';
            $dhlBadgeColor = 'emerald';
            if ($weightPerPackageKg >= 1.85) {
                $isNearLimit = true;
            }
        } elseif ($weightPerPackageKg <= 5.00) {
            $dhlClass = 'Paket bis 5 kg';
            $dhlBadgeColor = 'yellow';
            if ($weightPerPackageKg >= 4.80) {
                $isNearLimit = true;
            }
        } elseif ($weightPerPackageKg <= 10.00) {
            $dhlClass = 'Paket bis 10 kg';
            $dhlBadgeColor = 'amber';
            if ($weightPerPackageKg >= 9.70) {
                $isNearLimit = true;
            }
        } elseif ($weightPerPackageKg <= 31.50) {
            $dhlClass = 'Paket bis 31,5 kg';
            $dhlBadgeColor = 'orange';
            if ($weightPerPackageKg >= 31.00) {
                $isNearLimit = true;
            }
        } else {
            $dhlClass = 'Übergewicht (> 31,5 kg)';
            $dhlBadgeColor = 'red';
            $isNearLimit = true;
        }

        return [
            'items'                          => $itemsList,
            'total_items_count'              => $totalItemsCount,
            'product_weight_grams'           => $weightToUse,
            'packaging_type'                 => $packagingType,
            'packaging_name'                 => $packagingName,
            'packaging_weight_grams'         => $packagingWeightGrams,
            'buffer_grams'                   => $bufferGrams,
            'total_grams'                    => $totalGrams,
            'total_weight_kg'                => round($totalGrams / 1000, 2),
            'recommended_weight_per_package' => $weightPerPackageKg,
            'dhl_class'                      => $dhlClass,
            'dhl_badge_color'                => $dhlBadgeColor,
            'is_near_limit'                  => $isNearLimit,
            'has_fallback_weight'            => $hasFallbackWeight,
            'can_fit_in_envelope'            => $canFitInEnvelope,
            'suggested_packaging_type'       => $suggestedPackagingType,
        ];
    }

    /**
     * Calculate DHL shipping weight for the order.
     */
    public function calculateDhlWeight(int $packageCount = 1, string $packagingType = 'box'): float
    {
        $details = $this->getDhlWeightDetails($packageCount, $packagingType);
        return $details['recommended_weight_per_package'];
    }

    public function completePayment(?string $stripePaymentIntentId = null): void
    {
        if (
            in_array($this->payment_status, ['paid', 'refunded'], true)
            || in_array($this->status, ['cancelled', 'refunded'], true)
        ) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($stripePaymentIntentId) {
            // Lock the order row for update to prevent concurrent payment processing races
            $order = self::where('id', $this->id)->lockForUpdate()->first();
            if (
                !$order
                || in_array($order->payment_status, ['paid', 'refunded'], true)
                || in_array($order->status, ['cancelled', 'refunded'], true)
            ) {
                return;
            }

            // 1. GUTSCHEIN VERBRAUCHEN (Zuerst prüfen & abbuchen, damit bei 0-€-Fehlern sofort abgerollt wird, bevor neue Gutscheine entstehen)
            if ($order->coupon_code) {
                $giftVoucher = \App\Models\Marketing\MarketingGiftVoucher::where('code', $order->coupon_code)
                    ->lockForUpdate()
                    ->first();
                if ($giftVoucher) {
                    $requestedDiscount = max(0, (int) $order->discount_amount);
                    $actualDeducted = max(0, min((int) $giftVoucher->current_balance, $requestedDiscount));

                    if ($order->total_price == 0 && $requestedDiscount > 0 && (!$giftVoucher->isValid() || $actualDeducted < $requestedDiscount)) {
                        throw new \RuntimeException('Das Gutscheinguthaben reicht für diese Bestellung nicht mehr aus.');
                    }

                    $newBalance = max(0, (int) $giftVoucher->current_balance - $actualDeducted);
                    $giftVoucher->update([
                        'current_balance' => $newBalance,
                        'is_active' => $newBalance > 0
                    ]);

                    if ($actualDeducted > 0) {
                        \App\Models\Marketing\MarketingGiftVoucherLog::create([
                            'gift_voucher_id' => $giftVoucher->id,
                            'order_id' => $order->id,
                            'amount' => $actualDeducted,
                            'remaining_balance' => $newBalance
                        ]);
                    }
                } else {
                    $mv = \App\Models\Marketing\MarketingVoucher::where('code', $order->coupon_code)
                        ->lockForUpdate()
                        ->first();
                    if ($mv) {
                        if ($order->total_price == 0 && !$mv->isValid()) {
                            throw new \RuntimeException('Der verwendete Rabattcode ist nicht mehr gültig.');
                        }
                        $mv->increment('used_count');
                    }
                }
            }

            $newStatus = $order->isOnlyDigital() ? 'completed' : 'pending';

            $updateData = [
                'payment_status' => 'paid',
                'status' => $newStatus,
            ];

            if ($stripePaymentIntentId) {
                $updateData['stripe_payment_intent_id'] = $stripePaymentIntentId;
            }

            $order->update($updateData);

            // Synchronize the current instance status
            $this->payment_status = 'paid';
            $this->status = $newStatus;

            // --- LAGERBESTAND REDUZIEREN ---
            foreach ($order->items as $item) {
                if ($item->product) {
                    $itemVariantId = is_array($item->configuration) ? ($item->configuration['variant_id'] ?? null) : null;
                    $item->product->reduceStock($item->quantity, $itemVariantId);
                }
            }

            // --- GIFT VOUCHERS GENERIEREN ---
            foreach ($order->items as $item) {
                $config = $item->configuration ?? [];
                $isActualVoucherProduct = ($item->product && $item->product->slug === 'geschenkgutschein')
                    || str_contains(strtolower((string) $item->product_name), 'geschenkgutschein');

                if (!empty($config['is_gift_voucher']) && !$isActualVoucherProduct) {
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:price_tampering_attempt',
                        'Unautorisierte Gutschein-Generierung verhindert',
                        "Order {$order->order_number}: Artikel '{$item->product_name}' enthielt is_gift_voucher-Flag, ist aber kein Geschenkgutschein-Produkt.",
                        'error',
                        ['order_id' => $order->id, 'order_item_id' => $item->id]
                    );
                    continue;
                }

                if (!empty($config['is_gift_voucher']) && $isActualVoucherProduct) {
                    // Safety check to prevent duplicate vouchers for this item
                    $alreadyExists = \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $item->id)->exists();
                    if (!$alreadyExists) {
                        // Der Gutscheinwert darf niemals den tatsächlich berechneten/bezahlten Einzelpreis des Artikels überschreiten
                        $paidVoucherValue = max(500, min(100000, (int) ($item->unit_price > 0 ? $item->unit_price : ($config['amount_cents'] ?? 500))));

                        for ($i = 0; $i < $item->quantity; $i++) {
                            $code = \App\Models\Marketing\MarketingGiftVoucher::generateCode();
                            \App\Models\Marketing\MarketingGiftVoucher::create([
                                'code' => $code,
                                'order_item_id' => $item->id,
                                'initial_value' => $paidVoucherValue,
                                'current_balance' => $paidVoucherValue,
                                'recipient_name' => (string) ($config['recipient_name'] ?? $order->customer_name ?: 'Beschenkte Person'),
                                'recipient_email' => $config['recipient_email'] ?? null,
                                'personal_message' => isset($config['personal_message']) ? mb_substr($config['personal_message'], 0, 160) : null,
                                'delivery_method' => $config['delivery_method'] ?? 'email',
                                'is_active' => true,
                                'valid_until' => now()->addYears(3)->endOfYear(),
                            ]);
                        }
                    }
                }
            }

            // Gast-Kundenkonto NUR dann automatisch verifizieren, wenn es sich um ein frisch generiertes Gastkonto
            // mit Einmal-Passwort handelt (das ausschließlich an das E-Mail-Postfach gesendet wird)
            if (
                $order->customer
                && !$order->customer->hasVerifiedEmail()
                && (bool) $order->customer->needs_password_change
                && !empty($order->customer->temporary_password)
                && strcasecmp(trim((string) $order->email), trim((string) $order->customer->email)) === 0
            ) {
                $order->customer->markEmailAsVerified();
            }

            // --- FREUNDE WERBEN FREUNDE: 5 € GUTHABEN FÜR DEN EMPFEHLENDEN KUNDEN ---
            try {
                app(\App\Services\ReferralService::class)->processOrderReferralReward($order);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Referral reward processing error: ' . $e->getMessage());
            }

            // --- INFLUENCER & AFFILIATE: PROVISION BERECHNEN (DUAL-ATTRIBUTION) ---
            try {
                app(\App\Services\Marketing\InfluencerService::class)->processOrderCommission($order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Influencer commission processing error: ' . $e->getMessage());
            }

            // --- HERZENSPROJEKT: AUTOMATISCHER BEITRAG IN DEN SPENDENTOPF ---
            \App\Services\HerzensprojektService::recordDonationForOrder($order);

            // --- GAMIFICATION: SEELENFUNKEN & BESTELL-TITEL GUTSCHREIBEN ---
            try {
                app(\App\Services\Gamification\GamificationService::class)->rewardOrderFunken($order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Gamification order reward error: ' . $e->getMessage());
            }

            // --- DOKUMENTE & MAILS AN DEN BACKGROUND-WORKER ÜBERGEBEN ---
            \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($order);
        });
    }

    /**
     * Liefert alle sofort herunterladbaren digitalen Güter & Geschenkgutscheine dieser Bestellung
     * inklusive kryptografisch signierter Download-URLs (gültig für 7 Tage).
     */
    public function getInstantDownloads(): array
    {
        if (
            $this->payment_status !== 'paid'
            || in_array($this->status, ['cancelled', 'refunded'], true)
        ) {
            return [];
        }

        $this->loadMissing('items.product');
        $downloads = [];

        foreach ($this->items as $item) {
            $config = is_array($item->configuration) ? $item->configuration : [];

            // 1. Geschenkgutscheine (PDF + Code)
            if (!empty($config['is_gift_voucher'])) {
                $vouchers = \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $item->id)
                    ->where('is_active', true)
                    ->get();

                foreach ($vouchers as $voucher) {
                    $downloads[] = [
                        'type' => 'gift_voucher',
                        'title' => 'Wertgutschein (' . number_format($voucher->initial_value / 100, 2, ',', '.') . ' €)',
                        'subtitle' => 'Für ' . ($voucher->recipient_name ?: 'dich') . ' • Sofort einlösbar & druckfertiges PDF',
                        'code' => $voucher->code,
                        'format' => 'PDF',
                        'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                            'customer.gift-voucher.download',
                            now()->addDays(7),
                            ['voucher' => $voucher->id]
                        ),
                    ];
                }
                continue;
            }

            // 2. Digitale Produkte
            if ($item->product && $item->product->type === 'digital' && !empty($item->product->digital_download_path)) {
                $ext = strtoupper(pathinfo((string) ($item->product->digital_filename ?: $item->product->digital_download_path), PATHINFO_EXTENSION) ?: 'DATEI');
                $downloads[] = [
                    'type' => 'digital_product',
                    'title' => (string) ($item->product_name ?: $item->product->name),
                    'subtitle' => 'Digitale Produktdatei • Sofort-Download',
                    'code' => null,
                    'format' => $ext,
                    'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                        'digital-product.download',
                        now()->addDays(7),
                        ['item' => $item->id]
                    ),
                ];
            }
        }

        return $downloads;
    }

    /**
     * Führt alle geschäftslogischen Rückabwicklungen bei Stornierung oder Erstattung durch:
     * 1. Lagerbestand: Ausschließliche Rückbuchung unpersonalisierter Standardware (Laserprodukte verfallen als Ausschuss).
     * 2. Herzensprojekt: Rückbuchung des Spendenbeitrags.
     * 3. Gekaufte Wertgutscheine: Deaktivierung, um kostenlose Einlösung nach Erstattung zu verhindern.
     * 4. Eingelöste Gutscheine: Wiederherstellung des Guthabens bzw. Dekrementierung des Einlösezählers.
     * 5. Freunde-Werben: Stornierung unberechtigter Werbeprämien.
     */
    public function handleCancellation(string $reason = 'Stornierung', ?string $previousStatus = null, ?string $previousPaymentStatus = null): void
    {
        $effectiveStatus = $previousStatus ?? $this->getOriginal('status') ?? $this->status;
        $effectivePaymentStatus = $previousPaymentStatus ?? $this->getOriginal('payment_status') ?? $this->payment_status;
        $wasPaid = in_array($effectivePaymentStatus, ['paid', 'refunded'], true);
        $alreadyCancelledOrRefunded = in_array($effectiveStatus, ['cancelled', 'refunded'], true);

        // 1. Lagerbestand: NUR für bezahlte, unpersonalisierte Produkte und nur wenn vorher pending/processing (nicht bereits storniert/ausgeliefert)
        if ($wasPaid && !$alreadyCancelledOrRefunded && in_array($effectiveStatus, ['pending', 'processing'], true)) {
            foreach ($this->items as $item) {
                $isPersonalized = !empty($item->configuration) || ($item->product && $item->product->is_personalizable);
                if (!$isPersonalized && $item->product) {
                    $itemVariantId = is_array($item->configuration) ? ($item->configuration['variant_id'] ?? null) : null;
                    $item->product->restoreStock($item->quantity, $itemVariantId);
                }
            }
        }

        // 2. Herzensprojekt: Spendenbeitrag stornieren
        \App\Services\HerzensprojektService::reverseDonationForOrder($this, $reason);

        // 3. In dieser Order gekaufte Geschenkgutscheine sperren (Finanzschutz)
        foreach ($this->items as $item) {
            \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $item->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'current_balance' => 0,
                ]);
        }

        // 4. Vom Kunden eingelöste Gutscheine wiederherstellen (nur wenn tatsächlich bezahlt/abgebucht und noch nicht rückabgewickelt)
        if ($wasPaid && !$alreadyCancelledOrRefunded) {
            $this->restoreVouchersOnCancellation();
        }

        // 5. Freunde-Werben: Belohnung zurückbuchen
        try {
            app(\App\Services\ReferralService::class)->reverseOrderReferralReward($this);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Referral reward reversal error: ' . $e->getMessage());
        }

        // 5b. Influencer-Provision bei Storno/Widerruf zurückbuchen
        try {
            app(\App\Services\Marketing\InfluencerService::class)->reverseOrderCommission($this, $reason);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Influencer commission reversal error: ' . $e->getMessage());
        }

        // 6. Gamification: Gutgeschriebene Seelenfunken & Bestell-Titel bei Storno/Erstattung korrigieren
        if ($wasPaid && !$alreadyCancelledOrRefunded) {
            try {
                app(\App\Services\Gamification\GamificationService::class)->reverseOrderFunken($this);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Gamification order reversal error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Stellt verbrauchte Wertgutschein-Guthaben oder Aktionsgutschein-Zähler bei Stornierung/Erstattung wieder her.
     */
    public function restoreVouchersOnCancellation(): void
    {
        if (empty($this->coupon_code)) {
            return;
        }

        $effectivePaymentStatus = $this->getOriginal('payment_status') ?? $this->payment_status;
        if (!in_array($effectivePaymentStatus, ['paid', 'refunded'], true) && !in_array($this->payment_status, ['paid', 'refunded'], true)) {
            return;
        }

        // 1. Wertgutschein (MarketingGiftVoucher)
        $giftVoucher = \App\Models\Marketing\MarketingGiftVoucher::where('code', $this->coupon_code)->first();
        if ($giftVoucher && $this->discount_amount > 0) {
            // Idempotenz: Wurde die Rückbuchung für diese Bestellung bereits durchgeführt?
            $alreadyReversed = \App\Models\Marketing\MarketingGiftVoucherLog::where('gift_voucher_id', $giftVoucher->id)
                ->where('order_id', $this->id)
                ->where('amount', '<', 0)
                ->exists();

            if (!$alreadyReversed) {
                $usedLog = \App\Models\Marketing\MarketingGiftVoucherLog::where('gift_voucher_id', $giftVoucher->id)
                    ->where('order_id', $this->id)
                    ->where('amount', '>', 0)
                    ->first();

                $refundAmount = ($usedLog && $usedLog->amount > 0)
                    ? (int) $usedLog->amount
                    : max(0, (int) $this->discount_amount);

                if ($refundAmount > 0) {
                    $maxAllowedBalance = max((int) $giftVoucher->initial_value, (int) $giftVoucher->current_balance);
                    $newBalance = min($maxAllowedBalance, (int) $giftVoucher->current_balance + $refundAmount);

                    $giftVoucher->update([
                        'current_balance' => $newBalance,
                        'is_active' => true,
                    ]);

                    \App\Models\Marketing\MarketingGiftVoucherLog::create([
                        'gift_voucher_id' => $giftVoucher->id,
                        'order_id' => $this->id,
                        'amount' => -$refundAmount,
                        'remaining_balance' => $newBalance,
                    ]);

                    \Illuminate\Support\Facades\Log::info("Gutscheinguthaben von {$refundAmount} Cents für Gutschein {$giftVoucher->code} nach Storno von Order {$this->order_number} erstattet.");
                }
            }
        }

        // 2. Rabattcode (MarketingVoucher)
        $mv = \App\Models\Marketing\MarketingVoucher::where('code', $this->coupon_code)->first();
        if ($mv && $mv->used_count > 0) {
            $mv->decrement('used_count');
            \Illuminate\Support\Facades\Log::info("Rabattcode-Zähler für {$mv->code} nach Storno von Order {$this->order_number} zurückgesetzt.");
        }
    }
}



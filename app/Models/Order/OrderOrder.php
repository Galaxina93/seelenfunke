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

    protected $guarded = [];

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
        if ($this->payment_status === 'paid') {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($stripePaymentIntentId) {
            // Lock the order row for update to prevent concurrent payment processing races
            $order = self::where('id', $this->id)->lockForUpdate()->first();
            if (!$order || $order->payment_status === 'paid') {
                return;
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
                    $item->product->reduceStock($item->quantity);
                }
            }

            // --- GIFT VOUCHERS GENERIEREN ---
            foreach ($order->items as $item) {
                $config = $item->configuration ?? [];
                if (!empty($config['is_gift_voucher'])) {
                    // Safety check to prevent duplicate vouchers for this item
                    $alreadyExists = \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $item->id)->exists();
                    if (!$alreadyExists) {
                        for ($i = 0; $i < $item->quantity; $i++) {
                            $code = \App\Models\Marketing\MarketingGiftVoucher::generateCode();
                            $voucher = \App\Models\Marketing\MarketingGiftVoucher::create([
                                'code' => $code,
                                'order_item_id' => $item->id,
                                'initial_value' => $config['amount_cents'],
                                'current_balance' => $config['amount_cents'],
                                'recipient_name' => $config['recipient_name'],
                                'recipient_email' => $config['recipient_email'] ?? null,
                                'personal_message' => isset($config['personal_message']) ? mb_substr($config['personal_message'], 0, 160) : null,
                                'delivery_method' => $config['delivery_method'] ?? 'email',
                                'is_active' => true,
                                'valid_until' => now()->addYears(3)->endOfYear(),
                            ]);

                            if ($voucher->delivery_method === 'email') {
                                try {
                                    \Illuminate\Support\Facades\Mail::to($voucher->recipient_email ?: $order->email)
                                        ->send(new \App\Mail\NewGiftVoucherToCustomer($voucher));
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Gift voucher email delivery failed: ' . $e->getMessage());
                                }
                            }
                        }
                    }
                }
            }

            // GUTSCHEIN VERBRAUCHEN (Counter hochzählen / Wertgutschein abbuchen)
            if ($order->coupon_code) {
                $giftVoucher = \App\Models\Marketing\MarketingGiftVoucher::where('code', $order->coupon_code)
                    ->lockForUpdate()
                    ->first();
                if ($giftVoucher) {
                    $usedAmount = $order->discount_amount;
                    $newBalance = max(0, $giftVoucher->current_balance - $usedAmount);
                    $giftVoucher->update([
                        'current_balance' => $newBalance,
                        'is_active' => $newBalance > 0
                    ]);

                    \App\Models\Marketing\MarketingGiftVoucherLog::create([
                        'gift_voucher_id' => $giftVoucher->id,
                        'order_id' => $order->id,
                        'amount' => $usedAmount,
                        'remaining_balance' => $newBalance
                    ]);
                } else {
                    $mv = \App\Models\Marketing\MarketingVoucher::where('code', $order->coupon_code)->first();
                    if ($mv) {
                        $mv->increment('used_count');
                    }
                }
            }

            // --- FREUNDE WERBEN FREUNDE: 5 € GUTHABEN FÜR DEN EMPFEHLENDEN KUNDEN ---
            try {
                app(\App\Services\ReferralService::class)->processOrderReferralReward($order);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Referral reward processing error: ' . $e->getMessage());
            }

            // --- HERZENSPROJEKT: AUTOMATISCHER BEITRAG IN DEN SPENDENTOPF ---
            \App\Services\HerzensprojektService::recordDonationForOrder($order);

            // --- DOKUMENTE & MAILS AN DEN BACKGROUND-WORKER ÜBERGEBEN ---
            \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($order);
        });
    }
}



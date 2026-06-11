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
        'token'
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

    // GEÄNDERT: Zeigt nun auf Customer::class
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
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
     * Get DHL weight details for the order.
     */
    public function getDhlWeightDetails(): array
    {
        $totalProductWeightGrams = 0;
        $remainingProductWeightGrams = 0;
        $maxTaraWeight = 0;
        $totalItemsCount = 0;

        foreach ($this->items as $item) {
            if ($item->product) {
                $itemWeight = $item->product->weight > 0 ? $item->product->weight : 100;
                $totalProductWeightGrams += ($itemWeight * $item->quantity);
                
                $remainingQty = max(0, $item->quantity - $item->completed_quantity);
                $remainingProductWeightGrams += ($itemWeight * $remainingQty);
                
                if ($item->product->packaging_weight && $item->product->packaging_weight > $maxTaraWeight) {
                    $maxTaraWeight = $item->product->packaging_weight;
                }
                
                $totalItemsCount += $item->quantity;
            }
        }

        $weightToUse = $remainingProductWeightGrams > 0 ? $remainingProductWeightGrams : $totalProductWeightGrams;

        if ($weightToUse == 0 && $totalItemsCount > 0) {
            $weightToUse = $totalItemsCount * 100;
        }

        $packagingWeightGrams = $maxTaraWeight > 0 ? $maxTaraWeight : (int)shop_setting('packaging_weight_grams', 350);  

        return [
            'product_weight_grams' => $weightToUse,
            'packaging_weight_grams' => $packagingWeightGrams
        ];
    }

    /**
     * Calculate DHL shipping weight for the order.
     */
    public function calculateDhlWeight(int $packageCount = 1): float
    {
        $details = $this->getDhlWeightDetails();
        $weightToUse = $details['product_weight_grams'];
        $packagingWeightGrams = $details['packaging_weight_grams'];

        $totalGrams = $weightToUse + ($packageCount * $packagingWeightGrams);
        $weightPerPackage = ($totalGrams / 1000) / max(1, $packageCount);
        
        return max(0.01, round($weightPerPackage, 2));
    }

    public function completePayment(string $stripePaymentIntentId = null): void
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

            // --- NEU: DOKUMENTE & MAILS AN DEN BACKGROUND-WORKER ÜBERGEBEN ---
            \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($this);
        });
    }
}



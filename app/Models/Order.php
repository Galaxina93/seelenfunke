<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $guarded = [];

    protected $fillable = [
        'order_number',
        'customer_id',
        'email',
        'status',
        'payment_status',
        'payment_method',
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
        'deadline',
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

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'processing' => 'bg-blue-100 text-blue-800',
            'shipped' => 'bg-purple-100 text-purple-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getPaymentStatusColorAttribute()
    {
        return match($this->payment_status) {
            'paid' => 'bg-green-100 text-green-800',
            'unpaid' => 'bg-red-100 text-red-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'refunded' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
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
        $taxRate = (float) shop_setting('default_tax_rate', 19.00);
        $divisor = 1 + ($taxRate / 100);
        return (int) round($this->shipping_price - ($this->shipping_price / $divisor));
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

    public function invoices() {
        return $this->hasMany(Invoice::class);
    }

    public function toFormattedArray()
    {
        $items = [];
        $isSmallBusiness = (bool)shop_setting('is_small_business', false);
        $defaultTaxRate  = (float)shop_setting('default_tax_rate', 19);
        $validityDays    = (int)shop_setting('order_quote_validity_days', 14);
        $divisor = $isSmallBusiness ? 1.0 : (1 + ($defaultTaxRate / 100));

        foreach ($this->items as $item) {
            $items[] = [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'single_price' => number_format($item->unit_price / 100, 2, ',', '.'),
                'total_price' => number_format($item->total_price / 100, 2, ',', '.'),
                'config' => $item->configuration
            ];
        }

        // --- PRÄZISE NETTO-BERECHNUNG FÜR ANZEIGE ---
        $totalNettoCents = ($this->total_price - $this->tax_amount);

        $expressGross = $this->is_express ? (int)shop_setting('express_surcharge', 2500) : 0;
        $expressNettoCents = (int)round($expressGross / $divisor);

        $shippingGrossCents = $this->shipping_price ?? 0;
        $shippingNettoCents = (int)round($shippingGrossCents / $divisor);

        $goodsNettoCents = $totalNettoCents - $expressNettoCents - $shippingNettoCents;

        return [
            'quote_number' => $this->order_number,
            'quote_token'  => $this->token ?? '',
            'quote_expiry' => $this->expires_at
                ? $this->expires_at->format('d.m.Y')
                : now()->addDays($validityDays)->format('d.m.Y'),
            'express'      => (bool)$this->is_express,
            'deadline'     => $this->deadline,

            // Formatierte Summen
            'total_netto'  => number_format($totalNettoCents / 100, 2, ',', '.'),
            'total_vat'    => number_format($this->tax_amount / 100, 2, ',', '.'),
            'total_gross'  => number_format($this->total_price / 100, 2, ',', '.'),
            'shipping_price' => number_format($this->shipping_price / 100, 2, ',', '.'),

            // Detaillierte Netto-Werte für Mails
            'display_netto_goods'    => number_format($goodsNettoCents / 100, 2, ',', '.') . ' €',
            'display_netto_express'  => number_format($expressNettoCents / 100, 2, ',', '.') . ' €',
            'display_netto_shipping' => number_format($shippingNettoCents / 100, 2, ',', '.') . ' €',

            'is_small_business' => $isSmallBusiness,
            'tax_rate' => $defaultTaxRate,
            'tax_note' => $isSmallBusiness
                ? 'Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.'
                : "Enthaltene MwSt. ({$defaultTaxRate}%):",

            'contact' => [
                'vorname'  => $this->billing_address['first_name'] ?? '',
                'nachname' => $this->billing_address['last_name'] ?? '',
                'firma'    => $this->billing_address['company'] ?? '',
                'email'    => $this->email,
                'telefon'  => $this->billing_address['phone'] ?? $this->phone ?? '',
                'anmerkung'=> $this->notes ?? '',
                'country'  => $this->billing_address['country'] ?? 'DE'
            ],

            // NEU: Explizite Übergabe beider Adressen für die Mail-Vorlagen
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address ?? $this->billing_address,

            'items' => $items
        ];
    }
}

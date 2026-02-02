<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class QuoteRequest extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_express' => 'boolean',
        'deadline' => 'date',
        'expires_at' => 'datetime',
        'configuration' => 'array',
        // WICHTIG: Adressen müssen als Array gecastet werden
        'billing_address' => 'array',
        'shipping_address' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($quote) {
            if (empty($quote->token)) {
                $quote->token = Str::random(32);
            }
            if (empty($quote->expires_at)) {
                $quote->expires_at = now()->addDays(14);
            }
            if (empty($quote->status)) {
                $quote->status = 'open';
            }
        });
    }

    public function items()
    {
        return $this->hasMany(QuoteRequestItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function getIsGuestAttribute()
    {
        return is_null($this->customer_id);
    }

    public function isValid()
    {
        return $this->status === 'open' && $this->expires_at->isFuture();
    }

    public function toFormattedArray()
    {
        $items = [];

        // Dynamische Werte aus den Shop-Settings laden
        $isSmallBusiness = (bool)shop_setting('is_small_business', false);
        $defaultTaxRate  = (float)shop_setting('default_tax_rate', 19);
        $validityDays    = (int)shop_setting('order_quote_validity_days', 14);

        // Divisor für die Netto-Rückrechnung (z.B. 1.19)
        $divisor = $isSmallBusiness ? 1.0 : (1 + ($defaultTaxRate / 100));

        foreach ($this->items as $item) {
            $items[] = [
                'name'         => $item->product_name,
                'quantity'     => $item->quantity,
                'single_price' => number_format($item->unit_price / 100, 2, ',', '.'),
                'total_price'  => number_format($item->total_price / 100, 2, ',', '.'),
                'config'       => $item->configuration
            ];
        }

        // --- ZENTRALE NETTO-BERECHNUNG FÜR ANZEIGE ---
        // 1. Warenwert Netto (Summe aller Artikel-Bruttobeträge zurückgerechnet)
        $goodsGrossCents = $this->items->sum('total_price');
        $goodsNetto = ($goodsGrossCents / 100) / $divisor;

        // 2. Express Netto (Falls aktiv, Standard-Zuschlag nutzen)
        $expressGross = $this->is_express ? (int)shop_setting('express_surcharge', 2500) : 0;
        $expressNetto = ($expressGross / 100) / $divisor;

        // 3. Versand Netto
        $shippingNetto = (($this->shipping_price ?? 0) / 100) / $divisor;

        return [
            'quote_number' => $this->quote_number,
            'quote_token'  => $this->token ?? '',

            'quote_expiry' => $this->expires_at
                ? $this->expires_at->format('d.m.Y')
                : now()->addDays($validityDays)->format('d.m.Y'),

            'express'      => (bool)$this->is_express,
            'deadline'     => $this->deadline,

            'contact' => [
                'vorname'   => $this->first_name,
                'nachname'  => $this->last_name,
                'firma'     => $this->company,
                'email'     => $this->email,
                'telefon'   => $this->phone,
                'anmerkung' => $this->admin_notes,
                'country'   => $this->country ?? 'DE'
            ],

            'items'          => $items,

            // Summen-Werte (formatiert)
            'total_netto'    => number_format($this->net_total / 100, 2, ',', '.'),
            'total_vat'      => number_format($this->tax_total / 100, 2, ',', '.'),
            'total_gross'    => number_format($this->gross_total / 100, 2, ',', '.'),
            'shipping_price' => number_format(($this->shipping_price ?? 0) / 100, 2, ',', '.'),

            // NEU: Vorformatierte Netto-Werte für die Partials
            'display_netto_goods'    => number_format($goodsNetto, 2, ',', '.') . ' €',
            'display_netto_express'  => number_format($expressNetto, 2, ',', '.') . ' €',
            'display_netto_shipping' => number_format($shippingNetto, 2, ',', '.') . ' €',

            'is_small_business' => $isSmallBusiness,
            'tax_rate'          => $defaultTaxRate,
            'tax_note'          => $isSmallBusiness
                ? 'Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.'
                : "Enthaltene MwSt. ({$defaultTaxRate}%):",
        ];
    }
}

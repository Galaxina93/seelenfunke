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
        foreach ($this->items as $item) {
            $items[] = [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'single_price' => number_format($item->unit_price / 100, 2, ',', '.'),
                'total_price' => number_format($item->total_price / 100, 2, ',', '.'),
                'config' => $item->configuration
            ];
        }

        return [
            'quote_number' => $this->quote_number,
            'quote_token'  => $this->token ?? '',
            // FIX für den PDF-Fehler: Ablaufdatum generieren (z.B. in 14 Tagen)
            'quote_expiry' => $this->expires_at ? $this->expires_at->format('d.m.Y') : now()->addDays(14)->format('d.m.Y'),
            'express'      => (bool)$this->is_express,
            'deadline'     => $this->deadline,
            'contact' => [
                'vorname'  => $this->first_name,
                'nachname' => $this->last_name,
                'firma'    => $this->company,
                'email'    => $this->email,
                'telefon'  => $this->phone,
                'anmerkung'=> $this->admin_notes,
                'country'  => 'DE'
            ],
            'items' => $items,
            'total_netto'  => number_format($this->net_total / 100, 2, ',', '.'),
            'total_vat'    => number_format($this->tax_total / 100, 2, ',', '.'),
            'total_gross'  => number_format($this->gross_total / 100, 2, ',', '.'),
            'shipping_price' => number_format(($this->shipping_price ?? 0) / 100, 2, ',', '.')
        ];
    }
}

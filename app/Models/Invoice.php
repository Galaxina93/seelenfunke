<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'delivery_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'shipping_cost' => 'integer',
        'discount_amount' => 'integer',
        'volume_discount' => 'integer',
        'total' => 'integer',
        'custom_items' => 'array',
        'is_e_invoice' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function parent()
    {
        return $this->belongsTo(Invoice::class, 'parent_id');
    }

    public function child()
    {
        return $this->hasOne(Invoice::class, 'parent_id');
    }

    public function getItemsAttribute()
    {
        if ($this->order_id && $this->order) {
            return $this->order->items->map(function($item) {
                return (object)[
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate ?? 19,
                    'total_price' => $item->unit_price * $item->quantity,
                    'configuration' => $item->configuration
                ];
            });
        }

        return collect($this->custom_items)->map(fn($i) => (object)$i);
    }

    public function isCreditNote()
    {
        return in_array($this->type, ['credit_note', 'cancellation']);
    }

    public function getParsedHeaderTextAttribute()
    {
        return $this->parseVariables($this->header_text);
    }

    public function getParsedFooterTextAttribute()
    {
        return $this->parseVariables($this->footer_text);
    }

    protected function parseVariables($text)
    {
        if (empty($text)) return '';

        $variables = [
            '[%ZAHLUNGSZIEL%]' => $this->due_date ? $this->due_date->format('d.m.Y') : '',
            '[%KONTAKTPERSON%]' => shop_setting('owner_proprietor', 'Alina Steinhauer'),
            '[%RECHNUNGSNUMMER%]' => $this->invoice_number,
        ];

        return str_replace(array_keys($variables), array_values($variables), $text);
    }

    public static function calculateTax($amount, $countryCode = 'DE')
    {
        if ((bool)shop_setting('is_small_business', false)) {
            return 0;
        }

        $rate = (float)shop_setting('default_tax_rate', 19.0);
        $divisor = 1 + ($rate / 100);

        return (int) round($amount - ($amount / $divisor));
    }
}

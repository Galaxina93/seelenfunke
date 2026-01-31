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

    public static function calculateTax($amount, $countryCode = 'DE')
    {
        $rate = config("shop.countries.$countryCode.tax_rate", 19);
        return (int) round($amount - ($amount / (1 + ($rate / 100))));
    }
}

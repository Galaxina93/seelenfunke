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
}

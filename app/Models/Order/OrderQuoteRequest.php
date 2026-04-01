<?php

namespace App\Models\Order;

use App\Models\Customer\Customer;
use App\Traits\FormatsECommerceData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrderQuoteRequest extends Model
{
    use HasUuids, SoftDeletes, FormatsECommerceData;

    protected $table = 'order_quote_requests';

    protected $guarded = [];

    protected $casts = [
        'is_express' => 'boolean',
        'expires_at' => 'datetime',
        'configuration' => 'array',
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
                $validityDays = (int)shop_setting('order_quote_validity_days', 14);
                $quote->expires_at = now()->addDays($validityDays);
            }
            if (empty($quote->status)) {
                $quote->status = 'open';
            }
        });
    }

    public function items()
    {
        return $this->hasMany(OrderQuoteRequestItem::class, 'quote_request_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(OrderOrder::class, 'converted_order_id');
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

<?php

namespace App\Models\Order;

use App\Models\Invoice;
use App\Models\User;
use App\Traits\FormatsECommerceData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasUuids, FormatsECommerceData;

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
        'deadline' => 'date',
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
}

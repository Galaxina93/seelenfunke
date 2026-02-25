<?php

namespace App\Models\Cart;

use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'session_id',
        'customer_id',
        'coupon_code',
        'is_express',
        'deadline'
    ];

    protected $casts = [
        'is_express' => 'boolean',
        'deadline' => 'date'
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // GEÄNDERT: Heißt jetzt customer() und referenziert den Customer
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}

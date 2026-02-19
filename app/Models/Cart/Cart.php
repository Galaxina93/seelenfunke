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

    /**
     * Die Attribute, die per Mass Assignment gesetzt werden dürfen.
     * Wichtig für cart::firstOrCreate(['session_id' => ...])
     */
    protected $fillable = [
        'session_id',
        'customer_id',
        'coupon_code',
        'customer_id',
        'is_express',
        'deadline'
    ];

    protected $casts = [
        'is_express' => 'boolean',
        'deadline' => 'date'
    ];

    /**
     * Ein Warenkorb hat viele Positionen (Items).
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Ein Warenkorb gehört optional zu einem User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

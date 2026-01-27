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
        'expires_at' => 'datetime', // Wichtig für Carbon Funktionen
    ];

    /**
     * Automatische Generierung von Token und Ablaufdatum
     */
    protected static function booted()
    {
        static::creating(function ($quote) {
            if (empty($quote->token)) {
                $quote->token = Str::random(32);
            }
            if (empty($quote->expires_at)) {
                // 14 Tage Gültigkeit
                $quote->expires_at = now()->addDays(14);
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

    // Helper: Ist es ein Gast?
    public function getIsGuestAttribute()
    {
        return is_null($this->customer_id);
    }

    // Helper: Ist das Angebot noch gültig?
    public function isValid()
    {
        return $this->status === 'open' && $this->expires_at->isFuture();
    }
}

<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketingGiftVoucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'marketing_gift_vouchers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'code', 'order_item_id', 'initial_value', 'current_balance',
        'recipient_name', 'recipient_email', 'personal_message',
        'delivery_method', 'is_active', 'valid_until'
    ];

    protected $casts = [
        'initial_value' => 'integer',
        'current_balance' => 'integer',
        'is_active' => 'boolean',
        'valid_until' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = $model->id ?? (string) Str::uuid());
    }

    /**
     * Check if voucher is valid and has balance.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->current_balance <= 0) return false;

        $now = now();
        if ($this->valid_until && $now->gt($this->valid_until)) return false;

        return true;
    }

    public function orderItem()
    {
        return $this->belongsTo(\App\Models\Order\OrderOrderItem::class, 'order_item_id');
    }

    public function logs()
    {
        return $this->hasMany(MarketingGiftVoucherLog::class, 'gift_voucher_id');
    }

    /**
     * Generiert einen eindeutigen Gutscheincode mit dem Präfix "SEELENFUNKE".
     */
    public static function generateCode(): string
    {
        do {
            $code = 'SEELENFUNKE-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}

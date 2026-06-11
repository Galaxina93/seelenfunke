<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarketingGiftVoucherLog extends Model
{
    use HasFactory;

    protected $table = 'marketing_gift_voucher_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'gift_voucher_id', 'order_id', 'amount', 'remaining_balance'
    ];

    protected $casts = [
        'amount' => 'integer',
        'remaining_balance' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = $model->id ?? (string) Str::uuid());
    }

    public function voucher()
    {
        return $this->belongsTo(MarketingGiftVoucher::class, 'gift_voucher_id');
    }

    public function order()
    {
        return $this->belongsTo(\App\Models\Order\OrderOrder::class, 'order_id');
    }
}

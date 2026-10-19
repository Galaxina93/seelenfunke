<?php

namespace App\Models\Marketing;

use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarketingReferral extends Model
{
    use HasFactory;

    protected $table = 'marketing_referrals';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'referrer_email',
        'referrer_customer_id',
        'referrer_first_name',
        'referrer_last_name',
        'code',
        'voucher_id',
        'discount_percent',
        'reward_cents',
        'successful_referrals_count',
        'is_active',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'reward_cents' => 'integer',
        'successful_referrals_count' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function voucher()
    {
        return $this->belongsTo(MarketingVoucher::class, 'voucher_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }
}

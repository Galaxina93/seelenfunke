<?php

namespace App\Models\Marketing;

use App\Models\Order\OrderOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingHerzensprojektTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(OrderOrder::class, 'order_id');
    }

    public function action()
    {
        return $this->belongsTo(MarketingHerzensprojektAction::class, 'action_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->amount_cents >= 0 ? '+' : '';
        return $sign . number_format($this->amount_cents / 100, 2, ',', '.') . ' €';
    }
}

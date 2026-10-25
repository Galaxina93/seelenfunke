<?php

namespace App\Models\Marketing;

use App\Models\Order\OrderOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingInfluencerCommission extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'marketing_influencer_commissions';

    protected $fillable = [
        'influencer_id',
        'order_id',
        'tracking_link_id',
        'voucher_code',
        'attribution_source',
        'order_number',
        'order_net_amount_cents',
        'commission_rate_snapshot',
        'commission_amount_cents',
        'status',
        'payout_id',
        'approved_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'order_net_amount_cents' => 'integer',
        'commission_rate_snapshot' => 'integer',
        'commission_amount_cents' => 'integer',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function influencer()
    {
        return $this->belongsTo(MarketingInfluencer::class, 'influencer_id')->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(OrderOrder::class, 'order_id')->withTrashed();
    }

    public function trackingLink()
    {
        return $this->belongsTo(MarketingTrackingLink::class, 'tracking_link_id');
    }

    public function payout()
    {
        return $this->belongsTo(MarketingInfluencerPayout::class, 'payout_id');
    }
}

<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingTrackingLinkHit extends Model
{
    protected $table = 'marketing_tracking_link_hits';

    protected $fillable = [
        'tracking_link_id',
        'ip_hash',
        'device_type',
        'referrer',
    ];

    public function trackingLink()
    {
        return $this->belongsTo(MarketingTrackingLink::class, 'tracking_link_id');
    }
}

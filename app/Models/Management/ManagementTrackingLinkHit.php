<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagementTrackingLinkHit extends Model
{
    use HasFactory;

    protected $table = 'management_tracking_link_hits';

    protected $fillable = [
        'tracking_link_id',
        'ip_hash',
        'device_type',
        'referrer',
    ];

    public function trackingLink()
    {
        return $this->belongsTo(ManagementTrackingLink::class, 'tracking_link_id');
    }
}

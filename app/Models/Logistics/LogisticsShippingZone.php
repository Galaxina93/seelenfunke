<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsShippingZone extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function countries()
    {
        return $this->hasMany(LogisticsShippingZoneCountry::class);
    }

    public function rates()
    {
        return $this->hasMany(LogisticsShippingRate::class);
    }
}

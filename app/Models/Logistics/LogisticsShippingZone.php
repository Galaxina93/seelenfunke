<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LogisticsShippingZone extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function countries()
    {
        return $this->hasMany(LogisticsShippingZoneCountry::class);
    }

    public function rates()
    {
        return $this->hasMany(LogisticsShippingRate::class);
    }
}

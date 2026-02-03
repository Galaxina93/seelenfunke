<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function countries()
    {
        return $this->hasMany(ShippingZoneCountry::class);
    }

    public function rates()
    {
        return $this->hasMany(ShippingRate::class);
    }
}

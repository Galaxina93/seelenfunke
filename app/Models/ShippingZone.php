<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
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

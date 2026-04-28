<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LogisticsShippingZoneCountry extends Model
{
    use HasUuids;

    protected $guarded = [];
    public $timestamps = false;
}

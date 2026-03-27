<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LogisticsShippingRate extends Model
{
    use HasUuids;

    protected $guarded = [];
}

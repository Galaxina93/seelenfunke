<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasUuids;

    protected $guarded = [];
}

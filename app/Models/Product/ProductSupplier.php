<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductSupplier extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'dynamic_links' => 'array',
        'lead_time_land_days' => 'integer',
        'lead_time_air_days' => 'integer',
        'lead_time_sea_days' => 'integer',
        'lead_time_train_days' => 'integer',
    ];
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

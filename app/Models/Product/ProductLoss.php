<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductLoss extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'cost_value' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

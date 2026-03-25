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
        'reported_to_supplier_at' => 'datetime',
        'refund_received_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}

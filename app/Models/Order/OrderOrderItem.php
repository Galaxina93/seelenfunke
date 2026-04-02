<?php

namespace App\Models\Order;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_order_items';

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'is_completed' => 'boolean',
        'completed_quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(OrderOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderShipment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'shipping_label_path',
        'carrier',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(OrderOrder::class, 'order_id');
    }
}

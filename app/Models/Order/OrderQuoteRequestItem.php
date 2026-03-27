<?php

namespace App\Models\Order;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderQuoteRequestItem extends Model
{
    use HasUuids;

    protected $table = 'order_quote_request_items';

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
        'unit_price' => 'integer', // Cents
        'total_price' => 'integer', // Cents
    ];

    public function quoteRequest()
    {
        return $this->belongsTo(OrderQuoteRequest::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

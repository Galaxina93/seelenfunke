<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuoteRequestItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
        'unit_price' => 'integer', // Cents
        'total_price' => 'integer', // Cents
    ];

    public function quoteRequest()
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

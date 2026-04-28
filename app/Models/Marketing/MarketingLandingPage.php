<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingLandingPage extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $fillable = [
        'product_id',
        'slug',
        'title',
        'headline',
        'sales_copy',
        'cta_text',
        'status'
    ];

    public function product()
    {
        return $this->belongsTo(\App\Models\Product\Product::class);
    }
}

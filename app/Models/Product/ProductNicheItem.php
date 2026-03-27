<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductNicheItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'platform',
        'price',
        'sales_volume',
        'rating',
        'review_count',
        'image_url',
        'url',
        'niche_score',
        'raw_data',
        'scraped_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'rating' => 'decimal:2',
        'raw_data' => 'array',
        'scraped_at' => 'datetime',
    ];
}

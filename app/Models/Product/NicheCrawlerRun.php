<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class NicheCrawlerRun extends Model
{
    protected $fillable = [
        'admin_id',
        'name',
        'keyword',
        'platform',
        'products_data',
        'ai_recommendation',
        'ai_agent_id'
    ];

    protected $casts = [
        'products_data' => 'array',
    ];
}

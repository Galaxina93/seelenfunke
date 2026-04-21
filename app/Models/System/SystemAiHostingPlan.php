<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemAiHostingPlan extends Model
{
    protected $fillable = [
        'name',
        'token_limit',
        'price_monthly',
        'description',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'price_monthly' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}

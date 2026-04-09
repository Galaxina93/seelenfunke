<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemAiHostingPlan extends Model
{
    protected $fillable = [
        'name',
        'token_limit',
        'price_monthly',
        'is_active',
    ];
}

<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SystemCronjob extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'command',
        'parameters',
        'schedule',
        'is_active',
        'last_run_at',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];
}

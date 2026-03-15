<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiToolUsage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tool_usages';

    protected $fillable = [
        'tool_name',
        'used_at',
        'context',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'context' => 'array',
    ];
}

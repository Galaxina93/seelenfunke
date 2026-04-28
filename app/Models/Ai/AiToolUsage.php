<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiToolUsage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_agent_tool_usages';

    protected $fillable = [
        'ai_agent_id',
        'tool_name',
        'used_at',
        'context',
        'is_error',
        'error_message',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'context' => 'array',
        'is_error' => 'boolean',
    ];
}

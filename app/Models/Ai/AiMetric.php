<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiMetric extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_metrics';

    protected $fillable = [
        'ai_agent_id',
        'type',
        'input_tokens',
        'output_tokens',
        'total_time_ms',
        'is_success',
    ];

    protected $casts = [
        'is_success' => 'boolean',
    ];

    public function agent()
    {
        return $this->belongsTo(\App\Models\Ai\AiAgent::class, 'ai_agent_id');
    }
}

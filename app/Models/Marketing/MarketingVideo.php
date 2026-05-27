<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingVideo extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ai_agent_id',
        'title',
        'subtitle',
        'theme_color',
        'has_particles',
        'video_path',
        'config',
        'status', // 'draft', 'completed'
    ];

    protected $casts = [
        'has_particles' => 'boolean',
        'config' => 'array',
    ];

    public function agent()
    {
        return $this->belongsTo(\App\Models\Ai\AiAgent::class, 'ai_agent_id');
    }
}

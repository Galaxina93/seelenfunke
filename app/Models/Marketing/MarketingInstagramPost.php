<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingInstagramPost extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ai_agent_id',
        'image_url',
        'caption',
        'hashtags',
        'status', // 'draft', 'published', 'rejected'
    ];

    protected $casts = [
        'hashtags' => 'array',
    ];

    public function agent()
    {
        return $this->belongsTo(\App\Models\Ai\AiAgent::class, 'ai_agent_id');
    }
}

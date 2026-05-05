<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiWidgetConfig extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'ai_agent_id',
        'volume',
        'continuous_mode',
        'require_wake_word',
        'allow_voice_interruption',
    ];

    protected $casts = [
        'volume' => 'integer',
        'continuous_mode' => 'boolean',
        'require_wake_word' => 'boolean',
        'allow_voice_interruption' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function aiAgent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}

<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ai_role_id',
        'name',
        'wake_word',
        'role_description',
        'system_prompt',
        'model',
        'temperature',
        'is_active',
        'color',
        'icon',
        'profile_picture',
        'tts_enabled',
        'tts_provider',
        'tts_voice',
        'tts_api_url',
        'tts_speed',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(AiRole::class, 'ai_role_id');
    }

    // Accessor for backward compatibility and role inheritance
    public function getToolsAttribute()
    {
        return $this->role ? $this->role->tools : collect();
    }
}

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
        'is_in_chat',
        'color',
        'icon',
        'profile_picture',
        'tts_enabled',
        'tts_provider',
        'tts_voice',
        'tts_api_url',
        'tts_speed',
        'telegram_bot_token',
        'telegram_allowed_chat_ids',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_in_chat' => 'boolean',
        'tts_enabled' => 'boolean',
        'telegram_allowed_chat_ids' => 'array',
    ];

    public function department()
    {
        return $this->belongsTo(AiDepartment::class, 'ai_department_id');
    }

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

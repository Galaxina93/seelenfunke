<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'wake_word',
        'role_description',
        'system_prompt',
        'model',
        'temperature',
        'is_active',
        'color',
        'icon',
        'profile_picture', // NEW
    ];

    public function tools()
    {
        return $this->belongsToMany(AiTool::class, 'ai_agent_tool')
                    ->withTimestamps();
    }
}

<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiAgentSetting extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'funki_ai_agent_settings';

    protected $fillable = [
        'key',
        'value'
    ];
}

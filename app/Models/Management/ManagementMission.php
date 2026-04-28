<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ManagementMission extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function agent()
    {
        return $this->belongsTo(\App\Models\Ai\AiAgent::class, 'ai_agent_id');
    }
}

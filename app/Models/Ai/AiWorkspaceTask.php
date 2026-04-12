<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiWorkspaceTask extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'prompt',
        'status',
        'assigned_agent_id',
        'response_content',
        'ui_metadata',
        'completed_at',
    ];

    protected $casts = [
        'ui_metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'assigned_agent_id');
    }
}

<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiCall extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ai_agent_id',
        'ai_contact_id',
        'phone_number',
        'direction',
        'status',
        'duration_seconds',
        'external_call_id',
        'recording_url',
        'transcript',
        'summary',
    ];

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function contact()
    {
        return $this->belongsTo(AiContact::class, 'ai_contact_id');
    }
}

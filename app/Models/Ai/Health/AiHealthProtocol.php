<?php

namespace App\Models\Ai\Health;

use App\Models\Ai\AiAgent;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiHealthProtocol extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'ai_agent_id',
        'ai_health_treatment_plan_id',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\Admin\Admin::class, 'user_id');
    }

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function treatmentPlan()
    {
        return $this->belongsTo(AiHealthTreatmentPlan::class, 'ai_health_treatment_plan_id');
    }
}

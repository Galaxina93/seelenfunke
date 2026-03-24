<?php

namespace App\Models\Ai\Health;

use App\Models\Ai\AiAgent;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiHealthTreatmentPlan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'ai_agent_id',
        'title',
        'diagnosis_summary',
        'start_date',
        'end_date',
        'status',
        'result_evaluation',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\Admin\Admin::class, 'user_id');
    }

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function items()
    {
        return $this->hasMany(AiHealthTreatmentItem::class, 'plan_id');
    }
}

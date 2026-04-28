<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiHealthTreatmentItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'plan_id',
        'name',
        'dosage',
        'duration_days',
        'notes',
        'is_completed',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(AiHealthTreatmentPlan::class, 'plan_id');
    }
}

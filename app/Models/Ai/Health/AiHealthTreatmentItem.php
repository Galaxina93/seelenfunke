<?php

namespace App\Models\Ai\Health;

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
    ];

    public function plan()
    {
        return $this->belongsTo(AiHealthTreatmentPlan::class, 'plan_id');
    }
}

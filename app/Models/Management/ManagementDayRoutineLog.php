<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementDayRoutineLog extends Model
{
    use HasUuids;

    protected $table = 'management_day_routine_logs';

    protected $fillable = [
        'day_routine_id',
        'date',
        'is_completed',
        'is_sick',
        'completed_steps',
        'completed_at',
        'note',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_completed' => 'boolean',
        'is_sick' => 'boolean',
        'completed_steps' => 'array',
        'completed_at' => 'datetime',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(ManagementDayRoutine::class, 'day_routine_id');
    }
}

<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementDayRoutineStep extends Model
{
    use HasUuids;

    protected $table = 'management_day_routine_steps';

    protected $fillable = ['day_routine_id', 'title', 'position', 'duration_minutes'];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(ManagementDayRoutine::class, 'day_routine_id');
    }
}

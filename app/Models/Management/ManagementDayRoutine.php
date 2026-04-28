<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementDayRoutine extends Model
{
    use HasUuids;

    protected $table = 'management_day_routines';

    protected $fillable = [
        'start_time', 'title', 'message', 'icon', 'type', 'duration_minutes', 'is_active'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'is_active' => 'boolean'
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ManagementDayRoutineStep::class, 'day_routine_id')->orderBy('position');
    }
}

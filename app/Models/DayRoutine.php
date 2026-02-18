<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayRoutine extends Model
{
    use HasUuids;

    protected $fillable = [
        'start_time', 'title', 'message', 'icon', 'type', 'duration_minutes', 'is_active'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'is_active' => 'boolean'
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(DayRoutineStep::class)->orderBy('position');
    }
}

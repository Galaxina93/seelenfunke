<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayRoutineStep extends Model
{
    use HasUuids;

    protected $fillable = ['day_routine_id', 'title', 'position', 'duration_minutes'];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(DayRoutine::class);
    }
}

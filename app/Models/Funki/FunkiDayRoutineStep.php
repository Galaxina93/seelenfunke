<?php

namespace App\Models\Funki;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunkiDayRoutineStep extends Model
{
    use HasUuids;

    protected $fillable = ['funki_day_routine_id', 'title', 'position', 'duration_minutes'];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(FunkiDayRoutine::class);
    }
}

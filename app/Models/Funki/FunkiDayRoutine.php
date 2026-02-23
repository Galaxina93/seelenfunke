<?php

namespace App\Models\Funki;

use App\Models\Funki\FunkiDayRoutineStep;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunkiDayRoutine extends Model
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
        return $this->hasMany(FunkiDayRoutineStep::class)->orderBy('position');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CalendarEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'title', 'start_date', 'end_date', 'is_all_day',
        'recurrence', 'recurrence_end_date',
        'reminder_minutes',
        'category', 'description', 'ics_uid'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'recurrence_end_date' => 'date',
        'is_all_day' => 'boolean',
    ];

    // Helper: Ist es ein wiederkehrender Termin?
    public function isRecurring()
    {
        return !empty($this->recurrence);
    }
}

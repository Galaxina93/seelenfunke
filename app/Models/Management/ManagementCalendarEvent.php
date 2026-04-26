<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ManagementCalendarEvent extends Model
{
    use HasUuids;

    protected $table = 'management_calendar_events';

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

    protected static function booted()
    {
        static::created(function ($event) {
            // Find Alina or fallback to the first user
            $recipient = \App\Models\System\SystemUser::where('first_name', 'like', '%Alina%')->first();
            if (!$recipient) {
                $recipient = \App\Models\System\SystemUser::first();
            }

            if ($recipient && $recipient->email) {
                \Illuminate\Support\Facades\Mail::to($recipient->email)
                    ->queue(new \App\Mail\CalendarEventCreated($event));
            }
        });
    }
}

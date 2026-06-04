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
        'category', 'description', 'ics_uid', 'send_email'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'recurrence_end_date' => 'date',
        'is_all_day' => 'boolean',
        'send_email' => 'boolean',
    ];

    // Helper: Ist es ein wiederkehrender Termin?
    public function isRecurring()
    {
        return !empty($this->recurrence);
    }

    protected static function booted()
    {
        static::created(function ($event) {
            if (!$event->send_email) {
                return;
            }
            // Find Alina or fallback to the first Admin
            $recipient = \App\Models\Admin\Admin::where('first_name', 'like', '%Alina%')->first();
            if (!$recipient) {
                $recipient = \App\Models\Admin\Admin::first();
            }

            if ($recipient && $recipient->email) {
                \Illuminate\Support\Facades\Mail::to($recipient->email)
                    ->queue(new \App\Mail\CalendarEventCreated($event));
            }
        });
    }
}

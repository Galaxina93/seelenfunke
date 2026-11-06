<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementCalendarEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;

Route::prefix('funki/calendar')->group(function () {

    Route::get('/events', function (Request $request) {
        $start = $request->filled('start')
            ? Carbon::parse($request->input('start'))->startOfDay()
            : Carbon::today()->subYears(1)->startOfYear();
        $end = $request->filled('end')
            ? Carbon::parse($request->input('end'))->endOfDay()
            : Carbon::today()->addYears(2)->endOfYear();

        $normalEvents = ManagementCalendarEvent::where(function ($query) use ($start, $end) {
            $query->where('start_date', '<=', $end)
                  ->where(function ($sub) use ($start) {
                      $sub->where('end_date', '>=', $start)
                          ->orWhereNull('end_date')
                          ->orWhere('start_date', '>=', $start);
                  });
        })
        ->whereNull('recurrence')
        ->get();

        $expandedEvents = collect();

        foreach ($normalEvents as $ev) {
            if (!$ev->start_date) continue;
            $expandedEvents->push([
                'id' => (string)$ev->id,
                'master_id' => (string)$ev->id,
                'title' => $ev->title ?? 'Termin',
                'start' => $ev->start_date->toIso8601String(),
                'end' => $ev->end_date ? $ev->end_date->toIso8601String() : $ev->start_date->copy()->addHour()->toIso8601String(),
                'is_all_day' => (bool)$ev->is_all_day,
                'category' => $ev->category ?? 'general',
                'description' => $ev->description,
                'recurrence' => 'none',
                'recurrence_end_date' => $ev->recurrence_end_date ? $ev->recurrence_end_date->format('Y-m-d') : null,
                'reminder_minutes' => $ev->reminder_minutes,
                'priority' => $ev->priority ?? 'low',
                'is_task' => false
            ]);
        }

        $recurringTemplates = ManagementCalendarEvent::whereNotNull('recurrence')->get();
        $validRecurrences = ['daily', 'weekly', 'monthly', 'yearly'];

        foreach ($recurringTemplates as $tmpl) {
            if (!$tmpl->start_date || !in_array($tmpl->recurrence, $validRecurrences, true)) continue;
            if ($tmpl->recurrence_end_date && $tmpl->recurrence_end_date < $start) continue;

            $simDate = $tmpl->start_date->copy();

            if ($simDate < $start) {
                $fastForwardGuard = 0;
                while ($simDate < $start && $fastForwardGuard++ < 2500) {
                    switch ($tmpl->recurrence) {
                        case 'daily': $simDate->addDay(); break;
                        case 'weekly': $simDate->addWeek(); break;
                        case 'monthly': $simDate->addMonth(); break;
                        case 'yearly': $simDate->addYear(); break;
                        default: $simDate->addYear(100); break;
                    }
                }
            }

            $expandGuard = 0;
            while ($simDate <= $end && $expandGuard++ < 800) {
                if ($tmpl->recurrence_end_date && $simDate > $tmpl->recurrence_end_date) break;

                $duration = $tmpl->end_date ? abs($tmpl->start_date->diffInSeconds($tmpl->end_date)) : 3600;
                $simEnd = $simDate->copy()->addSeconds($duration);

                $expandedEvents->push([
                    'id' => (string)$tmpl->id,
                    'master_id' => (string)$tmpl->id,
                    'title' => $tmpl->title ?? 'Serie',
                    'start' => $simDate->toIso8601String(),
                    'end' => $simEnd->toIso8601String(),
                    'is_all_day' => (bool)$tmpl->is_all_day,
                    'category' => $tmpl->category ?? 'general',
                    'description' => $tmpl->description,
                    'recurrence' => $tmpl->recurrence,
                    'recurrence_end_date' => $tmpl->recurrence_end_date ? $tmpl->recurrence_end_date->format('Y-m-d') : null,
                    'reminder_minutes' => $tmpl->reminder_minutes,
                    'priority' => $tmpl->priority ?? 'low',
                    'is_task' => false
                ]);

                switch ($tmpl->recurrence) {
                    case 'daily': $simDate->addDay(); break;
                    case 'weekly': $simDate->addWeek(); break;
                    case 'monthly': $simDate->addMonth(); break;
                    case 'yearly': $simDate->addYear(); break;
                    default: $simDate->addYear(100); break;
                }
            }
        }

        // Include scheduled To-Do tasks (matching /admin/calender)
        if (class_exists(\App\Models\Management\ManagementTask::class)) {
            $tasksWithDueDate = \App\Models\Management\ManagementTask::whereNotNull('relevant_from')
                ->whereBetween('relevant_from', [$start, $end])
                ->where('is_archived', false)
                ->whereNull('parent_id')
                ->get();

            foreach ($tasksWithDueDate as $task) {
                if (!$task->relevant_from) continue;
                $expandedEvents->push([
                    'id' => 'task_' . $task->id,
                    'master_id' => 'task_' . $task->id,
                    'title' => '📋 ' . $task->title,
                    'start' => $task->relevant_from->copy()->startOfDay()->toIso8601String(),
                    'end' => $task->relevant_from->copy()->endOfDay()->toIso8601String(),
                    'is_all_day' => true,
                    'category' => 'task',
                    'description' => 'Aufgabe: ' . $task->title . ($task->is_completed ? ' (Erledigt)' : ' (Offen)'),
                    'recurrence' => 'none',
                    'recurrence_end_date' => null,
                    'reminder_minutes' => null,
                    'priority' => ($task->priority === 'hoch' || $task->priority === 'high') ? 'high' : 'low',
                    'is_task' => true
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $expandedEvents->sortBy('start')->values()]);
    });

    Route::post('/events', function (Request $request) {
        if ($request->has('is_all_day')) {
            $val = $request->input('is_all_day');
            if ($val === 'true') {
                $request->merge(['is_all_day' => true]);
            } elseif ($val === 'false') {
                $request->merge(['is_all_day' => false]);
            }
        }
        if ($request->has('send_email')) {
            $val = $request->input('send_email');
            if ($val === 'true') {
                $request->merge(['send_email' => true]);
            } elseif ($val === 'false') {
                $request->merge(['send_email' => false]);
            }
        }
 
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'nullable|date|after_or_equal:start',
            'is_all_day' => 'boolean',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'recurrence' => 'nullable|string|in:none,daily,weekly,monthly,yearly',
            'recurrence_end_date' => 'nullable|date',
            'reminder_minutes' => 'nullable|integer',
            'priority' => 'nullable|string|in:low,medium,high',
            'send_email' => 'boolean'
        ]);
 
        $event = ManagementCalendarEvent::create([
            'id' => Str::uuid(),
            'title' => $data['title'],
            'start_date' => Carbon::parse($data['start'])->setTimezone(config('app.timezone')),
            'end_date' => !empty($data['end']) ? Carbon::parse($data['end'])->setTimezone(config('app.timezone')) : Carbon::parse($data['start'])->setTimezone(config('app.timezone'))->addHour(),
            'is_all_day' => $data['is_all_day'] ?? false,
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? null,
            'recurrence' => (isset($data['recurrence']) && $data['recurrence'] === 'none') ? null : ($data['recurrence'] ?? null),
            'recurrence_end_date' => !empty($data['recurrence_end_date']) ? Carbon::parse($data['recurrence_end_date']) : null,
            'reminder_minutes' => $data['reminder_minutes'] ?? null,
            'priority' => $data['priority'] ?? 'low',
            'send_email' => $data['send_email'] ?? false
        ]);
 
        return response()->json(['success' => true, 'data' => $event]);
    });

    Route::get('/events/{id}', function ($id) {
        $event = ManagementCalendarEvent::findOrFail($id);
        return response()->json(['success' => true, 'data' => $event]);
    });

    Route::put('/events/{id}', function (Request $request, $id) {
        if (str_starts_with((string)$id, 'task_')) {
            $taskId = substr((string)$id, 5);
            $task = \App\Models\Management\ManagementTask::findOrFail($taskId);
            if ($request->filled('start')) {
                $task->relevant_from = Carbon::parse($request->input('start'))->setTimezone(config('app.timezone'));
            }
            if ($request->filled('title')) {
                $cleanTitle = preg_replace('/^📋\s*/u', '', (string)$request->input('title'));
                if ($cleanTitle !== '') {
                    $task->title = $cleanTitle;
                }
            }
            $task->save();
            return response()->json(['success' => true, 'data' => $task]);
        }

        if (str_contains((string)$id, '_')) {
            $parts = explode('_', (string)$id);
            $id = $parts[0];
        }

        $event = ManagementCalendarEvent::findOrFail($id);

        if ($request->has('is_all_day')) {
            $val = $request->input('is_all_day');
            if ($val === 'true') {
                $request->merge(['is_all_day' => true]);
            } elseif ($val === 'false') {
                $request->merge(['is_all_day' => false]);
            }
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'nullable|date|after_or_equal:start',
            'is_all_day' => 'boolean',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'recurrence' => 'nullable|string|in:none,daily,weekly,monthly,yearly',
            'recurrence_end_date' => 'nullable|date',
            'reminder_minutes' => 'nullable|integer',
            'priority' => 'nullable|string|in:low,medium,high'
        ]);

        $event->update([
            'title' => $data['title'],
            'start_date' => Carbon::parse($data['start'])->setTimezone(config('app.timezone')),
            'end_date' => !empty($data['end']) ? Carbon::parse($data['end'])->setTimezone(config('app.timezone')) : Carbon::parse($data['start'])->setTimezone(config('app.timezone'))->addHour(),
            'is_all_day' => $data['is_all_day'] ?? false,
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? null,
            'recurrence' => (isset($data['recurrence']) && $data['recurrence'] === 'none') ? null : ($data['recurrence'] ?? null),
            'recurrence_end_date' => array_key_exists('recurrence_end_date', $data)
                ? (!empty($data['recurrence_end_date']) ? Carbon::parse($data['recurrence_end_date']) : null)
                : $event->recurrence_end_date,
            'reminder_minutes' => array_key_exists('reminder_minutes', $data) ? $data['reminder_minutes'] : $event->reminder_minutes,
            'priority' => $data['priority'] ?? 'low'
        ]);

        return response()->json(['success' => true, 'data' => $event]);
    });

    Route::post('/import-ics', function (Request $request) {
        $content = (string)$request->input('ics_content', '');
        if (trim($content) === '') {
            return response()->json(['success' => false, 'message' => 'Keine ICS-Daten übergeben.'], 422);
        }

        $content = preg_replace('/\r\n[ \t]/', '', $content);
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);
        $count = 0;
        $eventBlocks = array_slice($matches[1] ?? [], 0, 1000);
        $validTimezones = timezone_identifiers_list();

        $detectCategory = function (string $title): string {
            $t = mb_strtolower($title);
            if (str_contains($t, 'restmüll') || str_contains($t, 'graue')) return 'restmuell';
            if (str_contains($t, 'papier') || str_contains($t, 'blaue')) return 'altpapier';
            if (str_contains($t, 'bio') || str_contains($t, 'braune')) return 'biomuell';
            if (str_contains($t, 'gelber') || str_contains($t, 'wertstoff')) return 'gelber_sack';
            if (str_contains($t, 'schadstoff') || str_contains($t, 'gift')) return 'schadstoffe';
            if (str_contains($t, 'sperrmüll')) return 'sperrmuell';
            if (str_contains($t, 'grün') || str_contains($t, 'garten')) return 'gruen';
            if (str_contains($t, 'baum') || str_contains($t, 'weihnacht')) return 'baum';
            if (str_contains($t, 'anruf')) return 'call';
            if (str_contains($t, 'besprechung') || str_contains($t, 'meeting')) return 'meeting';
            if (str_contains($t, 'geburtstag')) return 'birthday';
            if (str_contains($t, 'urlaub') || str_contains($t, 'ferien')) return 'vacation';
            if (str_contains($t, 'reise')) return 'travel';
            if (str_contains($t, 'projekt')) return 'project';
            if (str_contains($t, 'kunde')) return 'customer';
            return 'general';
        };

        foreach ($eventBlocks as $eventBlock) {
            preg_match('/UID:(.*?)(?:\r\n|\n|$)/', $eventBlock, $mUid);
            $uid = isset($mUid[1]) ? trim($mUid[1]) : null;

            preg_match('/SUMMARY:(.*?)(?:\r\n|\n|$)/', $eventBlock, $mTitle);
            $title = 'Termin';
            if (isset($mTitle[1])) {
                $title = trim($mTitle[1]);
                $title = str_replace(['&shy;', '\,', '\;', '\n', '\N'], ['', ',', ';', ' ', ' '], $title);
                $title = html_entity_decode($title);
            }

            preg_match('/DTSTART([^:]*):(\d{8}(?:T\d{6}Z?)?)/', $eventBlock, $mStart);
            if (empty($mStart[2])) continue;

            $params = $mStart[1];
            $rawDate = $mStart[2];
            $timezone = config('app.timezone');
            if (preg_match('/TZID=([^;]+)/', $params, $mTz) && in_array(trim($mTz[1]), $validTimezones, true)) {
                $timezone = trim($mTz[1]);
            }

            preg_match('/DTEND([^:]*):(\d{8}(?:T\d{6}Z?)?)/', $eventBlock, $mEnd);
            $uid = ($uid !== null && $uid !== '') ? $uid : ('ics_' . sha1($title . '|' . $rawDate . '|' . ($mEnd[2] ?? '')));

            try {
                $isAllDay = (strlen($rawDate) === 8);
                if ($isAllDay) {
                    $startDate = Carbon::createFromFormat('Ymd', $rawDate, $timezone)->startOfDay();
                } else {
                    $startDate = str_ends_with($rawDate, 'Z')
                        ? Carbon::parse($rawDate)->setTimezone($timezone)
                        : Carbon::createFromFormat('Ymd\THis', $rawDate, $timezone);
                }

                if (!empty($mEnd[2])) {
                    $rawEnd = $mEnd[2];
                    if (strlen($rawEnd) === 8) {
                        $endDate = Carbon::createFromFormat('Ymd', $rawEnd, $timezone)->subDay()->endOfDay();
                    } else {
                        $endDate = str_ends_with($rawEnd, 'Z')
                            ? Carbon::parse($rawEnd)->setTimezone($timezone)
                            : Carbon::createFromFormat('Ymd\THis', $rawEnd, $timezone);
                    }
                } else {
                    $endDate = $isAllDay ? $startDate->copy()->endOfDay() : $startDate->copy()->addHour();
                }

                $reminderMinutes = null;
                if (preg_match('/TRIGGER:(?:-)?(P.*?)(?:\r\n|\n|$)/', $eventBlock, $mTrigger)) {
                    try {
                        $interval = new \DateInterval(trim($mTrigger[1]));
                        $minutes = ($interval->d * 1440) + ($interval->h * 60) + $interval->i;
                        if ($minutes > 0) $reminderMinutes = $minutes;
                    } catch (\Exception $e) {}
                }

                ManagementCalendarEvent::updateOrCreate(
                    ['ics_uid' => $uid],
                    [
                        'title' => $title,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'is_all_day' => $isAllDay,
                        'category' => $detectCategory($title),
                        'reminder_minutes' => $reminderMinutes,
                        'description' => 'Importiert aus ICS',
                        'priority' => 'low'
                    ]
                );
                $count++;
            } catch (\Exception $e) {
                continue;
            }
        }

        return response()->json(['success' => true, 'imported' => $count]);
    });

    Route::delete('/events/{id}', function ($id) {
        if (str_contains($id, '_')) {
            $parts = explode('_', $id);
            $id = $parts[0];
        }
        ManagementCalendarEvent::destroy($id);
        return response()->json(['success' => true]);
    });

});

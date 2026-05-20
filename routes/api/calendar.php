<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementCalendarEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;

Route::prefix('funki/calendar')->group(function () {

    Route::get('/events', function (Request $request) {
        $start = Carbon::today()->subMonths(1);
        $end = Carbon::today()->addYear();

        $normalEvents = ManagementCalendarEvent::whereBetween('start_date', [$start, $end])
            ->whereNull('recurrence')
            ->get();

        $expandedEvents = collect();

        foreach ($normalEvents as $ev) {
            if (!$ev->start_date) continue;
            $expandedEvents->push([
                'id' => (string)$ev->id,
                'title' => $ev->title ?? 'Termin',
                'start' => $ev->start_date->toIso8601String(),
                'end' => $ev->end_date ? $ev->end_date->toIso8601String() : null,
                'is_all_day' => (bool)$ev->is_all_day,
                'category' => $ev->category ?? 'general',
                'description' => $ev->description,
                'recurrence' => 'none',
                'reminder_minutes' => $ev->reminder_minutes,
                'priority' => $ev->priority ?? 'low'
            ]);
        }

        $recurringTemplates = ManagementCalendarEvent::whereNotNull('recurrence')->get();
        $calcEnd = Carbon::today()->addMonths(6);

        foreach ($recurringTemplates as $tmpl) {
            if (!$tmpl->start_date) continue;
            $simDate = $tmpl->start_date->copy();

            if ($simDate < Carbon::today()) {
                while ($simDate < Carbon::today()) {
                    switch ($tmpl->recurrence) {
                        case 'daily': $simDate->addDay(); break;
                        case 'weekly': $simDate->addWeek(); break;
                        case 'monthly': $simDate->addMonth(); break;
                        case 'yearly': $simDate->addYear(); break;
                    }
                }
            }

            while ($simDate <= $calcEnd) {
                if ($tmpl->recurrence_end_date && $simDate > $tmpl->recurrence_end_date) break;

                $duration = $tmpl->end_date->diffInSeconds($tmpl->start_date);
                $simEnd = $simDate->copy()->addSeconds($duration);

                $expandedEvents->push([
                    'id' => $tmpl->id,
                    'title' => $tmpl->title ?? 'Serie',
                    'start' => $simDate->toIso8601String(),
                    'end' => $simEnd->toIso8601String(),
                    'is_all_day' => (bool)$tmpl->is_all_day,
                    'category' => $tmpl->category ?? 'general',
                    'description' => $tmpl->description,
                    'recurrence' => $tmpl->recurrence,
                    'reminder_minutes' => $tmpl->reminder_minutes,
                    'priority' => $tmpl->priority ?? 'low'
                ]);

                switch ($tmpl->recurrence) {
                    case 'daily': $simDate->addDay(); break;
                    case 'weekly': $simDate->addWeek(); break;
                    case 'monthly': $simDate->addMonth(); break;
                    case 'yearly': $simDate->addYear(); break;
                }
            }
        }
        return response()->json(['success' => true, 'data' => $expandedEvents->sortBy('start')->values()]);
    });

    Route::post('/events', function (Request $request) {
        $data = $request->validate([
            'title' => 'required|string',
            'start' => 'required|date',
            'end' => 'nullable|date',
            'is_all_day' => 'boolean',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
            'recurrence' => 'nullable|string',
            'reminder_minutes' => 'nullable|integer',
            'priority' => 'nullable|string'
        ]);

        $event = ManagementCalendarEvent::create([
            'id' => Str::uuid(),
            'title' => $data['title'],
            'start_date' => Carbon::parse($data['start']),
            'end_date' => $data['end'] ? Carbon::parse($data['end']) : Carbon::parse($data['start'])->addHour(),
            'is_all_day' => $data['is_all_day'] ?? false,
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? null,
            'recurrence' => (isset($data['recurrence']) && $data['recurrence'] === 'none') ? null : ($data['recurrence'] ?? null),
            'reminder_minutes' => $data['reminder_minutes'] ?? null,
            'priority' => $data['priority'] ?? 'low'
        ]);

        return response()->json(['success' => true, 'data' => $event]);
    });

    Route::get('/events/{id}', function ($id) {
        $event = ManagementCalendarEvent::findOrFail($id);
        return response()->json(['success' => true, 'data' => $event]);
    });

    Route::put('/events/{id}', function (Request $request, $id) {
        $event = ManagementCalendarEvent::findOrFail($id);

        $data = $request->validate([
            'title' => 'required|string',
            'start' => 'required|date',
            'end' => 'nullable|date',
            'is_all_day' => 'boolean',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
            'recurrence' => 'nullable|string',
            'reminder_minutes' => 'nullable|integer',
            'priority' => 'nullable|string'
        ]);

        $event->update([
            'title' => $data['title'],
            'start_date' => Carbon::parse($data['start']),
            'end_date' => $data['end'] ? Carbon::parse($data['end']) : Carbon::parse($data['start'])->addHour(),
            'is_all_day' => $data['is_all_day'] ?? false,
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? null,
            'recurrence' => (isset($data['recurrence']) && $data['recurrence'] === 'none') ? null : ($data['recurrence'] ?? null),
            'reminder_minutes' => $data['reminder_minutes'] ?? null,
            'priority' => $data['priority'] ?? 'low'
        ]);

        return response()->json(['success' => true, 'data' => $event]);
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

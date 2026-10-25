<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementDayRoutine;
use App\Models\Management\ManagementDayRoutineStep;
use App\Models\Management\ManagementDayRoutineLog;
use Carbon\Carbon;
use Illuminate\Support\Str;

Route::prefix('funki/routine')->group(function () {

    // 1. Get all routines with steps and today's log status
    Route::get('/', function (Request $request) {
        $date = $request->query('date', Carbon::now()->format('Y-m-d'));
        $routines = ManagementDayRoutine::with([
            'steps' => function($q) {
                $q->orderBy('position', 'asc');
            },
            'logs' => function($q) use ($date) {
                $q->whereDate('date', $date);
            }
        ])
        ->where('is_active', true)
        ->orderBy('start_time', 'asc')
        ->get();
        return response()->json(['success' => true, 'date' => $date, 'data' => $routines]);
    });

    // 2. Create routine
    Route::post('/', function (Request $request) {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'nullable|string|max:5000',
            'duration_minutes' => 'required|integer|min:1|max:1440',
            'start_time' => 'required|date_format:H:i:s',
            'icon' => 'nullable|string|max:100'
        ]);

        $routine = ManagementDayRoutine::create(array_merge($data, [
            'id' => Str::uuid(),
            'is_active' => true
        ]));

        return response()->json(['success' => true, 'data' => $routine->load('steps')]);
    });

    // 3. Update routine
    Route::put('/{id}', function (Request $request, $id) {
        $routine = ManagementDayRoutine::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'message' => 'nullable|string|max:5000',
            'duration_minutes' => 'sometimes|required|integer|min:1|max:1440',
            'start_time' => 'sometimes|required|date_format:H:i:s',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean'
        ]);

        $routine->update($data);
        return response()->json(['success' => true, 'data' => $routine->load('steps')]);
    });

    // 4. Delete routine
    Route::delete('/{id}', function ($id) {
        $routine = ManagementDayRoutine::findOrFail($id);
        $routine->steps()->delete();
        $routine->delete();
        return response()->json(['success' => true]);
    });

    // 5. Create step for routine
    Route::post('/{routineId}/steps', function (Request $request, $routineId) {
        $routine = ManagementDayRoutine::findOrFail($routineId);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1|max:1440'
        ]);

        $step = ManagementDayRoutineStep::create([
            'id' => Str::uuid(),
            'day_routine_id' => $routine->id,
            'title' => $data['title'],
            'duration_minutes' => $data['duration_minutes'],
            'position' => $routine->steps()->max('position') + 1
        ]);

        return response()->json(['success' => true, 'data' => $step]);
    });

    // 6. Delete step
    Route::delete('/steps/{stepId}', function ($stepId) {
        ManagementDayRoutineStep::destroy($stepId);
        return response()->json(['success' => true]);
    });

    // 7. Toggle daily routine completion log
    Route::post('/{routineId}/toggle-log', function (Request $request, $routineId) {
        $routine = ManagementDayRoutine::with('steps')->findOrFail($routineId);
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));

        $log = ManagementDayRoutineLog::where('day_routine_id', $routine->id)
            ->whereDate('date', $date)
            ->first();

        if ($log && $log->is_completed) {
            $log->update([
                'is_completed' => false,
                'completed_steps' => [],
                'completed_at' => null,
            ]);
        } else {
            $log = ManagementDayRoutineLog::updateOrCreate(
                ['day_routine_id' => $routine->id, 'date' => $date],
                [
                    'is_completed' => true,
                    'completed_steps' => $routine->steps->pluck('id')->map(fn($id) => (string) $id)->toArray(),
                    'completed_at' => Carbon::now(),
                ]
            );
        }

        return response()->json(['success' => true, 'data' => $log]);
    });

    // 8. Toggle sick day for a date
    Route::post('/toggle-sick-day', function (Request $request) {
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));
        $routines = ManagementDayRoutine::where('is_active', true)->get();

        $alreadySick = ManagementDayRoutineLog::whereDate('date', $date)
            ->where('is_sick', true)
            ->exists();

        if ($alreadySick) {
            ManagementDayRoutineLog::whereDate('date', $date)->update(['is_sick' => false]);
        } else {
            foreach ($routines as $routine) {
                ManagementDayRoutineLog::updateOrCreate(
                    ['day_routine_id' => $routine->id, 'date' => $date],
                    ['is_sick' => true]
                );
            }
        }

        return response()->json([
            'success' => true,
            'date' => $date,
            'is_sick' => !$alreadySick,
        ]);
    });

});

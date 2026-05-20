<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementDayRoutine;
use App\Models\Management\ManagementDayRoutineStep;
use Illuminate\Support\Str;

Route::prefix('funki/routine')->group(function () {

    // 1. Get all routines with steps
    Route::get('/', function () {
        $routines = ManagementDayRoutine::with(['steps' => function($q) {
            $q->orderBy('position', 'asc');
        }])
        ->where('is_active', true)
        ->orderBy('start_time', 'asc')
        ->get();
        return response()->json(['success' => true, 'data' => $routines]);
    });

    // 2. Create routine
    Route::post('/', function (Request $request) {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'nullable|string',
            'duration_minutes' => 'required|integer',
            'start_time' => 'required|date_format:H:i:s',
            'icon' => 'nullable|string'
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
            'message' => 'nullable|string',
            'duration_minutes' => 'sometimes|required|integer',
            'start_time' => 'sometimes|required|date_format:H:i:s',
            'icon' => 'nullable|string',
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
            'duration_minutes' => 'required|integer'
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

});

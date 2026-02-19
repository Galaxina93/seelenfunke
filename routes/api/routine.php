<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\DayRoutine;

Route::get('/funki/routine', function () {
    return DayRoutine::with('steps')
        ->where('is_active', true)
        ->orderBy('start_time')
        ->get();
});

Route::put('/funki/routine/{id}', function (Request $request, $id) {
    $routine = DayRoutine::findOrFail($id);
    $data = $request->validate([
        'title' => 'required',
        'message' => 'nullable',
        'duration_minutes' => 'required|integer',
        'start_time' => 'required'
    ]);
    $routine->update($data);
    return response()->json(['success' => true]);
});

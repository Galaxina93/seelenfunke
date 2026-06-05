<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use Illuminate\Support\Str;

Route::prefix('funki/tasks')->group(function () {

    // --- LISTS ---
    Route::get('/lists', function () {
        $lists = ManagementTaskList::orderBy('created_at')->get();
        return response()->json(['success' => true, 'data' => $lists]);
    });

    Route::post('/lists', function (Request $request) {
        $data = $request->validate([
            'name' => 'required|string',
            'icon' => 'nullable|string'
        ]);

        $list = ManagementTaskList::create([
            'id' => Str::uuid(),
            'name' => $data['name'],
            'icon' => $data['icon'] ?? 'bookmark',
            'color' => '#C5A059'
        ]);
        return response()->json(['success' => true, 'data' => $list]);
    });

    Route::put('/lists/{id}', function (Request $request, $id) {
        $list = ManagementTaskList::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string',
            'icon' => 'nullable|string'
        ]);

        $list->update([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? $list->icon
        ]);
        return response()->json(['success' => true, 'data' => $list]);
    });

    Route::delete('/lists/{id}', function ($id) {
        $list = ManagementTaskList::findOrFail($id);
        // Delete all tasks in the list
        ManagementTask::where('task_list_id', $list->id)->delete();
        $list->delete();
        return response()->json(['success' => true]);
    });

    // --- TASKS ---
    Route::get('/', function () {
        $tasks = ManagementTask::orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
            ->orderBy('position')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($task) {
                $task->title = $task->title ?? 'Ohne Titel';
                $parentTitle = null;
                if($task->parent_id) {
                    $parent = ManagementTask::find($task->parent_id);
                    $parentTitle = $parent ? $parent->title : null;
                }
                $task->parent_title = $parentTitle;
                return $task;
            });
            
        return response()->json(['success' => true, 'data' => $tasks]);
    });

    Route::post('/lists/{listId}/tasks', function (Request $request, $listId) {
        $data = $request->validate([
            'title' => 'required|string',
            'priority' => 'nullable|in:niedrig,mittel,hoch'
        ]);

        $task = ManagementTask::create([
            'id' => Str::uuid(),
            'task_list_id' => $listId,
            'parent_id' => null,
            'title' => $data['title'],
            'priority' => $data['priority'] ?? 'niedrig',
            'is_completed' => false
        ]);

        return response()->json(['success' => true, 'data' => $task]);
    });

    Route::put('/{id}', function (Request $request, $id) {
        $task = ManagementTask::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|required',
            'priority' => 'sometimes|in:niedrig,mittel,hoch',
            'is_completed' => 'sometimes|boolean',
            'relevant_from' => 'nullable|string'
        ]);
        if (array_key_exists('relevant_from', $data) || $request->has('relevant_from')) {
            $relevantVal = $request->input('relevant_from');
            $data['relevant_from'] = empty($relevantVal) ? null : \Carbon\Carbon::parse($relevantVal)->startOfDay();
        }
        $task->update($data);
        return response()->json(['success' => true, 'data' => $task]);
    });

    Route::put('/{id}/toggle', function ($id) {
        $task = ManagementTask::findOrFail($id);
        $task->update(['is_completed' => !$task->is_completed]);
        return response()->json(['success' => true, 'data' => $task]);
    });

    Route::post('/{id}/subtask', function (Request $request, $id) {
        $data = $request->validate(['title' => 'required|string']);
        $task = ManagementTask::findOrFail($id);

        $subtask = ManagementTask::create([
            'id' => Str::uuid(),
            'task_list_id' => $task->task_list_id,
            'parent_id' => $task->id,
            'title' => $data['title'],
            'priority' => 'niedrig'
        ]);
        return response()->json(['success' => true, 'data' => $subtask]);
    });

    Route::delete('/{id}', function($id) {
        $task = ManagementTask::findOrFail($id);
        // Also delete subtasks if any
        ManagementTask::where('parent_id', $task->id)->delete();
        $task->delete();
        return response()->json(['success' => true]);
    });

});

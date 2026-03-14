<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Support\Str;

Route::get('/funki/tasks', function () {
    return Task::orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
        ->orderBy('position')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($task) {
            $task->title = $task->title ?? 'Ohne Titel';
            $parentTitle = null;
            if($task->parent_id) {
                $parent = Task::find($task->parent_id);
                $parentTitle = $parent ? $parent->title : null;
            }
            $task->parent_title = $parentTitle;
            return $task;
        });
});

Route::get('/funki/tasks/lists', function () {
    return TaskList::orderBy('created_at')->get();
});

Route::post('/funki/tasks/lists', function (Request $request) {
    $data = $request->validate([
        'name' => 'required|string',
        'icon' => 'nullable|string'
    ]);

    $list = TaskList::create([
        'id' => Str::uuid(),
        'name' => $data['name'],
        'icon' => $data['icon'] ?? 'bookmark',
        'color' => '#C5A059'
    ]);
    return response()->json(['success' => true, 'list' => $list]);
});

Route::put('/funki/tasks/lists/{id}', function (Request $request, $id) {
    $list = TaskList::findOrFail($id);
    $data = $request->validate([
        'name' => 'required|string',
        'icon' => 'nullable|string'
    ]);

    $list->update([
        'name' => $data['name'],
        'icon' => $data['icon'] ?? $list->icon
    ]);
    return response()->json(['success' => true]);
});

Route::post('/funki/tasks/lists/{listId}/tasks', function (Request $request, $listId) {
    $data = $request->validate([
        'title' => 'required|string',
        'priority' => 'nullable|in:niedrig,mittel,hoch'
    ]);

    $task = Task::create([
        'id' => Str::uuid(),
        'task_list_id' => $listId,
        'parent_id' => null,
        'title' => $data['title'],
        'priority' => $data['priority'] ?? 'niedrig',
        'is_completed' => false
    ]);

    return response()->json(['success' => true, 'task' => $task]);
});

Route::post('/funki/tasks/{id}/toggle', function ($id) {
    $task = Task::find($id);
    if ($task) {
        $task->update(['is_completed' => !$task->is_completed]);
        return response()->json(['success' => true]);
    }
    return response()->json(['error' => 'Nicht gefunden'], 404);
});

Route::put('/funki/tasks/{id}', function (Request $request, $id) {
    $task = Task::findOrFail($id);
    $data = $request->validate([
        'title' => 'sometimes|required',
        'priority' => 'sometimes|in:niedrig,mittel,hoch'
    ]);
    $task->update($data);
    return response()->json(['success' => true]);
});

Route::post('/funki/tasks/{id}/subtask', function (Request $request, $id) {
    $data = $request->validate(['title' => 'required']);
    $task = Task::findOrFail($id);
    $taskListId = $task->task_list_id;

    Task::create([
        'id' => Str::uuid(),
        'task_list_id' => $taskListId,
        'parent_id' => $task->id,
        'title' => $data['title'],
        'priority' => 'niedrig'
    ]);
    return response()->json(['success' => true]);
});

Route::delete('/funki/tasks/{id}', function($id) {
    Task::destroy($id);
    return response()->json(['success' => true]);
});

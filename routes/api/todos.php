<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Todo;
use App\Models\TodoList; // NEU Hinzugefügt
use Illuminate\Support\Str;

Route::get('/funki/todos', function () {
    // ERWEITERT: 'is_completed' Filter entfernt, damit das Archiv in der App gefüllt werden kann!
    return Todo::orderBy('position')
        ->orderBy('created_at')
        ->get()
        ->map(function($todo) {
            $todo->title = $todo->title ?? 'Ohne Titel';
            $parentTitle = null;
            if($todo->parent_id) {
                $parent = Todo::find($todo->parent_id);
                $parentTitle = $parent ? $parent->title : null;
            }
            $todo->parent_title = $parentTitle;
            return $todo;
        });
});

// NEU: Alle Listen abrufen
Route::get('/funki/todos/lists', function () {
    return TodoList::orderBy('created_at')->get();
});

// NEU: Liste erstellen
Route::post('/funki/todos/lists', function (Request $request) {
    $data = $request->validate([
        'name' => 'required|string',
        'icon' => 'nullable|string'
    ]);

    $list = TodoList::create([
        'id' => Str::uuid(),
        'name' => $data['name'],
        'icon' => $data['icon'] ?? 'star',
        'color' => '#C5A059'
    ]);
    return response()->json(['success' => true, 'list' => $list]);
});

// NEU: Liste bearbeiten (umbenennen / icon ändern)
Route::put('/funki/todos/lists/{id}', function (Request $request, $id) {
    $list = TodoList::findOrFail($id);
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

// NEU: Hauptaufgabe (Root) in einer bestimmten Liste anlegen
Route::post('/funki/todos/lists/{listId}/tasks', function (Request $request, $listId) {
    $data = $request->validate(['title' => 'required|string']);

    $todo = Todo::create([
        'id' => Str::uuid(),
        'todo_list_id' => $listId,
        'parent_id' => null,
        'title' => $data['title'],
        'is_completed' => false
    ]);

    return response()->json(['success' => true, 'todo' => $todo]);
});

Route::post('/funki/todos/{id}/toggle', function ($id) {
    $todo = Todo::find($id);
    if ($todo) {
        $todo->update(['is_completed' => !$todo->is_completed]);
        return response()->json(['success' => true]);
    }
    return response()->json(['error' => 'Nicht gefunden'], 404);
});

Route::put('/funki/todos/{id}', function (Request $request, $id) {
    $todo = Todo::findOrFail($id);
    $data = $request->validate(['title' => 'required']);
    $todo->update(['title' => $data['title']]);
    return response()->json(['success' => true]);
});

Route::post('/funki/todos/{id}/subtask', function (Request $request, $id) {
    $data = $request->validate(['title' => 'required']);
    $todo = Todo::findOrFail($id);
    $todoListId = $todo->todo_list_id;

    Todo::create([
        'id' => Str::uuid(),
        'todo_list_id' => $todoListId,
        'parent_id' => $todo->id,
        'title' => $data['title']
    ]);
    return response()->json(['success' => true]);
});

Route::delete('/funki/todos/{id}', function($id) {
    Todo::destroy($id);
    return response()->json(['success' => true]);
});

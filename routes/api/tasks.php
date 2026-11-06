<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use Illuminate\Support\Str;

Route::prefix('funki/tasks')->group(function () {

    // --- LISTS ---
    Route::get('/lists', function () {
        $lists = ManagementTaskList::withCount([
            'tasks as open_count' => function ($q) {
                $q->where('is_completed', false)->whereNull('parent_id')->where('is_archived', false);
            },
            'tasks as completed_count' => function ($q) {
                $q->where('is_completed', true)->whereNull('parent_id')->where('is_archived', false);
            }
        ])
            ->where('is_archived', false)
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
        return response()->json(['success' => true, 'data' => $lists]);
    });

    Route::post('/lists', function (Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        $maxPos = (int) ManagementTaskList::max('position');
        $list = ManagementTaskList::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'icon' => $data['icon'] ?? 'list-bullet',
            'color' => $data['color'] ?? '#818CF8',
            'position' => $maxPos + 1,
        ]);
        $list->open_count = 0;
        $list->completed_count = 0;
        return response()->json(['success' => true, 'data' => $list]);
    });

    Route::put('/lists/{id}', function (Request $request, $id) {
        $list = ManagementTaskList::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'position' => 'nullable|integer',
            'is_archived' => 'nullable|boolean',
        ]);

        $list->update(array_filter([
            'name' => $data['name'] ?? $list->name,
            'icon' => array_key_exists('icon', $data) ? ($data['icon'] ?? $list->icon) : $list->icon,
            'color' => array_key_exists('color', $data) ? ($data['color'] ?? $list->color) : $list->color,
            'position' => array_key_exists('position', $data) ? $data['position'] : $list->position,
            'is_archived' => array_key_exists('is_archived', $data) ? $data['is_archived'] : $list->is_archived,
        ], fn($v) => $v !== null));
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
            'title' => 'required|string|max:255',
            'priority' => 'nullable|in:niedrig,mittel,hoch',
            'relevant_from' => 'nullable|date',
            'ai_plan' => 'nullable|string',
        ]);

        // Support smart list aliases ("my_day", "important", "planned", "all", "default")
        $targetList = null;
        if (in_array($listId, ['my_day', 'important', 'planned', 'all', 'default'], true)) {
            $targetList = ManagementTaskList::where('name', 'Aufgaben')->first()
                ?? ManagementTaskList::orderBy('position', 'asc')->orderBy('created_at', 'asc')->first();
            if (!$targetList) {
                $targetList = ManagementTaskList::create([
                    'id' => (string) Str::uuid(),
                    'name' => 'Aufgaben',
                    'icon' => 'home',
                    'color' => '#818CF8',
                    'position' => 1,
                ]);
            }
        } else {
            $targetList = ManagementTaskList::findOrFail($listId);
        }

        $priority = $data['priority'] ?? ($listId === 'important' ? 'hoch' : 'niedrig');
        $relevantFrom = null;
        if (!empty($data['relevant_from'])) {
            $relevantFrom = \Carbon\Carbon::parse($data['relevant_from'])->startOfDay();
        } elseif (in_array($listId, ['my_day', 'planned'], true)) {
            $relevantFrom = \Carbon\Carbon::today();
        }

        $task = ManagementTask::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $targetList->id,
            'parent_id' => null,
            'title' => $data['title'],
            'priority' => $priority,
            'relevant_from' => $relevantFrom,
            'ai_plan' => $data['ai_plan'] ?? null,
            'is_completed' => false,
        ]);

        return response()->json(['success' => true, 'data' => $task]);
    });

    Route::put('/{id}', function (Request $request, $id) {
        $task = ManagementTask::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'priority' => 'sometimes|in:niedrig,mittel,hoch',
            'is_completed' => 'sometimes|boolean',
            'relevant_from' => 'nullable|date',
            'ai_plan' => 'nullable|string',
            'task_list_id' => 'sometimes|string|exists:management_task_lists,id',
            'position' => 'sometimes|integer',
            'is_archived' => 'sometimes|boolean',
        ]);
        if (array_key_exists('relevant_from', $data) || $request->has('relevant_from')) {
            $relevantVal = $request->input('relevant_from');
            $data['relevant_from'] = empty($relevantVal) ? null : \Carbon\Carbon::parse($relevantVal)->startOfDay();
        }
        if ($request->has('ai_plan')) {
            $data['ai_plan'] = $request->input('ai_plan');
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
        $subtasks = ManagementTask::where('parent_id', $task->id)->get();
        foreach ($subtasks as $sub) {
            if (!empty($sub->file_paths)) {
                foreach ($sub->file_paths as $path) {
                    $pathStr = (string) $path;
                    if ($pathStr !== '' && !str_contains($pathStr, '..') && !str_contains($pathStr, "\0") && str_starts_with(ltrim($pathStr, '/'), 'leitung/tasks/') && \Illuminate\Support\Facades\Storage::disk('local')->exists($pathStr)) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($pathStr);
                    }
                }
            }
            $sub->delete();
        }
        if (!empty($task->file_paths)) {
            foreach ($task->file_paths as $path) {
                $pathStr = (string) $path;
                if ($pathStr !== '' && !str_contains($pathStr, '..') && !str_contains($pathStr, "\0") && str_starts_with(ltrim($pathStr, '/'), 'leitung/tasks/') && \Illuminate\Support\Facades\Storage::disk('local')->exists($pathStr)) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($pathStr);
                }
            }
        }
        $task->delete();
        return response()->json(['success' => true]);
    });

    Route::post('/{id}/files', function (Request $request, $id) {
        $task = ManagementTask::findOrFail($id);
        
        $request->validate([
            'file' => 'required'
        ]);

        $files = is_array($request->file('file')) ? $request->file('file') : [$request->file('file')];
        $storedPaths = [];
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'txt', 'md', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
        $blockedMimes = ['text/x-php', 'application/x-httpd-php', 'text/html', 'application/xhtml+xml', 'image/svg+xml', 'application/x-executable', 'application/x-msdownload'];
        
        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->getSize() > 20 * 1024 * 1024) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            $mime = strtolower((string) $file->getMimeType());
            if (!in_array($ext, $allowedExts, true) || in_array($mime, $blockedMimes, true)) {
                continue;
            }
            $path = $file->store('leitung/tasks/attachments', 'local');
            $storedPaths[] = $path;
        }

        $existing = $task->file_paths ?? [];
        $updated = array_merge($existing, $storedPaths);
        $task->update(['file_paths' => $updated]);

        return response()->json(['success' => true, 'data' => $task]);
    });

    Route::delete('/{id}/files', function (Request $request, $id) {
        $task = ManagementTask::findOrFail($id);
        $path = $request->input('path');
        if (!$path) {
            return response()->json(['success' => false, 'message' => 'Path missing'], 400);
        }

        $existing = $task->file_paths ?? [];
        if (($key = array_search($path, $existing)) !== false) {
            unset($existing[$key]);
            $pathStr = (string) $path;
            if ($pathStr !== '' && !str_contains($pathStr, '..') && !str_contains($pathStr, "\0") && str_starts_with(ltrim($pathStr, '/'), 'leitung/tasks/') && \Illuminate\Support\Facades\Storage::disk('local')->exists($pathStr)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($pathStr);
            }
            $task->update(['file_paths' => array_values($existing)]);
        }

        return response()->json(['success' => true, 'data' => $task]);
    });

});

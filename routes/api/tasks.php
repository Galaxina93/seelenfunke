<?php

use App\Http\Middleware\EnsureFunkenwerkDomainAccess;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\Management\ManagementTaskMessage;
use App\Services\Management\ManagementTaskTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Route::prefix('funki/tasks')
    ->middleware([EnsureFunkenwerkDomainAccess::class . ':tasks'])
    ->group(function () {

    $applyEmployeeTaskScope = static function ($query, Request $request): void {
        $u = $request->user();
        if ($u instanceof \App\Models\Employee\Employee) {
            $empId = (string) $u->id;
            $query->where(function ($sub) use ($empId) {
                $sub->where(function ($a) use ($empId) {
                    $a->where('assignee_type', 'employee')->where('assignee_id', $empId);
                })->orWhere(function ($c) use ($empId) {
                    $c->where('created_by_type', 'employee')->where('created_by_id', $empId);
                });
            });
        }
    };

    $findAuthorizedTask = static function (Request $request, $id, array $with = []): ManagementTask {
        $query = empty($with) ? ManagementTask::query() : ManagementTask::with($with);
        $task = $query->findOrFail($id);
        $u = $request->user();
        if ($u instanceof \App\Models\Employee\Employee) {
            $empId = (string) $u->id;
            $checkTarget = $task->parent_id ? (ManagementTask::find($task->parent_id) ?: $task) : $task;
            $isOwn = ($checkTarget->assignee_type === 'employee' && (string) $checkTarget->assignee_id === $empId)
                || ($checkTarget->created_by_type === 'employee' && (string) $checkTarget->created_by_id === $empId);
            if (!$isOwn) {
                abort(403, 'Zugriff auf fremde oder private Aufgaben verweigert.');
            }
        }

        return $task;
    };

    // --- UI CONFIG (Splitter Widths, Sound Mute & Saved Drag-Order in SystemCheckConfig) ---
    Route::get('/ui-config', function (ManagementTaskTicketService $ticketService) {
        return response()->json([
            'success' => true,
            'data' => $ticketService->loadUiConfig(),
        ]);
    });

    Route::put('/ui-config', function (Request $request, ManagementTaskTicketService $ticketService) {
        $patch = $request->only([
            'left_col_width_px',
            'my_tickets_height_pct',
            'sound_muted',
            'my_tickets_order',
            'open_tickets_order',
            'lists_order',
        ]);
        return response()->json([
            'success' => true,
            'data' => $ticketService->saveUiConfig($patch),
        ]);
    });

    Route::post('/reorder', function (Request $request, ManagementTaskTicketService $ticketService) {
        $data = $request->validate([
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'string|max:64',
            'is_mine' => 'required|boolean',
            'list_scope' => 'nullable|string|max:64',
        ]);
        $ticketService->reorderTickets(
            $data['ordered_ids'],
            (bool) $data['is_mine'],
            (string) ($data['list_scope'] ?? 'all')
        );
        return response()->json(['success' => true]);
    });

    // --- BEARBEITER (FUZZY SEARCH) & OPERATOR-PROFIL (SCORE / EP / TITEL / BACKGROUNDS) ---
    Route::get('/assignees', function (Request $request, ManagementTaskTicketService $ticketService) {
        $q = (string) $request->query('q', '');
        return response()->json([
            'success' => true,
            'data' => $ticketService->getAssignableActors($q),
        ]);
    });

    Route::get('/profile', function (Request $request, ManagementTaskTicketService $ticketService) {
        $viewer = $ticketService->resolveCurrentActor();
        $actorType = (string) $request->query('actor_type', $viewer['type']);
        $actorId = (string) $request->query('actor_id', $viewer['id']);

        return response()->json([
            'success' => true,
            'data' => $ticketService->getOperatorProfileModalPayload($actorType, $actorId, $viewer),
        ]);
    });

    Route::put('/profile', function (Request $request, ManagementTaskTicketService $ticketService) {
        $viewer = $ticketService->resolveCurrentActor();
        $data = $request->validate([
            'equipped_title_key' => 'nullable|string|max:60',
            'equipped_background_key' => 'nullable|string|max:60',
        ]);

        $ticketService->equipOperatorCosmetic(
            $viewer['type'],
            $viewer['id'],
            $data['equipped_title_key'] ?? null,
            $data['equipped_background_key'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $ticketService->getOperatorProfileModalPayload($viewer['type'], $viewer['id'], $viewer),
        ]);
    });

    // --- LISTS ---
    Route::get('/lists', function (Request $request) use ($applyEmployeeTaskScope) {
        $isEmployee = $request->user() instanceof \App\Models\Employee\Employee;
        $listsQuery = ManagementTaskList::withCount([
            'tasks as open_count' => function ($q) use ($request, $applyEmployeeTaskScope) {
                $q->where('is_completed', false)->whereNull('parent_id')->where('is_archived', false);
                $applyEmployeeTaskScope($q, $request);
            },
            'tasks as completed_count' => function ($q) use ($request, $applyEmployeeTaskScope) {
                $q->where('is_completed', true)->whereNull('parent_id')->where('is_archived', false);
                $applyEmployeeTaskScope($q, $request);
            }
        ])->where('is_archived', false);

        if ($isEmployee) {
            $listsQuery->where('is_team_visible', true);
        }

        $lists = $listsQuery
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
        return response()->json(['success' => true, 'data' => $lists]);
    });

    Route::post('/lists', function (Request $request) {
        if ($request->user() instanceof \App\Models\Employee\Employee) {
            abort(403, 'Listen können nur von der Leitung angelegt werden.');
        }
        $data = $request->validate([
            'id' => 'nullable|string|max:64',
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        if (!empty($data['id'])) {
            $existingById = ManagementTaskList::find($data['id']);
            if ($existingById) {
                $existingById->open_count = $existingById->tasks()->where('is_completed', false)->whereNull('parent_id')->where('is_archived', false)->count();
                $existingById->completed_count = $existingById->tasks()->where('is_completed', true)->whereNull('parent_id')->where('is_archived', false)->count();
                return response()->json(['success' => true, 'data' => $existingById]);
            }
        }

        $existingByName = ManagementTaskList::where('is_archived', false)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])
            ->first();
        if ($existingByName) {
            $existingByName->open_count = $existingByName->tasks()->where('is_completed', false)->whereNull('parent_id')->where('is_archived', false)->count();
            $existingByName->completed_count = $existingByName->tasks()->where('is_completed', true)->whereNull('parent_id')->where('is_archived', false)->count();
            return response()->json(['success' => true, 'data' => $existingByName]);
        }

        $maxPos = (int) ManagementTaskList::max('position');
        $list = ManagementTaskList::create([
            'id' => !empty($data['id']) ? $data['id'] : (string) Str::uuid(),
            'name' => trim($data['name']),
            'icon' => $data['icon'] ?? 'list-bullet',
            'color' => $data['color'] ?? '#C5A059',
            'position' => $maxPos + 1,
            'is_team_visible' => (bool) $request->boolean('is_team_visible', false),
            'is_archived' => false
        ]);
        $list->open_count = 0;
        $list->completed_count = 0;
        return response()->json(['success' => true, 'data' => $list]);
    });

    Route::put('/lists/{id}', function (Request $request, $id) {
        if ($request->user() instanceof \App\Models\Employee\Employee) {
            abort(403, 'Listen können nur von der Leitung bearbeitet werden.');
        }
        $list = ManagementTaskList::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'position' => 'nullable|integer',
            'is_team_visible' => 'nullable|boolean',
            'is_archived' => 'nullable|boolean',
        ]);
        $list->update(array_filter([
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'icon' => $data['icon'] ?? $list->icon,
            'color' => $data['color'] ?? $list->color,
            'position' => array_key_exists('position', $data) ? $data['position'] : $list->position,
            'is_team_visible' => array_key_exists('is_team_visible', $data) ? (bool) $data['is_team_visible'] : $list->is_team_visible,
            'is_archived' => array_key_exists('is_archived', $data) ? $data['is_archived'] : $list->is_archived,
        ], fn ($v) => $v !== null));
        return response()->json(['success' => true, 'data' => $list]);
    });

    Route::delete('/lists/{id}', function (Request $request, $id, ManagementTaskTicketService $ticketService) {
        if ($request->user() instanceof \App\Models\Employee\Employee) {
            abort(403, 'Listen können nur von der Leitung gelöscht werden.');
        }
        $list = ManagementTaskList::findOrFail($id);
        $tasks = ManagementTask::with('messages')->where('task_list_id', $list->id)->get();
        foreach ($tasks as $t) {
            $ticketService->purgeTaskAttachments($t);
            $t->delete();
        }
        $list->delete();
        return response()->json(['success' => true]);
    });

    // --- TASKS / PERSONAL TICKETS ---
    Route::get('/', function (Request $request, ManagementTaskTicketService $ticketService) use ($applyEmployeeTaskScope) {
        $ticketService->evaluateEscalations();

        $hasMessagesTable = Schema::hasTable('management_task_messages');
        $query = ManagementTask::with(['list', 'parent']);
        if ($hasMessagesTable) {
            $query->withCount('messages');
        }
        $applyEmployeeTaskScope($query, $request);

        $tasks = $query
            ->where('is_archived', false)
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($task) use ($hasMessagesTable) {
                $task->title = $task->title ?? 'Ohne Titel';
                $task->list_name = $task->list ? $task->list->name : 'Aufgaben';
                $task->parent_title = $task->parent ? $task->parent->title : null;
                $task->messages_count = $hasMessagesTable ? (int) ($task->messages_count ?? 0) : 0;
                return $task;
            });

        return response()->json(['success' => true, 'data' => $tasks]);
    });

    $createTaskHandler = function (Request $request, string $listId = 'default') {
        $data = $request->validate([
            'id' => 'nullable|string|max:64',
            'task_list_id' => 'nullable|string|max:64',
            'title' => 'required|string|max:255',
            'status' => 'nullable|in:open,waiting,in_progress,escalated,completed',
            'is_mine' => 'nullable|boolean',
            'priority' => 'nullable|in:niedrig,mittel,hoch',
            'relevant_from' => 'nullable|date',
            'due_time' => 'nullable|string|max:8',
            'duration_minutes' => 'nullable|integer|min:15|max:720',
            'ai_plan' => 'nullable|string',
            'list_name' => 'nullable|string|max:100',
            'is_completed' => 'nullable|boolean',
        ]);

        $effectiveListId = !empty($data['task_list_id']) ? (string) $data['task_list_id'] : $listId;

        $targetList = null;
        if (!in_array($effectiveListId, ['my_day', 'important', 'planned', 'all', 'default', 'my_tickets', 'open_tickets', 'escalated'], true)) {
            $targetList = ManagementTaskList::find($effectiveListId);
        }
        if (!$targetList && !empty($data['list_name'])) {
            $targetList = ManagementTaskList::where('is_archived', false)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['list_name']))])
                ->first();
        }
        if (!$targetList) {
            $targetList = ManagementTaskList::where('name', 'Aufgaben')->first()
                ?? ManagementTaskList::where('is_archived', false)->orderBy('position', 'asc')->orderBy('created_at', 'asc')->first();
            if (!$targetList) {
                $targetList = ManagementTaskList::create([
                    'id' => (string) Str::uuid(),
                    'name' => !empty($data['list_name']) ? trim($data['list_name']) : 'Aufgaben',
                    'icon' => 'home',
                    'color' => '#C5A059',
                    'position' => 1,
                    'is_archived' => false,
                ]);
            }
        }

        if (!empty($data['id'])) {
            $existingById = ManagementTask::find($data['id']);
            if ($existingById) {
                return response()->json(['success' => true, 'data' => $existingById]);
            }
        }

        $priority = $data['priority'] ?? 'niedrig';
        $relevantFrom = null;
        $dueTime = ManagementTask::normalizeDueTime($data['due_time'] ?? null);
        if (!empty($data['relevant_from'])) {
            $parsed = \Carbon\Carbon::parse($data['relevant_from']);
            $relevantFrom = $parsed->copy()->startOfDay();
            if ($dueTime === null && $parsed->format('H:i:s') !== '00:00:00') {
                $dueTime = $parsed->format('H:i');
            }
        }

        $isCompleted = (bool) ($data['is_completed'] ?? false);
        $status = !empty($data['status'])
            ? ManagementTask::normalizeStatus($data['status'])
            : ($isCompleted ? ManagementTask::STATUS_COMPLETED : ManagementTask::STATUS_OPEN);
        $isMine = array_key_exists('is_mine', $data)
            ? (bool) $data['is_mine']
            : ($effectiveListId === 'my_tickets');

        $maxPos = (int) ManagementTask::where('task_list_id', $targetList->id)->whereNull('parent_id')->max('position');
        $maxMyPos = $isMine ? ((int) ManagementTask::where('is_mine', true)->whereNull('parent_id')->max('my_position') + 1) : 0;

        $ticketService = app(ManagementTaskTicketService::class);
        $actor = $ticketService->resolveCurrentActor();
        $resolvedAssignee = ($actor['type'] ?? '') === 'employee'
            ? $actor
            : ($request->filled('assignee_key')
                ? $ticketService->resolveActorByKey((string) $request->input('assignee_key'))
                : ($status === ManagementTask::STATUS_IN_PROGRESS || $isMine ? $actor : null));

        $task = ManagementTask::create([
            'id' => !empty($data['id']) ? $data['id'] : (string) Str::uuid(),
            'task_list_id' => $targetList->id,
            'parent_id' => null,
            'title' => trim($data['title']),
            'status' => $status,
            'is_mine' => $isMine || $status === ManagementTask::STATUS_IN_PROGRESS,
            'assignee_type' => $resolvedAssignee['type'] ?? null,
            'assignee_id' => $resolvedAssignee['id'] ?? null,
            'assignee_name' => $resolvedAssignee['name'] ?? null,
            'assignee_avatar' => $resolvedAssignee['avatar'] ?? null,
            'created_by_type' => $actor['type'],
            'created_by_id' => $actor['id'],
            'priority' => $priority,
            'relevant_from' => $relevantFrom,
            'due_time' => $dueTime,
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 60),
            'ai_plan' => $data['ai_plan'] ?? null,
            'is_completed' => $status === ManagementTask::STATUS_COMPLETED,
            'is_archived' => false,
            'position' => $maxPos + 1,
            'my_position' => $maxMyPos,
            'last_activity_at' => now(),
            'claimed_at' => $status === ManagementTask::STATUS_IN_PROGRESS ? now() : null,
            'file_paths' => [],
        ]);

        return response()->json(['success' => true, 'data' => $task]);
    };

    Route::post('/', fn (Request $request) => $createTaskHandler($request, 'default'));
    Route::post('/lists/{listId}/tasks', fn (Request $request, $listId) => $createTaskHandler($request, (string) $listId));

    Route::put('/{id}', function (Request $request, $id, ManagementTaskTicketService $ticketService) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|in:open,waiting,in_progress,escalated,completed',
            'is_mine' => 'sometimes|boolean',
            'priority' => 'sometimes|in:niedrig,mittel,hoch',
            'is_completed' => 'sometimes|boolean',
            'relevant_from' => 'nullable|date',
            'due_time' => 'nullable|string|max:8',
            'duration_minutes' => 'nullable|integer|min:15|max:720',
            'ai_plan' => 'nullable|string',
            'task_list_id' => 'sometimes|string|exists:management_task_lists,id',
            'position' => 'sometimes|integer',
            'my_position' => 'sometimes|integer',
            'is_archived' => 'sometimes|boolean',
        ]);

        if ($task->is_escalated && !($data['is_completed'] ?? false) && ($data['status'] ?? null) !== ManagementTask::STATUS_COMPLETED) {
            if (array_key_exists('status', $data) && $data['status'] !== $task->status) {
                return response()->json([
                    'success' => false,
                    'requires_escalation_resolution' => true,
                    'message' => 'Eskaliertes Ticket erfordert neues Fälligkeitsdatum und ein kurzes Status-Update im Chat.',
                ], 422);
            }
        }

        if (array_key_exists('status', $data)) {
            $data['status'] = ManagementTask::normalizeStatus($data['status']);
        }

        if (array_key_exists('relevant_from', $data) || $request->has('relevant_from')) {
            $relevantVal = $request->input('relevant_from');
            if (empty($relevantVal)) {
                $data['relevant_from'] = null;
                if (!$request->has('due_time')) {
                    $data['due_time'] = null;
                }
            } else {
                $parsed = \Carbon\Carbon::parse($relevantVal);
                $data['relevant_from'] = $parsed->copy()->startOfDay();
                if (!$request->has('due_time') && $parsed->format('H:i:s') !== '00:00:00') {
                    $data['due_time'] = $parsed->format('H:i');
                }
            }
        }
        if ($request->has('due_time')) {
            $data['due_time'] = ManagementTask::normalizeDueTime($request->input('due_time'));
        }
        if ($request->has('ai_plan')) {
            $data['ai_plan'] = $request->input('ai_plan');
        }

        $task->update($data);
        return response()->json(['success' => true, 'data' => $task->fresh()]);
    });

    Route::put('/{id}/mine', function (Request $request, $id, ManagementTaskTicketService $ticketService) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id);
        $data = $request->validate([
            'is_mine' => 'required|boolean',
            'position' => 'nullable|integer|min:1',
        ]);
        $updated = $ticketService->moveToMine($task, (bool) $data['is_mine'], $data['position'] ?? null);
        return response()->json(['success' => true, 'data' => $updated]);
    });

    Route::put('/{id}/assign', function (Request $request, $id, ManagementTaskTicketService $ticketService) use ($findAuthorizedTask) {
        if ($request->user() instanceof \App\Models\Employee\Employee) {
            abort(403, 'Aufgaben-Zuweisung an Dritte ist der Leitung vorbehalten.');
        }
        $task = $findAuthorizedTask($request, $id);
        $data = $request->validate([
            'assignee_key' => 'nullable|string|max:120',
        ]);
        $updated = $ticketService->assignTicket($task, $data['assignee_key'] ?? null);
        return response()->json(['success' => true, 'data' => $updated]);
    });

    Route::put('/{id}/status', function (Request $request, $id, ManagementTaskTicketService $ticketService) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id);
        $data = $request->validate([
            'status' => 'required|in:open,waiting,in_progress,completed',
        ]);

        try {
            $updated = $ticketService->changeStatus($task, $data['status']);
            return response()->json(['success' => true, 'data' => $updated]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'requires_escalation_resolution' => true,
                'message' => $e->getMessage(),
            ], 422);
        }
    });

    Route::put('/{id}/toggle', function (Request $request, $id) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id);
        $nextCompleted = !$task->is_completed;
        $task->update([
            'is_completed' => $nextCompleted,
            'status' => $nextCompleted ? ManagementTask::STATUS_COMPLETED : ManagementTask::STATUS_OPEN,
        ]);
        return response()->json(['success' => true, 'data' => $task->fresh()]);
    });

    // --- TICKET CHAT, 5-MIN EDIT & ESCALATION RESOLUTION ---
    Route::get('/{id}/messages', function (Request $request, $id) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id, ['messages', 'subtasks', 'list']);
        $messages = $task->messages->map(fn ($msg) => ManagementTaskTicketService::serializeMessage($msg))->values();

        return response()->json([
            'success' => true,
            'task' => $task,
            'data' => $messages,
        ]);
    });

    Route::post('/{id}/messages', function (Request $request, $id, ManagementTaskTicketService $ticketService) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id);
        $content = $request->input('content', $request->input('message', ''));
        $rawFiles = $request->file('files', $request->file('file', []));
        $files = is_array($rawFiles) ? $rawFiles : ($rawFiles ? [$rawFiles] : []);

        try {
            $msg = $ticketService->postMessage($task, $content, $files);
            return response()->json([
                'success' => true,
                'data' => ManagementTaskTicketService::serializeMessage($msg),
                'task' => $task->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    });

    $editMessageHandler = function (Request $request, $id, $messageId, ManagementTaskTicketService $ticketService) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id);
        $msg = ManagementTaskMessage::where('management_task_id', $task->id)->findOrFail($messageId);

        $content = $request->input('content', $request->input('message', $msg->content));
        $keepPaths = $request->has('keep_paths') ? (array) $request->input('keep_paths', []) : null;
        $rawFiles = $request->file('files', $request->file('file', []));
        $files = is_array($rawFiles) ? $rawFiles : ($rawFiles ? [$rawFiles] : []);

        try {
            $updated = $ticketService->editMessage($msg, $content, $files, $keepPaths);
            return response()->json([
                'success' => true,
                'data' => ManagementTaskTicketService::serializeMessage($updated),
                'task' => $task->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    };

    Route::put('/{id}/messages/{messageId}', $editMessageHandler);
    Route::post('/{id}/messages/{messageId}', $editMessageHandler);

    Route::post('/{id}/resolve-escalation', function (Request $request, $id, ManagementTaskTicketService $ticketService) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id);
        $dueDate = (string) $request->input('relevant_from', $request->input('due_date', ''));
        $dueTime = $request->input('due_time');
        $duration = $request->filled('duration_minutes') ? (int) $request->input('duration_minutes') : null;
        $targetStatus = (string) $request->input('status', $task->status ?: ManagementTask::STATUS_IN_PROGRESS);
        $content = (string) $request->input('content', $request->input('message', ''));
        $rawFiles = $request->file('files', $request->file('file', []));
        $files = is_array($rawFiles) ? $rawFiles : ($rawFiles ? [$rawFiles] : []);

        try {
            $updated = $ticketService->resolveEscalation(
                task: $task,
                newDueDate: $dueDate,
                newDueTime: is_string($dueTime) ? $dueTime : null,
                chatStatusText: $content,
                targetStatus: $targetStatus,
                durationMinutes: $duration,
                files: $files
            );
            return response()->json(['success' => true, 'data' => $updated]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    });

    Route::get('/{id}/attachment', function (Request $request, $id) use ($findAuthorizedTask) {
        $findAuthorizedTask($request, $id);
        $path = ltrim(str_replace('\\', '/', (string) $request->query('path', '')), '/');
        if (
            $path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || !str_starts_with($path, 'leitung/tasks/')
            || !Storage::disk('local')->exists($path)
        ) {
            abort(404);
        }
        $fullPath = Storage::disk('local')->path($path);
        if (is_link($fullPath) || !is_file($fullPath)) {
            abort(404);
        }
        $downloadName = trim((string) $request->query('name', basename($path)));
        $safeName = str_replace(['"', "\r", "\n", "\0", '/', '\\'], '_', $downloadName) ?: basename($path);
        return response()->download($fullPath, $safeName);
    });

    Route::post('/{id}/subtask', function (Request $request, $id) use ($findAuthorizedTask) {
        $data = $request->validate(['title' => 'required|string']);
        $task = $findAuthorizedTask($request, $id);
        $maxPos = (int) ManagementTask::where('parent_id', $task->id)->max('position');

        $subtask = ManagementTask::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $task->task_list_id,
            'parent_id' => $task->id,
            'title' => trim($data['title']),
            'status' => ManagementTask::STATUS_OPEN,
            'priority' => 'niedrig',
            'is_completed' => false,
            'is_archived' => false,
            'position' => $maxPos + 1,
            'file_paths' => [],
        ]);
        $task->update(['last_activity_at' => now()]);
        return response()->json(['success' => true, 'data' => $subtask]);
    });

    Route::delete('/{id}', function (Request $request, $id, ManagementTaskTicketService $ticketService) {
        if ($request->user() instanceof \App\Models\Employee\Employee) {
            abort(403, 'Aufgaben können nur von der Leitung endgültig gelöscht werden.');
        }
        $task = ManagementTask::with('messages')->findOrFail($id);
        $subtasks = ManagementTask::with('messages')->where('parent_id', $task->id)->get();
        foreach ($subtasks as $sub) {
            $ticketService->purgeTaskAttachments($sub);
            $sub->delete();
        }
        $ticketService->purgeTaskAttachments($task);
        $task->delete();
        return response()->json(['success' => true]);
    });

    Route::post('/{id}/files', function (Request $request, $id, ManagementTaskTicketService $ticketService) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id);
        $request->validate(['file' => 'required']);
        $files = is_array($request->file('file')) ? $request->file('file') : [$request->file('file')];
        $stored = $ticketService->storeUploadedFiles($task, $files);
        $existing = $task->file_paths ?? [];
        foreach ($stored as $att) {
            if (!empty($att['path']) && !in_array($att['path'], $existing, true)) {
                $existing[] = $att['path'];
            }
        }
        $task->update([
            'file_paths' => array_values($existing),
            'last_activity_at' => now(),
        ]);
        return response()->json(['success' => true, 'data' => $task->fresh()]);
    });

    Route::delete('/{id}/files', function (Request $request, $id) use ($findAuthorizedTask) {
        $task = $findAuthorizedTask($request, $id);
        $path = $request->input('path');
        if (!$path) {
            return response()->json(['success' => false, 'message' => 'Path missing'], 400);
        }

        $existing = $task->file_paths ?? [];
        if (($key = array_search($path, $existing)) !== false) {
            unset($existing[$key]);
            $pathStr = (string) $path;
            if ($pathStr !== '' && !str_contains($pathStr, '..') && !str_contains($pathStr, "\0") && str_starts_with(ltrim($pathStr, '/'), 'leitung/tasks/') && Storage::disk('local')->exists($pathStr)) {
                Storage::disk('local')->delete($pathStr);
            }
            $task->update(['file_paths' => array_values($existing)]);
        }

        return response()->json(['success' => true, 'data' => $task]);
    });
});

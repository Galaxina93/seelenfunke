<?php

namespace App\Services\AI\Functions;

use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use Illuminate\Support\Str;

trait AiTaskFuncs
{
    /**
     * Intelligente Erkennung und Auflösung einer Aufgabenliste (per UUID, exaktem Namen, Slug oder Teilname).
     */
    public static function resolveTaskListId(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $input = trim($input);

        // 1. Direkte UUID
        if (Str::isUuid($input)) {
            $list = ManagementTaskList::find($input);
            if ($list) {
                return $list->id;
            }
        }

        // 2. Exakter Namensabgleich (case-insensitive)
        $list = ManagementTaskList::whereRaw('LOWER(name) = ?', [mb_strtolower($input)])->first();
        if ($list) {
            return $list->id;
        }

        // 3. Normalisierter Abgleich (Bindestriche, Unterstriche, Leerzeichen ignorieren)
        // z.B. "phase_1_produkte" oder "Phase 1 Produkte" -> "phase1produkte"
        $normalize = function ($str) {
            return preg_replace('/[^a-z0-9]/', '', mb_strtolower($str));
        };
        $normalizedInput = $normalize($input);

        if (!empty($normalizedInput)) {
            $allLists = ManagementTaskList::all();
            foreach ($allLists as $l) {
                if ($normalize($l->name) === $normalizedInput) {
                    return $l->id;
                }
            }

            // 4. Substring-Matching im normalisierten Namen
            foreach ($allLists as $l) {
                $normListName = $normalize($l->name);
                if (!empty($normListName) && (str_contains($normListName, $normalizedInput) || str_contains($normalizedInput, $normListName))) {
                    return $l->id;
                }
            }
        }

        // 5. Standard SQL LIKE Abgleich
        $escapedInput = addcslashes($input, '%_\\');
        $list = ManagementTaskList::where('name', 'LIKE', '%' . $escapedInput . '%')->first();
        if ($list) {
            return $list->id;
        }

        return null;
    }

    public static function getAiTaskFuncsSchema(): array
    {
        return [
            [
                'name' => 'task_get_all',
                'description' => 'Ruft aktuell offene System-Aufgaben ab (inklusive Listenname, Teilschritte und Dateianhänge). Filterbar nach Listenname oder Suchbegriff. Stichworte: Zeig mir meine Todos, Was muss ich tun, offene Aufgaben, Taskliste, TODO Liste. ACHTUNG: Dies ist NICHT für die Einkaufsliste. Nutze für Einkäufe `system_switch_agent` zu Einkaufi.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'list_name' => [
                            'type' => 'string',
                            'description' => 'Optional: Filtert Aufgaben nach Listenname (z.B. "PHASE 1 - PRODUKTE", "Marketing", "Existenzgründung").'
                        ],
                        'task_list_id' => [
                            'type' => 'string',
                            'description' => 'Optional: Exakte ID oder Name der Liste.'
                        ],
                        'search' => [
                            'type' => 'string',
                            'description' => 'Optional: Suchbegriff im Titel der Aufgaben.'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetTasks']
            ],
            [
                'name' => 'task_get_lists',
                'description' => 'Ruft alle existierenden Aufgabenlisten (To-Do Listen Kategorien) mit Namen und IDs ab. Stichworte: Welche Listen gibt es, Meine To-Do Listen, Tasklisten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTaskLists']
            ],
            [
                'name' => 'task_create',
                'description' => 'Erstellt eine neue Aufgabe (To-Do). Kann direkt einer Liste per Name (z.B. "Phase 1 - Produkte", "Marketing") oder ID zugewiesen werden. Stichworte: Schreib auf die Todo-Liste, Erinnere mich daran das zu tun, Neue Aufgabe anlegen, Todo erstellen. ACHTUNG: Dies ist NICHT für die Einkaufsliste.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Die genaue Aufgabe / Titel.'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Priorität der Aufgabe (Standard: mittel)',
                            'enum' => ['hoch', 'mittel', 'niedrig']
                        ],
                        'list_name' => [
                            'type' => 'string',
                            'description' => 'Name oder Teilname der Ziel-Liste (z.B. "PHASE 1 - PRODUKTE", "Marketing", "Existenzgründung"). Kann anstelle oder zusammen mit task_list_id übergeben werden.'
                        ],
                        'task_list_id' => [
                            'type' => 'string',
                            'description' => 'Optionale ID, Name oder Slug der Liste (z.B. "phase_1_produkte").'
                        ],
                        'relevant_from' => [
                            'type' => 'string',
                            'description' => 'Optionales Planungs- oder Fälligkeitsdatum (z.B. "2026-10-25" oder "heute", "morgen").'
                        ],
                        'subtasks' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Optionale Teilschritte / Unterpunkte, die direkt mit angelegt werden sollen.'
                        ]
                    ],
                    'required' => ['title']
                ],
                'callable' => [self::class, 'executeCreateTask']
            ],
            [
                'name' => 'task_add_subtask',
                'description' => 'Fügt einer bestehenden Aufgabe einen Teilschritt (Unterschritt) hinzu. Stichworte: Schritt hinzufügen, Teilschritt anlegen, Unterpunkt zu Aufgabe.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'Die ID oder der genaue Titel der übergeordneten Hauptaufgabe.'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Titel des neuen Teilschritts.'
                        ]
                    ],
                    'required' => ['task_id', 'title']
                ],
                'callable' => [self::class, 'executeAddSubtask']
            ],
            [
                'name' => 'task_update',
                'description' => 'Bearbeitet den Titel, die Priorität, das Fälligkeitsdatum oder die Liste einer bestehenden Aufgabe. Stichworte: Ändere die Aufgabe, setze Priorität auf hoch, verschiebe Todo.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'Die ID oder der Titel der zu bearbeitenden Aufgabe.'
                        ],
                        'new_title' => [
                            'type' => 'string',
                            'description' => 'Neuer Titel der Aufgabe (optional).'
                        ],
                        'new_priority' => [
                            'type' => 'string',
                            'description' => 'Neue Priorität (optional: hoch, mittel, niedrig).'
                        ],
                        'new_list_name' => [
                            'type' => 'string',
                            'description' => 'Name der neuen Ziel-Liste (optional, z.B. "Marketing", "PHASE 1 - PRODUKTE").'
                        ],
                        'new_list_id' => [
                            'type' => 'string',
                            'description' => 'Neue Listen-ID oder Name (optional).'
                        ],
                        'new_relevant_from' => [
                            'type' => 'string',
                            'description' => 'Neues Planungs- oder Fälligkeitsdatum (z.B. YYYY-MM-DD).'
                        ]
                    ],
                    'required' => ['task_id']
                ],
                'callable' => [self::class, 'executeUpdateTask']
            ],
            [
                'name' => 'task_complete',
                'description' => 'Markiert eine offene Aufgabe als erledigt. Stichworte: Hake Aufgabe ab, Todo erledigt, Task abschließen, ToDo abhaken.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'Die ID oder der genaue Titel der Aufgabe.'
                        ]
                    ],
                    'required' => ['task_id']
                ],
                'callable' => [self::class, 'executeCompleteTask']
            ],
            [
                'name' => 'task_delete',
                'description' => 'Löscht eine oder mehrere Aufgaben vollständig. Kann für Listen von IDs, Titeln oder für ganze Listen (mit Ausnahmen) genutzt werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Eine Liste von exakten Aufgaben-IDs.'
                        ],
                        'task_titles' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Eine Liste von exakten oder teilweisen Aufgabentiteln.'
                        ],
                        'list_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Wenn gesetzt, werden ALLE Aufgaben in diesen Listen gelöscht.'
                        ],
                        'exclude_titles' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Aufgaben, die NICHT gelöscht werden sollen (nützlich in Kombination mit list_names).'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeDeleteTask']
            ],
            [
                'name' => 'task_list_add',
                'description' => 'Erstellt eine neue Aufgabenliste (Kategorie).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name der neuen Liste.'
                        ],
                        'color' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Hex-Farbe der Liste (z.B. #10B981).'
                        ],
                        'icon' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Nur GÜLTIGE Heroicons: clipboard-document-list, check-circle, document-text, star, heart, bookmark, home, briefcase, user. Standard: clipboard-document-list.'
                        ]
                    ],
                    'required' => ['name']
                ],
                'callable' => [self::class, 'executeAddTaskList']
            ],
            [
                'name' => 'task_list_update',
                'description' => 'Aktualisiert eine bestehende Aufgabenliste (Name, Farbe, Icon).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'old_name' => [
                            'type' => 'string',
                            'description' => 'Der aktuelle Name der Liste.'
                        ],
                        'new_name' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Der neue Name der Liste.'
                        ],
                        'new_color' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Die neue Hex-Farbe.'
                        ],
                        'new_icon' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Das neue Heroicon.'
                        ]
                    ],
                    'required' => ['old_name']
                ],
                'callable' => [self::class, 'executeUpdateTaskList']
            ],
            [
                'name' => 'task_list_delete',
                'description' => 'Löscht eine oder mehrere Aufgabenlisten. Kann dynamisch gesteuert werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'list_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Die Namen der zu löschenden Listen.'
                        ],
                        'delete_all' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, werden ALLE Listen gelöscht (kann mit exclude_names kombiniert werden).'
                        ],
                        'exclude_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Listen, die NICHT gelöscht werden sollen.'
                        ],
                        'delete_tasks' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, werden alle Aufgaben in diesen Listen gelöscht. Sonst fallen sie auf "Ohne Liste" zurück.'
                        ],
                        'move_tasks_to_list' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Name der Zielliste. Aufgaben werden dorthin verschoben, bevor die Ursprungsliste gelöscht wird.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeDeleteTaskList']
            ],
            [
                'name' => 'task_read_file',
                'description' => 'Liest den Inhalt einer angehängten Datei zu einer Aufgabe (z.B. PDF, TXT, CSV, JSON, XML, MD) oder analysiert ein Bild (PNG, JPG, WEBP, GIF). Erfordert den exakten Pfad der Datei (path), den du durch task_get_all erhältst.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [
                            'type' => 'string',
                            'description' => 'Der exakte Pfad der Datei aus den Task-Details (z.B. "leitung/tasks/attachments/filename.pdf").'
                        ]
                    ],
                    'required' => ['file_path']
                ],
                'callable' => [self::class, 'executeReadFile']
            ]
        ];
    }

    public static function executeGetTasks(array $args)
    {
        try {
            $query = ManagementTask::where('is_completed', false)
                ->where('is_archived', false)
                ->whereNull('parent_id')
                ->with(['list', 'subtasks']);

            $listFilter = $args['task_list_id'] ?? $args['list_name'] ?? null;
            if (!empty($listFilter)) {
                $resolvedListId = self::resolveTaskListId($listFilter);
                if ($resolvedListId) {
                    $query->where('task_list_id', $resolvedListId);
                }
            }

            if (!empty($args['search'])) {
                $query->where('title', 'like', '%' . $args['search'] . '%');
            }

            $tasks = $query->orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
                ->orderBy('created_at', 'desc')
                ->limit(25)
                ->get();

            $formattedTasks = $tasks->map(function ($task) {
                $attachments = [];
                if (!empty($task->file_paths)) {
                    foreach ($task->file_paths as $path) {
                        $attachments[] = [
                            'filename' => basename($path),
                            'path' => $path
                        ];
                    }
                }

                $subtasks = $task->subtasks->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'title' => $sub->title,
                        'is_completed' => (bool) $sub->is_completed,
                    ];
                })->toArray();

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'priority' => $task->priority,
                    'created_at' => $task->created_at?->toDateTimeString(),
                    'relevant_from' => $task->relevant_from?->format('Y-m-d'),
                    'task_list_id' => $task->task_list_id,
                    'list_name' => $task->list?->name ?? 'Ohne Liste',
                    'subtasks' => $subtasks,
                    'has_attachments' => !empty($attachments),
                    'attachments' => $attachments
                ];
            });

            return [
                'status' => 'success',
                'open_tasks_count' => $formattedTasks->count(),
                'tasks' => $formattedTasks->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetTaskLists(array $args)
    {
        try {
            $lists = ManagementTaskList::where('is_archived', false)
                ->withCount(['tasks as open_tasks_count' => function ($q) {
                    $q->where('is_completed', false)->whereNull('parent_id')->where('is_archived', false);
                }])
                ->get(['id', 'name', 'color', 'icon']);

            return [
                'status' => 'success',
                'lists_count' => $lists->count(),
                'lists' => $lists->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeCreateTask(array $args)
    {
        try {
            if (empty($args['title'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Titel für die Aufgabe angegeben.'];
            }

            $title = trim($args['title']);

            // 1. Ziel-Liste ermitteln
            $targetListId = null;
            $targetListName = null;
            $listQuery = $args['task_list_id'] ?? $args['list_name'] ?? $args['task_list_name'] ?? null;

            if (!empty($listQuery)) {
                $targetListId = self::resolveTaskListId($listQuery);
            }

            if (!empty($listQuery) && !$targetListId) {
                // Falls der Nutzer eine neue Liste benannt hat, die noch nicht existiert
                $newList = ManagementTaskList::firstOrCreate(
                    ['name' => trim($listQuery)],
                    ['icon' => 'clipboard-document-list', 'color' => '#C5A059']
                );
                $targetListId = $newList->id;
                $targetListName = $newList->name;
            } elseif ($targetListId) {
                $targetList = ManagementTaskList::find($targetListId);
                $targetListName = $targetList?->name;
            }

            if (!$targetListId) {
                // Fallback: Erste aktive Liste verwenden
                $defaultList = ManagementTaskList::where('is_archived', false)
                    ->orderBy('position', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($defaultList) {
                    $targetListId = $defaultList->id;
                    $targetListName = $defaultList->name;
                } else {
                    $list = ManagementTaskList::firstOrCreate(
                        ['name' => 'Allgemein'],
                        ['icon' => 'sparkles', 'color' => '#10B981']
                    );
                    $targetListId = $list->id;
                    $targetListName = $list->name;
                }
            }

            // 2. Exakter Duplikats-Check NUR innerhalb derselben Liste
            $exactExisting = ManagementTask::where('task_list_id', $targetListId)
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($title)])
                ->first();

            if ($exactExisting) {
                return [
                    'status' => 'success',
                    'message' => "Die Aufgabe '{$exactExisting->title}' existiert bereits in der Liste '{$targetListName}' und ist noch offen.",
                    'task_id' => $exactExisting->id,
                    'list_name' => $targetListName,
                    'already_existed' => true
                ];
            }

            // 3. Fälligkeitsdatum / Relevanzdatum parsen
            $relevantFrom = null;
            if (!empty($args['relevant_from'])) {
                try {
                    $relString = strtolower(trim($args['relevant_from']));
                    if ($relString === 'heute') {
                        $relevantFrom = now()->startOfDay();
                    } elseif ($relString === 'morgen') {
                        $relevantFrom = now()->addDay()->startOfDay();
                    } else {
                        $relevantFrom = \Carbon\Carbon::parse($args['relevant_from'])->startOfDay();
                    }
                } catch (\Exception $e) {
                    // Ignore parse error
                }
            }

            // 4. Priorität normalisieren
            $priority = strtolower($args['priority'] ?? 'mittel');
            if (!in_array($priority, ['hoch', 'mittel', 'niedrig'])) {
                $priority = 'mittel';
            }

            // 5. Aufgabe anlegen
            $task = ManagementTask::create([
                'title' => substr($title, 0, 255),
                'priority' => $priority,
                'is_completed' => false,
                'task_list_id' => $targetListId,
                'relevant_from' => $relevantFrom
            ]);

            // 6. Teilschritte anlegen falls übergeben
            $subtasksCreated = [];
            if (!empty($args['subtasks']) && is_array($args['subtasks'])) {
                foreach ($args['subtasks'] as $subTitle) {
                    if (!empty(trim($subTitle))) {
                        $sub = ManagementTask::create([
                            'title' => substr(trim($subTitle), 0, 255),
                            'priority' => 'niedrig',
                            'is_completed' => false,
                            'task_list_id' => $targetListId,
                            'parent_id' => $task->id
                        ]);
                        $subtasksCreated[] = $sub->title;
                    }
                }
            }

            $msg = "Die Aufgabe '{$task->title}' wurde erfolgreich in der Liste '{$targetListName}' mit Priorität '{$task->priority}' angelegt.";
            if (!empty($subtasksCreated)) {
                $msg .= " Inklusive " . count($subtasksCreated) . " Teilschritten (" . implode(', ', $subtasksCreated) . ").";
            }
            if ($relevantFrom) {
                $msg .= " Fällig/Geplant ab: " . $relevantFrom->format('d.m.Y') . ".";
            }

            return [
                'status' => 'success',
                'message' => $msg,
                'task_id' => $task->id,
                'title' => $task->title,
                'list_name' => $targetListName,
                'list_id' => $targetListId
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Aufgabe: ' . $e->getMessage()];
        }
    }

    public static function executeAddSubtask(array $args)
    {
        try {
            if (empty($args['task_id'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine Aufgaben-ID (task_id) angegeben.'];
            }
            if (empty($args['title'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Titel für den Teilschritt angegeben.'];
            }

            $parentTask = null;
            if (Str::isUuid($args['task_id'])) {
                $parentTask = ManagementTask::find($args['task_id']);
            }
            if (!$parentTask) {
                $taskIdQuery = trim((string)$args['task_id']);
                if (mb_strlen($taskIdQuery) >= 3) {
                    $parentTask = ManagementTask::whereNull('parent_id')
                        ->where('is_completed', false)
                        ->where('title', 'like', '%' . addcslashes($taskIdQuery, '%_\\') . '%')
                        ->first();
                }
            }

            if (!$parentTask) {
                return ['status' => 'error', 'message' => "Übergeordnete Aufgabe '{$args['task_id']}' nicht gefunden."];
            }

            $subtask = ManagementTask::create([
                'title' => substr(trim($args['title']), 0, 255),
                'priority' => 'niedrig',
                'is_completed' => false,
                'task_list_id' => $parentTask->task_list_id,
                'parent_id' => $parentTask->id
            ]);

            return [
                'status' => 'success',
                'message' => "Teilschritt '{$subtask->title}' wurde zu Aufgabe '{$parentTask->title}' hinzugefügt.",
                'subtask_id' => $subtask->id,
                'parent_task_id' => $parentTask->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Hinzufügen des Teilschritts: ' . $e->getMessage()];
        }
    }

    public static function executeUpdateTask(array $args)
    {
        try {
            if (empty($args['task_id'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine Aufgaben ID oder Titel angegeben.'];
            }

            $task = null;
            if (Str::isUuid($args['task_id'])) {
                $task = ManagementTask::find($args['task_id']);
            }
            if (!$task) {
                $task = ManagementTask::where('title', 'like', '%' . $args['task_id'] . '%')->first();
            }

            if (!$task) {
                return ['status' => 'error', 'message' => 'Aufgabe nicht gefunden.'];
            }

            $changed = false;
            $updates = [];

            if (!empty($args['new_title'])) {
                $task->title = substr(trim($args['new_title']), 0, 255);
                $changed = true;
                $updates[] = "Titel geändert";
            }

            if (!empty($args['new_priority'])) {
                $prio = strtolower(trim($args['new_priority']));
                if (in_array($prio, ['hoch', 'mittel', 'niedrig'])) {
                    $task->priority = $prio;
                    $changed = true;
                    $updates[] = "Priorität auf '{$prio}' gesetzt";
                }
            }

            $newListQuery = $args['new_list_id'] ?? $args['new_list_name'] ?? null;
            if (!empty($newListQuery)) {
                $resolvedListId = self::resolveTaskListId($newListQuery);
                if ($resolvedListId) {
                    $task->task_list_id = $resolvedListId;
                    $targetList = ManagementTaskList::find($resolvedListId);
                    $changed = true;
                    $updates[] = "in Liste '{$targetList?->name}' verschoben";
                }
            }

            if (isset($args['new_relevant_from'])) {
                if (empty($args['new_relevant_from'])) {
                    $task->relevant_from = null;
                    $changed = true;
                    $updates[] = "Fälligkeitsdatum entfernt";
                } else {
                    try {
                        $task->relevant_from = \Carbon\Carbon::parse($args['new_relevant_from'])->startOfDay();
                        $changed = true;
                        $updates[] = "Fälligkeitsdatum auf " . $task->relevant_from->format('d.m.Y') . " gesetzt";
                    } catch (\Exception $e) {
                        // ignore parse error
                    }
                }
            }

            if ($changed) {
                $task->save();
                return [
                    'status' => 'success',
                    'message' => "Die Aufgabe '{$task->title}' wurde erfolgreich aktualisiert (" . implode(', ', $updates) . ").",
                    'task_id' => $task->id
                ];
            }

            return ['status' => 'success', 'message' => 'Es wurden keine Änderungen vorgenommen, da keine neuen Werte übergeben wurden.'];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Bearbeiten der Aufgabe: ' . $e->getMessage()];
        }
    }

    public static function executeCompleteTask(array $args)
    {
        try {
            if (empty($args['task_id'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine Aufgaben ID oder Titel angegeben.'];
            }

            $task = null;
            if (Str::isUuid($args['task_id'])) {
                $task = ManagementTask::find($args['task_id']);
            }
            if (!$task) {
                $taskQuery = trim((string)$args['task_id']);
                if (mb_strlen($taskQuery) >= 3) {
                    $task = ManagementTask::where('is_completed', false)
                        ->where('title', 'like', '%' . addcslashes($taskQuery, '%_\\') . '%')
                        ->first();
                }
            }

            if (!$task) {
                return ['status' => 'error', 'message' => 'Aufgabe nicht gefunden oder bereits erledigt.'];
            }

            $task->is_completed = true;
            $task->save();

            $listName = $task->list?->name ?? 'Deine Liste';

            return [
                'status' => 'success',
                'message' => "Die Aufgabe '{$task->title}' (aus Liste '{$listName}') wurde als erledigt markiert.",
                'task_id' => $task->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abschließen der Aufgabe: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteTask(array $args)
    {
        try {
            $taskIds = $args['task_ids'] ?? [];
            $taskTitles = $args['task_titles'] ?? [];
            $listNames = $args['list_names'] ?? [];
            $excludeTitles = $args['exclude_titles'] ?? [];

            if (empty($taskIds) && empty($taskTitles) && empty($listNames)) {
                return ['status' => 'error', 'message' => 'Es muss entweder eine Liste von IDs, Titeln oder Listen (list_names) angegeben werden.'];
            }

            $query = ManagementTask::query();

            if (!empty($listNames)) {
                $sanitizedListNames = [];
                foreach ($listNames as $lName) {
                    $cleaned = trim((string)$lName);
                    if (mb_strlen($cleaned) >= 2) {
                        $sanitizedListNames[] = addcslashes($cleaned, '%_\\');
                    }
                }
                if (empty($sanitizedListNames)) {
                    return ['status' => 'error', 'message' => 'Die angegebenen Listennamen sind zu kurz oder ungültig.'];
                }
                $query->whereHas('list', function($q) use ($sanitizedListNames) {
                    $q->where(function($subQ) use ($sanitizedListNames) {
                        foreach ($sanitizedListNames as $lName) {
                            $subQ->orWhere('name', 'like', '%' . $lName . '%');
                        }
                    });
                });
            } else {
                $sanitizedTitles = [];
                if (!empty($taskTitles)) {
                    foreach ($taskTitles as $title) {
                        $cleaned = trim((string)$title);
                        if (mb_strlen($cleaned) >= 3) {
                            $sanitizedTitles[] = addcslashes($cleaned, '%_\\');
                        }
                    }
                }

                if (empty($taskIds) && empty($sanitizedTitles)) {
                    return ['status' => 'error', 'message' => 'Keine gültigen Aufgaben-IDs oder ausreichend lange Aufgabentitel (mind. 3 Zeichen) angegeben.'];
                }

                $query->where(function($q) use ($taskIds, $sanitizedTitles) {
                    if (!empty($taskIds)) {
                        $q->whereIn('id', $taskIds);
                    }
                    if (!empty($sanitizedTitles)) {
                        foreach ($sanitizedTitles as $title) {
                            $q->orWhere('title', 'like', '%' . $title . '%');
                        }
                    }
                });
            }

            $tasks = $query->get();

            if ($tasks->isEmpty()) {
                return ['status' => 'error', 'message' => 'Keine passenden Aufgaben gefunden.'];
            }

            $deletedCount = 0;
            $deletedTitles = [];

            foreach ($tasks as $task) {
                $isExcluded = false;
                foreach ($excludeTitles as $exTitle) {
                    if (stripos($task->title, $exTitle) !== false) {
                        $isExcluded = true;
                        break;
                    }
                }

                if (empty($listNames) && !empty($taskTitles) && empty($taskIds)) {
                    $isIncluded = false;
                    foreach ($taskTitles as $inTitle) {
                        if (stripos($task->title, $inTitle) !== false) {
                            $isIncluded = true;
                            break;
                        }
                    }
                    if (!$isIncluded) continue;
                }

                if (!$isExcluded) {
                    $deletedTitles[] = $task->title;
                    $task->delete();
                    $deletedCount++;
                }
            }

            return [
                'status' => 'success',
                'message' => "Es wurden $deletedCount Aufgaben gelöscht.",
                'deleted_tasks' => $deletedTitles
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen der Aufgaben: ' . $e->getMessage()];
        }
    }

    public static function executeAddTaskList(array $args)
    {
        try {
            if (empty($args['name'])) {
                return ['status' => 'error', 'message' => 'Der Name der Liste fehlt.'];
            }

            $icon = $args['icon'] ?? 'clipboard-document-list';
            $allowedIcons = ['clipboard-document-list', 'check-circle', 'document-text', 'star', 'heart', 'bookmark', 'home', 'briefcase', 'user'];
            if (!in_array($icon, $allowedIcons)) {
                $icon = 'clipboard-document-list';
            }

            $color = $args['color'] ?? '#3B82F6';

            $list = ManagementTaskList::firstOrCreate(
                ['name' => $args['name']],
                ['icon' => $icon, 'color' => $color]
            );

            return ['status' => 'success', 'message' => "Die Liste '{$list->name}' wurde erfolgreich angelegt.", 'list_id' => $list->id];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Anlegen der Liste: ' . $e->getMessage()];
        }
    }

    public static function executeUpdateTaskList(array $args)
    {
        try {
            if (empty($args['old_name'])) {
                return ['status' => 'error', 'message' => 'Der alte Name der Liste fehlt.'];
            }

            $list = ManagementTaskList::where('name', 'like', $args['old_name'])->first();

            if (!$list) {
                return ['status' => 'error', 'message' => "Liste '{$args['old_name']}' nicht gefunden."];
            }

            if (!empty($args['new_name'])) {
                $list->name = $args['new_name'];
            }
            if (!empty($args['new_icon'])) {
                $icon = $args['new_icon'];
                $allowedIcons = ['clipboard-document-list', 'check-circle', 'document-text', 'star', 'heart', 'bookmark', 'home', 'briefcase', 'user'];
                if (!in_array($icon, $allowedIcons)) {
                    $icon = 'clipboard-document-list';
                }
                $list->icon = $icon;
            }
            if (!empty($args['new_color'])) {
                $list->color = $args['new_color'];
            }

            $list->save();

            return ['status' => 'success', 'message' => "Die Liste wurde erfolgreich aktualisiert auf: '{$list->name}'."];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Aktualisieren der Liste: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteTaskList(array $args)
    {
        try {
            $listNames = $args['list_names'] ?? [];
            $deleteAll = $args['delete_all'] ?? false;
            $excludeNames = $args['exclude_names'] ?? [];
            $deleteTasks = $args['delete_tasks'] ?? false;
            $moveToListName = $args['move_tasks_to_list'] ?? null;

            if (empty($listNames) && !$deleteAll) {
                return ['status' => 'error', 'message' => 'Es muss entweder eine Liste von Namen (list_names) oder delete_all = true angegeben werden.'];
            }

            $query = ManagementTaskList::query();

            if (!$deleteAll) {
                $query->where(function($q) use ($listNames) {
                    foreach ($listNames as $lName) {
                        $q->orWhere('name', 'like', '%' . $lName . '%');
                    }
                });
            }

            $lists = $query->get();

            if ($lists->isEmpty()) {
                return ['status' => 'error', 'message' => 'Keine passenden Listen gefunden.'];
            }

            // Ziel-Liste verarbeiten (wenn gesetzt)
            $targetListId = null;
            if (!empty($moveToListName)) {
                $targetList = ManagementTaskList::firstOrCreate(
                    ['name' => $moveToListName],
                    ['icon' => 'clipboard-document-list', 'color' => '#3B82F6']
                );
                $targetListId = $targetList->id;
            }

            $deletedCount = 0;
            $deletedNames = [];

            foreach ($lists as $list) {
                $isExcluded = false;
                foreach ($excludeNames as $exName) {
                    if (stripos($list->name, $exName) !== false) {
                        $isExcluded = true;
                        break;
                    }
                }

                if ($targetListId && $list->id === $targetListId) {
                    $isExcluded = true;
                }

                if (!$isExcluded) {
                    $listId = $list->id;
                    $deletedNames[] = $list->name;

                    if ($targetListId) {
                        ManagementTask::where('task_list_id', $listId)->update(['task_list_id' => $targetListId]);
                    } elseif ($deleteTasks) {
                        ManagementTask::where('task_list_id', $listId)->delete();
                    } else {
                        ManagementTask::where('task_list_id', $listId)->update(['task_list_id' => null]);
                    }

                    $list->delete();
                    $deletedCount++;
                }
            }

            $msg = "Es wurden $deletedCount Listen gelöscht.";
            if ($targetListId) {
                $msg .= " Alle enthaltenen Aufgaben wurden erfolgreich nach '$moveToListName' verschoben.";
            } elseif ($deleteTasks) {
                $msg .= " Alle enthaltenen Aufgaben wurden ebenfalls gelöscht.";
            } else {
                $msg .= " Enthaltene Aufgaben fielen auf 'Ohne Liste' zurück.";
            }

            return [
                'status' => 'success',
                'message' => $msg,
                'deleted_lists' => $deletedNames
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen der Listen: ' . $e->getMessage()];
        }
    }

    public static function executeReadFile(array $args)
    {
        try {
            if (empty($args['file_path'])) {
                return ['status' => 'success', 'message' => 'Kein Dateipfad angegeben.'];
            }

            $filePath = (string) $args['file_path'];

            // Security check to avoid path traversal
            if (
                str_contains($filePath, '..')
                || str_contains($filePath, '\\')
                || str_contains($filePath, "\0")
                || !str_starts_with($filePath, 'leitung/tasks/attachments/')
            ) {
                return ['status' => 'success', 'message' => 'Zugriff verweigert: Ungültiger Pfad.'];
            }

            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
                return ['status' => 'success', 'message' => 'Datei nicht gefunden.'];
            }

            $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
            $realPath = realpath($absolutePath);
            $allowedBase = realpath(\Illuminate\Support\Facades\Storage::disk('local')->path('leitung/tasks/attachments'));
            if ($realPath === false || ($allowedBase !== false && !str_starts_with($realPath, $allowedBase))) {
                return ['status' => 'success', 'message' => 'Zugriff verweigert: Ungültiger Pfad.'];
            }
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            $content = '';
            
            if ($extension === 'pdf') {
                if (class_exists(\App\Services\AI\AIFunctionsRegistry::class) && method_exists(\App\Services\AI\AIFunctionsRegistry::class, 'extractPdfContent')) {
                    $content = \App\Services\AI\AIFunctionsRegistry::extractPdfContent($absolutePath);
                } else {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($absolutePath);
                    $content = $pdf->getText();
                }
            } elseif (in_array($extension, ['txt', 'csv', 'md', 'json', 'xml'])) {
                $content = file_get_contents($absolutePath);
            } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                $mime = match ($extension) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                };
                $content = self::analyzeImageWithGemini($absolutePath, $mime);
            } else {
                return [
                    'status' => 'success', 
                    'message' => 'Dateiformat .' . $extension . ' kann nicht ausgelesen werden. Bitte nur PDF, TXT, CSV, JSON, XML, MD oder Bilder (PNG, JPG, WEBP, GIF) anfragen.'
                ];
            }

            // Text kürzen, falls extrem lang
            if (strlen($content) > 100000) {
                $content = substr($content, 0, 100000) . "... [Text wurde abgeschnitten, da zu lang]";
            }

            return [
                'status' => 'success',
                'filename' => basename($filePath),
                'content' => $content
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Lesen der Datei: ' . $e->getMessage()
            ];
        }
    }

    private static function analyzeImageWithGemini(string $absolutePath, string $mime): string
    {
        try {
            $base64 = base64_encode(file_get_contents($absolutePath));
            $modelName = 'gemini-3.5-flash';
            
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Analysiere dieses Bild im Detail und beschreibe genau, was darauf zu sehen ist. Falls Text (OCR) enthalten ist, transkribiere ihn vollständig auf Deutsch.'
                            ],
                            [
                                'inlineData' => [
                                    'mimeType' => $mime,
                                    'data' => $base64
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                ]
            ];

            $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
            if (empty($apiKey)) {
                return 'Fehler: Kein Gemini API Key konfiguriert.';
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . $apiKey;
            $response = \Illuminate\Support\Facades\Http::timeout(60)->post($url, $payload);

            if ($response->failed()) {
                return 'API Fehler bei der Bildanalyse: ' . $response->body();
            }

            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Keine Beschreibung generiert.';

        } catch (\Exception $e) {
            return 'Fehler bei der Bildanalyse: ' . $e->getMessage();
        }
    }
}

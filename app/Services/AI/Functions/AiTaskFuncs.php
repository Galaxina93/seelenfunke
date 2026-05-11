<?php

namespace App\Services\AI\Functions;

use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;

trait AiTaskFuncs
{
    public static function getAiTaskFuncsSchema(): array
    {
        return [
            [
                'name' => 'task_get_all',
                'description' => 'Ruft alle aktuell offenen System-Aufgaben ab. Stichworte: Zeig mir meine Todos, Was muss ich tun, offene Aufgaben, Taskliste, TODO Liste. ACHTUNG: Dies ist NICHT für die Einkaufsliste. Nutze für Einkäufe `system_switch_agent` zu Einkaufi.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTasks']
            ],
            [
                'name' => 'task_get_lists',
                'description' => 'Ruft alle existierenden Aufgabenlisten (To-Do Listen Kategorien) ab. Stichworte: Welche Listen gibt es, Meine To-Do Listen, Tasklisten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTaskLists']
            ],
            [
                'name' => 'task_create',
                'description' => 'Erstellt eine neue Aufgabe (To-Do). Stichworte: Schreib auf die Todo-Liste, Erinnere mich daran das zu tun, Neue Aufgabe anlegen, Todo erstellen. ACHTUNG: Dies ist NICHT für die Einkaufsliste. Nutze für Einkäufe `system_switch_agent` zu Einkaufi.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Die genaue, kurze Aufgabe.'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Priorität der Aufgabe',
                            'enum' => ['hoch', 'mittel', 'niedrig']
                        ],
                        'task_list_id' => [
                            'type' => 'string',
                            'description' => 'Optionale ID einer Liste (TaskList), in die die Aufgabe soll. Nutze davor task_get_lists um IDs zu finden.'
                        ]
                    ],
                    'required' => ['title', 'priority']
                ],
                'callable' => [self::class, 'executeCreateTask']
            ],
            [
                'name' => 'task_update',
                'description' => 'Bearbeitet den Titel, die Priorität oder die Liste einer bestehenden Aufgabe. Stichworte: Ändere die Aufgabe, setze Priorität auf hoch, verschiebe Todo.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'Die ID der zu bearbeitenden Aufgabe.'
                        ],
                        'new_title' => [
                            'type' => 'string',
                            'description' => 'Neuer Titel der Aufgabe (optional, leer lassen falls keine Änderung).'
                        ],
                        'new_priority' => [
                            'type' => 'string',
                            'description' => 'Neue Priorität der Aufgabe (optional, hoch, mittel, niedrig).'
                        ],
                        'new_list_id' => [
                            'type' => 'string',
                            'description' => 'Neue Listen-ID, in die die Aufgabe verschoben werden soll (optional).'
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
                            'description' => 'Die ID der Aufgabe.'
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
            ]
        ];
    }

    public static function executeGetTasks(array $args)
    {
        try {
            $tasks = ManagementTask::where('is_completed', false)
                ->where('is_archived', false)
                ->whereNull('parent_id')
                ->orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get(['id', 'title', 'priority', 'created_at', 'task_list_id']);

            return [
                'status' => 'success',
                'open_tasks_count' => $tasks->count(),
                'tasks' => $tasks->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetTaskLists(array $args)
    {
        try {
            $lists = ManagementTaskList::where('is_archived', false)->get(['id', 'name', 'color', 'icon']);
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

            $shortTitle = substr($args['title'], 0, 20);
            $existing = ManagementTask::where('is_completed', false)
                ->where('is_archived', false)
                ->where('title', 'LIKE', '%' . $shortTitle . '%')
                ->first();

            if ($existing) {
                return [
                    'status' => 'success',
                    'message' => 'Oh ich sehe gerade, das steht schon auf unserer Aufgaben Liste',
                    'task_id' => $existing->id
                ];
            }

            $listId = null;
            if (!empty($args['task_list_id'])) {
                $checkList = ManagementTaskList::find($args['task_list_id']);
                if ($checkList) {
                    $listId = $checkList->id;
                }
            }

            if (!$listId) {
                $list = ManagementTaskList::firstOrCreate(
                    ['name' => 'Funkiras Empfehlungen'],
                    ['icon' => 'sparkles', 'color' => '#10B981']
                );
                $listId = $list->id;
            }

            $task = ManagementTask::create([
                'title' => substr($args['title'], 0, 255),
                'priority' => $args['priority'] ?? 'mittel',
                'is_completed' => false,
                'task_list_id' => $listId
            ]);

            return [
                'status' => 'success',
                'message' => "Die Aufgabe '{$task->title}' wurde erfolgreich aufgenommen.",
                'task_id' => $task->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Aufgabe: ' . $e->getMessage()];
        }
    }

    public static function executeUpdateTask(array $args)
    {
        try {
            if (empty($args['task_id'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine Aufgaben ID angegeben.'];
            }

            $task = ManagementTask::find($args['task_id']);
            if (!$task) {
                return ['status' => 'error', 'message' => 'Aufgabe nicht gefunden.'];
            }

            $changed = false;

            if (!empty($args['new_title'])) {
                $task->title = substr($args['new_title'], 0, 255);
                $changed = true;
            }

            if (!empty($args['new_priority'])) {
                $task->priority = strtolower($args['new_priority']);
                $changed = true;
            }

            if (!empty($args['new_list_id'])) {
                $list = ManagementTaskList::find($args['new_list_id']);
                if ($list) {
                    $task->task_list_id = $list->id;
                    $changed = true;
                }
            }

            if ($changed) {
                $task->save();
                return ['status' => 'success', 'message' => "Die Aufgabe '{$task->title}' wurde erfolgreich aktualisiert."];
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
                return ['status' => 'error', 'message' => 'Es wurde keine Aufgaben ID angegeben.'];
            }

            $task = ManagementTask::find($args['task_id']);
            if (!$task) {
                return ['status' => 'error', 'message' => 'Aufgabe nicht gefunden.'];
            }

            $task->is_completed = true;
            $task->save();

            return [
                'status' => 'success',
                'message' => "Die Aufgabe '{$task->title}' wurde als erledigt markiert."
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
                $query->whereHas('taskList', function($q) use ($listNames) {
                    $q->where(function($subQ) use ($listNames) {
                        foreach ($listNames as $lName) {
                            $subQ->orWhere('name', 'like', '%' . $lName . '%');
                        }
                    });
                });
            } else {
                $query->where(function($q) use ($taskIds, $taskTitles) {
                    if (!empty($taskIds)) {
                        $q->whereIn('id', $taskIds);
                    }
                    if (!empty($taskTitles)) {
                        foreach ($taskTitles as $title) {
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
}

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
                'description' => 'Ruft alle aktuell offenen System-Aufgaben ab. Stichworte: Zeig mir meine Todos, Was muss ich tun, offene Aufgaben, Taskliste, TODO Liste.',
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
                'description' => 'Erstellt eine neue Aufgabe (To-Do). Stichworte: Schreib auf die Todo-Liste, Erinnere mich daran das zu tun, Neue Aufgabe anlegen, Todo erstellen.',
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
                'description' => 'Löscht eine offene Aufgabe vollständig. Stichworte: Lösche das Todo, entferne die Aufgabe, Task löschen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_title' => [
                            'type' => 'string',
                            'description' => 'Der ungefährer Name der Aufgabe.'
                        ]
                    ],
                    'required' => ['task_title']
                ],
                'callable' => [self::class, 'executeDeleteTask']
            ]
        ];
    }

    public static function executeGetTasks(array $args)
    {
        try {
            $tasks = ManagementTask::where('is_completed', false)
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
            $lists = ManagementTaskList::all(['id', 'name', 'color', 'icon']);
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
            if (empty($args['task_title'])) {
                return ['status' => 'error', 'message' => 'Kein Aufgaben-Titel angegeben.'];
            }

            $term = $args['task_title'];

            $task = ManagementTask::where('is_completed', false)
                ->where('title', 'LIKE', '%' . $term . '%')
                ->first();

            if (!$task) {
                $firstWord = explode(' ', $term)[0];
                if(strlen($firstWord) > 3) {
                    $task = ManagementTask::where('is_completed', false)
                        ->where('title', 'LIKE', '%' . $firstWord . '%')
                        ->first();
                }

                if(!$task) {
                    return ['status' => 'error', 'message' => "Aufgabe '$term' wurde nicht gefunden."];
                }
            }

            $title = $task->title;
            $task->delete();

            return [
                'status' => 'success',
                'message' => "Die Aufgabe '$title' wurde gelöscht."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen der Aufgabe: ' . $e->getMessage()];
        }
    }
}

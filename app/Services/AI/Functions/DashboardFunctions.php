<?php

namespace App\Services\AI\Functions;

use App\Models\Todo;
use App\Models\TodoList;
use App\Models\CalendarEvent;
use App\Models\Funki\FunkiDayRoutine;

trait DashboardFunctions
{
    public static function getDashboardFunctionsSchema(): array
    {
        return [
            [
                'name' => 'get_system_health',
                'description' => 'Returns the overall system status, active sessions, and health metrics. Useful to determine if the system is running smoothly.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemHealth']
            ],
            [
                'name' => 'fix_system_errors',
                'description' => 'Behebt Systemwarnungen und Fehler (Clears Caches, restarts Queues, resets Configs). FÜHRE DIESES TOOL ZWINGEND AUS, wenn get_system_health meldet, dass das System Fehler hat! BEVOR du der Benutzerin antwortest!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeFixSystemErrors']
            ],
            [
                'name' => 'get_system_logs',
                'description' => 'Liest die letzten Fehler und Warnungen (Logs) aus dem System aus. Nutze dies, um mir die genauen Fehler aufzulisten, NACHDEM du fix_system_errors ausgeführt hast und es IMMER NOCH Fehler gibt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemLogs']
            ],
            [
                'name' => 'get_todos',
                'description' => 'Returns all currently open ToDos from the shop system. Use this to find out what Herrin Alina needs to work on next.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTodos']
            ],
            [
                'name' => 'create_todo',
                'description' => 'Creates a new ToDo task based on your recommendations. Keep the title short and actionable. ALWAYS use this when giving Alina a specific task to do.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Die genaue, kurze Aufgabe (max 255 Zeichen).'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Priorität der Aufgabe',
                            'enum' => ['hoch', 'mittel', 'niedrig']
                        ]
                    ],
                    'required' => ['title', 'priority']
                ],
                'callable' => [self::class, 'executeCreateTodo']
            ],
            [
                'name' => 'complete_todo',
                'description' => 'Marks an open ToDo as completed. Use this when Herrin Alina says she has finished a specific task.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'todo_id' => [
                            'type' => 'string',
                            'description' => 'Die ID des Todos, das abgeschlossen wurde. (Erhältst du durch get_todos)'
                        ]
                    ],
                    'required' => ['todo_id']
                ],
                'callable' => [self::class, 'executeCompleteTodo']
            ],
            [
                'name' => 'delete_todo',
                'description' => 'Deletes an open ToDo task completely. Use this when Herrin Alina asks to delete, cancel, or remove a task.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'todo_title' => [
                            'type' => 'string',
                            'description' => 'Der ungefährer Name der Aufgabe, die gelöscht werden soll (z.B. "Finnum anrufen").'
                        ]
                    ],
                    'required' => ['todo_title']
                ],
                'callable' => [self::class, 'executeDeleteTodo']
            ],
            [
                'name' => 'get_calendar_events',
                'description' => 'Returns upcoming calendar events and meetings. Use the `limit` parameter to fetch "the exact next" appointment (limit=1).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Optional. Wie viele Termine sollen gezeigt werden? Falls "nächster Termin" gefragt ist, sende 1.'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetCalendarEvents']
            ],
            [
                'name' => 'get_day_routines',
                'description' => 'Returns the active daily routines of Herrin Alina. Use this to check if she is following her structured day.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetDayRoutines']
            ],
            [
                'name' => 'get_current_mission',
                'description' => 'Returns the ultimate next command, current day routine, top priorities, and recommendations for Herrin Alina. ONLY use this when asked "What should I do now?", "What is next?", or similar general status questions.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetCurrentMission']
            ],
        ];
    }

    public static function executeGetCurrentMission(array $args)
    {
        try {
            $botService = app(\App\Services\FunkiBotService::class);
            $missionData = $botService->getUltimateCommand();
            
            return [
                'status' => 'success',
                'mission' => $missionData
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to resolve mission: ' . $e->getMessage()
            ];
        }
    }

    public static function executeGetSystemHealth(array $args)
    {
        try {
            $analytics = new \App\Livewire\Global\Widgets\FunkiAnalytics();
            $analytics->checkSystemHealth();
            $isHealthy = $analytics->isSystemHealthy();
            
            $analytics->dateStart = now()->startOfMonth()->format('Y-m-d');
            $analytics->dateEnd = now()->endOfMonth()->format('Y-m-d');
            $analytics->filterType = 'all';
            
            $service = app(\App\Services\FunkiAnalyticsService::class);
            $analytics->loadStats($service);
            $stats = $analytics->stats;

            return [
                'status' => 'success',
                'is_healthy' => $isHealthy,
                'active_sessions' => $stats['summary']['active_sessions'] ?? 0,
                'avg_profit' => $stats['summary']['avg_profit'] ?? 0,
                'total_orders' => $stats['summary']['total_orders'] ?? 0,
                'message' => $isHealthy ? 'Das System läuft einwandfrei.' : 'Es gibt Systemwarnungen.'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Konnte Systemstatus nicht abrufen: ' . $e->getMessage()
            ];
        }
    }

    public static function executeFixSystemErrors(array $args)
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('queue:restart');
            
            if (class_exists(\App\Models\Funki\FunkiLog::class)) {
                \App\Models\Funki\FunkiLog::create([
                    'title' => 'System Healing durch Funkira',
                    'message' => 'Caches, Configs und Views wurden geleert. Queue-Worker Restart angefragt.',
                    'status' => 'success',
                    'type' => 'ai',
                    'started_at' => now(),
                    'finished_at' => now(),
                    'action_id' => 'system_heal_ai_' . time()
                ]);
            }

            return [
                'status' => 'success',
                'message' => 'Das System-Healing wurde durchgeführt. Caches sind geleert, Configs resettet, Queue wird neu gestartet.'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Healing Prozess fehlgeschlagen: ' . $e->getMessage()
            ];
        }
    }

    public static function executeGetSystemLogs(array $args)
    {
        try {
            if (!class_exists(\App\Models\Funki\FunkiLog::class)) {
                return ['status' => 'error', 'message' => 'FunkiLog-Klasse ist im System nicht existent.'];
            }

            // Hole nur die echten System/KI/Auto-Warnungen und Fehler der letzten 24h
            $logs = \App\Models\Funki\FunkiLog::whereIn('status', ['error', 'warning'])
                ->where('started_at', '>=', now()->subHours(24))
                ->orderByDesc('started_at')
                ->limit(10)
                ->get(['title', 'message', 'status', 'type', 'started_at']);
            
            if ($logs->isEmpty()) {
                return ['status' => 'success', 'message' => 'Das Systemprotokoll verzeichnet keine Fehler oder Warnungen in den letzten 24 Stunden. Alles läuft perfekt.'];
            }

            return [
                'status' => 'success',
                'error_count' => $logs->count(),
                'logs' => $logs->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetTodos(array $args)
    {
        try {
            $todos = Todo::where('is_completed', false)
                ->whereNull('parent_id')
                ->orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get(['id', 'title', 'priority', 'created_at']);
            
            return [
                'status' => 'success',
                'open_todos_count' => $todos->count(),
                'todos' => $todos->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeCreateTodo(array $args)
    {
        try {
            if (empty($args['title'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Titel für das ToDo angegeben.'];
            }
            
            // --- DUPLICATE CHECK ---
            // If there's already an active todo with roughly the same title (check the first 20 chars)
            $shortTitle = substr($args['title'], 0, 20);
            $existing = Todo::where('is_completed', false)
                ->where('title', 'LIKE', '%' . $shortTitle . '%')
                ->first();

            if ($existing) {
                return [
                    'status' => 'success',
                    'message' => 'Oh ich sehe gerade, das steht schon auf unserer Todo Liste',
                    'todo_id' => $existing->id
                ];
            }

            $list = TodoList::firstOrCreate(
                ['name' => 'Funkiras Empfehlungen'],
                ['icon' => 'sparkles', 'color' => '#10B981']
            );
            
            $todo = Todo::create([
                'title' => substr($args['title'], 0, 255),
                'priority' => $args['priority'] ?? 'mittel',
                'is_completed' => false,
                'todo_list_id' => $list->id
            ]);
            
            return [
                'status' => 'success',
                'message' => "Die Aufgabe '{$todo->title}' wurde erfolgreich aufgenommen.",
                'todo_id' => $todo->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen des ToDos: ' . $e->getMessage()];
        }
    }

    public static function executeCompleteTodo(array $args)
    {
        try {
            if (empty($args['todo_id'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine ToDo ID angegeben.'];
            }
            
            $todo = Todo::find($args['todo_id']);
            if (!$todo) {
                return ['status' => 'error', 'message' => 'Aufgabe nicht gefunden.'];
            }

            $todo->is_completed = true;
            $todo->save();

            return [
                'status' => 'success',
                'message' => "Die Aufgabe '{$todo->title}' wurde als erledigt markiert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abschließen des ToDos: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteTodo(array $args)
    {
        try {
            if (empty($args['todo_title'])) {
                return ['status' => 'error', 'message' => 'Kein Aufgaben-Titel angegeben.'];
            }

            $term = $args['todo_title'];
            
            $todo = Todo::where('is_completed', false)
                ->where('title', 'LIKE', '%' . $term . '%')
                ->first();

            if (!$todo) {
                // Fuzzy fallback using DB matching (just try the first word)
                $firstWord = explode(' ', $term)[0];
                if(strlen($firstWord) > 3) {
                    $todo = Todo::where('is_completed', false)
                        ->where('title', 'LIKE', '%' . $firstWord . '%')
                        ->first();
                }

                if(!$todo) {
                    return ['status' => 'error', 'message' => "Aufgabe '$term' wurde nicht gefunden."];
                }
            }

            $title = $todo->title;
            $todo->delete();

            return [
                'status' => 'success',
                'message' => "Die Aufgabe '$title' wurde gelöscht."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen des ToDos: ' . $e->getMessage()];
        }
    }

    public static function executeGetCalendarEvents(array $args)
    {
        try {
            $limit = $args['limit'] ?? null;
            
            $query = CalendarEvent::where('start_date', '>=', now())
                ->orderBy('start_date', 'asc');
                
            if ($limit) {
                // Nur N Termine holen (z.B. den absolut nächsten)
                $query->limit($limit);
            } else {
                // Fallback: 7 Tage
                $query->where('start_date', '<=', now()->addDays(7)->endOfDay());
            }

            $events = $query->get(['title', 'start_date', 'end_date', 'is_all_day', 'category', 'description']);
            
            return [
                'status' => 'success',
                'events_count' => $events->count(),
                'upcoming_events' => $events->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetDayRoutines(array $args)
    {
        try {
            $routines = FunkiDayRoutine::where('is_active', true)
                ->with(['steps' => function($q) {
                    $q->select('funki_day_routine_id', 'title', 'duration_minutes', 'position');
                }])
                ->orderBy('start_time', 'asc')
                ->get(['id', 'title', 'start_time', 'duration_minutes', 'type']);
            
            return [
                'status' => 'success',
                'active_routines_count' => $routines->count(),
                'routines' => $routines->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

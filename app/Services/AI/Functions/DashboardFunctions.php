<?php

namespace App\Services\AI\Functions;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\Funki\FunkiDayRoutine;

trait DashboardFunctions
{
    public static function getDashboardFunctionsSchema(): array
    {
        return [
            [
                'name' => 'get_system_health',
                'description' => 'Prüft den allgemeinen Systemstatus, aktive Sitzungen und Integritätsmetriken. Nützlich, um festzustellen, ob das System reibungslos läuft.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemHealth']
            ],
            [
                'name' => 'fix_system_errors',
                'description' => 'Agiert automatisch als Heiler: Behebt gefundene Systemwarnungen und Fehler (Leert Caches, startet Queues neu, setzt Configs zurück). FÜHRE DIESES TOOL ZWINGEND AUS, wenn get_system_health meldet, dass das System Fehler hat! BEVOR du der Benutzerin antwortest!',
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
                'name' => 'get_tasks',
                'description' => 'Ruft alle aktuell offenen Aufgaben aus dem Shopsystem ab. Nutze dies, um herauszufinden, was Herrin Alina als Nächstes tun muss.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTasks']
            ],
            [
                'name' => 'create_task',
                'description' => 'Erstellt eine neue Aufgabe basierend auf deinen Empfehlungen. Halte den Titel kurz und umsetzbar. VERWENDE DIES IMMER, wenn du Alina eine bestimmte Aufgabe zuweist.',
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
                'callable' => [self::class, 'executeCreateTask']
            ],
            [
                'name' => 'complete_task',
                'description' => 'Markiert eine offene Aufgabe als abgeschlossen. Verwende dies, wenn Herrin Alina sagt, dass sie eine bestimmte Aufgabe beendet hat.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'Die ID der Aufgabe, die abgeschlossen wurde. (Erhältst du durch get_tasks)'
                        ]
                    ],
                    'required' => ['task_id']
                ],
                'callable' => [self::class, 'executeCompleteTask']
            ],
            [
                'name' => 'delete_task',
                'description' => 'Löscht eine offene Aufgabe vollständig. Verwende dies, wenn Herrin Alina verlangt, eine Aufgabe zu löschen, abzubrechen oder zu entfernen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_title' => [
                            'type' => 'string',
                            'description' => 'Der ungefährer Name der Aufgabe, die gelöscht werden soll (z.B. "Finnum anrufen").'
                        ]
                    ],
                    'required' => ['task_title']
                ],
                'callable' => [self::class, 'executeDeleteTask']
            ],
            [
                'name' => 'get_day_routines',
                'description' => 'Ruft die aktiven Tagesroutinen von Herrin Alina ab. Nutze dies, um zu überprüfen, ob sie ihrem strukturierten Tag folgt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetDayRoutines']
            ],
            [
                'name' => 'get_current_mission',
                'description' => 'Gibt den ultimativen nächsten Befehl, die aktuelle Tagesroutine, Top-Prioritäten und Empfehlungen für Herrin Alina zurück. NUR verwenden, wenn gefragt wird "Was soll ich jetzt tun?", "Was steht als Nächstes an?" oder ähnliche allgemeine Statusfragen.',
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
            $botService = app(\App\Services\AiSupportService::class);
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

    public static function executeGetTasks(array $args)
    {
        try {
            $tasks = Task::where('is_completed', false)
                ->whereNull('parent_id')
                ->orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get(['id', 'title', 'priority', 'created_at']);

            return [
                'status' => 'success',
                'open_tasks_count' => $tasks->count(),
                'tasks' => $tasks->toArray()
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

            // --- DUPLICATE CHECK ---
            // If there's already an active task with roughly the same title (check the first 20 chars)
            $shortTitle = substr($args['title'], 0, 20);
            $existing = Task::where('is_completed', false)
                ->where('title', 'LIKE', '%' . $shortTitle . '%')
                ->first();

            if ($existing) {
                return [
                    'status' => 'success',
                    'message' => 'Oh ich sehe gerade, das steht schon auf unserer Aufgaben Liste',
                    'task_id' => $existing->id
                ];
            }

            $list = TaskList::firstOrCreate(
                ['name' => 'Funkiras Empfehlungen'],
                ['icon' => 'sparkles', 'color' => '#10B981']
            );

            $task = Task::create([
                'title' => substr($args['title'], 0, 255),
                'priority' => $args['priority'] ?? 'mittel',
                'is_completed' => false,
                'task_list_id' => $list->id
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

    public static function executeCompleteTask(array $args)
    {
        try {
            if (empty($args['task_id'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine Aufgaben ID angegeben.'];
            }

            $task = Task::find($args['task_id']);
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

            $task = Task::where('is_completed', false)
                ->where('title', 'LIKE', '%' . $term . '%')
                ->first();

            if (!$task) {
                // Fuzzy fallback using DB matching (just try the first word)
                $firstWord = explode(' ', $term)[0];
                if(strlen($firstWord) > 3) {
                    $task = Task::where('is_completed', false)
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

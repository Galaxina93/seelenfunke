<?php

namespace App\Services\AI\Functions;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\DayRoutine;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;

trait AiScoutFuncs
{
    public static function getAiScoutFuncsSchema(): array
    {
        return [
            [
                'name' => 'get_tasks',
                'description' => 'Ruft alle aktuell offenen System-Aufgaben ab. Stichworte: Zeig mir meine Todos, Was muss ich tun, offene Aufgaben, Taskliste, TODO Liste.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTasks']
            ],
            [
                'name' => 'create_task',
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
                        ]
                    ],
                    'required' => ['title', 'priority']
                ],
                'callable' => [self::class, 'executeCreateTask']
            ],
            [
                'name' => 'complete_task',
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
                'name' => 'delete_task',
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
            ],
            [
                'name' => 'get_day_routines',
                'description' => 'Ruft die aktiven, strukturierten Tagesroutinen ab. Stichworte: Wie sieht meine Routine aus, Meine Morning Routine, Tagesablauf, Abendroutine, was steht heute an.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetDayRoutines']
            ],
            [
                'name' => 'get_current_mission',
                'description' => 'Gibt den ultimativen nächsten Befehl oder Mission zurück, worauf sich der User fokussieren soll. Stichworte: Was ist mein Fokus, was soll ich als nächstes angreifen, meine Mission, Tagesziel.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetCurrentMission']
            ],
            [
                'name' => 'get_gamification_leaderboard',
                'description' => 'Zeigt die Gamification-Highscore-Liste der Kunden. Stichworte: Wer hat die meisten XP, Leaderboard, Level der Kunden, Punkte Rangliste.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetGamificationLeaderboard']
            ],
            [
                'name' => 'search_customers',
                'description' => 'Sucht nach einem Kunden im System anhand Name oder Email, und liefert Kunden-Lifetime-Value, Order-Anzahl etc. Stichworte: Suche Kunde, Details zu Frau Schmidt, Wer ist dieser Käufer, Kundenstammblatt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Vorname, Nachname oder Email des Kunden.'
                        ]
                    ],
                    'required' => ['search_query']
                ],
                'callable' => [self::class, 'executeSearchCustomers']
            ],
            [
                'name' => 'search_internet',
                'description' => 'Sucht live im Internet nach aktuellen Nachrichten, Themen oder Begriffen. Stichworte: Suche im Web, Was gibt es neues zu, Google nach, Websuche',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff für die Websuche.'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeSearchInternet']
            ],
        ];
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
            $routines = DayRoutine::where('is_active', true)
                ->with(['steps' => function($q) {
                    $q->select('day_routine_id', 'title', 'duration_minutes', 'position');
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

    public static function executeGetCurrentMission(array $args)
    {
        try {
            $botService = app(\App\Services\Ai\AiSupportService::class);
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

    public static function executeGetGamificationLeaderboard(array $args)
    {
        try {
            $leaders = CustomerGamification::with('customer')
                ->orderBy('total_xp', 'desc')
                ->take(5)->get();

            if ($leaders->isEmpty()) {
                return ['status' => 'success', 'message' => 'Noch keine Spieler in der Gamification-Tabelle.'];
            }

            $formatted = [];
            foreach ($leaders as $idx => $l) {
                $cName = $l->customer ? ($l->customer->first_name . ' ' . substr($l->customer->last_name, 0, 1) . '.') : 'Unbekannt';
                $formatted[] = [
                    'rank' => $idx + 1,
                    'customer' => $cName,
                    'level' => $l->current_level,
                    'xp' => number_format($l->total_xp, 0, ',', '.') . ' XP',
                    'title' => $l->title ?? 'Novize'
                ];
            }

            return ['status' => 'success', 'leaderboard' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Level-Statistiken konnten nicht geladen werden: ' . $e->getMessage()];
        }
    }

    public static function executeSearchCustomers(array $args)
    {
        try {
            if (empty($args['search_query'])) return ['status' => 'error', 'message' => 'Suchbegriff fehlt.'];
            $term = $args['search_query'];

            $customers = Customer::where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->take(3)->get();

            if ($customers->isEmpty()) {
                return ['status' => 'success', 'message' => "Kunde '$term' nicht gefunden."];
            }

            $formatted = [];
            foreach ($customers as $c) {
                $orderCount = \App\Models\Order\Order::where('customer_id', $c->id)->count();
                $spentCents = \App\Models\Order\Order::where('customer_id', $c->id)->where('status', 'completed')->sum('total_amount');
                
                $formatted[] = [
                    'name' => $c->first_name . ' ' . $c->last_name,
                    'email' => $c->email,
                    'registered_since' => $c->created_at ? $c->created_at->format('d.m.Y') : '-',
                    'total_orders' => $orderCount,
                    'total_spent' => number_format($spentCents / 100, 2, ',', '.') . ' €'
                ];
            }

            return ['status' => 'success', 'customers' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Kundensuche fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    public static function executeSearchInternet(array $args)
    {
        try {
            if (empty($args['query'])) return ['status' => 'error', 'message' => 'Suchbegriff fehlt.'];
            
            $query = urlencode($args['query']);
            $url = "https://html.duckduckgo.com/html/?q={$query}";
            
            $client = new \GuzzleHttp\Client([
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/100.0.4896.127 Safari/537.36',
                    'Accept-Language' => 'de-DE,de;q=0.9',
                    'Accept' => 'text/html'
                ],
                'timeout' => 8
            ]);
            
            $response = $client->request('GET', $url);
            $html = $response->getBody()->getContents();
            
            // Extract text snippets from DuckDuckGo Lite HTML results
            preg_match_all('/<a class="result__snippet[^>]+>(.*?)<\/a>/is', $html, $matches);
            
            if (empty($matches[1])) {
                // Try Wikipedia fallback if DuckDuckGo blocks us
                $wikiUrl = "https://de.wikipedia.org/w/api.php?action=query&list=search&srsearch={$query}&utf8=&format=json";
                $wikiResponse = $client->request('GET', $wikiUrl);
                $wikiData = json_decode($wikiResponse->getBody()->getContents(), true);
                
                if (!empty($wikiData['query']['search'])) {
                    $results = [];
                    foreach (array_slice($wikiData['query']['search'], 0, 3) as $item) {
                        $results[] = trim(strip_tags($item['snippet']));
                    }
                    if(!empty($results)) {
                         return ['status' => 'success', 'source' => 'Wikipedia', 'results' => $results];
                    }
                }
                return ['status' => 'success', 'message' => 'Keine brauchbaren Resultate zur Suchanfrage gefunden.'];
            }
            
            $results = [];
            foreach (array_slice($matches[1], 0, 3) as $snippet) {
                $clean = strip_tags(html_entity_decode($snippet));
                $results[] = trim($clean);
            }
            
            return ['status' => 'success', 'source' => 'Web Search', 'results' => $results];
            
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Internetzugriff fehlgeschlagen: ' . $e->getMessage()];
        }
    }
}

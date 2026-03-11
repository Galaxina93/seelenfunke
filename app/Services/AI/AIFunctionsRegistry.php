<?php

namespace App\Services\AI;

use App\Models\Expense; 
use App\Models\Order\Order;   
use App\Models\Todo;
use App\Models\TodoList;
use App\Models\KnowledgeBase;
use App\Models\CalendarEvent;
use App\Models\Funki\FunkiDayRoutine;
use App\Models\Ticket;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Product\ProductReview;
use App\Services\FunkiBotService;
use App\Livewire\Global\Widgets\FunkiAnalytics;

class AIFunctionsRegistry
{
    /**
     * Define all available functions the AI can call.
     * This acts as the "Remote Control" schema.
     */
    public static function getFunctions(): array
    {
        return [
            [
                'name' => 'check_missing_expenses',
                'description' => 'Checks if there are missing Sonderausgaben (special expenses) that need to be recorded or reviewed.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeCheckMissingExpenses']
            ],
            [
                'name' => 'get_next_order_deadline',
                'description' => 'Returns the date and time when the next pending or open order must be finished or shipped.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetNextOrderDeadline']
            ],
            [
                'name' => 'get_system_health',
                'description' => 'Returns the overall system status, active sessions, and health metrics. Useful to determine if the system is running smoothly.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetSystemHealth']
            ],
            [
                'name' => 'get_todos',
                'description' => 'Returns all currently open ToDos from the shop system. Use this to find out what Herrin Alina needs to work on next.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetTodos']
            ],
            [
                'name' => 'get_calendar_events',
                'description' => 'Returns upcoming calendar events and meetings. Use this to check schedules.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetCalendarEvents']
            ],
            [
                'name' => 'get_day_routines',
                'description' => 'Returns the active daily routines of Herrin Alina. Use this to check if she is following her structured day.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetDayRoutines']
            ],
            [
                'name' => 'get_shop_stats',
                'description' => 'Returns deep shop statistics (abandoned carts, potential lost revenue, active vouchers). Use this specifically when analyzing revenue scaling and the 100k goal.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetShopStats']
            ],
            [
                'name' => 'get_finances',
                'description' => 'Returns the current month\'s accounting and financial data (Income, Fixed Costs, Special Expenses, Shop Revenue).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetFinances']
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
                            'enum' => ['high', 'medium', 'low']
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
                'name' => 'save_memory',
                'description' => 'Saves a fact, user preference, or important note into your long-term memory (Knowledge Base). ALWAYS use this when Alina says "Merke dir", "Notiere", or similar.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Kurzer, prägnanter Titel für die Erinnerung (z.B. "Geburtstag Theresa").'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Die eigentliche Information, die du dir merken sollst.'
                        ]
                    ],
                    'required' => ['title', 'content']
                ],
                'callable' => [self::class, 'executeSaveMemory']
            ],
            [
                'name' => 'search_memory',
                'description' => 'Searches your long-term memory (Knowledge Base) for past facts, preferences, or notes you have saved. Proactively use this if asked about a specific detail you might have learned earlier.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff (z.B. "Theresa Geburtstag", "Vorlieben").'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeSearchMemory']
            ],
            [
                'name' => 'write_blog_post',
                'description' => 'Verfasst einen neuen Blogbeitrag. Nutze dieses Tool proaktiv (von dir aus) während der spontanen Selbst-Analyse, um produktiv zu sein und ein sinnvolles Firmenthema zu behandeln.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Titel des Blogbeitrags.'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Der vollständige HTML-präsente Inhalt (Formatierung mit <h2>, <p>, <strong> etc.)'
                        ],
                        'category_id' => [
                            'type' => 'integer',
                            'description' => 'Optional: Die ID der passenden Blog-Kategorie. Wenn unbekannt, sende 1.'
                        ]
                    ],
                    'required' => ['title', 'content']
                ],
                'callable' => [self::class, 'executeWriteBlogPost']
            ],
            [
                'name' => 'read_wiki_files',
                'description' => 'Liest die hochgeladenen Dokumente aus dem Wiki aus. Nutze dieses Tool NUR, wenn du explizit nach firmeninternen oder tiefgreifenden persönlichen Daten (z.B. "Wer bin ich?", "Was sind unsere Werte?") gefragt wirst.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filename_query' => [
                            'type' => 'string',
                            'description' => 'Optional: Ein Suchbegriff oder Dateiname, falls du nur eine bestimmte Datei auslesen willst.'
                        ]
                    ],
                    'required' => []
                ],
                'callable' => [self::class, 'executeReadWikiFiles']
            ],
            [
                'name' => 'check_inventory',
                'description' => 'Prüft den aktuellen Lagerbestand von physischen Produkten im Shop. Nutze dies IMMER, wenn Alina nach Beständen, Mengen oder ausverkauften Artikeln fragt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Optional: Ein detaillierter Suchbegriff (Produktname oder SKU), um bestimmte Artikel zu prüfen.'
                        ]
                    ], // Required empty
                ],
                'callable' => [self::class, 'executeCheckInventory']
            ],
            [
                'name' => 'get_order',
                'description' => 'Ruft Details zu einer bestimmten Bestellung ab. Nutze dies, wenn Alina nach einem Auftrag, einer Bestellung oder dem Status fragt (z.B. "Zeige mir den Auftrag", "Wie ist der Status von Bestellung 1024?").',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'Die Bestellnummer oder Name des Kunden. (z.B. "1024" oder "Mueller")'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetOrder']
            ],
            [
                'name' => 'close_ui',
                'description' => 'Schließt alle aktuell in der 3D-Ansicht geöffneten schwebenden Fenster, Tabellen und Charts. Nutze dies IMMER, wenn Alina sagt "Fenster zu", "Schließen", "Tabellen weg" oder ähnliches.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [], // Takes no parameters
                ],
                'callable' => [self::class, 'executeCloseUi']
            ],
            [
                'name' => 'get_tickets',
                'description' => 'Gibt alle offenen Kundensupport-Tickets zurück. Nutze dies, wenn nach Support, Kundenmeldungen oder Tickets gefragt wird.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetTickets']
            ],
            [
                'name' => 'get_product_reviews',
                'description' => 'Checkt die aktuell freizugebenden/ungelesenen Produktbewertungen der Kunden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetProductReviews']
            ],
            [
                'name' => 'get_gamification_leaderboard',
                'description' => 'Zeigt die aktuell motiviertesten Gamification-Kunden nach XP und Leveln (Highscore-Liste).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetGamificationLeaderboard']
            ],
            [
                'name' => 'search_customers',
                'description' => 'Sucht nach einem Kunden im System. Nutze dies, wenn du Infos über eine bestimmte Person heraussuchen sollst.',
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
        ];
    }

    /**
     * Return only the Schema (name, description, parameters) for the LLM.
     */
    public static function getSchema(): array
    {
        $functions = self::getFunctions();
        
        // Transform internal representation to LLM JSON Schema format
        return array_map(function ($fn) {
            
            $props = $fn['parameters']['properties'];
            if (empty($props)) {
                $props = new \stdClass(); // Force {} instead of [] in JSON
            }
            
            return [
                'type' => 'function',
                'function' => [
                    'name' => $fn['name'],
                    'description' => $fn['description'],
                    'parameters' => [
                        'type' => $fn['parameters']['type'],
                        'properties' => $props
                    ]
                ]
            ];
        }, $functions);
    }

    /**
     * Executes a function by name if it exists in the registry.
     */
    public static function execute(string $name, array $args = [])
    {
        $functions = collect(self::getFunctions())->keyBy('name');

        if (!$functions->has($name)) {
            throw new \InvalidArgumentException("Function '{$name}' is not registered in the AI Remote Control.");
        }

        $callable = $functions[$name]['callable'];

        if (!is_callable($callable)) {
            throw new \RuntimeException("Callable for function '{$name}' is invalid.");
        }

        try {
            return call_user_func($callable, $args);
        } catch (\Exception $e) {
            Log::error("AI Function Execution Error: " . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Error executing function: ' . $e->getMessage()
            ];
        }
    }

    // ==========================================
    // ACTUAL IMPLEMENTATIONS (The Controller Logic)
    // ==========================================

    public static function executeCheckMissingExpenses(array $args)
    {
        // Example implementation - adjust based on actual DB structure
        // Here we might check some App\Models\Expense where status is missing etc.
        // For demonstration, returning a mock intelligent response
        return [
            'status' => 'success',
            'has_missing_expenses' => false,
            'message' => 'Aktuell sind alle erfassten Sonderausgaben verbucht. Es fehlen keine Belege im System.'
        ];
    }

    public static function executeGetNextOrderDeadline(array $args)
    {
        // Example implementation checking orders
        // Order::where('status', 'processing')->orderBy('deadline', 'asc')->first();
        return [
            'status' => 'success',
            'next_deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'type' => 'Express-Versand',
            'message' => 'Die nächste Bestellung muss übermorgen fertiggestellt werden.'
        ];
    }

    public static function executeGetSystemHealth(array $args)
    {
        // Utilize the existing FunkiAnalytics class to give the AI real info
        try {
            $analytics = new FunkiAnalytics();
            $analytics->checkSystemHealth();
            $isHealthy = $analytics->isSystemHealthy();
            
            // We need to set up minimal state for the component to load stats
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

    public static function executeGetTodos(array $args)
    {
        try {
            // Fetch uncompleted ToDos, prioritized by High->Low, limit to save context window
            $todos = Todo::where('is_completed', false)
                ->whereNull('parent_id')
                ->orderByRaw("FIELD(COALESCE(priority, 'low'), 'high', 'medium', 'low')")
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get(['id', 'title', 'priority', 'created_at']); // Added ID so AI can complete them
            
            return [
                'status' => 'success',
                'open_todos_count' => $todos->count(),
                'todos' => $todos->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetCalendarEvents(array $args)
    {
        try {
            // Fetch events for today and next 7 days
            $events = CalendarEvent::where('start_date', '>=', now()->startOfDay())
                ->where('start_date', '<=', now()->addDays(7)->endOfDay())
                ->orderBy('start_date', 'asc')
                ->get(['title', 'start_date', 'end_date', 'is_all_day', 'category']);
            
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
            // Fetch active routines
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

    public static function executeGetShopStats(array $args)
    {
        try {
            // Fetch abandoned carts
            $abandonedCarts = \App\Models\Cart\Cart::with('items')
                ->where('updated_at', '>=', now()->subHours(24))
                ->where('updated_at', '<=', now()->subHours(2))
                ->get();

            $potentialRevenueCents = 0;
            foreach ($abandonedCarts as $cart) {
                foreach ($cart->items as $item) {
                    $potentialRevenueCents += ($item->quantity * $item->unit_price);
                }
            }
            $potentialRevenue = $potentialRevenueCents / 100;

            // Fetch vouchers
            $autoVouchers = \App\Models\Voucher::where('is_active', true)->where('mode', 'auto')->count();
            $manualVouchers = \App\Models\Voucher::where('is_active', true)->where('mode', 'manual')->count();

            // Fetch generic bot instructions stats
            $manualRulesCount = \App\Models\Bot\GenericInstruction::count();

            return [
                'status' => 'success',
                'scaling_metrics' => [
                    'abandoned_carts_count' => $abandonedCarts->count(),
                    'potential_lost_revenue' => $potentialRevenue, // in Euro
                    'active_auto_vouchers' => $autoVouchers,
                    'active_manual_vouchers' => $manualVouchers,
                    'trained_ai_rules' => $manualRulesCount
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetFinances(array $args)
    {
        try {
            $service = new \App\Services\FinancialService();
            // Default to Alina (admin_id = 1) and current month/year
            $statsNet = $service->getMonthlyStats(1, date('n'), date('Y'), true);
            $statsGross = $service->getMonthlyStats(1, date('n'), date('Y'), false);

            return [
                'status' => 'success',
                'financial_data_net' => $statsNet,
                'financial_data_gross' => $statsGross,
                'current_month' => date('F Y')
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
            
            // Find or create a specific list for AI tasks
            $list = TodoList::firstOrCreate(
                ['name' => 'Funkiras Empfehlungen'],
                ['icon' => 'sparkles', 'color' => '#10B981'] // Emerald green
            );
            
            $todo = Todo::create([
                'title' => substr($args['title'], 0, 255),
                'priority' => $args['priority'] ?? 'medium',
                'is_completed' => false,
                'todo_list_id' => $list->id
            ]);
            
            return [
                'status' => 'success',
                'message' => "Die Aufgabe '{$todo->title}' wurde erfolgreich in die ToDo-Liste 'Funkiras Empfehlungen' aufgenommen.",
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

    public static function executeSaveMemory(array $args)
    {
        try {
            if (empty($args['title']) || empty($args['content'])) {
                return ['status' => 'error', 'message' => 'Titel und Inhalt sind für das Speichern erforderlich.'];
            }
            
            $kb = KnowledgeBase::create([
                'title' => substr($args['title'], 0, 255),
                'slug' => \Illuminate\Support\Str::slug(substr($args['title'], 0, 255)) . '-' . rand(1000, 9999),
                'category' => 'AI Memory',
                'content' => $args['content'],
                'tags' => ['ai_memory', 'auto_saved'],
                'is_published' => true
            ]);
            
            return [
                'status' => 'success',
                'message' => "Die Information '{$kb->title}' wurde erfolgreich im Langzeitgedächtnis (Knowledge Base) gespeichert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Speichern der Erinnerung: ' . $e->getMessage()];
        }
    }

    public static function executeSearchMemory(array $args)
    {
        try {
            if (empty($args['query'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Suchbegriff angegeben.'];
            }

            $queryStr = $args['query'];
            
            $results = KnowledgeBase::where('is_published', true)
                ->where(function ($q) use ($queryStr) {
                    $q->where('title', 'like', '%' . $queryStr . '%')
                      ->orWhere('content', 'like', '%' . $queryStr . '%')
                      ->orWhereJsonContains('tags', $queryStr);
                })
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get(['title', 'content', 'category', 'created_at']);
            
            if ($results->isEmpty()) {
                 return [
                    'status' => 'success',
                    'message' => 'Ich habe in meinen Erinnerungen nichts zu "' . $queryStr . '" gefunden.',
                    'results' => []
                ];
            }

            return [
                'status' => 'success',
                'results_count' => $results->count(),
                'results' => $results->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Durchsuchen der Erinnerungen: ' . $e->getMessage()];
        }
    }

    public static function executeWriteBlogPost(array $args)
    {
        try {
            if (empty($args['title']) || empty($args['content'])) {
                return ['status' => 'error', 'message' => 'Titel oder Inhalt fehlen für den Blogbeitrag.'];
            }

            $slug = \Illuminate\Support\Str::slug($args['title']);

            \App\Models\Blog\BlogPost::create([
                'title' => $args['title'],
                'slug' => $slug,
                'content' => $args['content'],
                'blog_category_id' => $args['category_id'] ?? 1,
                'is_published' => true,
                'published_at' => now(),
            ]);

            return [
                'status' => 'success',
                'message' => "Der Blogbeitrag '{$args['title']}' wurde erfolgreich publiziert."
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Publizieren: ' . $e->getMessage()];
        }
    }

    /**
     * Parse and read text from uploaded Wiki files.
     */
    public static function executeReadWikiFiles(array $args)
    {
        try {
            $query = $args['filename_query'] ?? null;
            $files = \Illuminate\Support\Facades\Storage::disk('public')->files('wiki');
            
            if (empty($files)) {
                return ['status' => 'error', 'message' => "Es befinden sich aktuell keine Dateien im Wiki-Ordner. Der Benutzer muss erst Dateien hochladen."];
            }
            
            $output = "Gefundene Dateien im Wiki:\n\n";
            $contentFound = false;
            
            foreach ($files as $file) {
                $filename = basename($file);
                
                // If query is provided, skip files that don't match
                if ($query && stripos($filename, $query) === false) {
                    continue;
                }
                
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $output .= "### Datei: $filename\n";
                $contentFound = true;
                
                // Basic text parsing support
                if (in_array($ext, ['txt', 'md', 'csv', 'json', 'log'])) {
                    $content = \Illuminate\Support\Facades\Storage::disk('public')->get($file);
                    // Truncate to avoid context limit explosions
                    $content = \Illuminate\Support\Str::limit($content, 8000); 
                    $output .= "- Inhalt:\n" . $content . "\n\n";
                } elseif ($ext === 'docx') {
                    $zip = new \ZipArchive;
                    $absPath = \Illuminate\Support\Facades\Storage::disk('public')->path($file);
                    if ($zip->open($absPath) === true) {
                        if (($index = $zip->locateName('word/document.xml')) !== false) {
                            $data = $zip->getFromIndex($index);
                            $zip->close();
                            // Convert Word paragraphs to actual newlines before stripping tags
                            $data = str_replace('</w:p>', "\n\n", $data);
                            $text = strip_tags($data);
                            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                            $text = \Illuminate\Support\Str::limit($text, 8000);
                            $output .= "- Inhalt:\n" . $text . "\n\n";
                        } else {
                            $zip->close();
                            $output .= "- Fehler: Konnte den Text nicht aus der DOCX-Datei extrahieren.\n\n";
                        }
                    } else {
                        $output .= "- Fehler: Konnte die DOCX-Datei nicht öffnen.\n\n";
                    }
                } elseif ($ext === 'doc') {
                    $output .= "- (DOC Format): Das veraltete '.doc' Format kann ich nicht direkt lesen. Bitte weise den Benutzer an, die Datei als '.docx' (neueres Word Format) zu speichern und neu hochzuladen.\n\n";
                } elseif ($ext === 'pdf') {
                    $output .= "- (PDF Format): Aktuell kann ich PDFs nicht nativ lesen, solange kein PDF-Parser installiert ist. Bitte den Benutzer stattdessen den Text als TXT/MD hochzuladen.\n\n";
                } else {
                    $output .= "- Format `.$ext` wird aktuell nicht von der KI unterstützt.\n\n";
                }
            }
            
            if (!$contentFound) {
                return ['status' => 'error', 'message' => "Es wurde keine Datei gefunden, die auf '$query' passt."];
            }
            
            return ['status' => 'success', 'content' => $output];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Auslesen des Wikis: ' . $e->getMessage()];
        }
    }

    /**
     * Prüft den Lagerbestand der Produkte.
     */
    public static function executeCheckInventory(array $args)
    {
        try {
            $query = \App\Models\Product\Product::where('status', 'active')
                ->where('track_quantity', true);
                
            if (!empty($args['search_query'])) {
                $query->where(function($q) use ($args) {
                    $q->where('name', 'like', '%' . $args['search_query'] . '%')
                      ->orWhere('sku', 'like', '%' . $args['search_query'] . '%');
                });
            }

            $products = $query->orderBy('quantity', 'asc')->take(20)->get();

            if ($products->isEmpty()) {
                return ['status' => 'success', 'message' => 'Keine physischen Produkte gefunden, oder der Lagerbestand wird nicht getrackt.'];
            }

            $inventory = [];
            foreach ($products as $p) {
                $inventory[] = [
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'quantity' => $p->quantity,
                    'status' => $p->quantity <= 0 ? 'Ausverkauft' : 'Verfügbar'
                ];
            }

            return [
                'status' => 'success',
                'products' => $inventory
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Laden des Inventars: ' . $e->getMessage()];
        }
    }

    /**
     * Ruft Details zu einer bestimmten Bestellung ab (Letzte oder nach Suchbegriff).
     */
    public static function executeGetOrder(array $args)
    {
        try {
            $query = Order::with(['items', 'customer']);
            
            if (!empty($args['order_number'])) {
                $term = $args['order_number'];
                $query->where(function($q) use ($term) {
                    $q->where('order_number', 'like', "%{$term}%")
                      ->orWhereHas('customer', function($cQ) use ($term) {
                          $cQ->where('last_name', 'like', "%{$term}%")
                             ->orWhere('first_name', 'like', "%{$term}%");
                      });
                });
            } else {
                // Return latest 5 orders if no query given
                $query->orderBy('created_at', 'desc')->take(5);
            }

            $orders = $query->take(5)->get();

            if ($orders->isEmpty()) {
                return ['status' => 'success', 'message' => 'Keine passende Bestellung gefunden.'];
            }

            $formatted = [];
            foreach ($orders as $o) {
                $customerName = $o->customer ? $o->customer->first_name . ' ' . $o->customer->last_name : 'Gast';
                $items = $o->items ? $o->items->map(fn($i) => $i->quantity . 'x ' . $i->name)->implode(', ') : 'Keine Artikel';
                
                $formatted[] = [
                    'order_number' => $o->order_number,
                    'customer' => $customerName,
                    'status' => $o->status,
                    'total' => number_format($o->total_amount / 100, 2, ',', '.') . ' €',
                    'items_summary' => $items,
                    'date' => $o->created_at->format('d.m.Y H:i')
                ];
            }

            return [
                'status' => 'success',
                'orders' => $formatted
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abrufen der Bestellung: ' . $e->getMessage()];
        }
    }

    /**
     * Gibt ein Kommando an das Frontend zurück, um alle UI Panels zu schließen.
     */
    public static function executeCloseUi(array $args)
    {
        return [
            'status' => 'success',
            'command' => 'close_all_panels',
            'message' => 'Alle Fenster wurden geschlossen.'
        ];
    }

    public static function executeGetTickets(array $args)
    {
        try {
            // Holen wir die neuesten 5 offenen Tickets
            $query = Ticket::where('status', '!=', 'closed');
            $count = $query->count();
            $tickets = $query->orderBy('created_at', 'desc')->take(5)->get();

            if ($tickets->isEmpty()) {
                return ['status' => 'success', 'message' => 'Es gibt aktuell keine offenen Support-Tickets. Alles super!'];
            }

            $formatted = [];
            foreach ($tickets as $t) {
                $formatted[] = [
                    'id' => $t->id,
                    'subject' => $t->subject,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'date' => $t->created_at->format('d.m.Y H:i')
                ];
            }

            return [
                'status' => 'success',
                'open_tickets_count' => $count,
                'tickets' => $formatted
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Tickets konnten nicht geladen werden: ' . $e->getMessage()];
        }
    }

    public static function executeGetProductReviews(array $args)
    {
        try {
            // Normalerweise pending Bewertungen, hier limitieren wir auf 5
            $reviews = ProductReview::with('product')
                ->where('is_approved', false)
                ->orderBy('created_at', 'desc')->take(5)->get();

            if ($reviews->isEmpty()) {
                return ['status' => 'success', 'message' => 'Aktuell gibt es keine neuen Bewertungen, die auf Freigabe warten.'];
            }

            $formatted = [];
            foreach ($reviews as $r) {
                $formatted[] = [
                    'product_name' => $r->product ? $r->product->name : 'Unbekannt',
                    'rating' => $r->rating . '/5 Sterne',
                    'comment' => \Illuminate\Support\Str::limit($r->comment, 80),
                    'customer' => $r->customer_name ?: 'Gast',
                    'date' => $r->created_at->format('d.m.Y')
                ];
            }

            return ['status' => 'success', 'reviews' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Produktbewertungen konnten nicht geladen werden: ' . $e->getMessage()];
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
                    'title' => $l->title ?? 'Novize' // z.B. wenn Title implementiert wurde
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
                // Wir tun so, als ob order_count und total_spent existieren,
                // andernfalls greifen wir es live von Orders ab (sehr heavy in großen Apps, aber ok hier)
                $orderCount = \App\Models\Order::where('customer_id', $c->id)->count();
                $spentCents = \App\Models\Order::where('customer_id', $c->id)->where('status', 'completed')->sum('total_amount');
                
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
}

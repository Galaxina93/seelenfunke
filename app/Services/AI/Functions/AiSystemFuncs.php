<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use App\Models\Ai\AiAgent;

trait AiSystemFuncs
{
    public static function getAiSystemFuncsSchema(): array
    {
        return [

            [
                'name' => 'system_visualize_data',
                'description' => 'Zeigt strukturierte JSON-Daten visuell als Master Modal Dashboard für den User an. IMMER ausführen, wenn der User nach einer grafischen Übersicht, Tabelle, Liste oder Grafik fragt. Stichworte: Visualisiere mir, Zeig mir das als Liste, Tabelle einblenden, Übersicht anzeigen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string',
                            'description' => 'Grobe Kategorie der Daten in Kleinschreibung (z.B. "voucher", "customer", "task", "code", "finance", "system_health").'
                        ],
                        'data' => [
                            'type' => 'array',
                            'description' => 'Die nativen rohen JSON-Daten als Array. Das Backend kümmert sich um das Design. WENN du Quellcode/Code (Kategorie "code") visualisierst, packe ein Objekt mit "language" (z.B. php, js), "file_name" (falls bekannt) und "code_string" in das Array, oder lege einfach die reinen Informationen in ein formatierbares Objekt.',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => true
                            ]
                        ]
                    ],
                    'required' => ['category', 'data']
                ],
                'callable' => [self::class, 'executeVisualizeData']
            ],

            [
                'name' => 'system_close_ui',
                'description' => 'Schließt alle aktuell in der 3D-Ansicht geöffneten schwebenden Popups, Diagramme und Fenster. Stichworte: Fenster zu, UI schließen, Tabellen ausblenden, Mach das weg, Schließe alles.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCloseUi']
            ],
            [
                'name' => 'system_assign_tool_to_role',
                'description' => 'Gibt deinem Agenten (oder genauer gesagt deiner Rolle) dynamisch eine neue Fähigkeit (Werkzeug), die dir momentan fehlt. Nutze dies IMMER, wenn der Nutzer verlangt: "Gib dir mal die Fähigkeit X" oder "Aktiviere das Tool Y für dich".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tool_identifier' => [
                            'type' => 'string',
                            'description' => 'Der exakte system-interne Bezeichner der Fähigkeit (z.B. "system_read_code", "support_create_ticket").'
                        ]
                    ],
                    'required' => ['tool_identifier']
                ],
                'callable' => [self::class, 'executeAssignToolToRole']
            ],
            [
                'name' => 'system_open_nav_item',
                'description' => 'Navigiert das Dashboard auf eine bestimmte Unterseite. WICHTIG: Erkenne den natürlichsprachlichen Wunsch (z.B. "wo ich Gutschriften hinterlegen kann" -> /admin/credit-management, "Belege hinterlegen" -> /admin/financial-variable-costs) und wähle die EXAKTE URL aus folgenden Optionen:' . "\n" . \App\Services\Navigation\BackendNavigationService::getAiNavigationPrompt(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'Die exakte, vollständige URL /admin/... wie in der Beschreibung hinterlegt.'
                        ]
                    ],
                    'required' => ['url']
                ],
                'callable' => [self::class, 'executeOpenNavItem']
            ],
            [
                'name' => 'system_open_zentrum',
                'description' => 'Öffnet das visuelle 3D Zentrum (Funkira Widget) in der Front-Ansicht. Stichworte: Öffne das Zentrum, Zeig dich zentrum, Mach das Widget auf, Komm her Funkira.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeOpenZentrum']
            ],
            [
                'name' => 'system_close_zentrum',
                'description' => 'Schließt das visuelle 3D Zentrum. Stichworte: Zentrum schließen, Geh weg, Fokus modus, blend dich aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCloseZentrum']
            ],

            [
                'name' => 'system_search_chat_history',
                'description' => 'Suche im flüchtigen Chat-Verlauf der vergangenen Stunden/Tage. Nutze dies IMMER, wenn der User nach einer vergangenen Unterhaltung, einem Kontext von gestern oder kurzzeitigen Dingen aus dem Chat fragt. Stichworte: Worüber haben wir gestern gesprochen, Was habe ich gerade gesagt, Zeig alte Chat Logs.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'time_filter' => [
                            'type' => 'string',
                            'description' => "Zeitraum Filter. Erlaubt: 'today', 'yesterday', 'last_week', 'all' (Standard: 'all')",
                            'enum' => ['today', 'yesterday', 'last_week', 'all']
                        ],
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Ein optionales Suchwort, um die Historie einzugrenzen.'
                        ]
                    ],
                    'required' => ['time_filter']
                ],
                'callable' => [self::class, 'executeSearchChatHistory']
            ],
            [
                'name' => 'system_get_health',
                'description' => 'Pingt das Server-System an und prüft den technischen Zustand, CPU-Daten, Queue-Workers, Laravel-Caches und Fehler-Logs. Stichworte: Ist das System gesund, Systemüberprüfung, Check Systemstatus, Gibt es IT-Fehler.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemHealth']
            ],
            [
                'name' => 'system_fix_errors',
                'description' => 'Agiert als automatischer Administrator: Behebt gefundene Backend-Fehler durch Cache-Clearing, OPcache Resets und Queue Restarts. FÜHRE DIESES TOOL ZWINGEND AUS, wenn get_system_health Fehler meldet. Stichworte: Repariere das System, Behebe die Fehler, Auto-Heal starten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeFixSystemErrors']
            ],
            [
                'name' => 'system_get_logs',
                'description' => 'Liest detaillierte technische Exception-Logs und Fehler aus Laravel. Stichworte: Welche Errors gibt es genau, Lies das Logfile, Zeig mir den System-Fehler im Detail.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemLogs']
            ],
            [
                'name' => 'system_read_wiki',
                'description' => 'System-Werkzeug für Wissensdatenbank & RAG (Retrieval-Augmented Generation): Liest direkt und asynchron den gesamten Text der großen Wiki-Dokumente und Wissens-Dateien aus (kein DB-Memory!). Stichworte: Wissensdatenbank, Suche in der DB, RAG Dokumente, Lies im internen Firmen-Wiki, Welche PDF Regeln gibt es, Lese das Handbuch.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filename_query' => [
                            'type' => 'string',
                            'description' => 'EXAKTER Dateiname. ACHTUNG: Nutze dies NUR, wenn du eine ganz bestimmte Datei meinst (z.B. "Richtlinien.pdf") und deren Name exakt kennst. Wenn du eine Information / ein Thema suchst, lass diesen Parameter ZWINGEND LEER, um ALLE Dokumente nach der Antwort zu durchstöbern!'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeReadWikiFiles']
            ],
            [
                'name' => 'system_get_map',
                'description' => 'Generiert eine riesige dynamische Strukturkarte der Systemarchitektur und zeigt dir, welche Tabellen/Ressourcen verbaut sind. Stichworte: Wie ist das Backend aufgebaut, Zeig mir dein Architektur-Wissen, Modelle scannen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemMap']
            ],
            [
                'name' => 'agent_update_system_config',
                'description' => 'Ändere deine tiefgreifenden KI-Rollen, LLM-Modelle, Token-Grenzen, und Berechtigungen im System. Stichworte: Wechsle auf GPT-4, Setze Modus auf Chill, Aktiviere Shop-Rechte, Berechtigungen anpassen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'active_mode' => ['type' => 'string', 'description' => 'Setzt den Modus: business, default oder chill'],
                        'ai_model' => ['type' => 'string', 'description' => 'Das verwendete LLM Modell'],
                        'token_limit' => ['type' => 'integer', 'description' => 'Maximales Token-Limit'],
                        'human_in_the_loop' => ['type' => 'boolean', 'description' => 'Human in the loop erzwingen'],
                        'execution_limit' => ['type' => 'integer', 'description' => 'Anti-Loop Ausführungs-Limit'],
                        'voice_enabled' => ['type' => 'boolean', 'description' => 'Sprachausgabe (TTS) aktivieren/deaktivieren'],
                        'cap_shop_support' => ['type' => 'boolean', 'description' => 'Shop-Steuerung aktivieren/deaktivieren'],
                        'cap_system_diagnostics' => ['type' => 'boolean', 'description' => 'System-Diagnose aktivieren/deaktivieren'],
                        'cap_family_crm' => ['type' => 'boolean', 'description' => 'Familien-CRM aktivieren/deaktivieren'],
                    ]
                ],
                'callable' => [self::class, 'executeUpdateFunkiraConfiguration']
            ],
            [
                'name' => 'agent_update_runtime_config',
                'description' => 'Passe dein Verhalten zur Laufzeit an, z.B. wie schnell du sprichst, dein Name oder deine Kreativität (Temperatur). Stichworte: Sprich schneller, Senke Temperatur, Heiße jetzt anders, Sprachausgabe ändern.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'setting_key' => [
                            'type' => 'string',
                            'description' => 'Der Schlüssel der Einstellung (gültig: tts_speed, temperature, wake_word, name)',
                            'enum' => ['tts_speed', 'temperature', 'wake_word', 'name']
                        ],
                        'setting_value' => [
                            'type' => 'string',
                            'description' => 'Der neue Wert für die Einstellung. Z.B. "0.8" für tts_speed, um langsamer zu sprechen.'
                        ],
                    ],
                    'required' => ['setting_key', 'setting_value'],
                ],
                'callable' => [self::class, 'executeAgentConfig']
            ],
            [
                'name' => 'system_search_files',
                'description' => 'Sucht nach Dateinamen im Projekt-Verzeichnis. Nutze dies, um herauszufinden, ob eine Datei existiert oder wo sie liegt. (Eingeschränkt auf app, config, resources, routes, database). Stichworte: Wo ist die blade datei, Suche nach Datei, Zeige alle X Dateien.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filename_query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff für den Dateinamen (z.B. "AiChat", ".blade.php", "User").'
                        ]
                    ],
                    'required' => ['filename_query']
                ],
                'callable' => [self::class, 'executeSearchFiles']
            ],
            [
                'name' => 'system_search_code',
                'description' => 'IDE-ähnliche Suche nach Quellcode (String/Regex/Wort) im Projekt. Sucht in allen erlaubten Verzeichnissen nach dem Vorkommen deines Suchbegriffs. Hilft dir extrem, wenn du wissen willst "WO" eine bestimmte Logik verbaut ist oder welche Laravel Komponente dafür zuständig ist.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Der genaue Code-Schnipsel oder Suchbegriff (z.B. "class AiChat" oder "Mail::send").'
                        ]
                    ],
                    'required' => ['search_query']
                ],
                'callable' => [self::class, 'executeSearchCode']
            ],
            [
                'name' => 'system_read_code',
                'description' => 'Liest den Quellcode einer bestimmten Datei ein. WICHTIG: Erlaubt nur Lesen (Read-Only). Benutze dieses Werkzeug zwingend, um den Code einer Datei zu überprüfen, um dem User danach detailliertes Analyse-Feedback (inkl formattierten Code-Blöcken im Markdown) geben zu können.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [
                            'type' => 'string',
                            'description' => 'Der relative Dateipfad vom Projekt-Root aus (z.B. "app/Livewire/Shop/Ai/AiChat.php"). Keine absoluten Pfade!'
                        ],
                        'start_line' => [
                            'type' => 'integer',
                            'description' => 'Optional: Ab welcher Zeile soll gelesen werden? Standardmäßig 1.'
                        ],
                        'end_line' => [
                            'type' => 'integer',
                            'description' => 'Optional: Bis zu welcher Zeile soll gelesen werden? Standardmäßig bis zum Ende (Vorsicht bei riesigen Dateien).'
                        ]
                    ],
                    'required' => ['file_path']
                ],
                'callable' => [self::class, 'executeReadCode']
            ],
            [
                'name' => 'system_edit_file',
                'description' => 'Ersetzt einen exakten Quellcode-Block in einer Datei durch neuen Code. WICHTIGE REGEL: Im Autonomous / Execution Mode darfst und sollst du dieses Tool direkt ausführen, um Dateien selbstständig zu modifizieren und Bugs aktiv zu beheben. Erstelle bei größeren Änderungen erst ein "implementation_plan" Artefakt. Um Code zu bearbeiten MUSST du dieses Tool benutzen!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [
                            'type' => 'string',
                            'description' => 'Der relative Dateipfad vom Projekt-Root aus.'
                        ],
                        'search_content' => [
                            'type' => 'string',
                            'description' => 'Der exakte alte Code-Block, der gesucht werden soll (inkl. Whitespaces/Einrückungen, wie er von system_read_code zurückkam!).'
                        ],
                        'replace_content' => [
                            'type' => 'string',
                            'description' => 'Der neue Code-Block, der eingesetzt werden soll.'
                        ]
                    ],
                    'required' => ['file_path', 'search_content', 'replace_content']
                ],
                'callable' => [self::class, 'executeEditFile']
            ],
            [
                'name' => 'system_write_to_file',
                'description' => 'Überschreibt eine Datei GÄNZLICH mit neuem Code oder legt sie neu an. Gleiche Regel wie bei system_edit_file: Im Autonomous Mode hast du volle Berechtigung, die Datei direkt in das System zu schreiben.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [
                            'type' => 'string',
                            'description' => 'Der relative Dateipfad.'
                        ],
                        'new_content' => [
                            'type' => 'string',
                            'description' => 'Der gesamte, fertige Quellcode für die Datei.'
                        ]
                    ],
                    'required' => ['file_path', 'new_content']
                ],
                'callable' => [self::class, 'executeWriteToFile']
            ],
            [
                'name' => 'system_write_artifact',
                'description' => 'Schreibt oder aktualisiert ein Artefakt (z.B. einen Implementierungsplan oder Workflow). Nützlich, um strukturierte, persistente Pläne zu dokumentieren, die der User im UI überprüfen kann. Muss bei Architekturänderungen immer ausgeführt werden!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'artifact_name' => [
                            'type' => 'string',
                            'description' => 'Name des Artefakts ohne Dateiendung (z.B. implementation_plan oder workflow).'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Der gesamte Inhalt im Markdown Format.'
                        ]
                    ],
                    'required' => ['artifact_name', 'content']
                ],
                'callable' => [self::class, 'executeWriteArtifact']
            ]
        ];
    }


    public static function executeVisualizeData(array $args)
    {
        $category = strtolower($args['category'] ?? 'general');

        // Safety Fallbacks & Aliases
        if ($category === 'coupon' || $category === 'gutschein' || $category === 'coupons') {
            $category = 'voucher';
        }

        $data = $args['data'] ?? [];

        return [
            'status' => 'success',
            'message' => "Habe ein UI Master Modal für die Kategorie '{$category}' geöffnet.",
            '_frontend_event' => [
                'name' => 'open-ai-visualization',
                'detail' => [
                    'category' => $category,
                    'data' => $data
                ]
            ],
            '_fast_track' => true
        ];
    }



    public static function executeCloseUi(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Die UI wurde erfolgreich geschlossen.'
        ];
    }

    public static function executeOpenNavItem(array $args)
    {
        try {
            if (empty($args['url'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine URL übergeben.'];
            }

            $url = $args['url'];
            $structure = \App\Services\Navigation\BackendNavigationService::getStructure();
            
            $bestMatchUrl = null;

            // 1. Check for exact matches or very close text matches in the centralized config
            foreach ($structure as $section) {
                foreach ($section['items'] as $item) {
                    if ($item['type'] === 'single') {
                        if ($item['route'] === $url) $bestMatchUrl = $item['route'];
                    } elseif ($item['type'] === 'group') {
                        foreach ($item['children'] as $child) {
                            if ($child['route'] === $url) $bestMatchUrl = $child['route'];
                        }
                    }
                }
            }

            // 2. Automatisches & Dynamisches Index-Building aus der Backend-Navigation
            $fallbacks = [];
            
            // Spezifische Synonyme (Human in the Loop Slang -> Offizielle Route)
            $synonyms = [
                'financial-evaluation' => '/admin/financial-analytics',
                'financials' => '/admin/financial-analytics',
                'beleg' => '/admin/financial-variable-costs',
                'ausgabe' => '/admin/financial-variable-costs',
                'einkauf' => '/admin/financial-variable-costs',
                'schwund' => '/admin/product-fracture',
                'bruch' => '/admin/product-fracture',
                'schaden' => '/admin/product-fracture',
                'gutschrift' => '/admin/credit-management',
                'rueckerstattung' => '/admin/credit-management',
                'rechnung' => '/admin/invoices',
                'steuer' => '/admin/financial-tax',
                'bank' => '/admin/financial-banks',
                'konten' => '/admin/financial-banks',
            ];

            foreach ($structure as $section) {
                foreach ($section['items'] as $item) {
                    if ($item['type'] === 'single') {
                        $fallbacks[strtolower($item['title'])] = $item['route'];
                        $fallbacks[strtolower(basename($item['route']))] = $item['route'];
                    } elseif ($item['type'] === 'group') {
                        if (!empty($item['children'])) {
                            $fallbacks[strtolower($item['title'])] = $item['children'][0]['route'];
                        }
                        foreach ($item['children'] as $child) {
                            $fallbacks[strtolower($child['title'])] = $child['route'];
                            $fallbacks[strtolower(basename($child['route']))] = $child['route'];
                        }
                    }
                }
            }

            // Synonyme überschreiben die rohen Titel falls es Überschneidungen (z.B. Rechnung) gibt
            $fallbacks = array_merge($fallbacks, $synonyms);

            if (!$bestMatchUrl) {
                foreach ($fallbacks as $keyword => $targetUrl) {
                    if (str_contains(strtolower($url), $keyword)) {
                        // Exclude specific words for 'rechnung' to avoid overlap with others
                        if ($keyword === 'rechnung' && (str_contains(strtolower($url), 'eingangs') || str_contains(strtolower($url), 'variable'))) {
                            continue;
                        }
                        $bestMatchUrl = $targetUrl;
                        break;
                    }
                }
            }

            $url = $bestMatchUrl ?: $url;

            if ($url === 'switch_workspace_view:knowledge-base' || str_contains(strtolower($url), 'wissen') || str_contains(strtolower($url), 'rag')) {
                return [
                    'status' => 'success',
                    'message' => 'Die Wissensdatenbank wird nun clientseitig im Arbeitsbereich geöffnet.',
                    '_event' => [
                        'type' => 'dispatch',
                        'name' => 'open-ai-workspace-view',
                        'detail' => ['view' => 'knowledge-base']
                    ],
                    '_fast_track' => true
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Die Navigation wird nun clientseitig ausgeführt.',
                '_event' => [
                    'type' => 'navigate',
                    'url' => $url
                ],
                '_fast_track' => true
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der Navigation: ' . $e->getMessage()];
        }
    }
    public static function executeOpenZentrum(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Das Zentrum öffnet sich in diesem Augenblick im Browser.',
            '_event' => [
                'type' => 'dispatch',
                'name' => 'open-funkira'
            ],
            '_fast_track' => true
        ];
    }

    public static function executeCloseZentrum(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Das Zentrum schließt sich in diesem Augenblick im Browser.',
            '_event' => [
                'type' => 'dispatch',
                'name' => 'close-funkira'
            ],
            '_fast_track' => true
        ];
    }

    public static function executeSearchChatHistory(array $args)
    {
        $timeFilter = $args['time_filter'] ?? 'all';
        $keyword = $args['keyword'] ?? null;

        $query = \App\Models\Ai\AiChatMemory::where('session_id', session()->getId())
                                            ->orderBy('created_at', 'desc');

        switch ($timeFilter) {
            case 'today':
                $query->whereDate('created_at', \Carbon\Carbon::today());
                break;
            case 'yesterday':
                $query->whereDate('created_at', \Carbon\Carbon::yesterday());
                break;
            case 'last_week':
                $query->where('created_at', '>=', \Carbon\Carbon::now()->subDays(7));
                break;
        }

        if ($keyword) {
            $query->where('content', 'like', '%' . $keyword . '%');
        }

        $memories = $query->limit(50)->get();

        if ($memories->isEmpty()) {
            return [
                'status' => 'empty',
                'message' => 'Es wurden keine passenden Erinnerungen oder Logs zu dieser Suchanfrage in deiner aktuellen Session gefunden.'
            ];
        }

        $formattedLogs = $memories->map(function ($m) {
            return "[{$m->created_at->format('d.m. H:i')}] - Rolle: {$m->role} - Inhalt: {$m->content}";
        })->implode("\n");

        return [
            'status' => 'success',
            'summary' => 'Folgende Protokoll-Fetzen wurden im Chat-Verlauf gefunden (neueste zuerst):',
            'logs' => $formattedLogs
        ];
    }

    public static function executeGetSystemHealth(array $args)
    {
        try {
            $analytics = new \App\Livewire\Shop\Master\MasterAnalytics();
            $analytics->checkSystemHealth();
            $isHealthy = $analytics->isSystemHealthy();

            $analytics->dateStart = now()->startOfMonth()->format('Y-m-d');
            $analytics->dateEnd = now()->endOfMonth()->format('Y-m-d');
            $analytics->filterType = 'all';

            $service = app(\App\Services\AnalyticsService::class);
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

            if (class_exists(\App\Models\System\SystemLog::class)) {
                $agent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first() ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();
                \App\Models\System\SystemLog::create([
                    'ai_agent_id' => $agent ? $agent->id : null,
                    'title' => '[FUNKIRA] - System Healing',
                    'message' => '[Funkira] - Caches, Configs und Views wurden geleert. Queue-Worker Restart angefragt.',
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
            if (!class_exists(\App\Models\System\SystemLog::class)) {
                return ['status' => 'error', 'message' => 'GlobalLog-Klasse ist im System nicht existent.'];
            }

            // Hole nur die echten System/KI/Auto-Warnungen und Fehler der letzten 24h
            $logs = \App\Models\System\SystemLog::whereIn('status', ['error', 'warning'])
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

    public static function executeReadWikiFiles(array $args)
    {
        try {
            $query = $args['filename_query'] ?? null;
            $files = Storage::disk('public')->files('Shop/Ai/KnowledgeBase');

            if (empty($files)) {
                return ['status' => 'error', 'message' => "Es befinden sich aktuell keine Dateien im Wiki-Ordner. Der Benutzer muss erst Dateien hochladen."];
            }

            $output = "Gefundene Dateien im Wiki:\n\n";
            $contentFound = false;

            foreach ($files as $file) {
                $filename = basename($file);

                if ($query && stripos($filename, $query) === false) continue;

                \Illuminate\Support\Facades\Log::info("Funkira liest Wiki-Datei: " . $filename);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $output .= "### Datei: $filename\n";
                $contentFound = true;

                if (in_array($ext, ['txt', 'md', 'csv', 'json', 'log'])) {
                    $content = Storage::disk('public')->get($file);
                    $content = Str::limit($content, 8000);
                    $output .= "- Inhalt:\n" . $content . "\n\n";
                } elseif ($ext === 'docx') {
                    $zip = new ZipArchive;
                    $absPath = Storage::disk('public')->path($file);
                    if ($zip->open($absPath) === true) {
                        if (($index = $zip->locateName('word/document.xml')) !== false) {
                            $data = $zip->getFromIndex($index);
                            $zip->close();

                            // Remove all XML tags except for w:p (paragraphs) to create clean breaks
                            $data = str_replace('</w:p>', "\n\n", $data);
                            $data = str_replace('</w:tr>', "\n", $data); // Table rows
                            $data = strip_tags($data);

                            $text = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
                            // Clean up multiple newlines
                            $text = preg_replace("/\n{3,}/", "\n\n", $text);

                            $text = Str::limit(trim($text), 8000);
                            $output .= "- Inhalt:\n" . $text . "\n\n";
                        } else {
                            $zip->close();
                            $output .= "- Fehler: Konnte den Text nicht aus der DOCX-Datei extrahieren.\n\n";
                        }
                    } else {
                        $output .= "- Fehler: Konnte die DOCX-Datei nicht öffnen.\n\n";
                    }
                } elseif ($ext === 'doc') {
                    $output .= "- (DOC Format): Das veraltete '.doc' Format kann ich nicht direkt lesen. Bitte weise den Benutzer an, die Datei als '.docx' zu speichern.\n\n";
                } elseif ($ext === 'pdf') {
                    $output .= "- (PDF Format): Aktuell kann ich PDFs nicht nativ lesen. Bitte als TXT/MD hochladen.\n\n";
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

    public static function executeGetSystemMap(array $args)
    {
        try {
            $modelsPath = app_path('Models');

            if (!is_dir($modelsPath)) {
                return ['status' => 'error', 'message' => 'Models Verzeichnis nicht gefunden.'];
            }

            $map = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modelsPath));

            foreach ($iterator as $file) {
                if ($file->isDir()) continue;

                if ($file->getExtension() === 'php') {
                    $relativePath = str_replace($modelsPath . '/', '', $file->getPathname());
                    $parts = explode('/', $relativePath);

                    if (count($parts) > 1) {
                        $module = $parts[0];
                        $modelName = str_replace('.php', '', $parts[1]);

                        if (!isset($map[$module])) {
                            $map[$module] = [];
                        }
                        $map[$module][] = $modelName;
                    } else {
                        $modelName = str_replace('.php', '', $parts[0]);
                        if (!isset($map['Core'])) {
                            $map['Core'] = [];
                        }
                        $map['Core'][] = $modelName;
                    }
                }
            }

            $output = "System Architektur (Datenstruktur):\n";
            ksort($map);

            foreach ($map as $module => $models) {
                $output .= "\n[$module]\n";
                foreach ($models as $model) {
                    $output .= "- $model\n";
                }
            }

            $output .= "\nINFO FÜR FUNKIRA: Vergleiche diese Entitäten mit deinen verfügbaren Werkzeugen (tools). Wenn in der App Daten existieren (z.B. Returns, Newsletter, Tracking), für die dir noch Werkzeuge fehlen, weise den Benutzer darauf hin, dass diese programmiert werden müssen, damit du darüber Kontrolle erlangst.";

            $output .= "\n\nVERFÜGBARE SEITEN (NAVIGATION):\nFolgende Seiten existieren im System und können von dir mit dem Tool 'open_nav_item' aufgerufen werden:\n";
            $output .= \App\Services\Navigation\BackendNavigationService::getAiNavigationPrompt();

            return [
                'status' => 'success',
                'system_map' => $output
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Generieren der System-Map: ' . $e->getMessage()];
        }
    }

    public static function executeUpdateFunkiraConfiguration(array $args)
    {
        $restrictedKeys = ['api_provider', 'api_key', 'local_tts_url'];
        $changes = [];
        $errors = [];

        foreach ($args as $key => $value) {
            if (in_array($key, $restrictedKeys)) {
                $errors[] = "Sicherheits-Sperre: Du darfst die Einstellung '$key' nicht verändern.";
                continue;
            }

            if ($key === 'human_in_the_loop' && filter_var($value, FILTER_VALIDATE_BOOLEAN) === false) {
                $errors[] = "Sicherheits-Sperre: Du darfst Human-in-the-Loop nicht deaktivieren.";
                continue;
            }

            if ($key === 'execution_limit') {
                $currentLimit = (int) (\App\Models\Ai\AiAgentSetting::where('key', 'execution_limit')->value('value') ?? 3);
                if ((int)$value > $currentLimit) {
                    $errors[] = "Sicherheits-Sperre: Du darfst dein Ausführungs-Limit nicht erhöhen (Aktuell: $currentLimit, Versucht: $value).";
                    continue;
                }
            }

            if (is_bool($value)) {
                $valueToSave = $value ? '1' : '0';
            } else {
                $valueToSave = (string) $value;
            }

            \App\Models\Ai\AiAgentSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $valueToSave]
            );
            $changes[] = "$key => " . ($valueToSave === '' ? 'leer' : $valueToSave);
        }

        $result = [];
        if (!empty($changes)) {
            $result['success'] = "Folgendes wurde geändert: " . implode(', ', $changes);
        }
        if (!empty($errors)) {
            $result['failed'] = implode(' ', $errors);
        }

        if (empty($changes) && empty($errors)) {
            return ['status' => 'success', 'message' => 'Keine Einstellungen übergeben.'];
        }

        return ['status' => 'success', 'result' => $result];
    }

    public static function executeAgentConfig(array $args) {
        $key = $args['setting_key'] ?? null;
        $val = $args['setting_value'] ?? null;

        if (!$key || $val === null) {
            return ['status' => 'error', 'message' => 'Missing key or value'];
        }

        $agent = AiAgent::where('name', 'Funkira')->where('is_active', true)->first() ?? AiAgent::where('is_active', true)->first();

        if (!$agent) {
            return ['status' => 'error', 'message' => 'No agent found to edit'];
        }

        if (!in_array($key, ['tts_speed', 'temperature', 'wake_word', 'name'])) {
            return ['status' => 'error', 'message' => 'Invalid setting key. Allowed: tts_speed, temperature, wake_word, name'];
        }

        $oldValue = $agent->{$key};

        if ($key === 'tts_speed' || $key === 'temperature') {
            $val = (float) $val;
        }

        $agent->{$key} = $val;
        $agent->save();

        return [
            'status' => 'success',
            'message' => "Erfolgreich geändert von {$oldValue} auf {$val}.",
            'changed_key' => $key,
            'new_value' => $val,
            'ui_action' => 'reload_config'
        ];
    }

    public static function executeSearchFiles(array $args)
    {
        $query = $args['filename_query'] ?? '';
        if (strlen($query) < 2) return ['status' => 'error', 'message' => 'Suchbegriff zu kurz. Mindestens 2 Zeichen.'];

        $basePath = base_path();
        $allowedDirs = ['app', 'config', 'resources', 'routes', 'database'];
        $results = [];

        foreach ($allowedDirs as $dir) {
            $path = $basePath . '/' . $dir;
            if (!is_dir($path)) continue;

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isDir()) continue;
                if (stripos($file->getFilename(), $query) !== false) {
                    $results[] = str_replace($basePath . '/', '', $file->getPathname());
                    if (count($results) >= 50) break 2; // Stop traversing after 50 hits
                }
            }
        }

        if (empty($results)) {
            return ['status' => 'success', 'message' => 'Keine passenden Dateien gefunden.'];
        }

        return ['status' => 'success', 'files' => $results, 'message' => 'Maximal 50 Treffer angezeigt.'];
    }

    public static function executeSearchCode(array $args)
    {
        $query = $args['search_query'] ?? '';
        if (strlen($query) < 3) return ['status' => 'error', 'message' => 'Suchbegriff zu kurz (min. 3 Zeichen).'];

        $basePath = base_path();
        $allowedDirs = ['app', 'config', 'resources', 'routes', 'database'];
        
        $searchDirs = collect($allowedDirs)->map(fn($d) => escapeshellarg($basePath . '/' . $d))->implode(' ');
        $escapedQuery = escapeshellarg($query);
        
        // grep recursive, no-filename... wait we want filename, so default.
        // -r recursive, -n line numbers, -I ignore binary, -i case insensitive
        $cmd = "grep -rnIi $escapedQuery $searchDirs | head -n 50";
        
        exec($cmd, $output, $returnVar);

        if (empty($output)) {
             return ['status' => 'success', 'message' => 'Keine Treffer im Quellcode gefunden.'];
        }

        $formatted = [];
        foreach ($output as $line) {
            $cleanLine = str_replace($basePath . '/', '', $line);
            $formatted[] = $cleanLine;
        }

        return ['status' => 'success', 'matches' => $formatted, 'message' => 'Zeigt maximal die ersten 50 Treffer an (Format: Pfad:Zeile:Inhalt). Lese die Datei mit system_read_code für mehr Kontext ein.'];
    }

    public static function executeReadCode(array $args)
    {
        $path = ltrim($args['file_path'] ?? '', '/');
        if (empty($path)) return ['status' => 'error', 'message' => 'Kein Dateipfad angegeben.'];

        $fullPath = base_path($path);
        
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return ['status' => 'error', 'message' => "Datei '$path' existiert nicht. Bitte mit system_search_files überprüfen!"];
        }
        
        if (!str_starts_with(realpath($fullPath), realpath(base_path()))) {
             return ['status' => 'error', 'message' => 'Zugriff verweigert. Dateipfad liegt außerhalb des erlaubten Projektverzeichnisses.'];
        }

        if (str_contains(basename($fullPath), '.env')) {
             return ['status' => 'error', 'message' => 'Sicherheitsrichtlinie: .env Dateien dürfen nicht gelesen oder bearbeitet werden.'];
        }

        $lines = file($fullPath);
        if ($lines === false) {
             return ['status' => 'error', 'message' => 'Konnte Datei nicht lesen.'];
        }

        $startLine = isset($args['start_line']) ? max(1, (int)$args['start_line']) : 1;
        $endLine = isset($args['end_line']) ? min(count($lines), (int)$args['end_line']) : count($lines);

        if ($endLine - $startLine > 2000) {
             $endLine = $startLine + 2000;
             $warnings = " | WARNUNG: Ausgabe wurde zum Schutz deines Tokenspeichers auf 2000 Zeilen begrenzt. Benutze start_line und end_line für Paginierung.";
        } else {
             $warnings = "";
        }

        $slicedLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
        
        $contentLines = [];
        $currentLine = $startLine;
        foreach ($slicedLines as $l) {
            $contentLines[] = str_pad($currentLine, 4, ' ', STR_PAD_LEFT) . " | " . rtrim($l, "\r\n");
            $currentLine++;
        }

        $content = implode("\n", $contentLines);

        return [
            'status' => 'success', 
            'file' => $path,
            'info' => "Gelesene Zeilen $startLine bis $endLine von insgesamt " . count($lines) . " Zeilen." . $warnings,
            'code' => $content
        ];
    }

    public static function executeEditFile(array $args)
    {
        $path = ltrim($args['file_path'] ?? '', '/');
        $search = $args['search_content'] ?? '';
        $replace = $args['replace_content'] ?? '';

        if (empty($path) || empty($search)) {
            return ['status' => 'error', 'message' => 'file_path oder search_content fehlen.'];
        }

        $fullPath = base_path($path);

        // Path Traversal Check
        if (!file_exists($fullPath)) {
            return ['status' => 'error', 'message' => "Datei '$path' existiert nicht."];
        }

        if (!str_starts_with(realpath($fullPath), realpath(base_path()))) {
             return ['status' => 'error', 'message' => 'Zugriff verweigert. Dateipfad liegt außerhalb des erlaubten Projektverzeichnisses.'];
        }

        if (str_contains(basename($fullPath), '.env')) {
             return ['status' => 'error', 'message' => 'Sicherheitsrichtlinie: .env Dateien dürfen nicht bearbeitet werden.'];
        }

        $content = file_get_contents($fullPath);
        
        // Remove line numbers from search block if the AI accidentally copied them from read_tool
        $cleanSearch = preg_replace('/^\s*\d+\s*\|\s/m', '', $search);
        $cleanReplace = preg_replace('/^\s*\d+\s*\|\s/m', '', $replace);

        if (strpos($content, $cleanSearch) !== false) {
            $newContent = str_replace($cleanSearch, $cleanReplace, $content);
        } else {
            // Fallback: Whitespace-tolerant regex search
            $regexSafeSearch = preg_quote(trim($cleanSearch), '/');
            // Allow any combination of spaces, tabs, and newlines between words to match
            $regexSafeSearch = preg_replace('/[ \t\r\n]+/', '\s+', $regexSafeSearch);
            
            // Prepend a capture group for any leading spaces/tabs on the line where the match starts
            $regex = '/([ \t]*)' . $regexSafeSearch . '/s';

            if (preg_match($regex, $content, $matches)) {
                $matchedOriginal = $matches[0];
                $indentation = $matches[1] ?? '';
                
                // Calculate minimal indentation of the replacement block to make it relative
                $replaceLines = explode("\n", trim($cleanReplace, "\r\n"));
                $minReplaceIndent = null;
                foreach ($replaceLines as $line) {
                    if (trim($line) === '') continue;
                    preg_match('/^[ \t]*/', $line, $ind);
                    $len = strlen($ind[0]);
                    if ($minReplaceIndent === null || $len < $minReplaceIndent) {
                        $minReplaceIndent = $len;
                    }
                }
                
                // Remove relative indentation and add the target indentation
                $indentedReplace = implode("\n", array_map(function($line) use ($indentation, $minReplaceIndent) {
                    if (trim($line) === '') return '';
                    return $indentation . substr($line, $minReplaceIndent);
                }, $replaceLines));

                // We replace the matched block (which now includes the leading indentation)
                $newContent = str_replace($matchedOriginal, $indentedReplace, $content);
            } else {
                return ['status' => 'error', 'message' => 'Der gesuchte search_content Block wurde nicht in der Datei gefunden. Weder exakt noch tolerant. Bitte überprüfe die Datei mit system_read_code!'];
            }
        }
        file_put_contents($fullPath, $newContent);

        $deletedLines = substr_count($cleanSearch, "\n") + 1;
        $addedLines = substr_count($replace, "\n") + 1;

        return [
            'status' => 'success',
            'message' => "Die Datei '$path' wurde erfolgreich geändert!",
            '_frontend_thought_stream' => '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-emerald-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-gray-300 truncate max-w-full font-bold">' . basename($path) . '</div>
                               <div class="flex gap-2.5 mt-0.5">
                                   <span class="text-emerald-400">+' . $addedLines . ' Zeilen</span>
                                   <span class="text-red-400">-' . $deletedLines . ' Zeilen</span>
                               </div>
                           </div>'
        ];
    }

    public static function executeWriteToFile(array $args)
    {
        $path = ltrim($args['file_path'] ?? '', '/');
        $newContent = $args['new_content'] ?? '';

        if (empty($path)) {
            return ['status' => 'error', 'message' => 'file_path fehlt.'];
        }

        $fullPath = base_path($path);
        $dir = dirname($fullPath);

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        // Path Traversal Check
        if (!str_starts_with(realpath($dir), realpath(base_path()))) {
             return ['status' => 'error', 'message' => 'Zugriff verweigert. Dateipfad liegt außerhalb des erlaubten Projektverzeichnisses.'];
        }

        if (str_contains(basename($fullPath), '.env')) {
             return ['status' => 'error', 'message' => 'Sicherheitsrichtlinie: .env Dateien dürfen nicht überschrieben werden.'];
        }

        file_put_contents($fullPath, $newContent);

        $addedLines = substr_count($newContent, "\n") + 1;

        return [
            'status' => 'success',
            'message' => "Die Datei '$path' wurde erfolgreich komplett überschrieben / angelegt!",
            '_frontend_thought_stream' => '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-emerald-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-gray-300 truncate max-w-full font-bold">' . basename($path) . '</div>
                               <div class="flex gap-2.5 mt-0.5">
                                   <span class="text-emerald-400">+' . $addedLines . ' Zeilen</span>
                                   <span class="text-gray-500 italic">überschrieben/neu angelegt</span>
                               </div>
                           </div>'
        ];
    }

    public static function executeWriteArtifact(array $args)
    {
        $name = ltrim($args['artifact_name'] ?? '', '/');
        $content = $args['content'] ?? '';

        if (empty($name)) {
            return ['status' => 'error', 'message' => 'artifact_name fehlt.'];
        }

        $sessionId = session()->getId();
        if (!$sessionId) {
            return ['status' => 'error', 'message' => 'Keine aktive Session für Artefakt-Speicherung gefunden.'];
        }

        $filename = str_replace(' ', '_', strtolower($name)) . '.md';
        $path = 'ai-artifacts/' . $sessionId . '/' . $filename;
        
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $content);

        $addedLines = substr_count($content, "\n") + 1;

        return [
            'status' => 'success',
            'message' => "Artefakt '$filename' wurde erfolgreich gespeichert und ist im UI im Tab 'Pläne' abrufbar.",
            '_frontend_thought_stream' => '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-indigo-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-indigo-300 truncate max-w-full font-bold"><x-heroicon-o-document-check class="w-3 h-3 inline-block -mt-0.5" /> ' . $filename . '</div>
                               <div class="flex gap-2.5 mt-0.5">
                                   <span class="text-indigo-400">Artefakt generiert (' . $addedLines . ' Zeilen)</span>
                               </div>
                           </div>'
        ];
    }

    public static function executeAssignToolToRole(array $args, $agent = null)
    {
        $toolId = $args['tool_identifier'] ?? '';
        if (empty($toolId) || !$agent || !$agent->ai_role_id) {
            return "Fehler: Tool-Indentifier fehlt oder dir fehlt die feste Rolle im System.";
        }

        $tool = \App\Models\Ai\AiTool::where('identifier', $toolId)->first();
        if (!$tool) {
            return "Fehler: Das Werkzeug '{$toolId}' existiert nicht in der Datenbank.";
        }

        $role = \App\Models\Ai\AiRole::find($agent->ai_role_id);
        if ($role) {
            $role->tools()->syncWithoutDetaching([$tool->id]);
            return [
                'status' => 'success',
                'message' => "WICHTIG: Das Werkzeug '{$toolId}' wurde dir soeben in deiner Rolle '{$role->name}' aktiv freigeschaltet. Bitte bestätige dem User umgehend, dass du diese Fähigkeit ab sofort besitzt und anwenden kannst.",
                '_frontend_thought_stream' => '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-purple-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-purple-300 truncate max-w-full font-bold"><i class="bi bi-puzzle-fill"></i> Fähigkeit angeeignet</div>
                               <div class="flex gap-2.5 mt-0.5 text-xs">
                                   <span class="text-purple-400 font-black">' . $toolId . '</span>
                               </div>
                           </div>'
            ];
        }

        return "Fehler: System-Rolle nicht gefunden.";
    }
}

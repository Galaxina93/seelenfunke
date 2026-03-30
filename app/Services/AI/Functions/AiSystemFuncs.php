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
                            'description' => 'Grobe Kategorie der Daten in Kleinschreibung (z.B. "voucher", "customer", "task", "finance", "system_health").'
                        ],
                        'data' => [
                            'type' => 'array',
                            'description' => 'Die nativen rohen JSON-Daten als Array. Das Backend kümmert sich um das Design.',
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
                'description' => 'Liest direkt und asynchron den gesamten Text der großen Wiki-Dokumente und Wissens-Dateien aus (kein DB-Memory!). Stichworte: Suche in den Dokumenten, Lies im internen Firmen-Wiki, Welche PDF Regeln gibt es, Lese das Handbuch.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filename_query' => [
                            'type' => 'string',
                            'description' => 'EXAKTER Dateiname. ACHTUNG: Nutze dies NUR, wenn du eine ganz bestimmte Datei meinst (z.B. "Richtlinien.pdf") und deren Name exakt kennst. Wenn du eine Information / ein Thema suchst, lass diesen Parameter ZWINGEND LEER!'
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
            $analytics = new \App\Livewire\Global\Widgets\Analytics();
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
            $files = Storage::disk('public')->files('wiki');

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
}

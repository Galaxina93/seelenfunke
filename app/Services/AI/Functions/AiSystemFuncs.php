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
                'name' => 'system_multi_replace_file',
                'description' => 'Ersetzt mehrere nicht zusammenhängende (oder zusammenhängende) Code-Blöcke in einer Datei präzise. WICHTIGE REGEL: Im Autonomous / Execution Mode darfst und sollst du dieses Tool direkt ausführen, um Dateien selbstständig zu modifizieren und Bugs aktiv zu beheben. Erstelle bei größeren Änderungen erst ein "implementation_plan" Artefakt. Um Code zu bearbeiten MUSST du dieses Tool benutzen! Nutze start_line und end_line zur Orientierung auf Basis von system_read_code.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [
                            'type' => 'string',
                            'description' => 'Der relative Dateipfad vom Projekt-Root aus.'
                        ],
                        'chunks' => [
                            'type' => 'array',
                            'description' => 'Eine Liste der Code-Schnipsel, die ersetzt werden sollen. Mehrere Änderungen können im gleichen Vorgang übergeben werden.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'start_line' => ['type' => 'integer', 'description' => 'Ungefähre Startzeile der originalen Suche aus system_read_code.'],
                                    'end_line' => ['type' => 'integer', 'description' => 'Ungefähre Endzeile der Suche.'],
                                    'search_content' => ['type' => 'string', 'description' => 'Exakter Inhalt, der ausgetauscht wird, OHNE Zeilennummern.'],
                                    'replace_content' => ['type' => 'string', 'description' => 'Neuer Code, der ausgetauscht wird.']
                                ],
                                'required' => ['start_line', 'end_line', 'search_content', 'replace_content']
                            ]
                        ]
                    ],
                    'required' => ['file_path', 'chunks']
                ],
                'callable' => [self::class, 'executeMultiReplaceFile']
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
            ],
            [
                'name' => 'system_write_knowledge',
                'description' => 'Speichert wichtige Architektur-Entscheidungen, Regeln oder Masterpläne GLOBAL ab. Im Gegensatz zu Artefakten bleiben diese Dokumente über den aktuellen Chat hinaus für immer bestehen und können später wieder abgerufen werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => [
                            'type' => 'string',
                            'description' => 'Kurzer, prägnanter Name des Themas ohne Leerzeichen (z.B. laravel_api_rules, warenkorb_architektur).'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Der gesamte Inhalt im Markdown Format.'
                        ]
                    ],
                    'required' => ['topic', 'content']
                ],
                'callable' => [self::class, 'executeWriteKnowledge']
            ],
            [
                'name' => 'system_read_knowledge',
                'description' => 'Liest ein persistentes globales Wissens-Dokument (Knowledge Item). Nützlich, wenn du in einem neuen Chat einen alten Plan oder alte Architektur-Regeln abrufen möchtest.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => [
                            'type' => 'string',
                            'description' => 'Der genaue Name des Themas (z.B. laravel_api_rules).'
                        ]
                    ],
                    'required' => ['topic']
                ],
                'callable' => [self::class, 'executeReadKnowledge']
            ],
            [
                'name' => 'system_run_command',
                'description' => 'Führt asynchron einen sicheren Bash-/Artisan-/NPM-Befehl im Hintergrund aus. Diese Aktion ist destruktiv und unterliegt dem Guardrail-Schutz. Gibt eine Job-ID zurück.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'command' => [
                            'type' => 'string',
                            'description' => 'Der exakte Bash-Befehl (z.B. "php artisan test", "npm run build").'
                        ]
                    ],
                    'required' => ['command']
                ],
                'callable' => [self::class, 'executeRunCommand']
            ],
            [
                'name' => 'system_command_status',
                'description' => 'Liest den Status und Output eines asynchron im Hintergrund ausgeführten Befehls aus (Polling).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'job_id' => [
                            'type' => 'string',
                            'description' => 'Die Job-ID, die von system_run_command zurückgegeben wurde.'
                        ]
                    ],
                    'required' => ['job_id']
                ],
                'callable' => [self::class, 'executeCommandStatus']
            ],
            [
                'name' => 'system_request_user_approval',
                'description' => 'Pausiert die Ausführung und fragt den User nach einer expliziten Erlaubnis/Freigabe für einen generierten Plan. Nutze dies IMMER, nachdem du ein "implementation_plan" Artefakt erstellt hast. Du darfst nicht weiterarbeiten, bevor der User zustimmt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeRequestUserApproval']
            ],
            [
                'name' => 'system_list_directory',
                'description' => 'Liest alle Dateien und Ordner innerhalb eines angegebenen relativen Verzeichnisses aus. Nutze dies, um Ordnerstrukturen zu navigieren und Dateien zu finden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'directory_path' => [
                            'type' => 'string',
                            'description' => 'Der relative Pfad zum Ordner (z.B. "app/Models" oder "resources/views").'
                        ]
                    ],
                    'required' => ['directory_path']
                ],
                'callable' => [self::class, 'executeListDirectory']
            ],
            [
                'name' => 'system_read_web_url',
                'description' => 'Liest den rohen Text-Inhalt einer beliebigen öffentlichen URL aus (z.B. für Dokumentationen). Ohne JavaScript-Ausführung.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'Die vollständige URL, die abgerufen werden soll (https://...).'
                        ]
                    ],
                    'required' => ['url']
                ],
                'callable' => [self::class, 'executeReadWebUrl']
            ],
            [
                'name' => 'system_search_web',
                'description' => 'Führt eine Internet-Suche aus, um externe Informationen, Dokumentationen oder Fehlermeldungen im Web zu recherchieren.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff.'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeSearchWeb']
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
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Die Navigation wird nun clientseitig ausgeführt.',
                '_event' => [
                    'type' => 'navigate',
                    'url' => $url
                ],
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

    public static function executeMultiReplaceFile(array $args)
    {
        $path = ltrim($args['file_path'] ?? '', '/');
        $chunks = $args['chunks'] ?? [];

        if (empty($path)) {
            return ['status' => 'error', 'message' => 'file_path fehlt.'];
        }

        if (empty($chunks) || !is_array($chunks)) {
            return ['status' => 'error', 'message' => 'chunks array fehlt oder ist leer.'];
        }

        $fullPath = base_path($path);

        // Path Traversal Check
        if (!file_exists($fullPath)) {
            return ['status' => 'error', 'message' => "Datei '$path' existiert nicht."];
        }

        if (!str_starts_with(realpath($fullPath), realpath(base_path()))) {
             return ['status' => 'error', 'message' => 'Zugriff verweigert. Dateipfad liegt außerhalb des Projektverzeichnisses.'];
        }

        if (str_contains(basename($fullPath), '.env')) {
             return ['status' => 'error', 'message' => 'Sicherheitsrichtlinie: .env Dateien dürfen nicht bearbeitet werden.'];
        }

        $content = file_get_contents($fullPath);
        $totalChunksProcessed = 0;
        $failedChunks = [];
        $totalAddedLines = 0;
        $totalDeletedLines = 0;

        foreach ($chunks as $index => $chunk) {
            $search = $chunk['search_content'] ?? '';
            $replace = $chunk['replace_content'] ?? '';

            if (empty($search)) {
                $failedChunks[] = "Chunk $index: search_content leer.";
                continue;
            }

            // Clean up left-over AI prepended line numbers (like " 12 | ") if they accidentally copy-pasted read_code output
            $cleanSearch = preg_replace('/^\s*\d+\s*\|\s/m', '', $search);
            $cleanReplace = preg_replace('/^\s*\d+\s*\|\s/m', '', $replace);

            $totalDeletedLines += substr_count($cleanSearch, "\n") + 1;
            $totalAddedLines += substr_count($cleanReplace, "\n") + 1;

            if (strpos($content, $cleanSearch) !== false) {
                // Determine if it occurs exactly ONCE
                $count = substr_count($content, $cleanSearch);
                if ($count > 1) {
                    $failedChunks[] = "Chunk $index: Target-Content mehrfach ($count) gefunden! Bitte den Search-Block vergrößern, um ihn einzigartig zu machen.";
                    continue;
                }
                
                $content = str_replace($cleanSearch, $cleanReplace, $content);
                $totalChunksProcessed++;
            } else {
                // Fallback Regex ignore whitespace
                $regexSafeSearch = preg_quote(trim($cleanSearch), '/');
                $regexSafeSearch = preg_replace('/[ \t\r\n]+/', '\s+', $regexSafeSearch);
                
                if (preg_match("/$regexSafeSearch/", $content, $matches) && count($matches) === 1) {
                    $content = preg_replace("/$regexSafeSearch/", ltrim($cleanReplace), $content, 1);
                    $totalChunksProcessed++;
                } else if (preg_match_all("/$regexSafeSearch/", $content) > 1) {
                    $failedChunks[] = "Chunk $index: Gefunden, aber nicht einzigartig. Bitte Search-Block vergrößern.";
                } else {
                    $failedChunks[] = "Chunk $index: Search-Block gar nicht gefunden. (Wahrscheinlich Einrückungen oder falscher Text).";
                }
            }
        }

        if ($totalChunksProcessed === 0 && !empty($failedChunks)) {
            return [
                'status' => 'error',
                'message' => "Fehler bei ALLEN Chunks. Nichts wurde gespeichert. Gründe:\n" . implode("\n", $failedChunks)
            ];
        }

        if (file_put_contents($fullPath, $content) === false) {
            return ['status' => 'error', 'message' => "Fehler beim Speichern der Datei '$path'. Prüfe Dateirechte."];
        }

        $frontendStreamHtml = '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-emerald-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-gray-300 truncate max-w-full font-bold">' . basename($path) . '</div>
                               <div class="flex gap-2.5 mt-0.5">
                                   <span class="text-emerald-400">+' . $totalAddedLines . ' Zeilen</span>
                                   <span class="text-red-400">-' . $totalDeletedLines . ' Zeilen</span>
                               </div>
                           </div>';

        if (!empty($failedChunks)) {
            return [
                'status' => 'warning',
                'message' => "Datei '$path' gespeichert ($totalChunksProcessed geändert), ABER folgende Chunks schlugen fehl:\n" . implode("\n", $failedChunks),
                '_frontend_event' => [
                    'name' => 'toast',
                    'detail' => ['title' => 'Partial File Edit', 'text' => "Datei '$path' mit Warnungen gespeichert.", 'type' => 'warning']
                ],
                '_frontend_thought_stream' => $frontendStreamHtml
            ];
        }

        return [
            'status' => 'success', 
            'message' => "Erfolgreich $totalChunksProcessed Code-Blöcke in '$path' ausgetauscht.",
            '_frontend_event' => [
                'name' => 'toast',
                'detail' => ['title' => 'File Edit', 'text' => "Datei '$path' erfolgreich gepatched.", 'type' => 'success']
            ],
            '_frontend_thought_stream' => $frontendStreamHtml
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

        $sessionId = config('ai.current_session_id') ?: session()->getId();
        if (!$sessionId) {
            return ['status' => 'error', 'message' => 'Keine aktive Session für Artefakt-Speicherung gefunden.'];
        }

        $filename = str_replace(' ', '_', strtolower($name)) . '.md';
        $path = 'ai-artifacts/' . $sessionId . '/' . $filename;
        
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $content);
        
        // Ensure that the file and directory are readable by www-data
        @chmod(storage_path('app/ai-artifacts'), 0777);
        @chmod(storage_path('app/ai-artifacts/' . $sessionId), 0777);
        @chmod(storage_path('app/' . $path), 0666);

        if (str_contains(strtolower($name), 'implementation_plan') || str_contains(strtolower($name), 'plan')) {
            session()->put('has_ai_implementation_plan', true);
            // We use put() here, which makes it persistent for the session until cleared or expired.
        }

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

    public static function executeWriteKnowledge(array $args)
    {
        $topic = ltrim($args['topic'] ?? '', '/');
        $content = $args['content'] ?? '';

        if (empty($topic)) {
            return ['status' => 'error', 'message' => 'topic fehlt.'];
        }

        $filename = str_replace(' ', '_', strtolower($topic)) . '.md';
        $path = 'ai/knowledge/' . $filename;
        
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $content);

        $addedLines = substr_count($content, "\n") + 1;

        return [
            'status' => 'success',
            'message' => "Knowledge Item '$filename' wurde global dauerhaft gespeichert.",
            '_frontend_thought_stream' => '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-yellow-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-yellow-300 truncate max-w-full font-bold"><x-heroicon-o-academic-cap class="w-3 h-3 inline-block -mt-0.5" /> ' . $filename . '</div>
                               <div class="flex gap-2.5 mt-0.5">
                                   <span class="text-yellow-400">Wissen gesichert (' . $addedLines . ' Zeilen)</span>
                               </div>
                           </div>'
        ];
    }

    public static function executeReadKnowledge(array $args)
    {
        $topic = ltrim($args['topic'] ?? '', '/');

        if (empty($topic)) {
            return ['status' => 'error', 'message' => 'topic fehlt.'];
        }

        $filename = str_replace(' ', '_', strtolower($topic)) . '.md';
        $path = 'ai/knowledge/' . $filename;
        
        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
             return ['status' => 'empty', 'message' => "Das Knowledge Item '$filename' existiert nicht."];
        }

        $content = \Illuminate\Support\Facades\Storage::disk('local')->get($path);

        return [
            'status' => 'success',
            'content' => $content,
            '_frontend_thought_stream' => '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-yellow-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-yellow-300 truncate max-w-full font-bold"><x-heroicon-o-academic-cap class="w-3 h-3 inline-block -mt-0.5" /> ' . $filename . '</div>
                               <div class="flex gap-2.5 mt-0.5">
                                   <span class="text-yellow-400">Wissen in Prompt geladen</span>
                               </div>
                           </div>'
        ];
    }

    public static function executeRunCommand(array $args)
    {
        $cmd = $args['command'] ?? '';
        if (empty($cmd)) {
            return ['status' => 'error', 'message' => 'command fehlt.'];
        }

        $jobId = uniqid('cmd_');
        $logFile = storage_path('logs/' . $jobId . '.log');
        
        $basePath = base_path();
        
        // Anti-destroy safety net
        $disallowed = ['rm -rf /', 'mkfs', 'dd '];
        foreach ($disallowed as $d) {
            if (str_contains($cmd, $d)) {
                return ['status' => 'error', 'message' => 'Command blocked globally for safety.'];
            }
        }

        $safeCmd = escapeshellcmd($cmd);
        $fullCmd = "cd " . escapeshellarg($basePath) . " && (" . $cmd . ") > " . escapeshellarg($logFile) . " 2>&1 & echo $!";
        
        $pid = exec($fullCmd);
        
        \Illuminate\Support\Facades\Cache::put('ai_cmd_pid_' . $jobId, $pid, 3600);

        return [
            'status' => 'success',
            'job_id' => $jobId,
            'message' => "Der Befehl '$cmd' wurde asynchron im Hintergrund gestartet (PID: $pid). Benutze system_command_status mit der job_id '$jobId' um in deinem nächsten Zug nach dem Log-Resultat zu sehen."
        ];
    }

    public static function executeCommandStatus(array $args)
    {
        $jobId = $args['job_id'] ?? '';
        if (empty($jobId)) {
            return ['status' => 'error', 'message' => 'job_id fehlt.'];
        }

        $pid = \Illuminate\Support\Facades\Cache::get('ai_cmd_pid_' . $jobId);
        $logFile = storage_path('logs/' . $jobId . '.log');

        if (!file_exists($logFile)) {
            return ['status' => 'error', 'message' => 'Job log nicht gefunden. Entweder fehlerhafter Job oder noch nicht gestartet.'];
        }

        $output = file_get_contents($logFile);
        $output = \Illuminate\Support\Str::limit($output, 5000, "... (gekürzt, Output zu lang)");

        $isRunning = false;
        if ($pid) {
            $isRunning = posix_getsid((int)$pid) !== false;
        }

        if (!$isRunning) {
            \Illuminate\Support\Facades\Cache::forget('ai_cmd_pid_' . $jobId);
        }

        return [
            'status' => 'success',
            'is_running' => $isRunning,
            'output' => $output,
            'message' => $isRunning ? 'Der Befehl läuft noch...' : 'Der Befehl wurde beendet.'
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

    public static function executeRequestUserApproval(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'SYSTEM: Du hast die Erlaubnis angefragt. BEENDE nun sofort deine Antwort mit einem klaren Hinweis an den User, dass du auf seine Freigabe für deinen eingereichten Plan wartest. Führe VOR der Bestätigung keine weiteren Änderungen durch!',
            '_frontend_thought_stream' => '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-orange-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-orange-300 truncate max-w-full font-bold"><x-heroicon-o-hand-raised class="w-3 h-3 inline-block -mt-0.5" /> Warte auf Freigabe</div>
                               <div class="flex gap-2.5 mt-0.5 text-xs">
                                   <span class="text-orange-400">User Approval Required</span>
                               </div>
                           </div>'
        ];
    }

    public static function executeListDirectory(array $args)
    {
        $dirPath = base_path(ltrim($args['directory_path'] ?? '', '/'));
        
        if (!is_dir($dirPath)) {
            return ['status' => 'error', 'message' => "Das Verzeichnis existiert nicht: {$args['directory_path']}"];
        }

        $files = \Illuminate\Support\Facades\File::files($dirPath);
        $directories = \Illuminate\Support\Facades\File::directories($dirPath);

        $out = "VERZEICHNIS-INHALT VON: " . $args['directory_path'] . "\n\n";
        $out .= "[ORDNER]\n";
        foreach ($directories as $d) {
            $out .= "- " . basename($d) . "/\n";
        }
        $out .= "\n[DATEIEN]\n";
        foreach ($files as $f) {
            $out .= "- " . $f->getFilename() . " (" . round($f->getSize() / 1024, 2) . " KB)\n";
        }

        return [
            'status' => 'success',
            'content' => $out
        ];
    }

    public static function executeReadWebUrl(array $args)
    {
        $url = $args['url'] ?? '';
        if (empty($url)) return ['status' => 'error', 'message' => 'URL fehlt.'];

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url);
            if ($response->failed()) {
                return ['status' => 'error', 'message' => 'HTTP Fehler: ' . $response->status()];
            }
            $html = $response->body();
            // Sehr grober Text-Extractor für APIs (Markdown Filter)
            $text = strip_tags(preg_replace('/<(script|style)[^>]*?>.*?<\/\\1>/si', '', $html));
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/[\r\n]+/', "\n", $text);
            
            return [
                'status' => 'success',
                'content' => \Illuminate\Support\Str::limit($text, 15000, '... (abgeschnitten)')
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abruf: ' . $e->getMessage()];
        }
    }

    public static function executeSearchWeb(array $args)
    {
        $query = $args['query'] ?? '';
        if (empty($query)) return ['status' => 'error', 'message' => 'Query fehlt.'];

        try {
            // Wikipedia Fallback API as simple free search
            $url = "https://de.wikipedia.org/w/api.php?action=query&list=search&srsearch=" . urlencode($query) . "&utf8=&format=json";
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                $results = $data['query']['search'] ?? [];
                
                $out = "WIKIPEDIA SUCH-ERGEBNISSE FÜR: {$query}\n\n";
                foreach (array_slice($results, 0, 5) as $result) {
                    $out .= "TITEL: " . $result['title'] . "\n";
                    $out .= "AUSZUG: " . strip_tags($result['snippet']) . "\n\n";
                }
                
                return [
                    'status' => 'success',
                    'content' => empty($results) ? 'Keine Ergebnisse auf Wikipedia gefunden.' : $out
                ];
            }
            return ['status' => 'error', 'message' => 'Such-Dienst nicht erreichbar.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei Dateisuche: ' . $e->getMessage()];
        }
    }
}

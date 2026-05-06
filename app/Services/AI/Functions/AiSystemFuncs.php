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
        $agentMap = \Illuminate\Support\Facades\Cache::remember('ai_agent_capabilities_map', 3600, function() {
            $agents = \App\Models\Ai\AiAgent::with('role.tools')->where('is_active', true)->get();
            $list = [];
            foreach ($agents as $a) {
                $list[$a->name] = $a->tools->pluck('identifier')->toArray();
            }
            return json_encode($list);
        });

        $schema = [
            [
                'name' => 'system_call_contact',
                'description' => 'Führt einen KI-Sprachanruf bei einem Kontakt durch (z.B. über Vapi.ai oder Twilio).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'phone_number' => [
                            'type' => 'string',
                            'description' => 'Die Telefonnummer des Kontakts im E.164 Format (z.B. +491701234567).'
                        ],
                        'objective' => [
                            'type' => 'string',
                            'description' => 'Das Ziel des Anrufs (Was soll der Voice-Agent herausfinden oder besprechen?).'
                        ]
                    ],
                    'required' => ['phone_number', 'objective']
                ],
                'callable' => [self::class, 'executeCallContact']
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
                'name' => 'system_switch_agent',
                'description' => 'Wechselt den aktiven Agenten. Nutze dies IMMER, wenn der Nutzer sagt: "Ich möchte mit Agent X sprechen", "Hol mir Marketi", "Wechsle zu Buchi" etc. Du verabschiedest dich und übergibst das Wort.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'agent_name' => [
                            'type' => 'string',
                            'description' => 'Der Name des Ziel-Agenten (z.B. Marketi, Buchi, Systemi).'
                        ]
                    ],
                    'required' => ['agent_name']
                ],
                'callable' => [self::class, 'executeSwitchAgent']
            ],
            [
                'name' => 'system_create_database_backup',
                'description' => 'Erstellt ein manuelles und asynchrones System-Backup der Datenbank. Benutze dieses Tool, wenn der Nutzer ausdrücklich ein Backup der Datenbank wünscht.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCreateDatabaseBackup']
            ],
            [
                'name' => 'system_execute_command',
                'description' => 'Führt sichere Systemwartungs-Befehle aus (z.B. Cache leeren, Backups machen, Tests ausführen, Mails verarbeiten). Nutze dies, wenn der Nutzer dich bittet "Leere den Cache", "Mach ein Backup" oder "Starte den Mail-Worker".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'command' => [
                            'type' => 'string',
                            'enum' => ['cache', 'backup', 'test', 'mailworker', 'fetch_mails', 'storage'],
                            'description' => 'Der sichere Befehl, der ausgeführt werden soll. "cache" leert alle System-Caches. "backup" erstellt ein Datenbank-Backup. "test" führt Unit-Tests aus. "mailworker" triggert die KI-Postfach-Verarbeitung. "fetch_mails" ruft neue Mails vom IMAP ab. "storage" erneuert den Storage-Link.'
                        ]
                    ],
                    'required' => ['command']
                ],
                'callable' => [self::class, 'executeSystemCommand']
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
                'description' => 'Öffnet, aktualisiert, navigiert oder lädt eine bestimmte Unterseite im Dashboard neu. WICHTIG: Nutze dieses Tool IMMER, wenn der Nutzer eine Seite "öffnen", "neu laden", "refreshen" oder "anzeigen" möchte. Erkenne den natürlichsprachlichen Wunsch (z.B. "Lade die Seite Log neu", "wo ich Gutschriften hinterlegen kann" -> /admin/credit-management, "Belege hinterlegen" -> /admin/financial-variable-costs) und wähle die EXAKTE URL aus folgenden Optionen:' . "\n" . \App\Services\Navigation\BackendNavigationService::getAiNavigationPrompt(),
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
                'name' => 'system_trigger_ui_element',
                'description' => 'Löst einen Klick auf ein beliebiges UI-Element (Button, Link, Akkordeon, Tab) im Frontend aus. Benutze dies, wenn der Nutzer sagt "Drücke den Button X" oder "Klappe den Bereich Y auf".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'element_text' => [
                            'type' => 'string',
                            'description' => 'Der exakte oder ungefähre sichtbare Text des Elements, das geklickt werden soll (z.B. "Speichern", "Details aufklappen", "Neu laden").'
                        ]
                    ],
                    'required' => ['element_text']
                ],
                'callable' => [self::class, 'executeTriggerUiElement']
            ],
            [
                'name' => 'system_generate_pdf_report',
                'description' => 'Generiert ein PDF-Dokument aus Daten/Tabellen/Texten, die du aufbereitet hast, und bietet es dem Nutzer als direkten Download an oder versendet es per E-Mail. Nutze dies IMMER, wenn der Nutzer fragt "Erstelle mir daraus ein PDF", "Exportiere das als Bericht", "Schick mir das als PDF".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Titel des Berichts, z.B. "Fehlerprotokoll Systemi" oder "Finanz-Übersicht".'
                        ],
                        'content_markdown' => [
                            'type' => 'string',
                            'description' => 'Der eigentliche Inhalt der PDF im Markdown-Format (Tabellen, Listen, Überschriften, Fettgedrucktes).'
                        ],
                        'design' => [
                            'type' => 'string',
                            'description' => 'Das visuelle Design der PDF. "seelenfunke" (inkl. Briefkopf, CI-Farben, Logo) oder "generic" (neutrales Design ohne Firmenbezug). Standardmäßig "seelenfunke", es sei denn, der Nutzer wünscht neutral.',
                            'enum' => ['seelenfunke', 'generic']
                        ],
                        'target_action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit der generierten PDF passieren? "download" (öffnet Download-Dialog beim Nutzer im Browser), "email" (versendet die PDF per Mail an recipient_email).',
                            'enum' => ['download', 'email']
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Die E-Mail-Adresse des Empfängers. Wenn der Nutzer keine E-Mail nennt, lasse dieses Feld zwingend leer (null). Das System nutzt dann automatisch die Standard-E-Mail des Admins.'
                        ]
                    ],
                    'required' => ['title', 'content_markdown', 'target_action', 'design']
                ],
                'callable' => [self::class, 'executeGeneratePdfReport']
            ],
            [
                'name' => 'system_export_system_report',
                'description' => 'Generiert einen echten, nativen Systembericht (wie den Buchhaltungs-Export oder den CEO-Master-Report) und lädt diesen herunter, verschickt ihn per Mail oder speichert ihn im internen Datei-Manager. Nutze dies, wenn explizit der UStVA-Export, Finanz-Export, oder CEO-Report gewünscht wird.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'report_type' => [
                            'type' => 'string',
                            'description' => 'Welcher Bericht generiert werden soll. "tax_export" (Buchhaltungs-Export für Steuerbüro/UStVA als CSV) oder "ceo_report" (Der Master-Analytics CEO-Report als PDF).',
                            'enum' => ['tax_export', 'ceo_report']
                        ],
                        'target_action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit der Datei passieren? "download" (Browser-Download), "email" (Versand an E-Mail), oder "save_to_workspace" (Speichern im lokalen Datei-Manager).',
                            'enum' => ['download', 'email', 'save_to_workspace']
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Die Empfängeradresse. Wenn der Nutzer keine E-Mail nennt, lasse dieses Feld zwingend leer (null). Das System sendet es dann an die Standard-Admin-E-Mail.'
                        ],
                        'target_folder_name' => [
                            'type' => 'string',
                            'description' => 'Optional, wenn target_action = "save_to_workspace". Name des Unterordners (z.B. "buchhaltung_2026"). Standard ist Hauptordner.'
                        ]
                    ],
                    'required' => ['report_type', 'target_action']
                ],
                'callable' => [self::class, 'executeExportSystemReport']
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
                'description' => 'Agiert als automatischer Administrator: Behebt gefundene Backend-Fehler durch Cache-Clearing, OPcache Resets, Queue Restarts, Backup-Triggers und NPM-Kompilierung. FÜHRE DIESES TOOL ZWINGEND AUS, wenn get_system_health Fehler meldet. Stichworte: Repariere das System, Behebe die Fehler, Auto-Heal starten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'target' => [
                            'type' => 'string',
                            'description' => 'Gibt das Ziel der Reparatur an. Standard ist "all". Erlaubt: all, database, redis, queue, scheduler, backup, stripe, smtp, ws',
                            'enum' => ['all', 'database', 'redis', 'queue', 'scheduler', 'backup', 'stripe', 'smtp', 'ws']
                        ]
                    ]
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
                'name' => 'system_manage_logs',
                'description' => 'System-Werkzeug zur Verwaltung (Lösen, Fehler markieren oder Löschen) von Logs. WICHTIG FÜR MASSEN-AKTIONEN: Wenn der Benutzer sagt "Lösche alle Fehler die X heißen" oder "Lösche alle frontend_error", dann MUSST du target_scope="all" setzen, action="delete" wählen und den passenden Suchparameter (z.B. search_type="frontend_error" oder error_message_contains="X") übergeben. Nur so werden mehrere Einträge auf einmal gelöscht!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => [
                            'type' => 'string',
                            'description' => 'Die Aktion, die ausgeführt werden soll: "resolve" (als gelöst markieren), "set_error" (als Fehler markieren) oder "delete" (komplett aus der Datenbank löschen).',
                            'enum' => ['resolve', 'set_error', 'delete']
                        ],
                        'log_id' => [
                            'type' => 'integer',
                            'description' => 'Die exakte ID eines einzelnen Fehler-Logs. (Leer lassen für dynamische Suche).'
                        ],
                        'manage_all_similar' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true und eine log_id angegeben ist, wird die Aktion auf ALLE Fehler angewandt, die die exakt gleiche Fehlermeldung haben wie dieser Log.'
                        ],
                        'error_message_contains' => [
                            'type' => 'string',
                            'description' => 'Wenn keine log_id vorliegt: Wendet die Aktion auf Fehler an, deren Text (Message oder Title) diesen Begriff enthält.'
                        ],
                        'search_time' => [
                            'type' => 'string',
                            'description' => 'Filtert Logs nach Uhrzeit, z.B. "11:52:49".'
                        ],
                        'search_agent' => [
                            'type' => 'string',
                            'description' => 'Filtert Logs nach Agent-Name, z.B. "Systemi" oder "Funkira".'
                        ],
                        'search_type' => [
                            'type' => 'string',
                            'description' => 'Filtert Logs nach Typ, z.B. "ai_tool" oder "system".'
                        ],
                        'search_action_id' => [
                            'type' => 'string',
                            'description' => 'Filtert Logs nach Aktion, z.B. "ai_tool_..." oder "user:security_update".'
                        ],
                        'target_scope' => [
                            'type' => 'string',
                            'description' => 'WICHTIG: Bestimmt ob "all" (alle Treffer) oder "latest" (nur der absolut letzte Treffer) behandelt werden soll. Wenn der User sagt "alle" oder "mehrere", MUSST du diesen Wert zwingend auf "all" setzen! Standard ist "latest".',
                            'enum' => ['all', 'latest']
                        ]
                    ],
                    'required' => ['action']
                ],
                'callable' => [self::class, 'executeManageSystemLogs']
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
                'name' => 'system_patch_artifact',
                'description' => 'Bearbeitet ein bestehendes Artefakt (z.B. Implementierungsplan), indem ein spezifischer Textabschnitt durch einen neuen ersetzt wird. Perfekt, um z.B. Checklisten-Punkte von "- [ ]" auf "- [x]" zu setzen und den Bearbeitungsstand zu tracken.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'artifact_name' => [
                            'type' => 'string',
                            'description' => 'Name des Artefakts ohne Dateiendung (z.B. implementation_plan).'
                        ],
                        'search_text' => [
                            'type' => 'string',
                            'description' => 'Der exakte Text, der gesucht und ersetzt werden soll (z.B. "- [ ] Mein Schritt").'
                        ],
                        'replace_text' => [
                            'type' => 'string',
                            'description' => 'Der neue Text, der eingefügt werden soll (z.B. "- [x] Mein Schritt").'
                        ]
                    ],
                    'required' => ['artifact_name', 'search_text', 'replace_text']
                ],
                'callable' => [self::class, 'executePatchArtifact']
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
                'name' => 'system_get_rights',
                'description' => 'Gibt dem Agenten volle Datei- und Systemberechtigungen zurück, indem es das "funki rights" Skript ausführt (Cache leeren, www-data chown, chmod 775 auf storage und bootstrap/cache). Nutze dies, wenn du Permission Denied Fehler erhältst.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetRights']
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
                'description' => 'Mächtiges Deep Research Web-Tool. Führt eine hochintelligente, tiefgreifende Internet-Suche (Google Search Grounding) aus, um externe Informationen, News, medizinische Fakten oder Marktdaten zu recherchieren.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff oder die ausformulierte Recherche-Aufgabe.'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeSearchWeb']
            ]
        ];

        if (!app()->environment('local')) {
            $schema = array_filter($schema, function($tool) {
                return !in_array($tool['name'], ['system_multi_replace_file', 'system_write_to_file']);
            });
            $schema = array_values($schema);
        }

        return $schema;
    }


    public static function executeCreateDatabaseBackup(array $args)
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('backup:run', ['--only-db' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            return [
                'status' => 'success',
                'message' => 'Ein sicheres Datenbank-Backup wurde erfolgreich angestoßen und abgeschlossen.'
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('System Backup Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen des Backups: ' . $e->getMessage()];
        }
    }



    public static function executeCloseUi(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Die UI wurde erfolgreich geschlossen.'
        ];
    }

    public static function executeSwitchAgent(array $args)
    {
        $agentName = $args['agent_name'] ?? '';
        $agent = \App\Models\Ai\AiAgent::where('name', 'LIKE', '%' . $agentName . '%')->first();
        if ($agent) {
            return [
                'status' => 'success',
                'message' => "Der Kontext wurde erfolgreich an {$agent->name} übergeben.",
                'agent_id' => $agent->id,
                '_frontend_event' => [
                    'type' => 'dispatch',
                    'name' => 'ai-switch-agent',
                    'detail' => [
                        'agent_id' => $agent->id
                    ]
                ]
            ];
        }
        return ['status' => 'error', 'message' => 'Agent mit dem Namen ' . $agentName . ' nicht gefunden.'];
    }

    public static function executeSystemCommand(array $args)
    {
        try {
            if (empty($args['command'])) {
                return ['status' => 'error', 'message' => 'Kein Befehl angegeben.'];
            }

            $command = $args['command'];
            $output = '';

            switch ($command) {
                case 'cache':
                    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
                    $output = 'Alle Caches (Views, Routen, Config, App) wurden erfolgreich restlos geleert.';
                    break;
                case 'backup':
                    \Illuminate\Support\Facades\Artisan::call('backup:run', ['--only-db' => true]);
                    $output = 'Ein sicheres Datenbank-Backup wurde erfolgreich erstellt.';
                    break;
                case 'test':
                    \Illuminate\Support\Facades\Artisan::call('test');
                    $testOutput = \Illuminate\Support\Facades\Artisan::output();
                    $output = "Tests wurden ausgeführt. Zusammenfassung:\n" . substr($testOutput, -1000); // Nur die letzten 1000 Zeichen (Summary)
                    break;
                case 'mailworker':
                    \Illuminate\Support\Facades\Artisan::call('crm:ai-process-mails');
                    $output = 'Der KI-Mail-Worker wurde angestoßen und hat den Posteingang verarbeitet.';
                    break;
                case 'fetch_mails':
                    \Illuminate\Support\Facades\Artisan::call('crm:fetch-mails');
                    $output = 'Neue E-Mails wurden erfolgreich via IMAP abgerufen.';
                    break;
                case 'storage':
                    // Storage link command
                    \Illuminate\Support\Facades\Artisan::call('storage:link');
                    $output = 'Der Public-Storage-Link wurde erfolgreich erneuert.';
                    break;
                default:
                    return ['status' => 'error', 'message' => "Der Befehl '{$command}' ist nicht erlaubt oder existiert nicht auf der Sicherheits-Whitelist."];
            }

            return [
                'status' => 'success',
                'message' => $output
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('System Command Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler bei der System-Ausführung: ' . $e->getMessage()];
        }
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

    // executeAskAgent removed and migrated to AiCommunicationFuncs

    public static function executeCallContact(array $args)
    {
        $phoneNumber = $args['phone_number'] ?? null;
        $objective = $args['objective'] ?? null;

        if (!$phoneNumber || !$objective) {
            return ['status' => 'error', 'message' => 'Telefonnummer und Ziel des Anrufs müssen angegeben werden.'];
        }

        // Dummy implementation for the concept
        // Hier würde z.B. der API Call an Vapi.ai oder Twilio stattfinden.
        
        if (class_exists(\App\Models\Ai\AiCall::class)) {
            \App\Models\Ai\AiCall::create([
                'ai_agent_id' => auth()->user() ? null : null, // Agent ID müsste aus Kontext kommen
                'phone_number' => $phoneNumber,
                'direction' => 'outbound',
                'status' => 'initiated',
                'summary' => 'Initialisiert: ' . $objective
            ]);
        }

        return [
            'status' => 'success',
            'message' => 'Der Anruf an ' . $phoneNumber . ' wurde erfolgreich initiiert. Das Ziel ist: ' . $objective . '. Der Agent telefoniert nun im Hintergrund.'
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

    public static function executeManageSystemLogs(array $args)
    {
        $action = $args['action'] ?? 'resolve';
        $logId = $args['log_id'] ?? null;
        $manageAllSimilar = $args['manage_all_similar'] ?? false;
        
        $errorContains = $args['error_message_contains'] ?? null;
        $searchTime = $args['search_time'] ?? null;
        $searchAgent = $args['search_agent'] ?? null;
        $searchType = $args['search_type'] ?? null;
        $searchActionId = $args['search_action_id'] ?? null;
        
        // Defaults to 'latest' if any search parameter is used without log_id to prevent bulk disaster
        $targetScope = $args['target_scope'] ?? 'latest';

        $processedCount = 0;

        $processLog = function($log) use ($action, &$processedCount) {
            if ($action === 'delete') {
                $log->delete();
                $processedCount++;
            } elseif ($action === 'set_error') {
                if ($log->status !== 'error') {
                    $log->status = 'error';
                    if (str_starts_with($log->title, '[GELÖST] ')) {
                        $log->title = substr($log->title, strlen('[GELÖST] '));
                    }
                    $log->save();
                    $processedCount++;
                }
            } else {
                if ($log->status === 'error') {
                    $log->status = 'success';
                    if (!str_starts_with($log->title, '[GELÖST]')) {
                        $log->title = '[GELÖST] ' . $log->title;
                    }
                    $log->save();
                    $processedCount++;
                }
            }
        };

        if ($logId) {
            $log = \App\Models\System\SystemLog::find($logId);
            if (!$log) {
                return ['status' => 'error', 'message' => "Log mit ID {$logId} nicht gefunden."];
            }

            if ($manageAllSimilar) {
                $similarLogs = \App\Models\System\SystemLog::where('message', $log->message)->get();
                
                foreach ($similarLogs as $sl) {
                    $processLog($sl);
                }
            } else {
                $processLog($log);
            }
        } elseif ($errorContains || $searchTime || $searchAgent || $searchType || $searchActionId) {
            $query = \App\Models\System\SystemLog::query();
            
            if ($errorContains) {
                $query->where(function($q) use ($errorContains) {
                    $q->where('message', 'like', '%' . $errorContains . '%')
                      ->orWhere('title', 'like', '%' . $errorContains . '%');
                });
            }
            if ($searchTime) {
                $query->whereTime('created_at', 'like', $searchTime . '%');
            }
            if ($searchAgent) {
                if (strtolower($searchAgent) === 'systemi' || strtolower($searchAgent) === 'system') {
                    $query->whereNull('ai_agent_id');
                } else {
                    $query->whereHas('agent', function($q) use ($searchAgent) {
                        $q->where('name', 'like', '%' . $searchAgent . '%');
                    });
                }
            }
            if ($searchType) {
                $query->where('type', 'like', '%' . $searchType . '%');
            }
            if ($searchActionId) {
                $query->where('action_id', 'like', '%' . $searchActionId . '%');
            }
            
            if ($targetScope === 'latest') {
                $log = $query->latest()->first();
                if ($log) {
                    $processLog($log);
                }
            } else {
                $logs = $query->get();
                foreach ($logs as $log) {
                    $processLog($log);
                }
            }
        } else {
            return ['status' => 'error', 'message' => 'Bitte log_id oder Suchparameter (z.B. search_time, error_message_contains) angeben.'];
        }

        $actionText = $action === 'delete' ? 'GELÖSCHT' : ($action === 'set_error' ? 'als FEHLER markiert' : 'als GELÖST markiert');
        return [
            'status' => 'success',
            'message' => "Erfolgreich! Es wurden {$processedCount} System-Logs {$actionText}."
        ];
    }

    public static function executeFixSystemErrors(array $args)
    {
        $target = $args['target'] ?? 'all';
        $targets = $target === 'all' ? ['database', 'redis', 'queue', 'scheduler', 'backup', 'stripe', 'smtp', 'ws'] : [$target];
        $logs = [];

        try {
            foreach ($targets as $t) {
                switch ($t) {
                    case 'redis':
                    case 'database':
                        \Illuminate\Support\Facades\Artisan::call('config:clear');
                        \Illuminate\Support\Facades\Artisan::call('cache:clear');
                        \Illuminate\Support\Facades\Artisan::call('view:clear');
                        \Illuminate\Support\Facades\Artisan::call('route:clear');
                        $logs[] = "Cache, Views und Configs für {$t} geleert.";
                        break;
                    case 'queue':
                        \Illuminate\Support\Facades\Artisan::call('queue:restart');
                        $logs[] = "Queue Worker Restart-Signal gesendet.";
                        $failed = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs') ? \Illuminate\Support\Facades\DB::table('failed_jobs')->count() : 0;
                        if ($failed > 0) {
                            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => 'all']);
                            $logs[] = "{$failed} fehlgeschlagene Jobs wurden neu gestartet.";
                        }
                        break;
                    case 'scheduler':
                        \Illuminate\Support\Facades\Artisan::call('schedule:run');
                        $logs[] = "Scheduler manuell ausgeführt.";
                        break;
                    case 'backup':
                        \Illuminate\Support\Facades\Artisan::queue('backup:run', ['--only-db' => true]);
                        $logs[] = "Datenbank-Backup Auftrag gestartet.";
                        break;
                    case 'stripe':
                    case 'smtp':
                        \Illuminate\Support\Facades\Artisan::call('config:clear');
                        $logs[] = "Network Reset für {$t} durch Config-Clear durchgeführt.";
                        break;
                    case 'ws':
                        \Illuminate\Support\Facades\Artisan::call('config:clear');
                        $logs[] = "Config Cache Reset für WebSockets.";
                        try {
                            $process = \Symfony\Component\Process\Process::fromShellCommandline('export PATH=$PATH:/usr/local/bin:/usr/bin:/bin; npm run prod', base_path());
                            $process->setTimeout(120);
                            $process->run();
                            if ($process->isSuccessful()) {
                                $logs[] = "Frontend (NPM) erfolgreich neu gebaut.";
                            } else {
                                $logs[] = "Frontend (NPM) Build fehlgeschlagen. Error: " . substr($process->getErrorOutput(), 0, 100);
                            }
                        } catch (\Exception $e) {
                            $logs[] = "Frontend (NPM) Build Exception: " . $e->getMessage();
                        }
                        break;
                }
            }

            if (class_exists(\App\Models\System\SystemLog::class)) {
                $agent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first() ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();
                \App\Models\System\SystemLog::create([
                    'ai_agent_id' => $agent ? $agent->id : null,
                    'title' => '[FUNKIRA] - System Healing',
                    'message' => '[Funkira] - System Healing durchgeführt für: ' . implode(', ', $targets) . '. Logs: ' . implode(' | ', $logs),
                    'status' => 'success',
                    'type' => 'ai',
                    'started_at' => now(),
                    'finished_at' => now(),
                    'action_id' => 'system_heal_ai_' . time()
                ]);
            }

            return [
                'status' => 'success',
                'message' => 'Das System-Healing wurde durchgeführt.',
                'details' => $logs
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
            $files = Storage::disk('public')->files('agenten/ai/KnowledgeBase');

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

    public static function executeTriggerUiElement(array $args)
    {
        try {
            if (empty($args['element_text'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Suchtext übergeben.'];
            }

            return [
                'status' => 'success',
                'message' => 'Der Klick auf das Element wird nun clientseitig ausgeführt.',
                '_event' => [
                    'type' => 'dispatch',
                    'name' => 'ai-trigger-ui-element',
                    'detail' => [
                        'text' => $args['element_text']
                    ]
                ],
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Triggern des Elements: ' . $e->getMessage()];
        }
    }

    public static function executeGeneratePdfReport(array $args)
    {
        try {
            $title = $args['title'] ?? 'KI-Bericht';
            $markdown = $args['content_markdown'] ?? '';
            $design = $args['design'] ?? 'seelenfunke';
            $action = $args['target_action'] ?? 'download';
            $recipient = $args['recipient_email'] ?? null;
            $agentName = session('current_ai_agent_name', 'System'); // Could be fetched via context if available

            if (empty($markdown)) {
                return ['status' => 'error', 'message' => 'Der Markdown-Inhalt darf nicht leer sein.'];
            }

            // Convert Markdown to HTML
            $htmlContent = \Illuminate\Support\Str::markdown($markdown);

            // Select View
            $viewName = $design === 'generic' ? 'global.pdf.ai-report-generic' : 'global.pdf.ai-report-seelenfunke';

            // Generate PDF
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($viewName, [
                'title' => $title,
                'htmlContent' => $htmlContent,
                'agentName' => $agentName
            ]);

            $fileName = \Illuminate\Support\Str::slug($title) . '-' . time() . '.pdf';
            $filePath = 'public/reports/' . $fileName;

            \Illuminate\Support\Facades\Storage::put($filePath, $pdf->output());

            $downloadUrl = \Illuminate\Support\Facades\Storage::url($filePath);

            if ($action === 'email') {
                if (empty($recipient)) {
                    $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                }
                if (empty($recipient)) {
                    return ['status' => 'error', 'message' => 'Für den E-Mail-Versand muss eine Empfänger-E-Mail (recipient_email) angegeben werden, da keine System-E-Mail hinterlegt ist.'];
                }
                
                // Generic Mail sending logic
                \Illuminate\Support\Facades\Mail::send('global.mails.ai_report', ['title' => $title], function($message) use ($recipient, $title, $pdf, $fileName) {
                    $message->to($recipient)
                            ->subject("KI-Bericht: $title")
                            ->attachData($pdf->output(), $fileName, ['mime' => 'application/pdf']);
                });

                return [
                    'status' => 'success',
                    'message' => "Der PDF-Bericht '$title' wurde erfolgreich generiert und an $recipient gesendet."
                ];
            } else {
                // Download action -> trigger frontend to download
                return [
                    'status' => 'success',
                    'message' => "Der PDF-Bericht '$title' wurde generiert. Ein Download-Dialog öffnet sich nun beim Nutzer.",
                    '_event' => [
                        'type' => 'dispatch',
                        'name' => 'download-file',
                        'detail' => [
                            'url' => $downloadUrl,
                            'filename' => $fileName
                        ]
                    ]
                ];
            }

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der PDF-Generierung: ' . $e->getMessage()];
        }
    }

    public static function executeExportSystemReport(array $args)
    {
        try {
            $reportType = $args['report_type'] ?? null;
            $action = $args['target_action'] ?? 'download';
            $recipient = $args['recipient_email'] ?? null;
            $targetFolder = $args['target_folder_name'] ?? '';

            if (!$reportType) {
                return ['status' => 'error', 'message' => 'Es wurde kein report_type übergeben.'];
            }

            $adminId = session('current_admin_id', 1); // Fallback to 1 if session not set in CLI/AI context
            if (\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
                $adminId = \Illuminate\Support\Facades\Auth::guard('admin')->id();
            }

            $generatedFilePath = null;
            $generatedFileName = '';
            $title = '';

            if ($reportType === 'tax_export') {
                $service = app(\App\Services\FinancialService::class);
                // Hole den Export für den aktuellen Monat und Jahr
                $month = date('n');
                $year = date('Y');
                $generatedFilePath = $service->generateTaxExport($adminId, $month, $year);
                $generatedFileName = basename($generatedFilePath);
                $title = 'Buchhaltungs-Export';
            } elseif ($reportType === 'ceo_report') {
                $service = app(\App\Services\AnalyticsService::class);
                
                // Wir bauen die Daten für den Report zusammen, analog zu MasterAnalytics
                $dateStart = now()->startOfMonth()->format('Y-m-d');
                $dateEnd = now()->endOfMonth()->format('Y-m-d');
                $allLogins = $service->getAllLoginsCollection();
                $stats = $service->getStats($dateStart, $dateEnd, 'all', $allLogins, []);
                
                // Hole den Agenten für den Report (Teamleiter/CEO)
                $agent = \App\Models\Ai\AiAgent::where('is_active', true)->whereHas('role', function($q) {
                    $q->where('name', 'like', '%Teamleiter%')->orWhere('name', 'like', '%CEO%');
                })->first() ?? \App\Models\Ai\AiAgent::first();

                if (!$agent) {
                     return ['status' => 'error', 'message' => 'Kein aktiver Agent für den CEO-Report gefunden.'];
                }

                // Generiere den Prompt aus den Stats
                $prompt = "Du bist der CEO/Teamleiter. Schreibe einen professionellen CEO-Strategie-Report basierend auf diesen Daten:\n\n";
                $prompt .= json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                $prompt .= "Schreibe eine Executive Summary, analysiere die finanzielle Gesundheit, nenne kritische Punkte und gebe eine klare Roadmap. Nutze sauberes Markdown, keine Emojis. Alles auf Deutsch.";

                $markdownResponse = \App\Services\AI\AiAgentFactory::processDirectPrompt($agent, $prompt);
                $htmlContent = \Illuminate\Support\Str::markdown($markdownResponse);
                $htmlContent = mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8');

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('global.pdf.ceo-report', [
                    'htmlContent' => $htmlContent,
                    'agentName' => $agent->name
                ]);

                $generatedFileName = 'CEO-Strategie-Report-' . time() . '.pdf';
                // Temporäres Speichern
                $tempPath = storage_path('app/public/temp/' . $generatedFileName);
                if (!\Illuminate\Support\Facades\File::exists(dirname($tempPath))) {
                    \Illuminate\Support\Facades\File::makeDirectory(dirname($tempPath), 0755, true);
                }
                $pdf->save($tempPath);
                $generatedFilePath = $tempPath;
                $title = 'CEO-Strategie-Report';
            } else {
                return ['status' => 'error', 'message' => 'Unbekannter report_type: ' . $reportType];
            }

            // Verarbeitung der Datei anhand target_action
            if ($action === 'save_to_workspace') {
                $baseWorkspace = 'agenten/workspace';
                if (!empty($targetFolder)) {
                    $baseWorkspace .= '/' . trim($targetFolder, '/');
                }
                
                if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($baseWorkspace)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($baseWorkspace);
                }

                $finalPath = $baseWorkspace . '/' . $generatedFileName;
                
                // Kopiere von $generatedFilePath nach $finalPath (public disk)
                \Illuminate\Support\Facades\Storage::disk('public')->put(
                    $finalPath,
                    file_get_contents($generatedFilePath)
                );

                // Aufräumen, wenn temporär generiert (ceo_report)
                if ($reportType === 'ceo_report') {
                    @unlink($generatedFilePath);
                }

                return [
                    'status' => 'success',
                    'message' => "Der Bericht '$title' wurde erfolgreich generiert und im Datei-Manager unter '$finalPath' gespeichert."
                ];
            } elseif ($action === 'email') {
                if (empty($recipient)) {
                    $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address');
                }
                if (empty($recipient)) {
                    return ['status' => 'error', 'message' => 'Für den E-Mail-Versand muss recipient_email angegeben werden, da keine System-E-Mail gefunden wurde.'];
                }
                
                $pathForMail = $generatedFilePath;
                $nameForMail = $generatedFileName;

                \Illuminate\Support\Facades\Mail::send('global.mails.ai_report', ['title' => $title], function($message) use ($recipient, $title, $pathForMail, $nameForMail) {
                    $message->to($recipient)
                            ->subject("Ihr $title")
                            ->attach($pathForMail, ['as' => $nameForMail]);
                });

                // Aufräumen
                if ($reportType === 'ceo_report') {
                    @unlink($generatedFilePath);
                }

                return [
                    'status' => 'success',
                    'message' => "Der Bericht '$title' wurde generiert und per E-Mail an $recipient versendet."
                ];
            } else {
                // Default: Download
                // Wir müssen die Datei so zugänglich machen, dass der Browser sie laden kann
                $publicTempPath = 'reports/' . $generatedFileName;
                \Illuminate\Support\Facades\Storage::disk('public')->put(
                    $publicTempPath,
                    file_get_contents($generatedFilePath)
                );

                // Aufräumen
                if ($reportType === 'ceo_report') {
                    @unlink($generatedFilePath);
                }

                $downloadUrl = \Illuminate\Support\Facades\Storage::url($publicTempPath);

                return [
                    'status' => 'success',
                    'message' => "Der Bericht '$title' wurde generiert. Ein Download-Dialog öffnet sich nun.",
                    '_event' => [
                        'type' => 'dispatch',
                        'name' => 'download-file',
                        'detail' => [
                            'url' => $downloadUrl,
                            'filename' => $generatedFileName
                        ]
                    ]
                ];
            }

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Exportieren des System-Berichts: ' . $e->getMessage()];
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

        $sessionId = config('ai.current_session_id') ?: (auth()->check() ? 'user_' . auth()->id() : session()->getId());
        $userId = auth()->id();

        if (!$userId && !$sessionId) {
            return ['status' => 'error', 'message' => 'Keine aktive Session für Artefakt-Speicherung gefunden.'];
        }

        $filename = str_replace(' ', '_', strtolower($name)) . '.md';
        
        \App\Models\Ai\AiArtifact::updateOrCreate(
            [
                'name' => $filename,
                'user_id' => $userId,
                'session_id' => $userId ? null : $sessionId,
            ],
            [
                'content' => $content
            ]
        );

        // ZUSÄTZLICH: Datei physisch im Workspace speichern, damit der User sie im Dateimanager (Tab 'Dateien') bearbeiten kann.
        $workspacePath = 'agenten/workspace/pläne/' . $filename;
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists('agenten/workspace/pläne')) {
            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('agenten/workspace/pläne');
        }
        \Illuminate\Support\Facades\Storage::disk('public')->put($workspacePath, $content);

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

        if (!class_exists(\App\Models\Ai\AiKnowledgeBase::class)) {
             return ['status' => 'error', 'message' => 'Wissensdatenbank-System ist offline.'];
        }

        $category = \App\Models\Ai\AiKnowledgeBaseCategory::firstOrCreate(
            ['name' => 'System & Architektur'],
            [
                'slug' => \Illuminate\Support\Str::slug('System & Architektur'),
                // Falls 'description' nicht fillable ist, kann es hier weggelassen werden
            ]
        );

        $article = \App\Models\Ai\AiKnowledgeBase::updateOrCreate(
            ['title' => str_replace('_', ' ', $topic)],
            [
                'slug' => \Illuminate\Support\Str::slug($topic),
                'content' => $content,
                'ai_knowledge_base_category_id' => $category->id,
                'is_published' => true,
            ]
        );

        $addedLines = substr_count($content, "\n") + 1;

        return [
            'status' => 'success',
            'message' => "Knowledge Item '{$topic}' wurde global dauerhaft in der Datenbank gespeichert.",
            '_frontend_thought_stream' => '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-yellow-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-yellow-300 truncate max-w-full font-bold"><x-heroicon-o-academic-cap class="w-3 h-3 inline-block -mt-0.5" /> ' . htmlspecialchars($topic) . '</div>
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

        if (!class_exists(\App\Models\Ai\AiKnowledgeBase::class)) {
             return ['status' => 'error', 'message' => 'Wissensdatenbank-System ist offline.'];
        }

        $article = \App\Models\Ai\AiKnowledgeBase::where('title', str_replace('_', ' ', $topic))
                                                 ->orWhere('slug', \Illuminate\Support\Str::slug($topic))
                                                 ->first();
        
        if (!$article) {
             return ['status' => 'empty', 'message' => "Das Knowledge Item '{$topic}' existiert nicht in der Datenbank."];
        }

        return [
            'status' => 'success',
            'content' => $article->content,
            '_frontend_thought_stream' => '<div class="text-[10px] font-mono mt-1 pl-3 ml-2 border-l-2 border-yellow-500/50 p-1.5 rounded bg-black/20">
                               <div class="text-yellow-300 truncate max-w-full font-bold"><x-heroicon-o-academic-cap class="w-3 h-3 inline-block -mt-0.5" /> ' . htmlspecialchars($topic) . '</div>
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

    public static function executeGetRights(array $args)
    {
        $basePath = base_path();
        $funkiPath = $basePath . '/funki';
        
        if (!file_exists($funkiPath)) {
            return ['status' => 'error', 'message' => 'Das funki Skript konnte nicht gefunden werden.'];
        }

        $output = shell_exec("cd " . escapeshellarg($basePath) . " && ./funki rights 2>&1");
        
        return [
            'status' => 'success',
            'message' => 'Systemberechtigungen wurden erfolgreich erneuert!',
            'output' => $output
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
            $response = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->get($url);
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
            $apiKey = config('services.gemini.key');
            if (empty($apiKey)) {
                return ['status' => 'error', 'message' => 'Gemini API Key fehlt. Websuche nicht möglich.'];
            }

            // Wir nutzen Gemini 2.5 Flash für schnelle, tiefe Suche via Search Grounding
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

            $payload = [
                "contents" => [
                    [
                        "role" => "user",
                        "parts" => [
                            [
                                "text" => "Bitte recherchiere detailliert und präzise im Internet nach folgendem Thema. Liefere eine zusammenhängende, informative und auf den Punkt gebrachte Zusammenfassung (Deep Research). Suchanfrage: " . $query
                            ]
                        ]
                    ]
                ],
                "tools" => [
                    [
                        "googleSearch" => new \stdClass()
                    ]
                ]
            ];

            $response = \Illuminate\Support\Facades\Http::timeout(45)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $groundingMetadata = $data['candidates'][0]['groundingMetadata'] ?? null;
                
                if (empty($text)) {
                     return ['status' => 'error', 'message' => 'Keine Textantwort von Gemini Research erhalten.'];
                }

                $sourceHint = "";
                if ($groundingMetadata && isset($groundingMetadata['groundingChunks'])) {
                    $sourceHint = "\n\n(Quellen: ";
                    $sources = [];
                    foreach ($groundingMetadata['groundingChunks'] as $chunk) {
                        if (isset($chunk['web']['title'])) {
                            $sources[] = $chunk['web']['title'];
                        }
                    }
                    $sourceHint .= implode(", ", array_unique($sources)) . ")";
                }

                return [
                    'status' => 'success',
                    'content' => $text . $sourceHint,
                    'note' => 'Nutze diese detaillierten Deep Research Informationen, um dem User zu antworten.'
                ];
            }
            
            $err = $response->json();
            $errMsg = $err['error']['message'] ?? 'Unbekannter API Fehler';
            return ['status' => 'error', 'message' => 'Deep Research Service nicht erreichbar: ' . $errMsg];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei Deep Research Websuche: ' . $e->getMessage()];
        }
    }
}

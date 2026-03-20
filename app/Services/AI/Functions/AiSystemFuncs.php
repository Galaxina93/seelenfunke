<?php

namespace App\Services\AI\Functions;

use App\Models\AiKnowledgeBase;
use App\Models\PersonProfile;
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
                'name' => 'save_to_brain',
                'description' => 'Speichert eine Tatsache, Notiz, generelles Wissen, App-Einstellung, Passwort oder eine Information über eine reale Person in deinem zentralen Langzeit-Gehirn. Stichworte: Merke dir das, Notiere, Speicher das für immer, Merk dir ihr Lieblingsessen, Neues Wissen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Kurzer, prägnanter Titel (z.B. "Geburtstag Theresa", "WLAN Passwort").'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Die eigentliche Information, die du dir merken sollst.'
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Relevante Tags zur Kategorisierung. WICHTIG: Verwende Tags wie "person", "wissen", "einstellung" und bei Personen auch den Namen als eigenen Tag.'
                        ]
                    ],
                    'required' => ['title', 'content', 'tags']
                ],
                'callable' => [self::class, 'executeSaveToBrain']
            ],
            [
                'name' => 'visualize_data',
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
                'name' => 'search_brain',
                'description' => 'Durchsucht alle deine Gehirn-Areale (Wiki-Langzeitgedächtnis und alle gespeicherten Personenprofile) nach Wissen über die echte Welt, Fakten, Benutzern oder Einstellungen. Stichworte: Was weißt du über Theresa, Wann hat Max Geburtstag, Wie war das Passwort, Suche im Gehirn, Brain Scan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff (z.B. "Theresa Geburtstag", "Vorlieben", "Rentenversicherungsnummer").'
                        ],
                        'search_areas' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'enum' => ['persons', 'general_knowledge']],
                            'description' => 'Gibt an, wo gesucht werden soll. Du kannst beide Arrays übergeben, wenn du unsicher bist.'
                        ]
                    ],
                    'required' => ['query', 'search_areas']
                ],
                'callable' => [self::class, 'executeSearchBrain']
            ],
            [
                'name' => 'close_ui',
                'description' => 'Schließt alle aktuell in der 3D-Ansicht geöffneten schwebenden Popups, Diagramme und Fenster. Stichworte: Fenster zu, UI schließen, Tabellen ausblenden, Mach das weg, Schließe alles.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCloseUi']
            ],
            [
                'name' => 'open_nav_item',
                'description' => 'Navigiert das Dashboard des Benutzers auf eine bestimmte Unterseite im System-Backend. Stichworte: Bring mich zu den Bestellungen, Öffne Finanzen, Gehe zu den Gutscheinen, Bereich wechseln, Menüpunkte aufrufen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'The exact URL to navigate to, e.g. "/admin/orders" or "/admin/financial-evaluation"'
                        ]
                    ],
                    'required' => ['url']
                ],
                'callable' => [self::class, 'executeOpenNavItem']
            ],
            [
                'name' => 'open_zentrum',
                'description' => 'Öffnet das visuelle 3D Zentrum (Funkira Widget) in der Front-Ansicht. Stichworte: Öffne das Zentrum, Zeig dich zentrum, Mach das Widget auf, Komm her Funkira.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeOpenZentrum']
            ],
            [
                'name' => 'close_zentrum',
                'description' => 'Schließt das visuelle 3D Zentrum. Stichworte: Zentrum schließen, Geh weg, Fokus modus, blend dich aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCloseZentrum']
            ],
            [
                'name' => 'update_brain_entry',
                'description' => 'Aktualisiert einen fehlerhaften oder veralteten Eintrag in deinem Langzeit-Gehirn. Stichworte: Korrigiere ihr Geburtsdatum, Ändere das in meinem Gehirn, Update diesen Fakt, Info austauschen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff, um den alten Eintrag zu finden (z.B. der exakte bisherige Text oder der Vorname der Person).'
                        ],
                        'new_content' => [
                            'type' => 'string',
                            'description' => 'Der neue, korrigierte Inhalt, der gespeichert werden soll.'
                        ],
                        'is_person_fact' => [
                            'type' => 'boolean',
                            'description' => 'Setze dies auf true, wenn es sich um eine Person handelt.'
                        ],
                        'old_content_substring' => [
                            'type' => 'string',
                            'description' => 'Falls is_person_fact=true, gib hier den alten Teil des Textes an, der im Profil durch new_content ERSETZT werden soll. Wenn du das ganze Profil überschreiben willst, lass es leer.'
                        ]
                    ],
                    'required' => ['search_query', 'new_content', 'is_person_fact']
                ],
                'callable' => [self::class, 'executeUpdateBrainEntry']
            ],
            [
                'name' => 'delete_brain_entry',
                'description' => 'Löscht eine gespeicherte Information vollständig aus deinem Erinnerungs-Gehirn. Stichworte: Vergiss das, Lösche diese Info aus dem Profil, Entferne Notiz, Brain Reset.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff, um den zu löschenden Eintrag zu finden (z.B. der exakte Inhalt, Titel oder der Vorname der Person).'
                        ],
                        'is_person_fact' => [
                            'type' => 'boolean',
                            'description' => 'Setze dies auf true, wenn die zu löschende Info in einem Personenprofil liegt.'
                        ],
                        'content_substring_to_delete' => [
                            'type' => 'string',
                            'description' => 'Falls is_person_fact=true, gib hier genau den Teil des Profil-Textes an, der DAGEGEN LÖSCHT werden soll.'
                        ]
                    ],
                    'required' => ['search_query', 'is_person_fact']
                ],
                'callable' => [self::class, 'executeDeleteBrainEntry']
            ],
            [
                'name' => 'search_chat_history',
                'description' => 'Suche im flüchtigen Chat-Verlauf der vergangenen Stunden/Tage. Nutze dies IMMER, wenn der User nach einer vergangenen Unterhaltung, einem Kontext von gestern oder kurzzeitigen Dingen aus dem Chat fragt. Stichworte: Worüber haben wir gestern gesprochen, Was habe ich gerade gesagt, Zeig alte Chat Logs.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'time_filter' => [
                            'type' => 'string',
                            'description' => 'Zeitraum Filter. Erlaubt: \'today\', \'yesterday\', \'last_week\', \'all\' (Standard: \'all\')',
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
                'name' => 'get_system_health',
                'description' => 'Pingt das Server-System an und prüft den technischen Zustand, CPU-Daten, Queue-Workers, Laravel-Caches und Fehler-Logs. Stichworte: Ist das System gesund, Systemüberprüfung, Check Systemstatus, Gibt es IT-Fehler.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemHealth']
            ],
            [
                'name' => 'fix_system_errors',
                'description' => 'Agiert als automatischer Administrator: Behebt gefundene Backend-Fehler durch Cache-Clearing, OPcache Resets und Queue Restarts. FÜHRE DIESES TOOL ZWINGEND AUS, wenn get_system_health Fehler meldet. Stichworte: Repariere das System, Behebe die Fehler, Auto-Heal starten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeFixSystemErrors']
            ],
            [
                'name' => 'get_system_logs',
                'description' => 'Liest detaillierte technische Exception-Logs und Fehler aus Laravel. Stichworte: Welche Errors gibt es genau, Lies das Logfile, Zeig mir den System-Fehler im Detail.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemLogs']
            ],
            [
                'name' => 'read_wiki_files',
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
                'name' => 'get_system_map',
                'description' => 'Generiert eine riesige dynamische Strukturkarte der Systemarchitektur und zeigt dir, welche Tabellen/Ressourcen verbaut sind. Stichworte: Wie ist das Backend aufgebaut, Zeig mir dein Architektur-Wissen, Modelle scannen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemMap']
            ],
            [
                'name' => 'update_funkira_configuration',
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
                'name' => 'update_agent_configuration',
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

    public static function executeUpdateBrainEntry(array $args)
    {
        try {
            $query = strtolower(trim($args['search_query'] ?? ''));
            $newContent = $args['new_content'] ?? '';
            $isPersonFact = $args['is_person_fact'] ?? false;

            if (empty($query) || empty($newContent)) {
                return ['status' => 'error', 'message' => 'Suchbegriff und neuer Inhalt sind erforderlich.'];
            }

            if ($isPersonFact) {
                // Finde Person
                $bestMatch = null;
                $highestSimilarity = 0;
                foreach (PersonProfile::all() as $p) {
                    $dbFullName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name . ' ' . $p->last_name));
                    $dbFirstName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name));

                    if (str_contains($query, $dbFirstName) || str_contains($query, $dbFullName)) {
                        $bestMatch = $p;
                        break;
                    }
                }

                if ($bestMatch) {
                    $oldSub = $args['old_content_substring'] ?? null;
                    if ($oldSub && !empty(trim($oldSub))) {
                        // Suchen und Ersetzen
                        $currentFacts = $bestMatch->ai_learned_facts ?? '';
                        $updatedFacts = str_ireplace($oldSub, $newContent, $currentFacts);

                        if ($currentFacts !== $updatedFacts) {
                            $bestMatch->ai_learned_facts = $updatedFacts;
                            $bestMatch->save();
                            return ['status' => 'success', 'message' => "Der spezifische Fakt bei {$bestMatch->first_name} wurde aktualisiert."];
                        }
                    }

                    // Fallback: Einfach hinten dranhängen (wie save_to_brain)
                    $dateStr = now()->format('d.m.Y');
                    $$bestMatch->ai_learned_facts = ($bestMatch->ai_learned_facts ?? '') . "\n[{$dateStr}] KORRIGIERT: {$newContent}";
                    $bestMatch->save();
                    return ['status' => 'success', 'message' => "Die Korrektur wurde bei {$bestMatch->first_name} vermerkt."];
                }
                return ['status' => 'error', 'message' => 'Person für das Update nicht gefunden.'];
            } else {
                // Wiki Update
                $kb = AiKnowledgeBase::where('title', 'like', "%{$query}%")
                                   ->orWhere('content', 'like', "%{$query}%")
                                   ->first();
                if ($kb) {
                    $kb->content = $newContent;
                    $kb->save();
                    return ['status' => 'success', 'message' => "Der Wiki-Eintrag '{$kb->title}' wurde erfolgreich aktualisiert."];
                }
                return ['status' => 'error', 'message' => 'Kein passender Eintrag im Wiki gefunden.'];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Update fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteBrainEntry(array $args)
    {
        try {
            $query = strtolower(trim($args['search_query'] ?? ''));
            $isPersonFact = $args['is_person_fact'] ?? false;

            if (empty($query)) {
                return ['status' => 'error', 'message' => 'Suchbegriff zum Löschen ist erforderlich.'];
            }

            if ($isPersonFact) {
                $bestMatch = null;
                foreach (PersonProfile::all() as $p) {
                    $dbFullName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name . ' ' . $p->last_name));
                    $dbFirstName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name));

                    if (str_contains($query, $dbFirstName) || str_contains($query, $dbFullName)) {
                        $bestMatch = $p;
                        break;
                    }
                }

                if ($bestMatch) {
                    $subDel = $args['content_substring_to_delete'] ?? null;
                    if ($subDel && !empty(trim($subDel))) {
                        $currentFacts = $bestMatch->ai_learned_facts ?? '';
                        // Versuche auch Zeilenumbrüche etc. zu greifen (sehr rudimentär über str_ireplace)
                        $updatedFacts = str_ireplace($subDel, '', $currentFacts);
                        // Aufräumen von leeren Datumsklammern z.B. "[10.03.2024] \n" falls nur der Text gelöscht wurde
                        $updatedFacts = preg_replace('/\[\d{2}\.\d{2}\.\d{4}\]\s*(?:Notiz:.*?\))?\s*(?=\n|$)/im', '', $updatedFacts);
                        $updatedFacts = preg_replace('/^\s*[\r\n]/m', '', $updatedFacts); // Leere Zeilen entfernen

                        $bestMatch->ai_learned_facts = trim($updatedFacts);
                        $bestMatch->save();
                        return ['status' => 'success', 'message' => "Der Fakt bei {$bestMatch->first_name} wurde erfolgreich gelöscht."];
                    }
                    return ['status' => 'error', 'message' => 'Es wurde kein konkreter Text (content_substring_to_delete) übergeben, um nicht versehentlich alle Fakten der Person zu löschen.'];
                }
                return ['status' => 'error', 'message' => 'Person für die Löschung nicht gefunden.'];
            } else {
                // Wiki Delete
                $kb = AiKnowledgeBase::where('title', 'like', "%{$query}%")
                                   ->orWhere('content', 'like', "%{$query}%")
                                   ->first();
                if ($kb) {
                    $title = $kb->title;
                    $kb->delete();
                    return ['status' => 'success', 'message' => "Der Wiki-Eintrag '{$title}' wurde erfolgreich und permanent gelöscht."];
                }
                return ['status' => 'error', 'message' => 'Kein passender Eintrag im Wiki gefunden, der gelöscht werden könnte.'];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Löschen fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    public static function executeSaveToBrain(array $args)
    {
        try {
            if (empty($args['title']) || empty($args['content'])) {
                return ['status' => 'error', 'message' => 'Titel und Inhalt sind für das Speichern erforderlich.'];
            }

            $tags = $args['tags'] ?? [];
            $searchStrings = array_merge([$args['title']], $tags);

            $bestMatch = null;
            $highestSimilarity = 0;
            $allProfiles = PersonProfile::all();

            foreach ($searchStrings as $str) {
                $strLower = strtolower(trim($str));
                if (empty($strLower) || in_array($strLower, ['person', 'wissen', 'einstellung'])) continue;

                foreach ($allProfiles as $p) {
                    $dbFullName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name . ' ' . $p->last_name));
                    $dbFirstName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name));
                    $dbNickname = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->nickname ?? ''));

                    if ($dbFirstName === $strLower || $dbNickname === $strLower || $dbFullName === $strLower) {
                        $bestMatch = $p;
                        $highestSimilarity = 100;
                        break 2;
                    }

                    $simFirst = 0; similar_text($dbFirstName, $strLower, $simFirst);
                    $simFull = 0; similar_text($dbFullName, $strLower, $simFull);
                    $maxSim = max($simFirst, $simFull);

                    // Fange Fälle ab, in denen AI "Alina Steinhauer" übergibt, in DB aber nur "Alina" steht
                    if (str_contains($strLower, $dbFirstName) && strlen($dbFirstName) > 2) {
                         $maxSim = max($maxSim, 90);
                    }
                    if (str_contains($strLower, $dbFullName) && strlen(trim($dbFullName)) > 3) {
                         $maxSim = 100;
                    }

                    if ($maxSim > $highestSimilarity) {
                        $highestSimilarity = $maxSim;
                        $bestMatch = $p;
                    }
                }
            }

            // Schwellenwert > 80% (relativ streng, um false positives zu vermeiden)
            if ($bestMatch && $highestSimilarity > 80) {
                // Anti-Duplikat Check für Personen
                if (str_contains(strtolower($bestMatch->ai_learned_facts ?? ''), strtolower($args['content']))) {
                    return [
                        'status' => 'success',
                        'message' => "Diese Information merke ich mir kein zweites Mal, da sie mir bezüglich {$bestMatch->first_name} bereits bekannt ist."
                    ];
                }

                $dateStr = now()->format('d.m.Y');
                $newEntry = "\n[{$dateStr}] {$args['content']} (Notiz: {$args['title']})";

                $bestMatch->ai_learned_facts = ($bestMatch->ai_learned_facts ?? '') . $newEntry;
                $bestMatch->save();

                return [
                    'status' => 'success',
                    'message' => "Die Information wurde erfolgreich im Personen-Profil von {$bestMatch->first_name} gespeichert."
                ];
            }

            // Anti-Duplikat Check für AiKnowledgeBase
            $existingKb = AiKnowledgeBase::where('content', 'like', '%' . $args['content'] . '%')
                                       ->orWhere('title', $args['title'])
                                       ->exists();
            if ($existingKb) {
                return [
                    'status' => 'success',
                    'message' => 'Dieser identische Fakten-Eintrag existiert bereits in meinem generellen Wiki. Ich habe ihn nicht doppelt gespeichert.'
                ];
            }

            // Fallback: Speichere in AiKnowledgeBase
            $catId = \App\Models\AiKnowledgeBaseCategory::firstOrCreate(
                ['slug' => 'ai-memory'],
                ['name' => 'AI Memory']
            )->id;

            $kb = AiKnowledgeBase::create([
                'title' => substr($args['title'], 0, 255),
                'slug' => \Illuminate\Support\Str::slug(substr($args['title'], 0, 255)) . '-' . rand(1000, 9999),
                'ai_knowledge_base_category_id' => $catId,
                'content' => $args['content'],
                'is_published' => true
            ]);

            $tagList = array_merge(['ai_memory', 'auto_saved'], $tags);
            $syncTags = [];
            foreach ($tagList as $t) {
                $syncTags[] = \App\Models\AiKnowledgeBaseTag::firstOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($t)],
                    ['name' => $t]
                )->id;
            }
            $kb->tags()->sync($syncTags);

            return [
                'status' => 'success',
                'message' => "Die Information '{$kb->title}' wurde erfolgreich im allgemeinen Langzeitgedächtnis (Wiki) gespeichert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Speichern: ' . $e->getMessage()];
        }
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

    public static function executeSearchBrain(array $args)
    {
        try {
            if (empty($args['query'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Suchbegriff angegeben.'];
            }

            $queryStr = $args['query'];
            $areas = $args['search_areas'] ?? ['persons', 'general_knowledge'];
            $queryLower = strtolower(trim($queryStr));

            $results = [];

            // 1. Suche in Personen (falls erlaubt)
            if (in_array('persons', $areas)) {
                $allProfiles = PersonProfile::all();
                $bestMatch = null;
                $highestSimilarity = 0;

                foreach ($allProfiles as $p) {
                    $dbFullName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name . ' ' . $p->last_name));
                    $dbFirstName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name));
                    $dbLastName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->last_name));
                    $dbNickname = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->nickname ?? ''));

                    if (str_contains($queryLower, $dbFirstName) || str_contains($queryLower, $dbLastName) ||
                        ($dbNickname && str_contains($queryLower, $dbNickname))) {
                        $bestMatch = $p;
                        $highestSimilarity = 100;
                        break;
                    }

                    $simFirst = 0; similar_text($dbFirstName, $queryLower, $simFirst);
                    if ($simFirst > $highestSimilarity) {
                        $highestSimilarity = $simFirst;
                        $bestMatch = $p;
                    }
                }

                if ($bestMatch && $highestSimilarity > 60) {
                    $contextParts = [
                        "[PERSONEN PROFIL GEFUNDEN]",
                        "Name: {$bestMatch->full_name} " . ($bestMatch->nickname ? "(\"{$bestMatch->nickname}\")" : ''),
                        "Beziehung: " . ($bestMatch->relation_type ?? 'Unbekannt'),
                        "Geburtstag: " . ($bestMatch->birthday ? $bestMatch->birthday->format('Y-m-d') : 'Unbekannt'),
                        "E-Mail: " . ($bestMatch->email ?? 'Keine'),
                        "Telefon: " . ($bestMatch->phone ?? 'Keine'),
                    ];
                    if ($bestMatch->system_instructions) {
                        $contextParts[] = "\n--- SYSTEM INSTRUKTIONEN (WICHTIG!) ---\n" . $bestMatch->system_instructions;
                    }
                    if ($bestMatch->ai_learned_facts) {
                        $contextParts[] = "\n--- GELERNTES GEDAETCHNIS ---\n" . $bestMatch->ai_learned_facts;
                    }

                    $results[] = [
                        'type' => 'person_profile',
                        'data' => implode("\n", $contextParts)
                    ];
                }
            }

            // 2. Suche in der AiKnowledgeBase (falls erlaubt)
            if (in_array('general_knowledge', $areas)) {
                $kbResults = AiKnowledgeBase::with(['category', 'tags'])
                    ->where('is_published', true)
                    ->where(function ($q) use ($queryStr) {
                        $q->where('title', 'like', '%' . $queryStr . '%')
                          ->orWhere('content', 'like', '%' . $queryStr . '%')
                          ->orWhereHas('tags', function($t) use ($queryStr) {
                              $t->where('name', 'like', '%' . $queryStr . '%');
                          })
                          ->orWhereHas('category', function($c) use ($queryStr) {
                              $c->where('name', 'like', '%' . $queryStr . '%');
                          });
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();

                foreach ($kbResults as $kb) {
                    $results[] = [
                        'type' => 'knowledge_base',
                        'title' => $kb->title,
                        'category' => $kb->category ? $kb->category->name : 'Allgemein',
                        'tags' => $kb->tags->pluck('name')->implode(', '),
                        'content' => $kb->content,
                        'date' => $kb->created_at->format('Y-m-d')
                    ];
                }
            }

            if (empty($results)) {
                 return [
                    'status' => 'success',
                    'message' => 'Ich habe in meinem Gehirn zu "' . $queryStr . '" nichts gefunden.',
                    'results' => []
                ];
            }

            return ['status' => 'success', 'results_count' => count($results), 'results' => $results];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Durchsuchen des Gehirns: ' . $e->getMessage()];
        }
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
            if ($url === '/admin/newsletters') {
                $url = '/admin/newsletter';
            }
            if ($url === '/admin/vouchers') {
                $url = '/admin/voucher';
            }
            if ($url === '/admin/financial' || $url === '/admin/financials') {
                $url = '/admin/financial-evaluation';
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

            if (class_exists(\App\Models\Global\GlobalLog::class)) {
                $agent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->first();
                \App\Models\Global\GlobalLog::create([
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
            if (!class_exists(\App\Models\Global\GlobalLog::class)) {
                return ['status' => 'error', 'message' => 'GlobalLog-Klasse ist im System nicht existent.'];
            }

            // Hole nur die echten System/KI/Auto-Warnungen und Fehler der letzten 24h
            $logs = \App\Models\Global\GlobalLog::whereIn('status', ['error', 'warning'])
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

            $routesPath = base_path('routes/partials/admin_routes.php');
            if (file_exists($routesPath)) {
                $routesContent = file_get_contents($routesPath);
                $output .= "\n\nVERFÜGBARE SEITEN (NAVIGATION):\nFolgende Seiten existieren im System und können von dir mit dem Tool 'open_nav_item' aufgerufen werden:\n";

                preg_match_all("/Route::get\('(\/admin\/[^']+)'/i", $routesContent, $routeMatches);

                if (!empty($routeMatches[1])) {
                    $uniqueRoutes = array_unique($routeMatches[1]);
                    foreach ($uniqueRoutes as $routeUrl) {
                        $output .= "- $routeUrl\n";
                    }
                }
            }

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

        $agent = AiAgent::where('name', 'Funkira')->first() ?? AiAgent::first();

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

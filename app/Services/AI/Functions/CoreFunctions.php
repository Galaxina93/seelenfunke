<?php

namespace App\Services\AI\Functions;

use App\Models\KnowledgeBase;
use App\Models\PersonProfile;
use App\Services\AI\Functions\SearchChatHistory;

trait CoreFunctions
{
    public static function getCoreFunctionsSchema(): array
    {
        return [
            [
                'name' => 'save_to_brain',
                'description' => 'Speichert eine Tatsache, Notiz, Einstellung oder eine Information über eine Person in deinem zentralen Gehirn. Gib als \'tags\' unbedingt an, ob es sich um generelles Wissen oder Wissen über eine konkrete Person handelt.',
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
                'description' => 'Zeigt strukturierte Daten (wie Listen, Objekte oder Statistiken) visuell im Frontend (Master Modal) des Users an. Nutze dies IMMER, wenn der User nach einer Übersicht, Tabelle, Liste oder Grafik fragt.',
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
                'description' => 'Durchsucht alle deine Gehirn-Areale (sowohl das normale Wiki-Langzeitgedächtnis als auch Personenprofile von Freunden, Familie etc.) gleichzeitig.',
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
                'description' => 'Schließt alle aktuell in der 3D-Ansicht geöffneten schwebenden Fenster, Tabellen und Charts. Nutze dies IMMER, wenn Alina sagt "Fenster zu", "Schließen", "Tabellen weg" oder ähnliches.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCloseUi']
            ],
            [
                'name' => 'open_nav_item',
                'description' => 'Navigiert den Benutzer zu einer bestimmten Seite im System. Verwende dies, wenn der Benutzer darum bittet, einen bestimmten Bereich zu "öffnen", "dorthin zu gehen" oder "dorthin zu navigieren" (z. B. Bestellungen, Finanzen). Die Navigation wird komplett durch dieses Tool im Hintergrund gesteuert, du musst im Textfall nichts weiter erwähnen.',
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
                'description' => 'Öffnet das visuelle 3D Zentrum (Funkira Widget). Nutze dies IMMER, wenn Alina sagt "Öffne das Zentrum", "Zeig dich", "Zentrum aufrufen" oder ähnliches.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeOpenZentrum']
            ],
            [
                'name' => 'close_zentrum',
                'description' => 'Schließt das visuelle 3D Zentrum (Funkira Widget) und kehrt zum Chat zurück. Nutze dies IMMER, wenn Alina sagt "Schließe das Zentrum", "Zentrum zu", "Zurück zum Chat" oder ähnliches.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCloseZentrum']
            ],
            [
                'name' => 'update_brain_entry',
                'description' => 'Aktualisiert einen bestehenden Eintrag in deinem Gehirn (Wiki oder Personenprofil). Nutze dies, wenn du feststellst, dass eine Information veraltet oder falsch ist.',
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
                'description' => 'Löscht einen bestehenden Eintrag komplett aus deinem Gehirn (Wiki oder Personenprofil). Nutze dies, wenn der User dich explizit bittet, eine bestimmte Information zu vergessen.',
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
            array_merge(SearchChatHistory::schema()['function'], [
                'callable' => [self::class, 'executeSearchChatHistory']
            ]),

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
                $kb = KnowledgeBase::where('title', 'like', "%{$query}%")
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
                $kb = KnowledgeBase::where('title', 'like', "%{$query}%")
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

            // Anti-Duplikat Check für KnowledgeBase
            $existingKb = KnowledgeBase::where('content', 'like', '%' . $args['content'] . '%')
                                       ->orWhere('title', $args['title'])
                                       ->exists();
            if ($existingKb) {
                return [
                    'status' => 'success',
                    'message' => 'Dieser identische Fakten-Eintrag existiert bereits in meinem generellen Wiki. Ich habe ihn nicht doppelt gespeichert.'
                ];
            }

            // Fallback: Speichere in KnowledgeBase
            $kb = KnowledgeBase::create([
                'title' => substr($args['title'], 0, 255),
                'slug' => \Illuminate\Support\Str::slug(substr($args['title'], 0, 255)) . '-' . rand(1000, 9999),
                'category' => 'AI Memory',
                'content' => $args['content'],
                'tags' => array_merge(['ai_memory', 'auto_saved'], $tags),
                'is_published' => true
            ]);

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

            // 2. Suche in der KnowledgeBase (falls erlaubt)
            if (in_array('general_knowledge', $areas)) {
                $kbResults = KnowledgeBase::where('is_published', true)
                    ->where(function ($q) use ($queryStr) {
                        $q->where('title', 'like', '%' . $queryStr . '%')
                          ->orWhere('content', 'like', '%' . $queryStr . '%')
                          ->orWhereJsonContains('tags', $queryStr);
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(3)
                    ->get(['title', 'content', 'category', 'created_at']);

                foreach ($kbResults as $kb) {
                    $results[] = [
                        'type' => 'knowledge_base',
                        'title' => $kb->title,
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

            // Rewrite typische Plural-Fehler der KI
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
        return SearchChatHistory::call($args);
    }


}

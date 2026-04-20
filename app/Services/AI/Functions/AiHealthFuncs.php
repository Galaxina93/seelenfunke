<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\AiHealthProtocol;
use App\Models\Ai\AiHealthTreatmentPlan;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use Exception;
use Illuminate\Support\Facades\Auth;

trait AiHealthFuncs
{
    /**
     * Define the Health specific tools for Dr. Funki
     */
    public static function getAiHealthFuncsSchema(): array
    {
        return [
            [
                'name' => 'health_get_patient_file',
                'description' => 'Liest die aktive Patientenakte (Behandlungspläne, Medikamente, Protokolle) des Nutzers aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetPatientFile']
            ],
            [
                'name' => 'health_create_treatment_plan',
                'description' => 'Erstellt einen neuen medizinischen Behandlungsplan für den User inkl. Medikamente und Start-/Enddatum.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Titel des Behandlungsplans (z.B. "Magen-Darm Aufbaukur").'
                        ],
                        'diagnosis_summary' => [
                            'type' => 'string',
                            'description' => 'Zusammenfassung der ärztlichen Diagnose.'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Startdatum der Behandlung im Format YYYY-MM-DD.'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Erwartetes Enddatum (YYYY-MM-DD).'
                        ],
                        'items' => [
                            'type' => 'array',
                            'description' => 'Liste der Aufgaben, Medikamente oder Anwendungen.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'description' => 'Name des Medikaments/Aufgabe (z.B. Ibuprofen 400).'],
                                    'dosage' => ['type' => 'string', 'description' => 'Dosis/Häufigkeit (z.B. 1x morgens).'],
                                    'duration_days' => ['type' => 'integer', 'description' => 'Wie viele Tage?'],
                                    'notes' => ['type' => 'string', 'description' => 'Zusätzliche Einnahmehinweise.']
                                ],
                                'required' => ['name', 'dosage']
                            ]
                        ]
                    ],
                    'required' => ['title', 'diagnosis_summary', 'items', 'start_date']
                ],
                'callable' => [self::class, 'executeCreateTreatmentPlan']
            ],
            [
                'name' => 'health_update_treatment_plan',
                'description' => 'Bearbeitet einen bestehenden Behandlungsplan. Erlaubt das Ändern von Text/Daten, das Hinzufügen neuer Positionen (Medikamente/Aufgaben) und das Löschen von Positionen über ihre ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'plan_id' => [
                            'type' => 'string',
                            'description' => 'Die ID des zu bearbeitenden Behandlungsplans.'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Neuer Titel (falls Änderung gewünscht, sonst weglassen).'
                        ],
                        'diagnosis_summary' => [
                            'type' => 'string',
                            'description' => 'Neue/erweiterte Zusammenfassung der Diagnose.'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Neues Startdatum im Format YYYY-MM-DD.'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Neues Enddatum (YYYY-MM-DD).'
                        ],
                        'items_to_add' => [
                            'type' => 'array',
                            'description' => 'Liste NEUER Aufgaben oder Medikamente, die hinzugefügt werden sollen.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'dosage' => ['type' => 'string'],
                                    'duration_days' => ['type' => 'integer'],
                                    'notes' => ['type' => 'string']
                                ],
                                'required' => ['name', 'dosage']
                            ]
                        ],
                        'items_to_remove' => [
                            'type' => 'array',
                            'description' => 'Array von ITEM-IDs, die aus dem Plan gelöscht werden sollen.',
                            'items' => ['type' => 'integer']
                        ]
                    ],
                    'required' => ['plan_id']
                ],
                'callable' => [self::class, 'executeUpdateTreatmentPlan']
            ],
            [
                'name' => 'health_complete_treatment_plan',
                'description' => 'Markiert einen bestehenden Behandlungsplan als Durchgeführt und hinterlegt das Abschlussergebnis / Fazit.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'plan_id' => [
                            'type' => 'string',
                            'description' => 'Die ID des Behandlungsplans.'
                        ],
                        'result_evaluation' => [
                            'type' => 'string',
                            'description' => 'Detailliertes und fachliches Fazit / Ergebnis der Behandlung.'
                        ]
                    ],
                    'required' => ['plan_id', 'result_evaluation']
                ],
                'callable' => [self::class, 'executeCompleteTreatmentPlan']
            ],
            [
                'name' => 'health_write_protocol',
                'description' => 'Dokumentiert dauerhaft ein ärztliches Analyse-Fazit / Ergebnis-Protokoll eines Gesprächs für die Akte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => [
                            'type' => 'string',
                            'description' => 'Klares, umfangreiches medizinisches Markdown-Ergebnisprotokoll.'
                        ]
                    ],
                    'required' => ['content']
                ],
                'callable' => [self::class, 'executeWriteProtocol']
            ],
            [
                'name' => 'health_search_medical_web',
                'description' => 'Durchsucht das Internet nach spezifischen, aktuellen medizinischen Studien, Medikamentenhinweisen oder Symptomen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff, z.B. "Langzeitwirkungen von Medikament X" oder "Aktuelle Symptombehandlung Y".'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeSearchMedicalWeb']
            ],
            [
                'name' => 'health_create_medication',
                'description' => 'Fügt ein neues, aktives Medikament in die Patientenakte ein, sobald der Nutzer ein Medikament erwähnt, das er aktuell, langfristig oder kurzfristig einnimmt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name des Medikaments (z.B. Ibuprofen 400).'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Zusätzliche Infos, wofür es eingenommen wird, Nebenwirkungen oder Notizen.'
                        ],
                        'active_ingredients' => [
                            'type' => 'string',
                            'description' => 'Hauptwirkstoff(e) des Medikaments (falls bekannt).'
                        ],
                        'dosage' => [
                            'type' => 'string',
                            'description' => 'Dosierung (z.B. 400mg, 1 Tablette).'
                        ],
                        'frequency' => [
                            'type' => 'string',
                            'description' => 'Häufigkeit der Einnahme (z.B. 1x morgens, bei Bedarf).'
                        ],
                        'is_long_term' => [
                            'type' => 'boolean',
                            'description' => 'True, wenn es sich um eine ständige Dauermedikation handelt. False bei kurzfristigem Bedarf.'
                        ]
                    ],
                    'required' => ['name']
                ],
                'callable' => [self::class, 'executeCreateMedication']
            ],
            [
                'name' => 'health_create_todo',
                'description' => 'Erstellt eine ärztliche To-Do oder Erinnerung für den Patienten (z.B. "Termin beim Hautarzt vereinbaren", "Bluthochdruck messen").',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Kurze To-Do.'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Priorität',
                            'enum' => ['hoch', 'mittel', 'niedrig']
                        ]
                    ],
                    'required' => ['title']
                ],
                'callable' => [self::class, 'executeCreateHealthTodo']
            ],
            [
                'name' => 'health_get_doctors',
                'description' => 'Liest alle Ärzte und Arztpraxen (Kontakte) aus dem Adressbuch aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetDoctors']
            ],
            [
                'name' => 'health_create_doctor',
                'description' => 'Legt einen neuen Arzt oder eine neue medizinische Anlaufstelle (Praxis) im Adressbuch an.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => ['type' => 'string', 'description' => 'Vorname (oder Angabe wie "Gemeinschaftspraxis").'],
                        'last_name' => ['type' => 'string', 'description' => 'Nachname des Arztes.'],
                        'nickname' => ['type' => 'string', 'description' => 'Spitzname oder Anzeigename (z.B. "Hausarzt Dr. Müller").'],
                        'relation_type' => ['type' => 'string', 'description' => 'Art des Arztes (z.B. "Hausarzt", "Hautarzt").'],
                        'email' => ['type' => 'string'],
                        'phone' => ['type' => 'string', 'description' => 'Telefon und ggf. Faxnummer.'],
                        'street' => ['type' => 'string'],
                        'postal_code' => ['type' => 'string'],
                        'city' => ['type' => 'string'],
                        'system_instructions' => ['type' => 'string', 'description' => 'Zusätzliche Notizen zum Arzt, Sprechzeiten oder Spezialisierungen.']
                    ],
                    'required' => ['first_name', 'last_name', 'relation_type']
                ],
                'callable' => [self::class, 'executeCreateDoctor']
            ],
            [
                'name' => 'health_update_doctor',
                'description' => 'Aktualisiert (Schreiben/Archivieren) die Daten eines bestehenden Arztes im Adressbuch über seine ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Die Kontakt-ID des Arztes.'],
                        'first_name' => ['type' => 'string'],
                        'last_name' => ['type' => 'string'],
                        'nickname' => ['type' => 'string'],
                        'relation_type' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'street' => ['type' => 'string'],
                        'postal_code' => ['type' => 'string'],
                        'city' => ['type' => 'string'],
                        'system_instructions' => ['type' => 'string', 'description' => 'Notizen zum Arzt. Schreibe "[ARCHIVIERT]" hier hinein, um den Arzt zu archivieren.']
                    ],
                    'required' => ['contact_id']
                ],
                'callable' => [self::class, 'executeUpdateDoctor']
            ],
            [
                'name' => 'health_delete_doctor',
                'description' => 'Löscht einen Arzt vollständig aus dem Adressbuch.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Die Kontakt-ID des Arztes.'],
                    ],
                    'required' => ['contact_id']
                ],
                'callable' => [self::class, 'executeDeleteDoctor']
            ]
        ];
    }

    public static function executeGetPatientFile(array $args)
    {
        try {
            $user = Auth::user();
            if (!$user) return ['error' => true, 'message' => 'Nicht authentifiziert.'];

            $activePlans = AiHealthTreatmentPlan::where('user_id', $user->id)
                ->where('status', 'active')
                ->with('items')
                ->get();

            $medications = \App\Models\Ai\AiHealthMedication::where('user_id', $user->id)
                ->get();

            $protocols = AiHealthProtocol::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return [
                'success' => true,
                'active_treatment_plans' => $activePlans->toArray(),
                'active_medications' => $medications->toArray(),
                'recent_protocols' => $protocols->toArray()
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public static function executeCreateTreatmentPlan(array $args)
    {
        try {
            $user = Auth::user();
            if (!$user) return ['error' => true, 'message' => 'Kein Admin/CEO authentifiziert.'];

            $plan = AiHealthTreatmentPlan::create([
                'user_id' => $user->id,
                'ai_agent_id' => session('current_ai_agent_id'),
                'title' => $args['title'],
                'diagnosis_summary' => $args['diagnosis_summary'],
                'start_date' => $args['start_date'] ?? null,
                'end_date' => $args['end_date'] ?? null,
                'status' => 'active',
            ]);

            if (!empty($args['items'])) {
                foreach ($args['items'] as $item) {
                    $plan->items()->create([
                        'name' => $item['name'] ?? 'Unbekannt',
                        'dosage' => $item['dosage'] ?? '0',
                        'duration_days' => $item['duration_days'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => "Behandlungsplan erfolgreich erstellt.",
                'plan_id' => $plan->id
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public static function executeUpdateTreatmentPlan(array $args)
    {
        try {
            $plan = AiHealthTreatmentPlan::findOrFail($args['plan_id']);
            
            $updateData = [];
            if (isset($args['title'])) $updateData['title'] = $args['title'];
            if (isset($args['diagnosis_summary'])) $updateData['diagnosis_summary'] = $args['diagnosis_summary'];
            if (isset($args['start_date'])) $updateData['start_date'] = $args['start_date'];
            if (isset($args['end_date'])) $updateData['end_date'] = $args['end_date'];
            
            if (!empty($updateData)) {
                $plan->update($updateData);
            }
            
            if (!empty($args['items_to_remove'])) {
                $plan->items()->whereIn('id', $args['items_to_remove'])->delete();
            }

            if (!empty($args['items_to_add'])) {
                foreach ($args['items_to_add'] as $item) {
                    $plan->items()->create([
                        'name' => $item['name'] ?? 'Unbekannt',
                        'dosage' => $item['dosage'] ?? '0',
                        'duration_days' => $item['duration_days'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => "Behandlungsplan erfolgreich aktualisiert."
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => 'Plan nicht gefunden oder fehlerhafte Bearbeitung: ' . $e->getMessage()];
        }
    }

    public static function executeCompleteTreatmentPlan(array $args)
    {
        try {
            $plan = AiHealthTreatmentPlan::findOrFail($args['plan_id']);
            $plan->update([
                'status' => 'completed',
                'result_evaluation' => $args['result_evaluation']
            ]);

            return ['success' => true, 'message' => "Plan als durchgeführt markiert."];
        } catch (Exception $e) {
            return ['error' => true, 'message' => 'Plan nicht gefunden oder Fehler bei Speicherung.'];
        }
    }

    public static function executeGetDoctors(array $args)
    {
        try {
            $doctors = \App\Models\Management\ManagementContact::where('relation_type', 'like', '%arzt%')
                ->orWhere('relation_type', 'like', '%Praxis%')
                ->orderBy('last_name', 'asc')
                ->get();
            return [
                'success' => true,
                'doctors' => $doctors->toArray()
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public static function executeCreateDoctor(array $args)
    {
        try {
            $doctor = \App\Models\Management\ManagementContact::create([
                'first_name' => $args['first_name'],
                'last_name' => $args['last_name'],
                'nickname' => $args['nickname'] ?? '',
                'relation_type' => $args['relation_type'],
                'email' => $args['email'] ?? '',
                'phone' => $args['phone'] ?? '',
                'street' => $args['street'] ?? '',
                'postal_code' => $args['postal_code'] ?? '',
                'city' => $args['city'] ?? '',
                'system_instructions' => $args['system_instructions'] ?? '',
            ]);

            return [
                'success' => true,
                'message' => 'Medizinischer Kontakt (Arzt/Praxis) erfolgreich angelegt.',
                'contact_id' => $doctor->id
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public static function executeUpdateDoctor(array $args)
    {
        try {
            $doctor = \App\Models\Management\ManagementContact::findOrFail($args['contact_id']);
            
            $updateData = [];
            foreach (['first_name', 'last_name', 'nickname', 'relation_type', 'email', 'phone', 'street', 'postal_code', 'city', 'system_instructions'] as $field) {
                if (isset($args[$field])) {
                    $updateData[$field] = $args[$field];
                }
            }

            if (!empty($updateData)) {
                $doctor->update($updateData);
            }

            return [
                'success' => true,
                'message' => 'Medizinischer Kontakt (Arzt/Praxis) erfolgreich aktualisiert.'
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => 'Arzt nicht gefunden oder Fehler beim Update: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteDoctor(array $args)
    {
        try {
            $doctor = \App\Models\Management\ManagementContact::findOrFail($args['contact_id']);
            $doctor->delete();
            return ['success' => true, 'message' => 'Arzt erfolgreich aus dem System gelöscht.'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => 'Löschen fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    public static function executeWriteProtocol(array $args)
    {
        try {
            $user = Auth::user();
            if (!$user) return ['error' => true, 'message' => 'Nicht authentifiziert.'];

            $activePlan = AiHealthTreatmentPlan::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            $protocol = AiHealthProtocol::create([
                'user_id' => $user->id,
                'ai_agent_id' => session('current_ai_agent_id'),
                'ai_health_treatment_plan_id' => $activePlan ? $activePlan->id : null,
                'content' => $args['content'],
            ]);

            return [
                'success' => true,
                'message' => "Protokoll in der Patientenakte gespeichert.",
                'protocol_id' => $protocol->id
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public static function executeSearchMedicalWeb(array $args)
    {
        try {
            $apiKey = env('SCRAPER_API_KEY', '707ccc851a9e7c4759106d2f6e6bf764');
            $query = $args['query'];

            \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                'progress' => 20,
                'action_text' => 'Baue Stealth-Verbindung (ScraperAPI) auf für: "' . $query . '" ...'
            ], 60);

            $targetUrl = "https://html.duckduckgo.com/html/?q=" . urlencode($query);

            $response = \Illuminate\Support\Facades\Http::timeout(60)->get('http://api.scraperapi.com', [
                'api_key' => $apiKey,
                'url' => $targetUrl,
                'country_code' => 'de',
            ]);

            if (!$response->successful()) {
                return ['error' => true, 'message' => 'Die Suche ist aktuell fehlgeschlagen (HTTP ' . $response->status() . '). BITTE BRICH AB UND ANTWORTE AUS DEINEM EIGENEN WISSENSSTAND. Führe keine weiteren Suchen durch.'];
            }

            \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                'progress' => 70,
                'action_text' => 'Web-DOM geladen. Extrahiere medizinische Nodes...'
            ], 60);

            $html = $response->body();

            // Parse simple text snippets
            preg_match_all('/<a class="result__snippet[^>]*>(.*?)<\/a>/is', $html, $matches);
            $snippets = $matches[1] ?? [];

            $results = [];
            foreach(array_slice($snippets, 0, 7) as $snippet) {
                $results[] = trim(strip_tags(html_entity_decode($snippet, ENT_QUOTES, 'UTF-8')));
            }

            if (empty($results)) {
                preg_match_all('/class="result__body".*?>(.*?)<\/div>/is', $html, $fallbackMatches);
                $fallbackSnippets = $fallbackMatches[1] ?? [];
                foreach(array_slice($fallbackSnippets, 0, 5) as $snippet) {
                    $results[] = trim(strip_tags(html_entity_decode($snippet, ENT_QUOTES, 'UTF-8')));
                }
            }

            if (empty($results)) {
                return [
                    'error' => true,
                    'message' => "Die Internetsuche nach '{$query}' lieferte keine direkten Text-Auswertungen (mögliche Bot-Sperre). Führe KEINE weitere Suche durch. Nutze ab sofort stattdessen ausschließlich dein intern antrainiertes medizinisches Fachwissen für die Diagnose."
                ];
            }

            \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                'progress' => 100,
                'action_text' => count($results) . ' Resultate gefiltert. Lese Daten ein...'
            ], 60);

            return [
                'success' => true,
                'query' => $query,
                'results' => array_filter($results),
                'note' => 'Nutze diese Auszüge aus dem Web für deine medizinische Antwort. VERMEIDE WEITERE SUCHEN, falls du jetzt ausreichende Daten hast.'
            ];

        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => 'Web-Suche blockiert oder Timeout: ' . $e->getMessage() . '. STOPPE DIE ENDLOSSCHLEIFE. Führe diese oder andere Suchen NICHT erneut aus! Entwickle ein Fazit oder Protokoll aus deinem Trainingsdatensatz.'
            ];
        }
    }

    public static function executeCreateMedication(array $args)
    {
        try {
            $user = Auth::user();
            if (!$user) return ['error' => true, 'message' => 'Nicht authentifiziert.'];

            $med = \App\Models\Ai\AiHealthMedication::create([
                'user_id' => $user->id,
                'name' => $args['name'],
                'description' => $args['description'] ?? null,
                'active_ingredients' => $args['active_ingredients'] ?? null,
                'dosage' => $args['dosage'] ?? null,
                'frequency' => $args['frequency'] ?? null,
                'is_long_term' => $args['is_long_term'] ?? false,
            ]);

            return [
                'success' => true,
                'message' => "Medikament " . $args['name'] . " wurde erfolgreich zur Patientenakte hinzugefügt.",
                'medication_id' => $med->id
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public static function executeCreateHealthTodo(array $args)
    {
        try {
            if (empty($args['title'])) {
                return ['status' => 'error', 'message' => 'Titel fehlt.'];
            }

            $list = ManagementTaskList::firstOrCreate(
                ['name' => 'Ärztliche Anweisungen'],
                ['icon' => 'heart', 'color' => '#EF4444']
            );

            $task = ManagementTask::create([
                'title' => substr($args['title'], 0, 255),
                'priority' => $args['priority'] ?? 'hoch',
                'is_completed' => false,
                'task_list_id' => $list->id
            ]);

            return [
                'status' => 'success',
                'message' => "Die medizinische Aufgabe '{$task->title}' wurde dem Patienten zugewiesen.",
                'task_id' => $task->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Aufgabe: ' . $e->getMessage()];
        }
    }
}

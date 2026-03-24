<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\Health\AiHealthProtocol;
use App\Models\Ai\Health\AiHealthTreatmentPlan;
use Illuminate\Support\Facades\Auth;
use Exception;

trait AiHealthFuncs
{
    /**
     * Define the Health specific tools for Dr. Funki
     */
    public static function getAiHealthFuncsSchema(): array
    {
        return [
            [
                'name' => 'create_treatment_plan',
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
                'callable' => function (array $args) {
                    try {
                        $user = Auth::user();
                        if (!$user) return ['error' => true, 'message' => 'Kein Admin/CEO authentifiziert.'];

                        $plan = AiHealthTreatmentPlan::create([
                            'user_id' => $user->id,
                            'ai_agent_id' => session('current_ai_agent_id'), // Assuming session tracks the agent
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
            ],
            [
                'name' => 'complete_treatment_plan',
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
                'callable' => function (array $args) {
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
            ],
            [
                'name' => 'write_health_protocol',
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
                'callable' => function (array $args) {
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
            ],
            [
                'name' => 'search_medical_web',
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
                'callable' => function (array $args) {
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
                            // 'keep_headers' => 'true'
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
                            // Secondary fallback matching if DuckDuckGo changes DOM
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
            ],
            [
                'name' => 'create_health_medication',
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
                'callable' => function (array $args) {
                    try {
                        $user = Auth::user();
                        if (!$user) return ['error' => true, 'message' => 'Nicht authentifiziert.'];

                        $med = \App\Models\Ai\Health\AiHealthMedication::create([
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
            ]
        ];
    }
}

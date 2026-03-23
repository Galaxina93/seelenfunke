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

                        $protocol = AiHealthProtocol::create([
                            'user_id' => $user->id,
                            'ai_agent_id' => session('current_ai_agent_id'),
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
                    // Fallback to a simulated search if no DuckDuckGo or specific API is configured.
                    // Ideally we use a SerpAPI or DDG here. For now we will return a generic search prompt 
                    // that tells the Agent to use their existing intelligence if they can't browse directly,
                    // or implement a basic CURL to Wikipedia / DuckDuckGo Lite.
                    
                    try {
                        $query = urlencode($args['query']);
                        $url = "https://html.duckduckgo.com/html/?q={$query}";
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) MedicalAgent/1.0");
                        $html = curl_exec($ch);
                        curl_close($ch);

                        if (!$html) {
                            return ['error' => true, 'message' => 'Die Suche ist aktuell fehlgeschlagen. Netzwerkfehler.'];
                        }

                        // Parse simple text snippets
                        preg_match_all('/<a class="result__snippet[^>]*>(.*?)<\/a>/is', $html, $matches);
                        $snippets = $matches[1] ?? [];
                        
                        $results = [];
                        foreach(array_slice($snippets, 0, 5) as $snippet) {
                            $results[] = strip_tags($snippet);
                        }

                        return [
                            'success' => true,
                            'query' => $args['query'],
                            'results' => $results,
                            'note' => 'Nutze diese Auszüge aus dem Web für deine medizinische Antwort.'
                        ];
                        
                    } catch (Exception $e) {
                        return ['error' => true, 'message' => 'Web-Suche nicht verfügbar: ' . $e->getMessage()];
                    }
                }
            ]
        ];
    }
}

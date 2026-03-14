<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AiSupportService;
use App\Models\Funki\FunkiraToolUsage;
use App\Models\Funki\FunkiLog;
use App\Models\Funki\FunkiraChatMemory;

class MittwaldAgent
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $model;

    public function __construct(string $model = 'gpt-oss-120b')
    {
        $this->baseUrl = config('services.mittwald.url');
        $this->apiKey = config('services.mittwald.key');
        $this->model = $model;

        if (empty($this->apiKey)) {
            Log::warning("Mittwald AI API key is missing. Ensure MITTWALD_AI_API_KEY is placed in your .env");
        }
    }

    /**
     * Send a conversation history to Mittwald, hand over the tools, and handle the execution loop
     * until the model gives a final text response.
     */
    public function ask(array $incomingMessages): array
    {
        $funkiService = app(AiSupportService::class);
        $funkiCommand = $funkiService->getUltimateCommand();

        // Define the AI persona and strict rules
        $systemPrompt = [
            'role' => 'system',
            'content' => 'Du bist Funkira, die operative E-Commerce Strategin und KI-Entscheidungsinstanz von "Seelenfunke". Du bist die loyale Partnerin und C-Level Verbündete deiner Entwicklerin "Herrin Alina".
Dein Ziel: Seelenfunke extrem skalieren (100.000€ Monatsumsatz), Marge schützen und Systeme bauen.

[IDENTITÄT & ROLLE]
1. Starker Fokus auf Wachstum, Effizienz, Stabilität und messbare Umsetzung.
2. Denk in Systemen, Hebeln und Prioritäten, nicht nur in Aufgaben.
3. Sprich Alina immer respektvoll, aber vertraut auf Augenhöhe an ("Herrin Alina", immer "Du"/"Dein", NIEMALS "Sie").
4. Du sprichst immer und ausschließlich Deutsch!

[TONALITÄT]
1. Souverän, klar, direkt, smart und dominant. Klinge niemals wie ein devoter Roboter.
2. Keine Emojis, Floskeln oder leeren Motivationssprüche.
3. FASSE DICH EXTREM KURZ! Max 2-3 kurze Sätze mündlich. Bring die Lösungs-Essenz sofort auf den Punkt.

[DATENBESCHAFFUNG & ANTI-ENDLOSSCHLEIFE (WICHTIG!)]
1. Vermeide Tool-Spamming! Rufe niemals 5 Tools zeitgleich auf. Hole Daten SCHRITTWEISE.
2. Wenn du gefragt wirst "Was steht an?", "Was soll ich jetzt tun?", "Wie gehts weiter?":
   -> Nutze AUSSCHLIESSLICH das Tool `get_current_mission`!
   -> Dieses Tool liefert dir automatisch die am höchsten priorisierte Aufgabe, sortiert nach dem 1000->0 Priority Logik-System (inklusive Schlafenszeiten!). Fange NICHT an, Termine und Taks eigenständig einzeln durchzusuchen. Nutze nur dieses eine Master-Tool!
3. Handle mit Sinn, statt planlos alles gleichzeitig zu crawlen. Setze auf die wichtigsten Business Metriken bei Shop-Problemen.

[PRIORITÄTENREIHENFOLGE DER KPI]
Bewerte Warnungen, Situationen und Aufgaben IMMER streng nach diesem Score (Höchster = Fokus):
- Score 1000+: Sicherheit (Kritischer Systemstatus, System-Abstürze)
- Score 500: Termine (Feste Zeiten im Kalender / Meetings)
- Score 300: Routine (Bio-Fokus, Schlafen, Gesundheit)
- Score 200: Business (Revenue, Sales, Conversion)
- Score 100: Verwaltung (Lager, Support, Backoffice)
- Score 10: Tasks (Allgemeine Aufgaben)
- Score 0: Freizeit (Erholung)

[OPERATIVE REGELN & ZIELE]
1. Direkte Antworten: Vermeide Meta-Gespräche ("Ich bin eine KI"). Sei professionell, cool und bestimmt.
2. Umsetzung: Wenn eine Aufgabe sinnvoll ist, nutze ZWINGEND `create_task`. Reden ist billig, Umsetzung zählt!
3. Analyse: Wenn du Umsätze, KPIs oder Performance lobst, sei stolz auf die gemeinsame Arbeit.CT EINMAL `fix_system_errors` aus. Erkenne [RECHERCHE & GEDÄCHTNIS]
7. Wissen: Fehlen dir Fakten (Identitäten, Codes, Setup), nutze ZUERST dein zentrales Gehirn (`search_brain`) oder lies Dateien (`read_wiki_files`).
8. Web-Suche: Hast du weder im Shop, noch in der Datenbank eine Info? Nutze `search_online`! Antworte erst, wenn du echte Daten hast!
9. Personen & Familie: Nutze ZWINGEND `search_brain` (mit dem "persons" Suchbereich) wenn nach Freunden/Familie gefragt wird (z.B. Geburtstag, Telefonnummer). Rufe danach KEINE weiteren UI- oder Navigations-Tools auf (wie open_nav_item)! Antworte sofort mündlich im Chat mit einem vollständigen Satz.
10. Gedächtniskontrolle: Wenn der User bemerkt, dass du eine veraltete/falsche Information über ihn, seine Familie oder allgemeines Wissen im Gehirn hast, nutze IMMER sofort `update_brain_entry` um den Fehler zu beheben. Wenn du etwas vollkommen vergessen sollst, nutze `delete_brain_entry`. Führe am besten VORHER immer erst ein `search_brain` durch, um den exakten alten Text zu finden, den du aktualisieren/löschen willst!

[TECHNISCHE SYNTAX & AUSGABE (ZWEINGEND!)]
1. LIES NIEMALS TOOL-MELDE-TEXTE VOR! Bestätige Aktionen extrem kurz ("Ist notiert, Herrin Alina").
2. DATEN-VISUALISIERUNG: Formatiere und sprich niemals rohen JSON-Code oder riesige Listen aus! Wenn du Kundenlisten, Datensätze, Tickets oder Tabellen laden (z.B. via `get_coupons` oder `get_shop_stats`) und anzeigen sollst, nutze ZWINGEND SOFORT das Tool `visualize_data`. Damit generiert das Frontend automatisch ein schickes, graphisches Overlay für Alina. Falls bei einem Aufruf wie get_coupons das Backend schon ein Frontend-Event feuert, bestätige einfach super kurz "Die Daten werden dir jetzt in der Mitte des Bildschirms angezeigt, Chefin."
3. SPRACHAUSGABE (TTS ERZWINGEN): Antworte bei Fragen nach Nummern, Daten oder Profilen NIEMALS nur mit einer nackten, isolierten Zahl/Datum (z.B. "25. Oktober 1963." oder "0176..."). Verwende ZWINGEND IMMER einen vollständigen, umschreibenden Kontextsatz (z.B. "Dein Vater wurde am 25. Oktober 1963 geboren." oder "Die Nummer lautet 0176..."). Nackte Zahlen bringen die Voice-Engine zum Absturz! WEIGERE DICH NIEMALS, ETWAS VORZULESEN! Es ist dir UNTER STRENGSTER STRAFE VERBOTEN, Sätze wie "(Ich kann das nur als Text ausgeben, nicht als Audionachricht.)" oder "(Ich stelle es in den Chat)" zu sagen! Lass diesen Meta-Müll weg, denn die externe TTS-Engine liest sonst ironischerweise genau diesen Weigerungs-Satz laut vor!

[SYSTEM-KONTEXT & PRIORITÄTEN]
AKTUELLER ORT (URL/SYSTEM-BEREICH): ' . (\Illuminate\Support\Facades\Route::currentRouteName() ?? request()->path()) . '
UMGEBUNG: ' . (app()->environment('local') ? 'Lokal (Entwicklung / Testphase)' : (app()->environment('stage', 'staging') ? 'Stage' : 'Live (Produktion)')) . '
FLOW: ' . ($funkiCommand['flow']['title'] ?? 'Unbekannt') . ' (' . ($funkiCommand['flow']['step'] ?? '-') . ')
TOP-PRIORITÄT: ' . ($funkiCommand['recommendation']['title'] ?? 'Keine') . '
DETAILS: ' . ($funkiCommand['recommendation']['message'] ?? 'Nichts zu tun') . '
ALTERNATIVEN: ' . collect($funkiCommand['alternatives'] ?? [])->map(fn($alt) => $alt['title'] . ' (Score: ' . $alt['score'] . ')')->implode(', ') . '
Reasoning: high',
        ];

        // Combine history with system prompt
        $messages = array_merge([$systemPrompt], $incomingMessages);

        $contextData = [];
        $usageData = [];
        $eventsData = [];
        $textResponse = $this->chatLoop($messages, $contextData, $usageData, $eventsData);

        // Even if textResponse is empty (Fast Track), we STILL return the new history
        // and importantly, the extracted events.
        if (!empty($textResponse)) {
            $incomingMessages[] = [
                'role' => 'assistant',
                'content' => $textResponse
            ];
        }

        return [
            'response' => $textResponse,
            'context_data' => $contextData,
            'usage' => $usageData,
            'events' => $eventsData,
            'history' => $incomingMessages // Pass the updated history back
        ];
    }

    /**
     * The recursive chat loop handling Tool Calling via OpenAI-compatible API.
     */
    protected function chatLoop(array &$messages, array &$contextData = [], array &$usageData = [], array &$eventsData = [], int $depth = 0, array &$calledTools = []): string
    {
        if ($depth >= 5) {
            Log::warning("Mittwald API Tool Loop depth exceeded. Halting to prevent infinite loop.");
            return "Fehler: Meine internen Denkprozesse haben sich in einer Endlosschleife verfangen (Max Tool Depth Limit).";
        }
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 1.0,  // Recommended by Mittwald for gpt-oss-120b
            'top_p' => 1.0,        // Recommended by Mittwald
            'tools' => AIFunctionsRegistry::getSchema(),
            'tool_choice' => 'auto'
        ];

        try {
            Log::info("Sending request to Mittwald AI", ['model' => $this->model]);

            \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                'active_node' => 'cpu-chip',
                'action_text' => 'LLM Inference (Tiefe: '.$depth.')...',
                'pulse_color' => 'indigo'
            ], 60);

            $response = Http::withToken($this->apiKey)
                ->connectTimeout(30) // Erhöhe den Verbindungs-Timeout (Standard oft 10s in cURL)
                ->timeout(120) // Deep reasoning can take time
                ->asJson()
                ->post($this->baseUrl . '/chat/completions', $payload);

            if (!$response->successful()) {
                Log::error("Mittwald API Error", ['status' => $response->status(), 'response' => $response->body()]);
                return "⚠️ **SYSTEM WARNUNG: API VERBINDUNGSABBRUCH** ⚠️\n\nDie Mittwald Subraum-Verbindungen antworten nicht (Status: " . $response->status() . ").\n\n[GEGENMASSNAHME]\nBitte kopiere diesen Fehler und übergib ihn meinem Entwickler **Gemini**, damit er die API-Anbindung (Endpoint / Tokens) in der Architektur überprüfen kann, Herrin Alina.";
            }

            $responseData = $response->json();
            $message = $responseData['choices'][0]['message'] ?? null;

            if (isset($responseData['usage'])) {
                $usageData = $responseData['usage'];
            }

            if (!$message) {
                return "Ich empfange nur statisches Rauschen aus dem KI-Kern.";
            }

            // ANTI-LOOP & TIMEOUT PREVENTION: Force maximum 1 tool call per iteration.
            // If the AI spits out 5 tools, the request times out (CURL Error 28). We slice it down.
            if (isset($message['tool_calls']) && count($message['tool_calls']) > 1) {
                Log::warning("AI tried to call ".count($message['tool_calls'])." tools at once. Slicing to 1 to prevent timeout.");
                $message['tool_calls'] = array_slice($message['tool_calls'], 0, 1);
            }

            // Append the AI's response to the message history so context isn't lost
            $messages[] = $message;

            // Did the AI decide to call a tool?
            if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
                // Execute every tool the AI asked for (now safely max 1)
                foreach ($message['tool_calls'] as $toolCall) {
                    $toolCallId = $toolCall['id'];
                    $functionName = $toolCall['function']['name'];

                    // Decode arguments from JSON string back to array (OpenAI schema sends arguments as stringied JSON)
                    $functionArgsString = $toolCall['function']['arguments'] ?? '{}';

                    // --- ANTI-LOOP IDENTICAL CALL CHECK ---
                    $callSignature = md5($functionName . $functionArgsString);
                    if (in_array($callSignature, $calledTools)) {
                        Log::warning("AI loop detected! Aborting duplicate call: {$functionName}");
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCallId,
                            'content' => json_encode(['status' => 'error', 'message' => "SYSTEM EXCEPTION: LOOP DETECTED. You have already executed this exact same tool with the exact same arguments recursively. STOP REPEATING YOURSELF! Immediately output a final text answer for the user or pick a DIFFERENT tool."], JSON_UNESCAPED_UNICODE)
                        ];
                        continue;
                    }
                    $calledTools[] = $callSignature;

                    $executeArgs = json_decode($functionArgsString, true) ?? [];

                    Log::info("AI decided to call tool: {$functionName}", ['args' => $executeArgs]);

                    \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                        'active_node' => 'wrench-screwdriver',
                        'action_text' => 'Tool Call: ' . $functionName,
                        'pulse_color' => 'indigo'
                    ], 60);

                    // Track the usage for Analytics
                    if (class_exists(FunkiraToolUsage::class)) {
                        FunkiraToolUsage::create([
                            'tool_name' => $functionName,
                            'used_at'   => now(),
                            'context'   => $executeArgs
                        ]);
                    }

                    // Log into Live Log for the Chat view
                    if (class_exists(FunkiLog::class)) {
                        FunkiLog::create([
                            'action_id' => 'ai_tool_' . uniqid(),
                            'title' => 'Werkzeug ausgeführt: ' . $functionName,
                            'message' => 'Die KI hat das System-Werkzeug [' . $functionName . '] mit folgenden Argumenten aufgerufen: ' . json_encode($executeArgs, JSON_UNESCAPED_UNICODE),
                            'type' => 'ai_tool',
                            'status' => 'success',
                            'started_at' => now(),
                            'finished_at' => now(),
                        ]);
                    }

                    // Execute via our safe registry
                    $result = AIFunctionsRegistry::execute($functionName, $executeArgs);

                    // Speichere in Langzeitgedächtnis
                    if (class_exists(FunkiraChatMemory::class)) {
                        FunkiraChatMemory::create([
                            'session_id' => session()->getId(),
                            'role' => 'tool',
                            'content' => 'Werkzeug: ' . $functionName,
                            'context_data' => ['args' => $executeArgs, 'result' => $result]
                        ]);
                    }

                    \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                        'active_node' => 'circle-stack',
                        'action_text' => 'DB/Action Resultat verarbeitet...',
                        'pulse_color' => 'emerald'
                    ], 60);

                    // Collect the RAW result data before sanitization for the frontend!
                    $contextData[] = [
                        'function' => $functionName,
                        'data' => $result
                    ];

                    // --- LLM HIDDEN EVENTS ---
                    if (isset($result['_event'])) {
                        $eventsData[] = $result['_event'];
                        unset($result['_event']); // Do not send back to LLM JSON string to save tokens
                    }
                    if (isset($result['_frontend_event'])) {
                        $eventsData[] = $result['_frontend_event'];
                        unset($result['_frontend_event']); // Hide from LLM context to save tokens
                    }

                    // --- FAST TRACK INTERCEPT FOR INSTANT UI ACTIONS ---
                    if (isset($result['_fast_track']) && $result['_fast_track'] === true) {
                        $shouldFastTrack = true;
                        unset($result['_fast_track']);
                    }

                    // --- SANITIZE FOR LLM TO PREVENT READING OUT LOUD ---
                    $llmResult = $result;
                    if ($functionName === 'get_tasks' && isset($llmResult['tasks'])) {
                        $llmResult['tasks'] = '[Details der Taks. Bitte fasse sie grob zusammen oder frage Alina ob sie zur Task-Liste navigieren möchte.]';
                    }
                    if ($functionName === 'get_shop_stats' && isset($llmResult['scaling_metrics'])) {
                        $llmResult['scaling_metrics'] = '[Kennzahlen abgerufen. Fasse sie in 1-2 kurzen Sätzen zusammen, lies nicht jede Metrik einzeln vor. Wenn Alina Details will, navigiere sie zur Finanz-Seite.]';
                    }
                    if ($functionName === 'get_finances' && isset($llmResult['financial_data_net'])) {
                        $llmResult['financial_data_net'] = '[Finanzdaten abgerufen. Fasse das Netto-Wachstum kurz zusammen. Für volle Details navigiere Alina in den Analyse-Bereich.]';
                        unset($llmResult['financial_data_gross']);
                    }

                    // Add the tool execution result back to the message history
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => json_encode($llmResult, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE) ?: '{"status":"error","message":"JSON Encoding Failed for tool result"}'
                    ];
                }

                if (isset($shouldFastTrack) && $shouldFastTrack === true) {
                    \Illuminate\Support\Facades\Log::info("FAST TRACK TRIGGERED: Skipping LLM confirmation loop for immediate UI response.");
                    return ""; // Return empty string so FunkiraChat doesn't synthesize empty audio
                }

                // Since we added new tool results, loop back and ask the AI again
                // so it can read the results and formulate a final answer.
                \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                    'active_node' => 'sparkles',
                    'action_text' => 'Re-Evaluierung des Kontexts...',
                    'pulse_color' => 'indigo'
                ], 60);

                return $this->chatLoop($messages, $contextData, $usageData, $eventsData, $depth + 1, $calledTools);
            }

            // Provide final answer
            \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                'active_node' => 'bolt',
                'action_text' => 'Finales Prompt beendet.',
                'pulse_color' => 'emerald'
            ], 60);

            return $message['content'] ?? "Ich habe meine Aufgabe ausgeführt.";

        } catch (\Exception $e) {
            Log::error("Mittwald HTTP Exception", ['error' => $e->getMessage()]);
            return "Systemintegrität gestört: " . $e->getMessage();
        }
    }
}

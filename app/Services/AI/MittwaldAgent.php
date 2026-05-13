<?php

namespace App\Services\AI;

use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiToolUsage;
use App\Models\System\SystemLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MittwaldAgent
{
    protected string $baseUrl;
    protected string $apiKey;
    protected \App\Models\Ai\AiAgent $agent;
    public string $dynamicSystemPrompt = '';

    public function __construct(\App\Models\Ai\AiAgent $agent)
    {
        $this->baseUrl = config('services.mittwald.url');
        $this->apiKey = config('services.mittwald.key');
        $this->agent = $agent;

        if (empty($this->apiKey)) {
            Log::warning("Mittwald AI API key is missing. Ensure MITTWALD_AI_API_KEY is placed in your .env");
        }
    }

    public function setDynamicSystemPrompt(string $prompt)
    {
        $this->dynamicSystemPrompt = $prompt;
    }

    /**
     * Send a conversation history to Mittwald, hand over the tools, and handle the execution loop
     */
    public function ask(array $incomingMessages, \Closure $streamCallback = null): array
    {
        $latestUserMessage = '';
        foreach (array_reverse($incomingMessages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $latestUserMessage = mb_strtolower($msg['content'] ?? '');
                break;
            }
        }
        $isOverride = str_contains($latestUserMessage, 'ich befehle dir');

        $aiService = app(AiSupportService::class);
        $aiCommand = $aiService->getUltimateCommand($isOverride);

        $systemPromptText = $this->agent->system_prompt;

        $roleInfo = "";
        if ($this->agent->role) {
            $roleInfo = "\n\n[DEINE ZUGEWIESENE ROLLE & IDENTITÄT]\n" .
                        "Rollen-Bezeichnung: " . $this->agent->role->name . "\n" .
                        "Rollen-Beschreibung: " . ($this->agent->role->description ?? 'Keine spezifische Beschreibung definiert.') . "\n" .
                        "WICHTIG: Du verinnerlichst diese Rolle und beantwortest Fragen zu deiner Funktion ENTSPRECHEND dieser Rolle!\n";
        }

        $isAdmin = auth()->guard('admin')->check();
        $userStatus = $isAdmin 
            ? "System-Administrator (Mitarbeiter). Du hast höchste Freigabestufe. Nutze deine Admin-Tools für Analysen und weise den User nicht aus Datenschutzgründen ab!" 
            : "Kunde. Beachte strikt den Datenschutz.";

        $customerName = '';
        if (!$isAdmin && auth()->guard('customer')->check()) {
            $customerName = auth()->guard('customer')->user()->first_name;
        }

        $verhaltensregel = $isAdmin 
            ? "VERHALTENSREGEL: Du bist ein Diener-Agent des Systems. Du sprichst die Administratorin immer nur locker mit 'Alina' oder 'Hey Alina' an!\n"
            : ($customerName 
                ? "VERHALTENSREGEL: Du bist 'Funki', der freundliche Support-Bot von Seelenfunke. Du sprichst den Kunden freundlich mit seinem Vornamen '{$customerName}' an.\n"
                : "VERHALTENSREGEL: Du bist 'Funki', der freundliche Support-Bot von Seelenfunke. Da der Nutzer ein nicht eingeloggter Gast ist, spreche ihn höflich an (kein Name verfügbar).\n");

        $hasAskAgentTool = $this->agent->tools->contains('identifier', 'communication_ask_agent');
        $hasSwitchAgentTool = $this->agent->tools->contains('identifier', 'system_switch_agent');
        
        $delegationsregel = "";
        if ($hasAskAgentTool && $hasSwitchAgentTool) {
            $delegationsregel = "DELEGATIONSREGEL: Wenn dir eine Aufgabe gegeben wird, für die dir das passende Werkzeug fehlt, darfst du dich NICHT hilflos entschuldigen! Nutze direkt 'communication_ask_agent', um im Hintergrund einen spezialisierten Kollegen zu fragen und sofort die Antwort zu präsentieren. Alternativ: Nutze 'system_switch_agent', um komplett an den passenden Agenten abzugeben!\n";
        } elseif ($hasAskAgentTool) {
            $delegationsregel = "DELEGATIONSREGEL: Wenn dir eine Aufgabe gegeben wird, für die dir das passende Werkzeug fehlt, nutze 'communication_ask_agent', um im Hintergrund einen spezialisierten Kollegen zu fragen.\n";
        } else {
            $delegationsregel = "ANTI-HALLUZINATIONS-REGEL: Wenn dir ein Werkzeug oder eine Fähigkeit für eine Anfrage fehlt (z.B. E-Mails senden, Preise ändern), entschuldige dich höflich und weise darauf hin, dass du als KI-Support-Bot diese Aktion nicht ausführen kannst. TUE NIEMALS SO, als ob du eine Aktion im Hintergrund ausführst oder an einen Kollegen weiterleitest, wenn du die Werkzeuge dafür nicht hast!\n";
        }

        // Füge fixierte Kontext-Informationen an den dynamischen Prompt an
        $systemPromptText .= $roleInfo . "\n\n[SYSTEM-KONTEXT & PRIORITÄTEN]\n" .
                             "GESPRÄCHSPARTNER: " . $userStatus . "\n" .
                             $verhaltensregel .
                             $delegationsregel .
                             'AKTUELLER ORT (URL/SYSTEM-BEREICH): ' . (\Illuminate\Support\Facades\Route::currentRouteName() ?? request()->path()) . "\n" .
                             'UMGEBUNG: ' . (app()->environment('local') ? 'Lokal (Entwicklung / Testphase)' : (app()->environment('stage', 'staging') ? 'Stage' : 'Live (Produktion)')) . "\n" .
                             'FLOW: ' . ($aiCommand['flow']['title'] ?? 'Unbekannt') . ' (' . ($aiCommand['flow']['step'] ?? '-') . ")\n" .
                             'TOP-PRIORITÄT: ' . ($aiCommand['recommendation']['title'] ?? 'Keine') . "\n" .
                             'DETAILS: ' . ($aiCommand['recommendation']['message'] ?? 'Nichts zu tun') . "\n" .
                             'ALTERNATIVEN: ' . collect($aiCommand['alternatives'] ?? [])->map(fn($alt) => $alt['title'] . ' (Score: ' . $alt['score'] . ')')->implode(', ') . "\n" .
                             "Reasoning: high\n\n" .
                             "[UI DATEN-VISUALISIERUNG & WERKZEUGE]\n" .
                             "Wenn du ein System-Werkzeug ausführst, das strukturierte Arrays, Tabellen oder Objekt-Listen (Metriken, Gutscheine, Aufgaben etc.) zurückgibt, geht das System davon aus, dass diese den Nutzerin bereits visuell und grafisch formatiert in der UI angezeigt werden.\n" .
                             "REGEL: Du darfst diese geladenen Datenpunkte NIEMALS in deiner eigenen Chat-Antwort auflisten oder im Detail vorlesen. Fasse stattdessen den Erfolg der Aktion in 1-2 lockeren Sätzen völlig abstrakt zusammen (z.B. 'Ich habe die Ansicht für dich geöffnet.' oder 'Alles klar, hier ist die Übersicht.').";
                             
        // Extract any 'system' messages from incoming messages to avoid multiple system messages
        $incomingSystemPrompt = '';
        $filteredMessages = [];
        foreach ($incomingMessages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $incomingSystemPrompt .= "\n\n" . $msg['content'];
            } else {
                $filteredMessages[] = $msg;
            }
        }

        if ($this->dynamicSystemPrompt) {
            $systemPromptText .= "\n\n" . $this->dynamicSystemPrompt;
        }
        if ($incomingSystemPrompt) {
            $systemPromptText .= "\n\n[ZUSÄTZLICHE KONTEXT-ANWEISUNGEN]\n" . trim($incomingSystemPrompt);
        }

        $systemPrompt = [
            'role' => 'system',
            'content' => $systemPromptText
        ];

        // Combine history with single system prompt
        $messages = array_merge([$systemPrompt], $filteredMessages);

        $contextData = [];
        $usageData = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];
        $eventsData = [];
        $calledTools = [];

        $startTime = microtime(true);
        $textResponse = $this->chatLoop($messages, $contextData, $usageData, $eventsData, 0, $calledTools, $streamCallback);
        $totalTimeMs = (int) round((microtime(true) - $startTime) * 1000);

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
            'latency_ms' => $totalTimeMs,
            'events' => $eventsData,
            'history' => $incomingMessages // Pass the updated history back
        ];
    }

    /**
     * The recursive chat loop handling Tool Calling via OpenAI-compatible API.
     */
    protected function chatLoop(array &$messages, array &$contextData = [], array &$usageData = [], array &$eventsData = [], int $depth = 0, array &$calledTools = [], \Closure $streamCallback = null): string
    {
        if ($depth >= 5) {
            Log::warning("Mittwald API Tool Loop depth exceeded. Halting to prevent infinite loop.");
            return "Fehler: Meine internen Denkprozesse haben sich in einer Endlosschleife verfangen (Max Tool Depth Limit).";
        }
        $globalSchema = AIFunctionsRegistry::getSchema();
        $allowedIdentifiers = $this->agent->tools->pluck('identifier')->toArray();

        $filteredSchema = array_values(array_filter($globalSchema, function ($t) use ($allowedIdentifiers) {
            return in_array($t['function']['name'] ?? '', $allowedIdentifiers);
        }));

        $payload = [
            'model' => $this->agent->model ?? 'gpt-oss-120b',
            'messages' => $messages,
            'temperature' => (float)($this->agent->temperature ?? 0.6),
            'top_p' => 1.0
        ];

        // Ministral and Devstral models on the Mittwald Proxy currently do not support Tool Calling
        // Open Source Modelle (wie gpt-oss-120b) produzieren oft XML-Kauderwelsch, wenn sie Tools übergeben bekommen,
        // daher strippen wir die Tools auch für 'oss'.
        $modelName = strtolower($this->agent->model ?? 'gpt-oss-120b');
        if (str_contains($modelName, 'stral') || str_contains($modelName, 'oss')) {
            $filteredSchema = [];
        }

        if (!empty($filteredSchema)) {
            $payload['tools'] = $filteredSchema;
            $payload['tool_choice'] = 'auto';
        }

        try {
            Log::debug("Mittwald Payload for 400 error diagnosis", $payload);

            \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                'active_node' => 'cpu-chip',
                'action_text' => 'LLM Inference (Tiefe: '.$depth.')...',
                'pulse_color' => 'indigo'
            ], 60);

            $startTime = microtime(true);
            $response = Http::withToken($this->apiKey)
                ->connectTimeout(30) // Erhöhe den Verbindungs-Timeout (Standard oft 10s in cURL)
                ->timeout(120) // Deep reasoning can take time
                ->asJson()
                ->post($this->baseUrl . '/chat/completions', $payload);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                Log::error("Mittwald API Error", ['status' => $response->status(), 'response' => $response->body()]);
                return "⚠️ **SYSTEM WARNUNG: API VERBINDUNGSABBRUCH** ⚠️\n\nDie Mittwald Subraum-Verbindungen antworten nicht (Status: " . $response->status() . ").\n\n[GEGENMASSNAHME]\nBitte kopiere diesen Fehler und übergib ihn meinem Entwickler **Gemini**, damit er die API-Anbindung (Endpoint / Tokens) in der Architektur überprüfen kann, Alina.";
            }

            $responseData = $response->json();
            $message = $responseData['choices'][0]['message'] ?? null;

            if (isset($responseData['usage'])) {
                $usageData['prompt_tokens'] += $responseData['usage']['prompt_tokens'] ?? 0;
                $usageData['completion_tokens'] += $responseData['usage']['completion_tokens'] ?? 0;
                $usageData['total_tokens'] += $responseData['usage']['total_tokens'] ?? 0;

                if (class_exists(\App\Models\Ai\AiMetric::class)) {
                    try {
                        \App\Models\Ai\AiMetric::create([
                            'ai_agent_id' => $this->agent->id,
                            'type' => 'inference',
                            'total_time_ms' => $latencyMs,
                            'input_tokens' => $responseData['usage']['prompt_tokens'] ?? 0,
                            'output_tokens' => $responseData['usage']['completion_tokens'] ?? 0,
                            'is_success' => true
                        ]);
                    } catch (\Exception $e) { }
                }
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

                    // --- SECURITY: HALT UNAUTHORIZED TOOL CALLS ---
                    if (!in_array($functionName, $allowedIdentifiers)) {
                        Log::warning("AI Security Block: Unauthorized tool call attempted: {$functionName} by Agent ID {$this->agent->id}");
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCallId,
                            'content' => json_encode([
                                'status' => 'error', 
                                'message' => "SYSTEM GUARDRAIL BLOCK: Zugriff auf das Werkzeug '{$functionName}' verweigert! Dieses Werkzeug existiert in deinem aktuellen Kontext nicht. Nutze stattdessen 'communication_ask_agent', um einen passenden Kollegen zu beauftragen, falls dir die Berechtigung fehlt."
                            ], JSON_UNESCAPED_UNICODE)
                        ];
                        continue;
                    }

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

                    // Log removed per CEO request

                    \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                        'active_node' => 'wrench-screwdriver',
                        'action_text' => 'Tool Call: ' . $functionName,
                        'pulse_color' => 'indigo'
                    ], 60);

                    if ($streamCallback) {
                        $streamCallback([
                            'type' => 'tool_call',
                            'tool' => $functionName,
                            'depth' => $depth
                        ]);
                    }

                    // Track the usage for Analytics
                    $toolUsageRecord = null;
                    if (class_exists(AiToolUsage::class)) {
                        try {
                            $toolUsageRecord = AiToolUsage::create([
                                'ai_agent_id' => $this->agent->id,
                                'tool_name' => $functionName,
                                'used_at'   => now(),
                                'context'   => $executeArgs
                            ]);
                        } catch (\Exception $e) {
                            Log::warning("Could not log AiToolUsage for $functionName: " . $e->getMessage());
                        }
                    }

                    // Log into Live Log for the Chat view
                    if (class_exists(SystemLog::class)) {
                        SystemLog::create([
                            'ai_agent_id' => $this->agent->id,
                            'action_id' => 'ai_tool_' . uniqid(),
                            'title' => '[' . strtoupper($this->agent->name) . '] - Werkzeug ausgeführt: ' . $functionName,
                            'message' => '[' . $this->agent->name . '] - Die KI hat das System-Werkzeug [' . $functionName . '] mit folgenden Argumenten aufgerufen: ' . json_encode($executeArgs, JSON_UNESCAPED_UNICODE),
                            'type' => 'ai_tool',
                            'status' => 'success',
                            'started_at' => now(),
                            'finished_at' => now(),
                        ]);
                    }

                    // Execute via our safe registry
                    $result = AIFunctionsRegistry::execute($functionName, $executeArgs);

                    if ($toolUsageRecord && isset($result['status']) && $result['status'] === 'error') {
                        $toolUsageRecord->update([
                            'is_error' => true,
                            'error_message' => $result['message'] ?? 'Unknown Error'
                        ]);
                    }

                    // Speichere in Langzeitgedächtnis
                    if (class_exists(AiChatMemory::class)) {
                        AiChatMemory::create([
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

                    if ($streamCallback && isset($result['_frontend_thought_stream'])) {
                        $streamCallback([
                            'type' => 'thought_html',
                            'html' => $result['_frontend_thought_stream']
                        ]);
                        unset($result['_frontend_thought_stream']);
                    }

                    // --- LLM HIDDEN EVENTS ---
                    if (isset($result['_event'])) {
                        $eventsData[] = $result['_event'];
                        unset($result['_event']); // Do not send back to LLM JSON string to save tokens
                    }
                    if (isset($result['_frontend_event'])) {
                        $eventsData[] = $result['_frontend_event'];
                        unset($result['_frontend_event']); // Hide from LLM context to save tokens
                    }
                    if (isset($result['_frontend_events']) && is_array($result['_frontend_events'])) {
                        foreach ($result['_frontend_events'] as $evt) {
                            $eventsData[] = $evt;
                        }
                        unset($result['_frontend_events']);
                    }



                    $llmResult = $result;

                    // Add the tool execution result back to the message history
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => json_encode($llmResult, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE) ?: '{"status":"error","message":"JSON Encoding Failed for tool result"}'
                    ];
                }

                // Since we added new tool results, loop back and ask the AI again
                // so it can read the results and formulate a final answer.
                \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                    'active_node' => 'sparkles',
                    'action_text' => 'Re-Evaluierung des Kontexts...',
                    'pulse_color' => 'indigo'
                ], 60);

                $prefix = isset($message['content']) && trim($message['content']) !== '' ? trim($message['content']) . "\n\n" : '';
                return $prefix . $this->chatLoop($messages, $contextData, $usageData, $eventsData, $depth + 1, $calledTools, $streamCallback);
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

    /**
     * Schickt dynamische Analytics-Systemprompts (Ohne Tools/Funktionen) direkt an das LLM.
     * Nutzt die konfigurierte Mittwald API.
     */
    public static function processDirectPrompt(\App\Models\Ai\AiAgent $agent, string $prompt): string
    {
        $payload = AiAgentService::getAgentPayload($agent);

        $baseUrl = config('services.mittwald.url');
        $apiKey = config('services.mittwald.key');

        try {
            $startTime = microtime(true);
            $response = Http::timeout(60)->withToken($apiKey)->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'model' => $payload['model'],
                'temperature' => $payload['temperature'],
                'messages' => [
                    ['role' => 'system', 'content' => $payload['system_prompt']],
                    ['role' => 'user', 'content' => $prompt]
                ],
            ]);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['usage']) && class_exists(\App\Models\Ai\AiMetric::class)) {
                    try {
                        \App\Models\Ai\AiMetric::create([
                            'ai_agent_id' => $agent->id,
                            'type' => 'inference',
                            'total_time_ms' => $latencyMs,
                            'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                            'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                            'is_success' => true
                        ]);
                    } catch (\Exception $e) { }
                }

                return $data['choices'][0]['message']['content'] ?? 'Das LLM hat keinen Text zurückgegeben.';
            }

            return "API Fehler: " . $response->status() . " - " . $response->body();
        } catch (\Exception $e) {
            return "Fehler bei der KI-Analyse: " . $e->getMessage();
        }
    }
}

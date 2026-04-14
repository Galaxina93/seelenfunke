<?php

namespace App\Services\AI;

use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiToolUsage;
use App\Models\System\SystemLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAgent
{
    protected string $baseUrl;
    protected string $apiKey;
    protected \App\Models\Ai\AiAgent $agent;
    public string $dynamicSystemPrompt = '';

    public function __construct(\App\Models\Ai\AiAgent $agent)
    {
        $this->baseUrl = config('services.gemini.url');
        $this->apiKey = config('services.gemini.key');
        $this->agent = $agent;

        if (empty($this->apiKey)) {
            Log::warning("Gemini AI API key is missing. Ensure GEMINI_AI_API_KEY is placed in your .env");
        }
    }

    public function setDynamicSystemPrompt(string $prompt)
    {
        $this->dynamicSystemPrompt = $prompt;
    }

    /**
     * Send a conversation history to Gemini, hand over the tools, and handle the execution loop
     * until the model gives a final text response.
     */
    public function ask(array $incomingMessages): array
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

        // Füge fixierte Kontext-Informationen an den dynamischen Prompt an
        $systemPromptText .= $roleInfo . "\n\n[SYSTEM-KONTEXT & PRIORITÄTEN]\n" .
                             "VERHALTENSREGEL: Du bist ein Diener-Agent des Systems. Du sprichst die Benutzerin immer nur locker mit 'Alina' oder 'Hey Alina' an!\n" .
                             'AKTUELLER ORT (URL/SYSTEM-BEREICH): ' . (\Illuminate\Support\Facades\Route::currentRouteName() ?? request()->path()) . "\n" .
                             'UMGEBUNG: ' . (app()->environment('local') ? 'Lokal (Entwicklung / Testphase)' : (app()->environment('stage', 'staging') ? 'Stage' : 'Live (Produktion)')) . "\n" .
                             'FLOW: ' . ($aiCommand['flow']['title'] ?? 'Unbekannt') . ' (' . ($aiCommand['flow']['step'] ?? '-') . ")\n" .
                             'TOP-PRIORITÄT: ' . ($aiCommand['recommendation']['title'] ?? 'Keine') . "\n" .
                             'DETAILS: ' . ($aiCommand['recommendation']['message'] ?? 'Nichts zu tun') . "\n" .
                             'ALTERNATIVEN: ' . collect($aiCommand['alternatives'] ?? [])->map(fn($alt) => $alt['title'] . ' (Score: ' . $alt['score'] . ')')->implode(', ') . "\n" .
                             'Reasoning: high';
                             
        if ($this->dynamicSystemPrompt) {
            $systemPromptText .= "\n\n" . $this->dynamicSystemPrompt;
        }

        $systemPrompt = [
            'role' => 'system',
            'content' => $systemPromptText
        ];

        // Combine history with system prompt
        $messages = array_merge([$systemPrompt], $incomingMessages);

        $contextData = [];
        $usageData = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];
        $eventsData = [];

        $startTime = microtime(true);
        $textResponse = $this->chatLoop($messages, $contextData, $usageData, $eventsData);
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
    protected function chatLoop(array &$messages, array &$contextData = [], array &$usageData = [], array &$eventsData = [], int $depth = 0, array &$calledTools = []): string
    {
        if ($depth >= 5) {
            Log::warning("Gemini API Tool Loop depth exceeded. Halting to prevent infinite loop.");
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

        // Ministral and Devstral models on the Gemini Proxy currently do not support Tool Calling
        // Passing the 'tools' array to them results in a 400 Bad Request error.
        $modelName = strtolower($this->agent->model ?? 'gpt-oss-120b');
        if (str_contains($modelName, 'stral')) {
            $filteredSchema = [];
        }

        if (!empty($filteredSchema)) {
            $payload['tools'] = $filteredSchema;
            $payload['tool_choice'] = 'auto';
        }

        try {
            // Log::info("Sending request to Gemini AI", ['model' => $payload['model'], 'temperature' => $payload['temperature']]);

            \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                'active_node' => 'cpu-chip',
                'action_text' => 'LLM Inference (Tiefe: '.$depth.')...',
                'pulse_color' => 'indigo'
            ], 60);

            $startTime = microtime(true);
            $response = Http::withToken($this->apiKey)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ]
                ])
                ->connectTimeout(30) // Erhöhe den Verbindungs-Timeout (Standard oft 10s in cURL)
                ->timeout(120) // Deep reasoning can take time
                ->asJson()
                ->post(rtrim($this->baseUrl, '/') . '/chat/completions', $payload);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                Log::error("Gemini API Error", ['status' => $response->status(), 'response' => $response->body()]);
                return "⚠️ **SYSTEM WARNUNG: API VERBINDUNGSABBRUCH** ⚠️\n\nDie Gemini Subraum-Verbindungen antworten nicht (Status: " . $response->status() . ").\n\n[GEGENMASSNAHME]\nBitte kopiere diesen Fehler und übergib ihn meinem Entwickler **Gemini**, damit er die API-Anbindung (Endpoint / Tokens) in der Architektur überprüfen kann, Alina.";
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
            Log::error("Gemini HTTP Exception", ['error' => $e->getMessage()]);
            return "Systemintegrität gestört: " . $e->getMessage();
        }
    }

    /**
     * Schickt dynamische Analytics-Systemprompts (Ohne Tools/Funktionen) direkt an das LLM.
     * Nutzt die konfigurierte Gemini API.
     */
    public static function processDirectPrompt(\App\Models\Ai\AiAgent $agent, string $prompt): string
    {
        $payload = AiAgentService::getAgentPayload($agent);

        $baseUrl = config('services.gemini.url');
        $apiKey = config('services.gemini.key');

        try {
            $startTime = microtime(true);
            
            $url = rtrim($baseUrl, '/') . '/chat/completions';
            
            $requestPayload = [
                'model' => $payload['model'],
                'temperature' => $payload['temperature'],
                'messages' => [
                    ['role' => 'system', 'content' => $payload['system_prompt']],
                    ['role' => 'user', 'content' => $prompt]
                ],
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'Expect:' // Prevent Expect: 100-continue which causes Docker MTU timeouts
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestPayload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $responseString = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($curlError) {
                return "API Fehler: cURL Error - " . $curlError;
            }

            if ($httpCode === 200 && $responseString) {
                $data = json_decode($responseString, true);
                
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

            return "API Fehler: " . $httpCode . " - " . $responseString;
        } catch (\Exception $e) {
            return "Fehler bei der KI-Analyse: " . $e->getMessage();
        }
    }

    /**
     * Schickt einen Prompt zusammen mit einem Bild (Base64) an das Vision LLM (Gemini 1.5).
     */
    public static function processVisionPrompt(\App\Models\Ai\AiAgent $agent, string $prompt, string $base64Image, string $mimeType = 'image/jpeg'): string
    {
        $payload = AiAgentService::getAgentPayload($agent);
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return "Vision API Fehler: Kein Gemini API Key konfiguriert.";
        }

        try {
            $startTime = microtime(true);
            
            // Nutze explizit gemini-2.5-flash-image, da dieses spezialisierte Modell nativ Base64 unterstützt
            // und nicht von den generellen 503 "High Demand" Ausfällen der Standardmodelle betroffen ist.
            $googleUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent?key=' . $apiKey;
            
            // Entferne evtl. vorhandene data:image/jpeg;base64,... Header, falls Base64 nicht raw ist
            if (str_contains($base64Image, ',')) {
                $base64Image = explode(',', $base64Image)[1];
            }

            $requestPayload = [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $payload['system_prompt']]
                    ]
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64Image
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // WICHTIG: Ersetze Guzzle durch puren curl_init, da Guzzle / Http::post in Docker hier wegen 
            // Expect: 100-continue und TCP MTU-Fragmentierung bei der Base64 payload in Timeout 28 rennt.
            $ch = curl_init($googleUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestPayload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Docker IPv6 Blackholing verhindern

            $responseString = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($curlError) {
                return "Vision API Fehler: cURL Timeout/Error - {$curlError}";
            }

            if ($httpCode !== 200) {
                return "Vision API Fehler: {$httpCode} - {$responseString}";
            }

            $data = json_decode($responseString, true);
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $data['candidates'][0]['content']['parts'][0]['text'];
                
                // Track Usage if metric exists
                if (class_exists(\App\Models\Ai\AiMetric::class)) {
                    try {
                        \App\Models\Ai\AiMetric::create([
                            'ai_agent_id' => $agent->id,
                            'type' => 'inference_vision',
                            'total_time_ms' => $latencyMs,
                            'is_success' => true
                        ]);
                    } catch (\Exception $e) { }
                }
                
                return $text;
            }

            return "Vision API Fehler: Unerwartete Antwortstruktur von Google.";
            
        } catch (\Exception $e) {
            return "Fehler bei der KI-Bildanalyse: " . $e->getMessage();
        }
    }
}

<?php

namespace App\Services\AI;

use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiToolUsage;
use App\Models\Global\GlobalLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MittwaldAgent
{
    protected string $baseUrl;
    protected string $apiKey;
    protected \App\Models\Ai\AiAgent $agent;

    public function __construct(\App\Models\Ai\AiAgent $agent)
    {
        $this->baseUrl = config('services.mittwald.url');
        $this->apiKey = config('services.mittwald.key');
        $this->agent = $agent;

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
        
        // Füge fixierte Kontext-Informationen an den dynamischen Prompt an
        $systemPromptText .= "\n\n[SYSTEM-KONTEXT & PRIORITÄTEN]\n" .
                             'AKTUELLER ORT (URL/SYSTEM-BEREICH): ' . (\Illuminate\Support\Facades\Route::currentRouteName() ?? request()->path()) . "\n" .
                             'UMGEBUNG: ' . (app()->environment('local') ? 'Lokal (Entwicklung / Testphase)' : (app()->environment('stage', 'staging') ? 'Stage' : 'Live (Produktion)')) . "\n" .
                             'FLOW: ' . ($aiCommand['flow']['title'] ?? 'Unbekannt') . ' (' . ($aiCommand['flow']['step'] ?? '-') . ")\n" .
                             'TOP-PRIORITÄT: ' . ($aiCommand['recommendation']['title'] ?? 'Keine') . "\n" .
                             'DETAILS: ' . ($aiCommand['recommendation']['message'] ?? 'Nichts zu tun') . "\n" .
                             'ALTERNATIVEN: ' . collect($aiCommand['alternatives'] ?? [])->map(fn($alt) => $alt['title'] . ' (Score: ' . $alt['score'] . ')')->implode(', ') . "\n" .
                             'Reasoning: high';

        $systemPrompt = [
            'role' => 'system',
            'content' => $systemPromptText
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
            'model' => $this->agent->model ?? 'gpt-oss-120b',
            'messages' => $messages,
            'temperature' => (float)($this->agent->temperature ?? 0.6),
            'top_p' => 1.0,
            'tools' => AIFunctionsRegistry::getSchema(),
            'tool_choice' => 'auto'
        ];

        try {
            Log::info("Sending request to Mittwald AI", ['model' => $payload['model'], 'temperature' => $payload['temperature']]);

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
                    if (class_exists(AiToolUsage::class)) {
                        AiToolUsage::create([
                            'tool_name' => $functionName,
                            'used_at'   => now(),
                            'context'   => $executeArgs
                        ]);
                    }

                    // Log into Live Log for the Chat view
                    if (class_exists(GlobalLog::class)) {
                        GlobalLog::create([
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

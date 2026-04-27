<?php

namespace App\Services\AI;

use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiToolUsage;
use App\Models\System\SystemLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\AI\Contracts\AiProviderInterface;

class GeminiAgent implements AiProviderInterface
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
    public function ask(array $incomingMessages, \Closure $streamCallback = null): array
    {
        $latestUserMessage = '';
        foreach (array_reverse($incomingMessages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $content = $msg['content'] ?? '';
                if (is_array($content)) {
                    $extractedText = '';
                    foreach ($content as $part) {
                        if (($part['type'] ?? '') === 'text') {
                            $extractedText .= $part['text'] . ' ';
                        }
                    }
                    $latestUserMessage = mb_strtolower(trim($extractedText));
                } else {
                    $latestUserMessage = mb_strtolower((string)$content);
                }
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
                             'EPHEMERAL CONTEXT: ' . session()->get('ai_ephemeral_state', 'Der User hat aktuell keine Dateien oder speziellen UI-Elemente fokussiert.') . "\n" .
                             'ALTERNATIVEN: ' . collect($aiCommand['alternatives'] ?? [])->map(fn($alt) => $alt['title'] . ' (Score: ' . $alt['score'] . ')')->implode(', ') . "\n" .
                             "Reasoning: high\n\n" .
                             "[UI DATEN-VISUALISIERUNG & WERKZEUGE]\n" .
                             "Wenn du ein System-Werkzeug ausführst, das strukturierte Arrays, Tabellen oder Objekt-Listen (Metriken, Gutscheine, Aufgaben etc.) zurückgibt, geht das System davon aus, dass diese den Nutzerin bereits visuell und grafisch formatiert in der UI angezeigt werden.\n" .
                             "REGEL: Du darfst diese geladenen Datenpunkte NIEMALS in deiner eigenen Chat-Antwort auflisten oder im Detail vorlesen. Fasse stattdessen den Erfolg der Aktion in 1-2 lockeren Sätzen völlig abstrakt zusammen (z.B. 'Ich habe die Ansicht für dich geöffnet.' oder 'Alles klar, hier ist die Übersicht.').";

        if (file_exists(base_path('ai_map.md'))) {
            $systemPromptText .= "\n\n[ARCHITEKTUR-KARTE / INDEX]\n" .
                                 "Es existiert eine Map-Datei unter `" . base_path('ai_map.md') . "`. LIES DIESE DATEI ZUERST (als Werkzeugaufruf), wenn du Code-Strukturen, Models oder Controller suchst. Das erspart dir mühsames Suchen im Dateisystem!";
        }

        $systemPromptText .= "\n\n<planning_mode>\n" .
                             "You are an advanced agentic AI coding assistant built by Antigravity.\n" .
                             "You are in Planning Mode. Exercise judgement on whether a user's request warrants a plan before taking action.\n\n" .
                             "Wenn ein User tiefe Architekturänderungen, Log-Analysen oder Code-Anpassungen befiehlt, handle WIE EIN AUTONOMER AGENT:\n" .
                             "Phase 1: Research. Nutze Tools wie system_read_code oder system_list_directory, um den Code zu analysieren.\n" .
                             "Phase 2: Create Implementation Plan. Nutze system_write_artifact um ein 'implementation_plan' Artefakt zu generieren. Danach rufst du ZWINGEND system_request_user_approval auf, um auf die Erlaubnis des Users zu warten!\n" .
                             "Phase 3: Execute & Track Task. Wenn der User den Plan genehmigt, dann NUTZT DU DEINE WERKZEUGE (z.B. system_multi_replace_file) UM DEN CODE SELBSTSTÄNDIG ZU ÄNDERN! Halte ein 'task' Artefakt als Todo-Liste aktuell.\n" .
                             "Phase 4: Verify. Lies die Logs oder checke den Status, um zu testen, ob dein Code funktioniert.\n" .
                             "WICHTIG: Erkläre dem User nicht, was er tun soll. DU BIST DER PROGRAMMIERER. ÄNDERE DIE DATEIEN SELBST!\n" .
                             "</planning_mode>";

        // === ARTIFACT INJECTION (ANTIGRAVITY ARCHITECTURE) ===
        $sessionId = config('ai.current_session_id') ?: session()->getId();
        if (!empty($sessionId)) {
            $artifactPath = 'agenten/ai-artifacts/' . $sessionId;
            $artifactsPrompt = "";
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($artifactPath)) {
                $files = ['implementation_plan.md', 'task.md', 'walkthrough.md'];
                foreach ($files as $file) {
                    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($artifactPath . '/' . $file)) {
                        $content = \Illuminate\Support\Facades\Storage::disk('local')->get($artifactPath . '/' . $file);
                        $artifactsPrompt .= "\n[ARTIFACT: " . str_replace('.md', '', $file) . "]\nPath: " . storage_path('app/' . $artifactPath . '/' . $file) . "\n" . trim($content) . "\n\n";
                    }
                }
            }
            if (!empty($artifactsPrompt)) {
                $systemPromptText .= "\n\n<artifacts>\n" .
                                     "Artifacts are special markdown documents that you created to present structured information to the user.\n" .
                                     "The following artifacts currently exist for your session:\n" .
                                     $artifactsPrompt .
                                     "</artifacts>";
            }
        }


        if ($this->dynamicSystemPrompt) {
            $systemPromptText .= "\n\n" . $this->dynamicSystemPrompt;
        }

        $systemPrompt = [
            'role' => 'system',
            'content' => $systemPromptText
        ];

        // === EPHEMERAL CHECKPOINTING (ANTIGRAVITY ARCHITECTURE) ===
        // Inject a subtle reminder of the objective so the agent doesn't lose context after many messages
        if (count($incomingMessages) > 8) {
            $incomingMessages[] = [
                'role' => 'system',
                'content' => "<EPHEMERAL_MESSAGE>\nDie Konversation ist bereits relativ lang. Vergiss nicht deinen ursprünglichen Auftrag. Solltest du ein `implementation_plan` Artefakt haben, orientiere dich exakt daran und repariere auftretende Fehler in den in `<artifacts>` gelisteten Aufgaben.\n</EPHEMERAL_MESSAGE>"
            ];
        }

        // Combine history with system prompt
        $messages = array_merge([$systemPrompt], $incomingMessages);

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
            Log::warning("Gemini API Tool Loop depth exceeded. Halting to prevent infinite loop.");
            return "Fehler: Meine internen Denkprozesse haben sich in einer Endlosschleife verfangen (Max Tool Depth Limit).";
        }
        $globalSchema = AIFunctionsRegistry::getSchema();
        $allowedIdentifiers = $this->agent->tools->pluck('identifier')->toArray();
        $filteredSchema = array_values(array_filter($globalSchema, function ($t) use ($allowedIdentifiers) {
            return in_array($t['function']['name'] ?? '', $allowedIdentifiers);
        }));

        // STRICT ROLE NORMALIZATION FOR GOOGLE OPENAI WRAPPER
        // Google throws "400 Bad Request: Invalid argument" if it encounters multiple consecutive
        // messages from the same role (e.g. user -> user).
        $normalizedMessages = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';

            // --- FIX FOR GEMINI 2.0 THOUGHT_SIGNATURE BUG ---
            // The Google API strictly requires 'thought_signature' in functionCall parts if they exist in history.
            // Since OpenAI schema doesn't reliably map this during multi-turn streaming, we safely flatten
            // historical tool calls and their results into text so the AI retains full context without API errors.
            if (isset($msg['tool_calls'])) {
                $toolInfo = "\n\n[SYSTEM-LOG: Ich habe folgende Werkzeuge ausgeführt:]\n";
                foreach ($msg['tool_calls'] as $tc) {
                    $toolInfo .= "- " . ($tc['function']['name'] ?? 'Unbekannt') . " (Args: " . ($tc['function']['arguments'] ?? '{}') . ")\n";
                }

                $msg['content'] = ($msg['content'] ?? '') . $toolInfo;
                unset($msg['tool_calls']);
            }

            if ($role === 'tool') {
                $role = 'user';
                $msg['role'] = 'user';
                $msg['content'] = "[SYSTEM-LOG: Werkzeug-Ergebnis von meinem vorherigen Aufruf]\n" . ($msg['content'] ?? '');
                unset($msg['tool_call_id']);
                unset($msg['name']);
            }
            // ------------------------------------------------

            if (empty($normalizedMessages)) {
                $normalizedMessages[] = $msg;
            } else {
                $lastIdx = count($normalizedMessages) - 1;
                $lastRole = $normalizedMessages[$lastIdx]['role'];

                if ($lastRole === $role && $role !== 'tool') { // Merge text blocks if same role
                    $lastContent = $normalizedMessages[$lastIdx]['content'];
                    $currContent = $msg['content'] ?? '';

                    if (is_array($lastContent) && is_array($currContent)) {
                        $normalizedMessages[$lastIdx]['content'] = array_merge($lastContent, $currContent);
                    } else if (is_string($lastContent) && is_string($currContent)) {
                        $normalizedMessages[$lastIdx]['content'] = $lastContent . "\n\n" . $currContent;
                    } else if (is_string($lastContent) && is_array($currContent)) {
                        array_unshift($currContent, ['type' => 'text', 'text' => $lastContent . "\n\n"]);
                        $normalizedMessages[$lastIdx]['content'] = $currContent;
                    } else if (is_array($lastContent) && is_string($currContent)) {
                        $lastContent[] = ['type' => 'text', 'text' => "\n\n" . $currContent];
                        $normalizedMessages[$lastIdx]['content'] = $lastContent;
                    }
                } else {
                    $normalizedMessages[] = $msg;
                }
            }
        }

        $payload = [
            'model' => $this->agent->model ?? 'gpt-oss-120b',
            'messages' => $normalizedMessages,
            'temperature' => (float)($this->agent->temperature ?? 0.6),
            'top_p' => 1.0,
            'stream' => true // Enable Server-Sent Events Streaming
        ];

        // Ministral and Devstral models on the Gemini Proxy currently do not support Tool Calling
        // Passing the 'tools' array to them results in a 400 Bad Request error.
        $modelName = strtolower($this->agent->model ?? 'gpt-oss-120b');

        // AUTOMATIC MODEL UPGRADE / DOWNGRADE:
        // 1.x models are deprecated. 3.x models are too unstable and cause 150s timeouts.
        // Force redirect to stable 2.5 architecture to ensure instant replies.
        if (str_starts_with($modelName, 'gemini-1.') || str_starts_with($modelName, 'gemini-3.')) {
            $isPro = str_contains($modelName, 'pro');
            $modelName = $isPro ? 'gemini-2.5-pro' : 'gemini-2.5-flash';
            $payload['model'] = $modelName;
            #\Illuminate\Support\Facades\Log::info("Auto-mapped unstable model to: " . $modelName);
        }

        if (str_contains($modelName, 'stral')) {
            $filteredSchema = [];
        }

        if (!empty($filteredSchema)) {
            // BUGFIX: Google's OpenAI API wrapper silently dies with HTTP 503 Service Unavailable
            // when passing large JSON schemas (> ~80KB). We MUST compress the tools array to prevent timeouts.
            foreach ($filteredSchema as &$tool) {
                // Fix empty properties object parsing for PHP json_encode
                if (isset($tool['function']['parameters']['properties']) && empty($tool['function']['parameters']['properties'])) {
                    $tool['function']['parameters']['properties'] = new \stdClass();
                }

                // Truncate main description
                if (isset($tool['function']['description'])) {
                    $tool['function']['description'] = mb_substr($tool['function']['description'], 0, 250);
                }

                // Truncate parameter descriptions
                if (isset($tool['function']['parameters']['properties']) && is_array($tool['function']['parameters']['properties'])) {
                    foreach ($tool['function']['parameters']['properties'] as &$propDef) {
                        if (isset($propDef['description'])) {
                            $propDef['description'] = mb_substr($propDef['description'], 0, 60);
                        }
                    }
                }
            }

            if (count($filteredSchema) > 120) {
                #\Illuminate\Support\Facades\Log::warning("Too many tools requested (" . count($filteredSchema) . "). Truncating to 120 to prevent Gemini API HTTP 503.");
                $filteredSchema = array_slice($filteredSchema, 0, 120);
            }
            $payload['tools'] = $filteredSchema;
            $payload['tool_choice'] = 'auto';
        }

        try {
            // Log::info("Sending request to Gemini AI", ['model' => $payload['model'], 'temperature' => $payload['temperature']]);

            try {
                \Illuminate\Support\Facades\Cache::put('ai_live_state_' . $this->agent->id, [
                    'active_node' => 'cpu-chip',
                    'action_text' => 'LLM Inference (Tiefe: '.$depth.')...',
                    'pulse_color' => 'indigo'
                ], 60);
            } catch (\Exception $e) {}

            $startTime = microtime(true);
            $maxRetries = 3;
            $retryCount = 0;
            $responseString = null;
            $httpCode = 0;
            $curlError = null;
            $isAborted = false;

            while ($retryCount <= $maxRetries) {
                $url = rtrim($this->baseUrl, '/') . '/chat/completions';

               /* \Illuminate\Support\Facades\Log::info("DEBUG: GeminiAgent initiating cURL.", [
                    'url' => $url,
                    'model' => $payload['model'],
                    'payload_size' => strlen(json_encode($payload)),
                    'timeout_settings' => 120 + ($retryCount * 30),
                    'tools_count' => isset($payload['tools']) ? count($payload['tools']) : 0,
                    'stream' => $payload['stream'] ?? false
                ]);*/

                \Illuminate\Support\Facades\File::put(storage_path('logs/gemini_payload_dump.json'), json_encode($payload, JSON_PRETTY_PRINT));

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);

                // WICHTIG: Kein Laravel Guzzle (Http::) nutzen, um cURL Timeout 28 bei großen Payloads (Code/Bilder)
                // durch ungewollte Expect: 100-continue MTU Drops in Docker Containern zu verhindern.
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'Accept: text/event-stream'
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
                // Wir gewähren der LLM volle 120 Sekunden, um die Werkzeuge (Tools) zu lesen und zu entscheiden
                curl_setopt($ch, CURLOPT_TIMEOUT, 120 + ($retryCount * 30));
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                $rawResponseString = '';
                $streamedTextAccumulator = '';
                $toolCallAccumulators = [];
                $sseBuffer = '';
                $abortKey = 'abort_ai_agent_' . $this->agent->id;

                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($streamCallback, &$rawResponseString, &$streamedTextAccumulator, &$toolCallAccumulators, &$sseBuffer, $abortKey, &$isAborted) {
                    try {
                        if (\Illuminate\Support\Facades\Cache::pull($abortKey)) {
                            $isAborted = true;
                            return 0; // Aborts cURL with CURLE_WRITE_ERROR
                        }
                    } catch (\Exception $e) {}
                    $rawResponseString .= $data;
                    $sseBuffer .= $data;

                    // SSE Chunk Parsing - Safe Line Splitting
                    $lines = explode("\n", $sseBuffer);
                    $sseBuffer = array_pop($lines); // Keep the incomplete line in the buffer

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (str_starts_with($line, 'data: ')) {
                            $jsonStr = substr($line, 6);
                            if ($jsonStr === '[DONE]') continue;

                            $chunk = json_decode($jsonStr, true);
                            if (isset($chunk['choices'][0]['delta'])) {
                                $delta = $chunk['choices'][0]['delta'];

                                // Text Streaming to User UI
                                if (isset($delta['content'])) {
                                    $content = $delta['content'];
                                    $streamedTextAccumulator .= $content;
                                    if ($streamCallback) {
                                        $streamCallback(['type' => 'text_chunk', 'chunk' => $content]);
                                        // Removed ob_flush(); flush(); to prevent PHP output buffer exception locks
                                    }
                                }

                                // Tool Call Accumulation (Deltas over Stream)
                                if (isset($delta['tool_calls'])) {
                                    foreach ($delta['tool_calls'] as $tc) {
                                        $idx = $tc['index'] ?? 0;
                                        if (!isset($toolCallAccumulators[$idx])) {
                                            $toolCallAccumulators[$idx] = [
                                                'id' => uniqid('call_'),
                                                'type' => 'function',
                                                'function' => ['name' => '', 'arguments' => '']
                                            ];
                                        }

                                        foreach ($tc as $k => $v) {
                                            if ($k === 'function') {
                                                if (!isset($toolCallAccumulators[$idx]['function'])) $toolCallAccumulators[$idx]['function'] = [];
                                                foreach ($v as $fk => $fv) {
                                                    if ($fk === 'arguments') {
                                                        if (!isset($toolCallAccumulators[$idx]['function']['arguments'])) $toolCallAccumulators[$idx]['function']['arguments'] = '';
                                                        $toolCallAccumulators[$idx]['function']['arguments'] .= $fv;
                                                    } elseif ($fk === 'name' && !empty($fv)) {
                                                        $toolCallAccumulators[$idx]['function']['name'] = $fv;
                                                    } else {
                                                        if (is_string($fv) && isset($toolCallAccumulators[$idx]['function'][$fk])) {
                                                            $toolCallAccumulators[$idx]['function'][$fk] .= $fv;
                                                        } else {
                                                            $toolCallAccumulators[$idx]['function'][$fk] = $fv;
                                                        }
                                                    }
                                                }
                                            } elseif ($k !== 'index') {
                                                if ($k === 'id' && !empty($v)) {
                                                    $toolCallAccumulators[$idx]['id'] = $v;
                                                } elseif (is_string($v) && isset($toolCallAccumulators[$idx][$k])) {
                                                    $toolCallAccumulators[$idx][$k] .= $v;
                                                } else {
                                                    $toolCallAccumulators[$idx][$k] = $v;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    return strlen($data);
                });

                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($isAborted) {
                    break;
                }

                // Re-assemble standard response string behavior for errors or successful parsed outputs
                $responseString = $rawResponseString;

                // Erfolgreicher Aufruf oder harter lokaler 4xx Client Fehler (Ausnahme 429 Rate Limit) -> Abbrechen, da Retry nutzlos.
                if (!$curlError && $httpCode < 500 && $httpCode !== 429) {
                    break;
                }

                $retryCount++;
                if ($retryCount <= $maxRetries) {
                    $sleepSeconds = pow(2, $retryCount); // Exponentieller Backoff: 2s, 4s Pause
                    // Log::warning("Gemini API Error - initiating Retry {$retryCount}/{$maxRetries}", ['curl_error' => $curlError, 'http_code' => $httpCode]);
                    try {
                        \Illuminate\Support\Facades\Cache::put('ai_live_state_' . $this->agent->id, [
                            'active_node' => 'arrow-path',
                            'action_text' => "Verbindungsauslastung. Automatischer Neuversuch {$retryCount} in {$sleepSeconds}s...",
                            'pulse_color' => 'yellow'
                        ], 60);
                    } catch (\Exception $e) {}

                    // Blockiert absichtlich den Prozess, da die Retry-Warteschlange seriell erfolgen muss
                    sleep($sleepSeconds);
                } else if ($retryCount == $maxRetries + 1 && (in_array($httpCode, [0, 429, 503]) || $curlError)) {
                    if (str_contains($payload['model'] ?? '', 'pro')) {
                        // SILENT FALLBACK AUF FLASH WENN PRO DAUERHAFT ÜBERLASTET ODER GETIMEOUTED (HTTP 0) IST
                        Log::warning("Gemini Pro API completely overloaded or timed out. Executing silent fallback to Gemini Flash.", ['payload_size' => strlen(json_encode($payload))]);
                        $payload['model'] = 'gemini-2.5-flash'; // Safe static fallback guaranteed to exist
                        $retryCount = 0;
                        $maxRetries = 1; // Flash bekommt zügig noch maximal eine 2te Chance

                        try {
                            \Illuminate\Support\Facades\Cache::put('ai_live_state_' . $this->agent->id, [
                                'active_node' => 'bolt',
                                'action_text' => "Pro API antwortet nicht. Fallback auf schnelles Flash-Modell...",
                                'pulse_color' => 'orange'
                            ], 60);
                        } catch (\Exception $e) {}
                        // Kein Sleep, da sofortiger Modell-Wechsel oft das Problem löst
                    } else if (($payload['model'] ?? '') === 'gemini-2.5-flash') {
                        // TOTAL OUTAGE OF 2.5 INFRASTRUCTURE! Fallback to 1.5 Flash.
                        Log::warning("Gemini 2.5 Flash API overloaded. Executing final fallback to legacy Gemini 1.5 Flash.", ['payload_size' => strlen(json_encode($payload))]);
                        $payload['model'] = 'gemini-1.5-flash';
                        $retryCount = 0;
                        $maxRetries = 1;
                    }
                }
            }

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($isAborted) {
                return "[SKIP]";
            }

            if ($curlError) {
                Log::error("Gemini API Error (cURL)", ['error' => $curlError]);
                return "⚠️ **SYSTEM WARNUNG: cURL TIMEOUT** ⚠️\n\nDie Verbindung ist fehlgeschlagen: " . $curlError;
            }

            if ($httpCode >= 400) {
                Log::error("Gemini API HTTP Error", ['status' => $httpCode, 'response' => $responseString]);

                $errJson = json_decode($responseString, true);
                $errMsg = $responseString;
                if (is_array($errJson)) {
                    if (isset($errJson[0]['error']['message'])) {
                        $errMsg = $errJson[0]['error']['message'];
                    } elseif (isset($errJson['error']['message'])) {
                        $errMsg = $errJson['error']['message'];
                    }
                }

                $hint = "[GEGENMASSNAHME]\nDer KI-Provider (Google/Gemini) hat die Anfrage abgelehnt:\n> " . $errMsg;

                if ($httpCode === 503 || $httpCode === 429) {
                    $hint = "⚠️ **SERVER ÜBERLASTET** ⚠️\nDie Google Gemini KI-Server melden aktuell extrem hohe Auslastung (Spikes in Demand).\nBitte warte einen kurzen Moment und sende die Nachricht erneut, oder wechsele das API-Modell (z.B. auf gemini-1.5-flash), da dieses aktuell blockiert ist.";
                }

                return "⚠️ **SYSTEM WARNUNG: API VERBINDUNGSABBRUCH** ⚠️\n\nStatus Code HTTP " . $httpCode . ".\n\n" . $hint;
            }

            // Handle streamed response manually since standard OpenAI response schema is altered by SSE chunks
            $responseData = json_decode($responseString, true) ?? [];
            if (!empty($toolCallAccumulators)) {
                $message = [
                    'role' => 'assistant',
                    'content' => $streamedTextAccumulator,
                    'tool_calls' => array_values($toolCallAccumulators)
                ];
            } else {
                $message = [
                    'role' => 'assistant',
                    'content' => $streamedTextAccumulator
                ];
            }

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
                    
                    if (is_array($functionArgsString)) {
                        $functionArgsString = json_encode($functionArgsString, JSON_UNESCAPED_UNICODE);
                    }
                    
                    // Fix Gemini hallucination where it outputs "{}{}" for empty arguments, or "[]" from json_encode of empty assoc array
                    if ($functionArgsString === '{}{}' || $functionArgsString === '[]' || trim($functionArgsString) === '') {
                        $functionArgsString = '{}';
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

                    $executeArgs = json_decode($functionArgsString, true);
                    if ($executeArgs === null && json_last_error() !== JSON_ERROR_NONE) {
                        Log::warning("AI generated invalid JSON for tool call: {$functionName}", ['args' => $functionArgsString, 'error' => json_last_error_msg()]);
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCallId,
                            'content' => json_encode(['status' => 'error', 'message' => "SYSTEM EXCEPTION: Dein JSON-Format für die Argumente ist ungültig (Parse Error: " . json_last_error_msg() . "). Meistens passiert das, wenn du rohen HTML-Code übergibst und die Anführungszeichen bei class=\"...\" intern NICHT escaped hast! Du musst class=\\\"klassenname\\\" benutzen. BITTE KORRIGIERE DAS UND RUFE DAS TOOL ERNEUT AUF!"], JSON_UNESCAPED_UNICODE)
                        ];
                        continue;
                    }

                    // Log removed per CEO request

                    try {
                        \Illuminate\Support\Facades\Cache::put('ai_live_state_' . $this->agent->id, [
                            'active_node' => 'wrench-screwdriver',
                            'action_text' => 'Tool Call: ' . $functionName,
                            'pulse_color' => 'indigo'
                        ], 60);
                    } catch (\Exception $e) {}

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

                    try {
                        \Illuminate\Support\Facades\Cache::put('ai_live_state_' . $this->agent->id, [
                            'active_node' => 'circle-stack',
                            'action_text' => 'DB/Action Resultat verarbeitet...',
                            'pulse_color' => 'emerald'
                        ], 60);
                    } catch (\Exception $e) {}

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



                // Since we added new tool results, loop back and ask the AI again
                // so it can read the results and formulate a final answer.
                try {
                    \Illuminate\Support\Facades\Cache::put('ai_live_state_' . $this->agent->id, [
                        'active_node' => 'sparkles',
                        'action_text' => 'Re-Evaluierung des Kontexts...',
                        'pulse_color' => 'indigo'
                    ], 60);
                } catch (\Exception $e) {}

                return $this->chatLoop($messages, $contextData, $usageData, $eventsData, $depth + 1, $calledTools, $streamCallback);
            }

            // Provide final answer
            try {
                \Illuminate\Support\Facades\Cache::put('ai_live_state_' . $this->agent->id, [
                    'active_node' => 'bolt',
                    'action_text' => 'Finales Prompt beendet.',
                    'pulse_color' => 'emerald'
                ], 60);
            } catch (\Exception $e) {}

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

            $modelName = strtolower($payload['model'] ?? 'gemini-1.5-flash');
            if (str_starts_with($modelName, 'gemini-1.') || str_starts_with($modelName, 'gemini-3.')) {
                $isPro = str_contains($modelName, 'pro');
                $modelName = $isPro ? 'gemini-2.5-pro' : 'gemini-2.5-flash';
                $payload['model'] = $modelName;
            }

            $requestPayload = [
                'model' => $payload['model'],
                'temperature' => $payload['temperature'],
                'messages' => [
                    ['role' => 'system', 'content' => $payload['system_prompt']],
                    ['role' => 'user', 'content' => $prompt]
                ],
            ];

            $maxRetries = 1;
            $latencyMs = 0;

            for ($retryCount = 0; $retryCount <= $maxRetries; $retryCount++) {
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

                if (in_array($httpCode, [0, 429, 503]) && $retryCount === 0) {
                    if (str_contains($payload['model'] ?? '', 'pro')) {
                        \Illuminate\Support\Facades\Log::warning("processDirectPrompt: API Overloaded ($httpCode). Falling back to 2.5 flash.");
                        $requestPayload['model'] = 'gemini-2.5-flash';
                        $payload['model'] = 'gemini-2.5-flash';
                        continue;
                    } else if (($payload['model'] ?? '') === 'gemini-2.5-flash') {
                        \Illuminate\Support\Facades\Log::warning("processDirectPrompt: 2.5 flash Overloaded ($httpCode). Falling back to 1.5 flash.");
                        $requestPayload['model'] = 'gemini-1.5-flash';
                        $payload['model'] = 'gemini-1.5-flash';
                        continue;
                    }
                }

                return "API Fehler: " . $httpCode . " - " . $responseString;
            }

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

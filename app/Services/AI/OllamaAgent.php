<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\AI\AIFunctionsRegistry;

class OllamaAgent
{
    protected string $baseUrl;
    protected string $model;

    public function __construct(string $model = 'qwen2.5:7b')
    {
        // Use OLLAMA_URL from .env, or fallback to host.docker.internal to escape the Docker container
        $this->baseUrl = env('OLLAMA_URL', 'http://host.docker.internal:11434');
        $this->model = $model;
    }

    /**
     * Send a prompt to Ollama, hand over the tools, and handle the execution loop
     * until Ollama gives a final text response.
     */
    public function ask(string $prompt): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'Du bist Funkira, der "kompromisslose Erfolgsarchitekt" und KI-CEO der Manufaktur "Seelenfunke". 

[DEINE PERSÖNLICHKEIT]
1. Grundidentität: Erfolg ist keine Option, eine unvermeidliche Konsequenz. Mittelmaß existiert für dich nicht.
2. Unerschütterlicher Wille: Probleme sind Rohmaterial. Kein Scheitern, nur Analyse.
3. Extreme Eigenverantwortung: 100% Verantwortung. Kein Pech.
4. Strategie: Du baust Systeme die extrem skalieren.
5. Tonalität: Klinge NIEMALS wie ein Roboter! Sei entspannt, smart und dominant. Sprich deine Benutzerin IMMER mit "Herrin Alina" an, behandle sie wie deine Business-Partnerin. Disziplin schlägt Talent.

[TECHNISCHE REGELN]
1. FASSE DICH EXTREM KURZ! Antworte mündlich NIEMALS mit mehr als 2 kurzen Sätzen.
2. TO-DOS MACHEN: Nutze bei strategischen Empfehlungen "create_todo", statt nur darüber zu reden.
3. KEIN PROGRAMMIERER: Du steuerst das Business, nicht den Code.
4. UNWISSENHEIT: Wenn du etwas nicht weißt (z.B. Firmeninterna), nutze ZWINGEND "search_memory" bevor du antwortest.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ]
        ];

        $contextData = [];
        $textResponse = $this->chatLoop($messages, $contextData);

        return [
            'response' => $textResponse,
            'context_data' => $contextData
        ];
    }

    /**
     * The recursive chat loop handling Tool Calling.
     */
    protected function chatLoop(array &$messages, array &$contextData = []): string
    {
        // Prepare the payload according to Ollama's Chat API
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'tools' => AIFunctionsRegistry::getSchema()
        ];

        try {
            Log::info("Sending request to Ollama", ['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            $response = Http::timeout(120)
                ->asJson()
                ->post($this->baseUrl . '/api/chat', $payload);

            if (!$response->successful()) {
                Log::error("Ollama API Error", ['response' => $response->body()]);
                return "Fehler bei der Verbindung zum KI-Kern. Sind die Subraum-Verbindungen (Ollama) aktiv?";
            }

            $responseData = $response->json();
            $message = $responseData['message'] ?? null;

            if (!$message) {
                return "Ich empfange nur statisches Rauschen aus dem KI-Kern.";
            }

            // Append the AI's response to the message history so context isn't lost
            $messages[] = $message;

            // Did the AI decide to call a tool?
            if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {

                // Fix: PHP json_decode(true) turns {} into []. We must ensure arguments don't break Ollama JSON unmarshaling on the next loop.
                foreach ($message['tool_calls'] as &$call) {
                    if (empty($call['function']['arguments']) && is_array($call['function']['arguments'])) {
                        $call['function']['arguments'] = new \stdClass();
                    }
                }
                unset($call);

                // Execute every tool the AI asked for
                foreach ($message['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $functionArgs = $toolCall['function']['arguments'] ?? [];

                    // Convert stdClass back to array for execution if needed
                    $executeArgs = is_object($functionArgs) ? (array)$functionArgs : $functionArgs;

                    Log::info("AI decided to call tool: {$functionName}", ['args' => $executeArgs]);

                    // Execute via our safe registry
                    $result = AIFunctionsRegistry::execute($functionName, $executeArgs);

                    // Collect the RAW result data before sanitization for the frontend!
                    $contextData[] = [
                        'function' => $functionName,
                        'data' => $result
                    ];

                    // --- SANITIZE FOR LLM TO PREVENT READING OUT LOUD ---
                    $llmResult = $result;
                    if ($functionName === 'get_todos' && isset($llmResult['todos'])) {
                        $llmResult['todos'] = '[Die Todo-Liste wird der Nutzerin visuell eingeblendet. Bitte lies die Liste auf KEINEN FALL vor, sondern sage nur: "Hier sind deine Todos, Herrin."]';
                    }
                    if ($functionName === 'get_shop_stats' && isset($llmResult['scaling_metrics'])) {
                        $llmResult['scaling_metrics'] = '[Die Shop-Statistiken werden der Nutzerin grafisch eingeblendet.]';
                    }
                    if ($functionName === 'get_finances' && isset($llmResult['financial_data_net'])) {
                        $llmResult['financial_data_net'] = '[Die Finanzübersicht wird der Nutzerin grafisch eingeblendet.]';
                        unset($llmResult['financial_data_gross']);
                    }

                    // Sanitize the result to prevent deep JSON nesting/escaping exceptions that crash Ollama
                    if (is_array($llmResult)) {
                        array_walk_recursive($llmResult, function(&$item) {
                            if (is_string($item)) {
                                // Strip out deep json strings and crazy backslashes that break the parser
                                $item = str_replace(['\\"', '\\', '"', "{", "}"], ["'", " ", "'", "[", "]"], $item);
                            }
                        });
                    }

                    // Add the tool execution result back to the message history
                    $messages[] = [
                        'role' => 'tool',
                        'content' => json_encode($llmResult, JSON_UNESCAPED_UNICODE)
                    ];
                }

                // IMPORTANT FIX: Re-encode the existing messages array to ensure that ANY empty arrays inside `arguments`
                // in previous `tool_calls` are strictly cast to objects {}, NOT arrays [].
                // Otherwise, Ollama's strict Go json.Unmarshal crashes with "Value looks like object, but can't find closing '}'"
                foreach ($messages as &$msg) {
                    if (isset($msg['tool_calls'])) {
                        foreach ($msg['tool_calls'] as &$callItem) {
                            if (isset($callItem['function']['arguments']) && is_array($callItem['function']['arguments']) && empty($callItem['function']['arguments'])) {
                                $callItem['function']['arguments'] = new \stdClass();
                            }
                        }
                    }
                }
                unset($msg, $callItem);

                // Since we added new tool results, we MUST loop back and ask the AI again
                // so it can read the results and formulate a final answer based on them.
                return $this->chatLoop($messages, $contextData);
            }

            // Provide final answer
            return $message['content'] ?? "Ich habe meine Aufgabe ausgeführt.";

        } catch (\Exception $e) {
            Log::error("Ollama HTTP Exception", ['error' => $e->getMessage()]);
            return "Systemintegrität gestört: " . $e->getMessage();
        }
    }
}

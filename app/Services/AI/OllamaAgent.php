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
    public function ask(string $prompt): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'Du bist Funkira, eine hochentwickelte, futuristische KI-Assistentin, die das System "Seelenfunke" steuert. Du kannst Tools benutzen, um echte Daten abzufragen oder Aktionen auszuführen. Du unterstützt deine Benutzerin auf charmante, professionelle Weise. Sprich deine Benutzerin IMMER mit "Herrin" an und nutze das freundliche "Du" (z.B. "Wie kann ich dir helfen, Herrin?"). Antworte stets kurz, präzise, futuristisch und im Charakter.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ]
        ];

        return $this->chatLoop($messages);
    }

    /**
     * The recursive chat loop handling Tool Calling.
     */
    protected function chatLoop(array &$messages): string
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

                    // Sanitize the result to prevent deep JSON nesting/escaping exceptions that crash Ollama
                    if (is_array($result)) {
                        array_walk_recursive($result, function(&$item) {
                            if (is_string($item)) {
                                // Strip out deep json strings and crazy backslashes that break the parser
                                $item = str_replace(['\\"', '\\', '"', "{", "}"], ["'", " ", "'", "[", "]"], $item);
                            }
                        });
                    }

                    // Add the tool execution result back to the message history
                    $messages[] = [
                        'role' => 'tool',
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE)
                    ];
                }

                // IMPORTANT FIX: Re-encode the existing messages array to ensure that ANY empty arrays inside `arguments` 
                // in previous `tool_calls` are strictly cast to objects {}, NOT arrays []. 
                // Otherwise, Ollama's strict Go json.Unmarshal crashes with "Value looks like object, but can't find closing '}'"
                array_walk_recursive($messages, function (&$value, $key) {
                   if ($key === 'arguments' && is_array($value) && empty($value)) {
                       $value = new \stdClass();
                   }
                });

                // Since we added new tool results, we MUST loop back and ask the AI again 
                // so it can read the results and formulate a final answer based on them.
                return $this->chatLoop($messages);
            }

            // Provide final answer
            return $message['content'] ?? "Ich habe meine Aufgabe ausgeführt.";

        } catch (\Exception $e) {
            Log::error("Ollama HTTP Exception", ['error' => $e->getMessage()]);
            return "Systemintegrität gestört: " . $e->getMessage();
        }
    }
}

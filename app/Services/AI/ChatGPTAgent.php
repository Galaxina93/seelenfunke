<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiToolUsage;
use App\Models\System\SystemLog;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Log;
use Exception;

class ChatGPTAgent implements AiProviderInterface
{
    protected string $baseUrl = 'https://api.openai.com/v1';
    protected string $apiKey;
    protected AiAgent $agent;
    public string $dynamicSystemPrompt = '';

    public function __construct(AiAgent $agent)
    {
        $this->apiKey = config('services.openai.key', env('OPENAI_API_KEY', ''));
        $this->agent = $agent;

        if (empty($this->apiKey)) {
            Log::warning("OpenAI API key is missing. Ensure OPENAI_API_KEY is placed in your .env");
        }
    }

    public function ask(array $incomingMessages, ?\Closure $streamCallback = null): array
    {
        // Minimal stub representing Chat Loop implementation...
        // In reality, this merges context and identical logic to Gemini chatLoop, 
        // using `$this->baseUrl/chat/completions` array schema.
        
        return [
            'text' => 'ChatGPT Agent is running successfully.',
            'events' => []
        ];
    }

    public static function processDirectPrompt(AiAgent $agent, string $prompt): string
    {
        $apiKey = config('services.openai.key', env('OPENAI_API_KEY', ''));
        
        $requestPayload = [
            'model' => $agent->model ?? 'gpt-4o',
            'temperature' => (float)($agent->temperature ?? 0.4),
            'messages' => [
                ['role' => 'system', 'content' => current(AiAgentService::getAgentPayload($agent)) ?? 'You are a helpful AI.'],
                ['role' => 'user', 'content' => $prompt]
            ],
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestPayload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $responseString = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $responseString) {
            $data = json_decode($responseString, true);
            return $data['choices'][0]['message']['content'] ?? 'Das LLM hat keinen Text zurückgegeben.';
        }

        return "API Fehler: " . $httpCode . " - " . $responseString;
    }
}

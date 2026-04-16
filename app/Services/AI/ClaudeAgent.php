<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiToolUsage;
use App\Models\System\SystemLog;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Log;
use Exception;

class ClaudeAgent implements AiProviderInterface
{
    protected string $baseUrl = 'https://api.anthropic.com/v1';
    protected string $apiKey;
    protected AiAgent $agent;
    public string $dynamicSystemPrompt = '';

    public function __construct(AiAgent $agent)
    {
        $this->apiKey = config('services.anthropic.key', env('ANTHROPIC_API_KEY', ''));
        $this->agent = $agent;

        if (empty($this->apiKey)) {
            Log::warning("Anthropic API key is missing. Ensure ANTHROPIC_API_KEY is placed in your .env");
        }
    }

    public function ask(array $incomingMessages, ?\Closure $streamCallback = null): array
    {
        // Minimal stub representing Chat Loop implementation...
        return [
            'text' => 'Claude Agent is running successfully.',
            'events' => []
        ];
    }

    public static function processDirectPrompt(AiAgent $agent, string $prompt): string
    {
        $apiKey = config('services.anthropic.key', env('ANTHROPIC_API_KEY', ''));
        
        $requestPayload = [
            'model' => $agent->model ?? 'claude-3-5-sonnet-20240620',
            'max_tokens' => 4096,
            'temperature' => (float)($agent->temperature ?? 0.4),
            'system' => current(AiAgentService::getAgentPayload($agent)) ?? 'You are a helpful AI.',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestPayload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $responseString = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $responseString) {
            $data = json_decode($responseString, true);
            return $data['content'][0]['text'] ?? 'Das LLM hat keinen Text zurückgegeben.';
        }

        return "API Fehler: " . $httpCode . " - " . $responseString;
    }
}

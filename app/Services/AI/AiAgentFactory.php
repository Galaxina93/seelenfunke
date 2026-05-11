<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use Exception;

class AiAgentFactory
{
    /**
     * Resolves the correct API Agent Service instance based on the AI Agent's provider.
     *
     * @param AiAgent $agent
     * @return \App\Services\AI\Contracts\AiProviderInterface
     */
    public static function make(AiAgent $agent)
    {
        $provider = strtolower($agent->provider ?? 'google');
        $model = strtolower($agent->model ?? '');

        // Auto-detect Mittwald models even if provider is wrongly set
        if (str_contains($model, 'oss') || str_contains($model, 'stral') || str_contains($model, 'qwen') || $provider === 'mittwald') {
            return class_exists(MittwaldAgent::class) ? new MittwaldAgent($agent) : new GeminiAgent($agent);
        }

        if ($provider === 'openai') {
             // Will return an instance of ChatGPTAgent and if it errors, fallback to another provider later
             return class_exists(ChatGPTAgent::class) ? new ChatGPTAgent($agent) : new GeminiAgent($agent);
        }

        if ($provider === 'anthropic') {
             return class_exists(ClaudeAgent::class) ? new ClaudeAgent($agent) : new GeminiAgent($agent);
        }

        // Default: Google Gemini API (Proxy or direct)
        return new GeminiAgent($agent);
    }

    /**
     * Routes the direct prompt to the correct Agent Service logic.
     * Has auto-fallback logic if the primary provider yields an API error (503/429).
     *
     * @param AiAgent $agent
     * @param string $prompt
     * @return string
     */
    public static function processDirectPrompt(AiAgent $agent, string $prompt): string
    {
        $provider = strtolower($agent->provider ?? 'google');
        $model = strtolower($agent->model ?? '');

        // Auto-detect Mittwald models
        if (str_contains($model, 'oss') || str_contains($model, 'stral') || str_contains($model, 'qwen') || $provider === 'mittwald') {
            $provider = 'mittwald'; // Force provider variable for logic
        }

        try {
            if ($provider === 'openai' && class_exists(ChatGPTAgent::class)) {
                $response = ChatGPTAgent::processDirectPrompt($agent, $prompt);
            } elseif ($provider === 'anthropic' && class_exists(ClaudeAgent::class)) {
                $response = ClaudeAgent::processDirectPrompt($agent, $prompt);
            } elseif ($provider === 'mittwald' && class_exists(MittwaldAgent::class)) {
                $response = MittwaldAgent::processDirectPrompt($agent, $prompt);
            } else {
                $response = GeminiAgent::processDirectPrompt($agent, $prompt);
            }
            
            // Check if response starts with API Error for Swarm Routing Fallback
            if (str_starts_with($response, 'API Fehler:') && !empty($agent->fallback_provider)) {
                \Illuminate\Support\Facades\Log::warning("Swarm Router Fallback triggered. Provider {$provider} failed. Switching to {$agent->fallback_provider}.");
                $agent->provider = $agent->fallback_provider;
                return self::processDirectPrompt($agent, $prompt); // recursive fallback
            }

            return $response;

        } catch (\Exception $e) {
            if (!empty($agent->fallback_provider) && $agent->provider !== $agent->fallback_provider) {
                \Illuminate\Support\Facades\Log::warning("Swarm Runtime Exception triggered fallback. Switch to {$agent->fallback_provider}.", ['exception' => $e->getMessage()]);
                $agent->provider = $agent->fallback_provider;
                return self::processDirectPrompt($agent, $prompt);
            }
            return "Kritischer Fehler der Provider-Infrastruktur: " . $e->getMessage();
        }
    }
}

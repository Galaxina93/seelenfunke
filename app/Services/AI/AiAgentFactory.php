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
        // Default: Google Gemini API
        return new GeminiAgent($agent);
    }

    /**
     * Routes the direct prompt to the correct Agent Service logic.
     *
     * @param AiAgent $agent
     * @param string $prompt
     * @return string
     */
    public static function processDirectPrompt(AiAgent $agent, string $prompt): string
    {
        try {
            $response = GeminiAgent::processDirectPrompt($agent, $prompt);
            return $response;
        } catch (\Exception $e) {
            return "Kritischer Fehler der Provider-Infrastruktur: " . $e->getMessage();
        }
    }
}

<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use Exception;

class AiAgentFactory
{
    /**
     * Resolves the correct API Agent Service instance based on the AI Agent's model name.
     *
     * @param AiAgent $agent
     * @return MittwaldAgent|GeminiAgent
     */
    public static function make(AiAgent $agent)
    {
        $model = strtolower($agent->model ?? '');

        if (str_starts_with($model, 'gemini')) {
            return new GeminiAgent($agent);
        }

        // Default or Fallback: Mittwald Proxy API
        return new MittwaldAgent($agent);
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
        $model = strtolower($agent->model ?? '');

        if (str_starts_with($model, 'gemini')) {
            return GeminiAgent::processDirectPrompt($agent, $prompt);
        }

        // Default or Fallback
        return MittwaldAgent::processDirectPrompt($agent, $prompt);
    }
}

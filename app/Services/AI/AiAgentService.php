<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use Exception;

class AiAgentService
{
    /**
     * Retrieves an active AI Agent by its ID or Name, including its allowed tools.
     *
     * @param int|string $identifier
     * @return AiAgent
     * @throws Exception
     */
    public static function getAgent($identifier): AiAgent
    {
        $query = AiAgent::where('is_active', true)->with('tools');

        if (is_numeric($identifier)) {
            $agent = $query->find($identifier);
        } else {
            $agent = $query->where('name', $identifier)->first();
        }

        if (!$agent) {
            throw new Exception("AI Agent '{$identifier}' not found or is inactive.");
        }

        return $agent;
    }

    /**
     * Prepares the payload configuration for an Agent, filtering the global
     * AIFunctionsRegistry schema to only include tools the agent is permitted to use.
     *
     * @param AiAgent $agent
     * @return array
     */
    public static function getAgentPayload(AiAgent $agent): array
    {
        $allowedToolIdentifiers = $agent->tools->pluck('identifier')->toArray();

        // Extrahiere das Schema aus der globalen Registry
        $globalSchema = class_exists('\App\Services\AI\AIFunctionsRegistry')
            ? \App\Services\AI\AIFunctionsRegistry::getSchema()
            : [];

        // Filtere das Schema: Nur Tools, die dem Agenten zugewiesen sind
        $filteredSchema = array_filter($globalSchema, function ($toolData) use ($allowedToolIdentifiers) {
            $functionName = $toolData['function']['name'] ?? null;
            return in_array($functionName, $allowedToolIdentifiers);
        });

        // Key-Reset nach array_filter
        $filteredSchema = array_values($filteredSchema);

        return [
            'model' => $agent->model ?? 'gpt-oss-120b',
            'temperature' => (float) ($agent->temperature ?? 0.4),
            'system_prompt' => $agent->system_prompt,
            'tools' => empty($filteredSchema) ? null : $filteredSchema,
        ];
    }
}

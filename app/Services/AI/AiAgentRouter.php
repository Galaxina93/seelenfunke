<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;

class AiAgentRouter
{
    /**
     * Bestimmt den zuständigen internen Agenten in 0 ms (ohne zusätzlichen LLM-Netzwerkaufruf).
     * Da Funkira als zentrale Super-Intelligenz alle internen Fähigkeitspools dynamisch selbst verwaltet
     * und Funki strikt für den externen Shop-Support isoliert ist, übernimmt intern immer Funkira.
     *
     * @param string $userInput
     * @param array $currentlyActiveIds
     * @return array
     */
    public static function determineRequiredAgents(string $userInput, array $currentlyActiveIds = []): array
    {
        $internalAgents = AiAgent::where('is_active', true)
            ->where('name', '!=', 'Funki')
            ->get();

        if ($internalAgents->isEmpty()) {
            return $currentlyActiveIds;
        }

        $funkira = $internalAgents->firstWhere('name', 'Funkira');
        if ($funkira) {
            return [$funkira->id];
        }

        return [$internalAgents->first()->id];
    }
}

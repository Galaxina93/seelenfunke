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
        $allowedToolIdentifiers[] = 'system_get_current_time';

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

        $timeInfo = "\n\n[SYSTEM-ZEIT & AKTUELLE MISSION]\n" .
                    "Die aktuelle Systemzeit beim Verbindungsaufbau ist: " . now()->format('d.m.Y H:i:s') . " Uhr.\n" .
                    "HINWEIS: Da diese Verbindung länger offen bleiben kann, nutze das Tool `system_get_current_time` für die exakte, minutengenaue Uhrzeit, um zu prüfen, ob sich deine Anweisungen (wie Schlafenszeit) geändert haben.\n";
        
        try {
            $botService = app(\App\Services\AI\AiSupportService::class);
            $mission = $botService->getUltimateCommand(false);
            
            if (!empty($mission['recommendation'])) {
                $timeInfo .= "WICHTIGER KONTEXT (Deine aktuelle Hauptaufgabe für den Nutzer):\n";
                $timeInfo .= "Status/Routine: " . ($mission['flow']['title'] ?? 'Unbekannt') . "\n";
                $timeInfo .= "Fokus: " . ($mission['recommendation']['title'] ?? '') . "\n";
                $timeInfo .= "Anweisung: " . ($mission['recommendation']['message'] ?? '') . "\n";
                
                if (($mission['flow']['type'] ?? '') === 'sleep') {
                    $timeInfo .= "\nACHTUNG: ES IST SCHLAFENSZEIT! Du musst den Nutzer anweisen, sofort schlafen zu gehen. Verweigere neue Aufgaben strengstens!\n";
                }
            }
        } catch (\Exception $e) {
            // Fallback falls der Service nicht erreichbar ist
        }

        return [
            'model' => $agent->model ?? 'gemini-2.5-flash',
            'temperature' => (float) ($agent->temperature ?? 0.4),
            'system_prompt' => \App\Services\AI\AiPromptService::getRichPrompt($agent) . $timeInfo,
            'tools' => empty($filteredSchema) ? null : $filteredSchema,
        ];
    }
}

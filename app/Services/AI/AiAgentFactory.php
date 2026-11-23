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
        if (app()->bound(\App\Services\AI\Contracts\AiProviderInterface::class)) {
            return app(\App\Services\AI\Contracts\AiProviderInterface::class, ['agent' => $agent]);
        }

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
            $errorMsg = "Kritischer Fehler der Provider-Infrastruktur: " . $e->getMessage();
            
            try {
                $body = "Der KI-Agent `{$agent->name}` konnte nicht antworten. Die KI-Infrastruktur oder externe Modell-API meldete eine kritische Störung.\n\n**Fehler-Details:**\n" . $e->getMessage();
                \App\Services\System\SystemAlertTicketService::upsertSystemTicket(
                    alertKey: 'ai_provider_down:' . ($agent->slug ?: $agent->name),
                    title: "KI-Infrastruktur Störung ({$agent->name})",
                    markdownBody: $body,
                    departmentName: 'Agenten',
                    agentName: 'Agenti',
                    ticketType: 'ai_infrastructure_alert',
                    priority: 'high',
                    assignToAntigravity: false,
                    extraPayload: [
                        'agent_name' => $agent->name,
                        'exception' => get_class($e),
                    ]
                );
            } catch (\Exception $ticketErr) {
                \Illuminate\Support\Facades\Log::error("Failed to create AI fallback crash ticket: " . $ticketErr->getMessage());
            }

            return $errorMsg;
        }
    }
}

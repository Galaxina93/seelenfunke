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
            $errorMsg = "Kritischer Fehler der Provider-Infrastruktur: " . $e->getMessage();
            
            try {
                $email = config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                $body = "Der KI-Agent '{$agent->name}' konnte nicht antworten. Die gesamte KI-Infrastruktur oder API ist abgestürzt.\n\nFehler-Details:\n" . $e->getMessage();
                \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Services\AI\Mails\AiAgentMessageMail("SYSTEM-NOTFALL: KI-Infrastruktur down", $body, "System"));
            } catch (\Exception $mailErr) {
                \Illuminate\Support\Facades\Log::error("Failed to send AI fallback crash email: " . $mailErr->getMessage());
            }

            return $errorMsg;
        }
    }
}

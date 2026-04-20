<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAgentRouter
{
    /**
     * Analyzes user input against the available agents and determines who must be in the chat.
     * 
     * @param string $userInput
     * @param array $currentlyActiveIds
     * @return array
     */
    public static function determineRequiredAgents(string $userInput, array $currentlyActiveIds = []): array
    {
        $agents = AiAgent::where('is_active', true)->with('role')->get();
        if ($agents->isEmpty()) {
            return $currentlyActiveIds;
        }

        $agentList = [];
        foreach ($agents as $agent) {
            $tools = $agent->role ? collect($agent->role->tools)->pluck('name')->implode(', ') : 'Keine spezifischen Fähigkeiten';
            $agentList[] = "ID: {$agent->id}\nName: {$agent->name}\nRolle: " . ($agent->role ? $agent->role->name : 'Allrounder') . "\nBeschreibung: {$agent->role_description}\nWerkzeuge: {$tools}\n---";
        }
        $agentStr = implode("\n", $agentList);

        $sysPrompt = "Du bist ein intelligenter Routing-Dispatcher für ein Multi-Agenten System.
Deine Aufgabe ist es herauszufinden, welcher oder welche Agenten die Nutzeranfrage am besten beantworten können.
Hier sind die verfügbaren Agenten:

{$agentStr}

NUTZERANFRAGE: '{$userInput}'

REGELN FÜR DAS ROUTING:
1. Analysiere das Ziel der Nutzeranfrage und vergleiche es präzise mit den expliziten 'Werkzeuge' und 'Rolle' der Agenten.
2. Spricht der Nutzer einen Agenten explizit namentlich an (z.B. 'Funkira mach mal...'), vergleiche, ob dieser Agent das überhaupt kann!
3. Wenn der namentlich genannte Agent aber fachlich absolut NICHT das passende Werkzeug besitzt (z.B. Funkira wird nach Gutscheinen gefragt, Funkira hat aber nur Code-Tools, Luna hat aber VoucherTools), dann LASS DEN FALSCHEN AGENTEN WEG und inkludiere **NUR** die ID des echten Fach-Agenten! (Strikter Handover).
4. Wird kein Agent genannt, wähle exakt den EINEN fachlich passendsten Agenten basierend auf den Tools aus. Wenn ein allgemeines 'Hallo' kommt, wähle den Agenten, der allgemeine Fragen klärt.
5. Antworte AUSSCHLIESSLICH mit einem puren JSON-Array von Strings (die Agent-IDs). Keine Markdown-Blöcke, keine Rückfragen, reines JSON.
Beispiel richtig: [\"id1\"]
Beispiel falsch: Hier sind die Agenten: [...]";

        try {
            // Pick API from the first active agent to ensure we have a valid key
            $defaultAgent = $agents->first();
            $modelStr = strtolower($defaultAgent->model ?? '');
            
            if (str_starts_with($modelStr, 'gemini')) {
                $llmUrl = config('services.gemini.url') ?: 'https://generativelanguage.googleapis.com/v1beta/openai/';
                $endpoint = rtrim($llmUrl, '/') . '/chat/completions';
                $apiKey = config('services.gemini.key');
                $modelName = 'gemini-2.5-flash'; // Forcing working 2.x arch
            } else {
                $llmUrl = config('services.mittwald.url') ?: 'https://api.mittwald.example/v1';
                $endpoint = rtrim($llmUrl, '/') . '/chat/completions';
                $apiKey = config('services.mittwald.key');
                $modelName = $defaultAgent->model ?: 'auto';
            }

            if (!$apiKey) {
                return $currentlyActiveIds; // Fallback if no config
            }

            $response = Http::timeout(8)
                ->withToken($apiKey)
                ->post($endpoint, [
                    'model' => $modelName,
                    'messages' => [
                        ['role' => 'user', 'content' => $sysPrompt]
                    ], 
                    'temperature' => 0.0,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content') ?? '[]';
                $content = str_replace(['```json', '```'], '', trim($content));
                $decoded = json_decode($content, true);
                
                if (is_array($decoded) && count($decoded) > 0) {
                    $validIds = $agents->pluck('id')->toArray();
                    $filteredDecoded = array_intersect($decoded, $validIds); // Ensure fake IDs aren't returned
                    
                    if (!empty($filteredDecoded)) {
                        return array_values(array_unique(array_merge($currentlyActiveIds, $filteredDecoded)));
                    }
                }
            } else {
                Log::error("AiAgentRouter Response Error", ['body' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error("AiAgentRouter Exception: " . $e->getMessage());
        }

        // Fallback => return whatever was active
        return $currentlyActiveIds;
    }
}

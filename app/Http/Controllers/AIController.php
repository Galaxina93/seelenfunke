<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ai\AiChatMemory;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Http\Request;

class AIController extends Controller
{
    /**
     * Retrieve the JSON Schema describing all available AI Tools.
     */
    public function schema(Request $request)
    {
        // Here you would implement rudimentary security check.
        // Example: if($request->header('X-AI-TOKEN') !== env('AI_SECRET')) return abort(403);

        return response()->json([
            'status' => 'success',
            'tools' => AIFunctionsRegistry::getSchema(),
        ]);
    }

    /**
     * Execute an AI Tool call coming from Ollama/Python Script.
     */
    public function execute(Request $request)
    {
        // Example Payload:
        // { "function": "get_system_health", "args": { "param1": "val1" } }

        $request->validate([
            'function' => 'required|string',
            'args' => 'sometimes|array'
        ]);

        $functionName = $request->input('function');
        $args = $request->input('args', []);

        try {
            // Forward execution to the registry
            $result = AIFunctionsRegistry::execute($functionName, $args);

            return response()->json([
                'status' => 'success',
                'function' => $functionName,
                'result' => $result
            ]);

        } catch (\InvalidArgumentException $e) {
            // Function not found
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);

        } catch (\Exception $e) {
            // Internal execution error
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Frontend Endpoint: Receives a conversation history, sends it to the Agent, and returns the response.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'prompt' => 'sometimes|string|max:1000',
            'history' => 'sometimes|array'
        ]);

        $history = $request->input('history', []);

        // Backwards compatibility or single-shot prompts
        if (empty($history) && $request->has('prompt')) {
            $history[] = [
                'role' => 'user',
                'content' => $request->input('prompt')
            ];
        }

        $map = \App\Services\AI\AIFunctionsRegistry::getAdminNavigationMap();

        $navMap = "Verfügbare Admin-Routen & Bezeichnungen:\n";
        foreach ($map as $path => $name) {
            $navMap .= "- " . $name . " => " . $path . "\n";
        }

        $dynamicSystemPrompt = "WICHTIG ZUR NAVIGATION: Wenn du das Tool `open_nav_item` einsetzt, wähle IMMER nur eine exakte Route aus dieser Liste. Erfinde und rate NIEMALS fremde URLs! Nutze ausschließlich diese:\n" . $navMap . "\n" .
                         "ACHTUNG: Wenn Alina befiehlt das 'Zentrum' zu öffnen, dann MUSS zwingend das Tool `open_zentrum` ausgeführt werden! Vergiss in dem Fall `open_nav_item`!";

        $aiAgent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first() ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();
        
        if (!$aiAgent) {
            return response()->json(['status' => 'error', 'message' => 'No AI Agent found in database.'], 500);
        }

        $agent = \App\Services\AI\AiAgentFactory::make($aiAgent);
        
        if (method_exists($agent, 'setDynamicSystemPrompt')) {
            $agent->setDynamicSystemPrompt($dynamicSystemPrompt);
        }

        $result = $agent->ask($history);

        // Speichere finalen Dialog-Verlauf in der Datenbank
        $sessionId = session()->getId();

        // Was hat der User gesagt? (Finde die neuste User-Nachricht)
        $userMsg = collect($history)->reverse()->firstWhere('role', 'user');
        if ($userMsg) {
            AiChatMemory::create([
                'session_id' => $sessionId,
                'role' => 'user',
                'content' => $userMsg['content'],
            ]);
        }

        // Was hat die KI final geantwortet?
        if (!empty($result['response'])) {
            AiChatMemory::create([
                'session_id' => $sessionId,
                'role' => 'assistant',
                'content' => $result['response'],
            ]);
        }

        // Generiere Audio (Base64) falls möglich
        $base64Audio = null;
        
        $ttsProvider = $aiAgent ? $aiAgent->tts_provider : 'none';
        $ttsEnabled = $aiAgent ? (bool) $aiAgent->tts_enabled : false;

        if ($ttsProvider === 'none') {
            $ttsEnabled = false;
        }

        if ($ttsEnabled && !empty($result['response']) && $aiAgent && $ttsProvider === 'toni_xttsv2') {
            try {
                $cleanText = $result['response'];
                $speed = $aiAgent->tts_speed ?? 1.0;
                $apiUrl = $aiAgent->tts_api_url ?: env('TONI_AI_URL', 'http://192.168.188.32:8000');
                if (!str_ends_with(parse_url($apiUrl, PHP_URL_PATH) ?? '', '/api/toni/tts')) {
                    $apiUrl = rtrim($apiUrl, '/') . '/api/toni/tts';
                }
                $apiKey = env('TONI_AI_API_KEY');
                
                // Standard payload design for Toni / XTTS API
                $payload = [
                    'text' => $cleanText,
                    'language' => 'de',
                    'speed' => $speed
                ];
                
                if ($aiAgent->tts_voice) {
                    $payload['voice_key'] = $aiAgent->tts_voice;
                }

                $request = \Illuminate\Support\Facades\Http::connectTimeout(2)->timeout(30);
                if (!empty($apiKey)) {
                    $request = $request->withToken($apiKey);
                }

                $ttsResponse = $request->post($apiUrl, $payload);
                
                $isAudio = str_contains($ttsResponse->header('Content-Type'), 'audio/');

                // Fallback: If Toni fails (HTTP error or JSON error payload instead of audio), retry with 'default'
                if ((!$ttsResponse->successful() || !$isAudio) && isset($payload['voice_key'])) {
                    \Illuminate\Support\Facades\Log::warning("Toni rejected voice_key '{$payload['voice_key']}'. Retrying with default voice: " . $ttsResponse->body());
                    unset($payload['voice_key']);
                    $ttsResponse = $request->post($apiUrl, $payload);
                    $isAudio = str_contains($ttsResponse->header('Content-Type'), 'audio/');
                }

                if ($ttsResponse->successful() && $isAudio) {
                    // Alles gut, Bytestream empfangen
                    $base64Audio = base64_encode($ttsResponse->body());
                } else {
                    \Illuminate\Support\Facades\Log::warning("Toni TTS Soft-Error in AIController: " . $ttsResponse->body());
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Toni TTS Fetch failed: " . $e->getMessage());
            }
        }

        // Metriken speichern (Fix für Analytics Dashboard fehlende Chat Activity)
            // Tracking is now automatically handled centrally in AiAgentFactory

        return response()->json([
            'status' => 'success',
            'agent_name' => $aiAgent ? $aiAgent->name : 'Funkira',
            'tts_enabled' => $ttsEnabled,
            'response' => $result['response'],
            'history' => $result['history'] ?? [],
            'context_data' => $result['context_data'] ?? [],
            'events_data' => $result['events'] ?? [],
            'usage' => $result['usage'] ?? [],
            'audio' => $base64Audio
        ]);
    }
}

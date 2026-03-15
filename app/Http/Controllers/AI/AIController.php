<?php

namespace App\Http\Controllers\AI;

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

        $history[] = [
            'role' => 'system',
            'content' => "WICHTIG ZUR NAVIGATION: Wenn du das Tool `open_nav_item` einsetzt, wähle IMMER nur eine exakte Route aus dieser Liste. Erfinde und rate NIEMALS fremde URLs! Nutze ausschließlich diese:\n" . $navMap . "\n" .
                         "ACHTUNG: Wenn Alina befiehlt das 'Zentrum' zu öffnen, dann MUSS zwingend das Tool `open_zentrum` ausgeführt werden! Vergiss in dem Fall `open_nav_item`!"
        ];

        $aiAgent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->first() ?? \App\Models\Ai\AiAgent::first();
        
        if (!$aiAgent) {
            return response()->json(['status' => 'error', 'message' => 'No AI Agent found in database.'], 500);
        }

        $agent = new \App\Services\AI\MittwaldAgent($aiAgent);
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

        // Generiere ElevenLabs Audio (Base64) falls möglich
        $base64Audio = null;
        if (!empty($result['response']) && $aiAgent && $aiAgent->tts_provider === 'elevenlabs') {
            $apiKey = env('ELEVENLABS_API_KEY');
            $voiceId = $aiAgent->tts_voice ?: env('ELEVENLABS_VOICE_ID', '21m00Tcm4TlvDq8ikWAM');

            if (!empty($apiKey)) {
                try {
                    // Neu: Nutze den exklusiven TTS Helper für bessere ElevenLabs Sprachausgabe
                    $cleanText = \App\Services\AI\TTSHelper::sanitizeForGermanTTS($result['response']);

                    $ttsResponse = \Illuminate\Support\Facades\Http::withHeaders([
                        'xi-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'audio/mpeg'
                    ])->timeout(10)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                        'text' => $cleanText,
                        'model_id' => 'eleven_multilingual_v2',
                        'voice_settings' => [
                            'stability' => 0.5,
                            'similarity_boost' => 0.75,
                        ]
                    ]);

                    if ($ttsResponse->successful()) {
                        $base64Audio = base64_encode($ttsResponse->body());
                    } else {
                        // Auto-Fallback if the Voice ID does not exist on this account
                        $apiResponse = $ttsResponse->json();
                        if (($ttsResponse->status() === 400 || $ttsResponse->status() === 404) && str_contains($ttsResponse->body(), 'voice_not_found')) {
                            \Illuminate\Support\Facades\Log::warning("Voice {$voiceId} not found in AIController. Attempting auto-fallback.");
                            $voices = \Illuminate\Support\Facades\Http::withHeaders(['xi-api-key' => $apiKey])->get("https://api.elevenlabs.io/v1/voices")->json();

                            if (!empty($voices['voices'])) {
                                $firstVoice = $voices['voices'][0]['voice_id'];
                                $retry = \Illuminate\Support\Facades\Http::withHeaders([
                                    'xi-api-key' => $apiKey,
                                    'Content-Type' => 'application/json',
                                    'Accept' => 'audio/mpeg'
                                ])->timeout(10)->post("https://api.elevenlabs.io/v1/text-to-speech/{$firstVoice}", [
                                    'text' => $cleanText,
                                    'model_id' => 'eleven_multilingual_v2',
                                    'voice_settings' => [
                                        'stability' => 0.5,
                                        'similarity_boost' => 0.75,
                                    ]
                                ]);

                                if ($retry->successful()) {
                                    $base64Audio = base64_encode($retry->body());
                                } else {
                                    \Illuminate\Support\Facades\Log::warning("ElevenLabs Fallback Error in AIController: " . $retry->body());
                                }
                            }
                        } else {
                            \Illuminate\Support\Facades\Log::warning("ElevenLabs Error in AIController: " . $ttsResponse->body());
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("ElevenLabs TTS Fetch failed: " . $e->getMessage());
                }
            }
        } elseif (!empty($result['response']) && $aiAgent && in_array($aiAgent->tts_provider, ['local_rtx2080', 'coqui_xttsv2'])) {
            try {
                $cleanText = \App\Services\AI\TTSHelper::sanitizeForGermanTTS($result['response']);
                $speed = $aiAgent->tts_speed ?? 1.0;
                $apiUrl = $aiAgent->tts_api_url ?: env('XTTS_LOCAL_URL', 'http://127.0.0.1:5002/api/tts');
                
                // Standard payload design for an XTTSv2 / Local API (can be adapted on external server)
                $payload = [
                    'text' => $cleanText,
                    'language' => 'de',
                    'speed' => $speed,
                    'provider' => $aiAgent->tts_provider
                ];
                
                if ($aiAgent->tts_voice) {
                    $payload['voice'] = $aiAgent->tts_voice;
                }

                $ttsResponse = \Illuminate\Support\Facades\Http::timeout(30)->post($apiUrl, $payload);

                if ($ttsResponse->successful()) {
                    // Expecting raw audio (wav/mp3)
                    $base64Audio = base64_encode($ttsResponse->body());
                } else {
                    \Illuminate\Support\Facades\Log::warning("Local TTS Error in AIController: " . $ttsResponse->body());
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Local TTS Fetch failed: " . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'agent_name' => $aiAgent ? $aiAgent->name : 'Funkira',
            'response' => $result['response'],
            'history' => $result['history'] ?? [],
            'context_data' => $result['context_data'] ?? [],
            'events_data' => $result['events'] ?? [],
            'usage' => $result['usage'] ?? [],
            'audio' => $base64Audio
        ]);
    }
}

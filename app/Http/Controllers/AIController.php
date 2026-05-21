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

        $sessionId = $request->input('chat_session_id') ?: $request->input('session_id');
        if ($sessionId) {
            config(['ai.current_session_id' => $sessionId]);
            // Only set PHP session ID if it's not a database UUID/ID to avoid corrupting native sessions
            if (strlen($sessionId) <= 40 && !str_contains($sessionId, '-')) {
                session()->setId($sessionId);
                session()->start();
            }
        }

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
            'prompt' => 'sometimes|string',
            'history' => 'sometimes|array'
        ]);

        $history = $request->input('history', []);

        // Clean history from frontend specific keys (name, color, icon, etc.) to prevent API schema validation errors
        $cleanHistory = [];
        foreach ($history as $msg) {
            $content = $msg['content'] ?? '';
            $cleanMsg = [
                'role' => $msg['role'] ?? 'user',
            ];

            // Image Extraction Support for Vision Models
            if (is_string($content) && str_contains($content, '[SYSTEM_IMAGE]: data:image/')) {
                $parts = explode('[SYSTEM_IMAGE]: ', $content);
                $textPart = trim($parts[0]);
                $imgUrl = trim($parts[1]); // This will be the data:image/... string
                
                $contentArr = [];
                if (!empty($textPart)) {
                    $contentArr[] = ['type' => 'text', 'text' => $textPart];
                }
                $contentArr[] = ['type' => 'image_url', 'image_url' => ['url' => $imgUrl]];
                $cleanMsg['content'] = $contentArr;
            } else if (is_string($content) && str_contains($content, '[SYSTEM_IMAGE_PATH]: ')) {
                $parts = explode('[SYSTEM_IMAGE_PATH]: ', $content);
                $textPart = trim($parts[0]);
                $imgPath = trim($parts[1]); 

                $contentArr = [];
                if (!empty($textPart)) {
                    $contentArr[] = ['type' => 'text', 'text' => $textPart];
                }

                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($imgPath)) {
                    $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($imgPath);
                    $base64 = base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($imgPath));
                    $dataUrl = "data:{$mimeType};base64,{$base64}";
                    $contentArr[] = ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]];
                }
                
                $cleanMsg['content'] = $contentArr;
            } else {
                $cleanMsg['content'] = $content;
            }

            if (isset($msg['tool_calls'])) {
                $cleanMsg['tool_calls'] = $msg['tool_calls'];
            }
            if (isset($msg['tool_call_id'])) {
                $cleanMsg['tool_call_id'] = $msg['tool_call_id'];
            }
            $cleanHistory[] = $cleanMsg;
        }
        $history = $cleanHistory;

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
                         "ACHTUNG: Wenn Alina befiehlt das 'Zentrum' zu öffnen, dann MUSS zwingend das Tool `open_zentrum` ausgeführt werden! Vergiss in dem Fall `open_nav_item`!\n" .
                         "WICHTIG ZUR SPRACHAUSGABE: Wenn du lange Erklärungen, Pläne oder Code schreibst, fasse das Wichtigste in ein bis zwei Sätzen zusammen und umschließe diese Zusammenfassung mit <speak>...</speak> Tags. Nur der Text innerhalb der <speak> Tags wird laut vorgelesen. Der Rest erscheint nur im Text-Log. Beispiel: `<speak>Ich habe den Plan wie gewünscht erstellt.</speak> Hier sind die Details: ...`";

        $agentId = $request->input('agent_id');
        if ($agentId) {
            $aiAgent = \App\Models\Ai\AiAgent::find($agentId);
        } else {
            $aiAgent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first() 
                ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();
        }
        
        if (!$aiAgent) {
            return response()->json(['status' => 'error', 'message' => 'No AI Agent found in database.'], 500);
        }

        $agent = \App\Services\AI\AiAgentFactory::make($aiAgent);
        
        if (method_exists($agent, 'setDynamicSystemPrompt')) {
            $agent->setDynamicSystemPrompt($dynamicSystemPrompt);
        }

        $result = $agent->ask($history);

        // Speichere finalen Dialog-Verlauf in der Datenbank
        $sessionId = $request->input('chat_session_id');
        
        if (!$sessionId) {
            $sessionId = session()->getId();
            if (auth()->check()) {
                $lastSession = \App\Models\Ai\AiChatSession::where('user_id', auth()->id())
                                 ->orderBy('updated_at', 'desc')
                                 ->first();
                if ($lastSession) {
                    $sessionId = $lastSession->id;
                } else {
                    $newSession = \App\Models\Ai\AiChatSession::create(['user_id' => auth()->id(), 'title' => 'Push-to-Talk Chat']);
                    $sessionId = $newSession->id;
                }
            }
        }

        // Was hat der User gesagt? (Finde die neuste User-Nachricht)
        $userMsg = collect($history)->reverse()->firstWhere('role', 'user');
        
        $chatSession = \App\Models\Ai\AiChatSession::find($sessionId);
        $user = $chatSession ? $chatSession->user : (auth()->user() ?? null);
        
        $userCtx = [
            'name' => $user ? ($user->first_name ?? 'User') : 'User',
            'color' => 'gray-400',
            'icon' => 'user',
            'profile_picture' => ($user && $user->profile) ? $user->profile->photo_path : null,
        ];

        if ($userMsg) {
            $dbContent = $userMsg['content'];
            if (is_array($dbContent)) {
                $textParts = [];
                foreach ($dbContent as $part) {
                    if (isset($part['type']) && $part['type'] === 'text') {
                        $textParts[] = $part['text'];
                    } else if (isset($part['type']) && $part['type'] === 'image_url') {
                        $textParts[] = "[Bild]";
                    }
                }
                $dbContent = implode("\n", $textParts);
            }
            AiChatMemory::create([
                'session_id' => $sessionId,
                'role' => 'user',
                'content' => $dbContent,
                'context_data' => $userCtx,
            ]);
        }

        $assistantCtx = [
            'name' => $aiAgent ? $aiAgent->name : 'Funkira',
            'color' => $aiAgent ? $aiAgent->color : 'emerald-500',
            'icon' => 'robot',
            'profile_picture' => $aiAgent ? $aiAgent->profile_picture : null,
        ];

        // Was hat die KI final geantwortet?
        if (!empty($result['response'])) {
            AiChatMemory::create([
                'session_id' => $sessionId,
                'role' => 'assistant',
                'content' => $result['response'],
                'context_data' => $assistantCtx,
            ]);
        }

        // Generiere Audio (Base64) falls möglich
        $base64Audio = null;
        
        $ttsProvider = $aiAgent ? $aiAgent->tts_provider : 'none';
        $ttsEnabled = $aiAgent ? (bool) $aiAgent->tts_enabled : false;

        if ($ttsProvider === 'none') {
            $ttsEnabled = false;
        }

        if ($ttsEnabled && !empty($result['response']) && $aiAgent && $ttsProvider === 'gemini_native') {
            try {
                $cleanText = $result['response'];
                
                // Extract <speak> tag content if present
                if (preg_match('/<speak>(.*?)<\/speak>/is', $cleanText, $matches)) {
                    $cleanText = trim($matches[1]);
                } else {
                    // Fallback to full text but strip markdown
                    $cleanText = strip_tags($cleanText);
                }

                $voiceName = $aiAgent->tts_voice ?: 'Puck';
                $apiKey = env('GEMINI_API_KEY');
                
                // Wir nutzen gemini-3.1-flash-tts-preview, was Audio Modalitäten nativ unterstützt.
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-tts-preview:generateContent?key=' . $apiKey;
                
                $data = [
                    "contents" => [
                        ["role" => "user", "parts" => [["text" => "Lies folgenden Text exakt so vor: " . $cleanText]]]
                    ],
                    "generationConfig" => [
                        "responseModalities" => ["AUDIO"],
                        "speechConfig" => [
                            "voiceConfig" => [
                                "prebuiltVoiceConfig" => [
                                    "voiceName" => $voiceName
                                ]
                            ]
                        ]
                    ]
                ];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                $json = json_decode($response, true);
                if (isset($json['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                    // Gemini returned base64 string directly; it is raw PCM (24kHz, mono, 16-bit little-endian)
                    $rawBase64 = $json['candidates'][0]['content']['parts'][0]['inlineData']['data'];
                    $pcmData = base64_decode($rawBase64);
                    
                    // Wrap the RAW PCM into a compliant WAV container
                    $numChannels = 1;
                    $sampleRate = 24000;
                    $bitsPerSample = 16;
                    $byteRate = $sampleRate * $numChannels * ($bitsPerSample / 8);
                    $blockAlign = $numChannels * ($bitsPerSample / 8);
                    $subchunk2Size = strlen($pcmData);
                    $chunkSize = 36 + $subchunk2Size;
                    
                    $wavHeader = pack('A4VA4A4VvvVVvvA4V',
                        'RIFF', $chunkSize, 'WAVE',
                        'fmt ', 16, 1, $numChannels, $sampleRate, $byteRate, $blockAlign, $bitsPerSample,
                        'data', $subchunk2Size
                    );
                    
                    $fullWavData = $wavHeader . $pcmData;
                    $base64Audio = base64_encode($fullWavData);
                } else {
                    \Illuminate\Support\Facades\Log::warning("Gemini Native TTS Soft-Error in AIController: " . print_r($json, true));
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Gemini Native TTS Fetch failed: " . $e->getMessage());
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

    public function saveCameraSnapshot(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,webp|max:10240' // max 10MB
        ]);

        $file = $request->file('image');

        $dir = 'agenten/workspace/Kamera-Snapshots';
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($dir)) {
            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($dir);
        }

        $filename = 'snapshot_' . date('Y-m-d_H-i-s') . '.jpg';
        $path = $file->storeAs($dir, $filename, 'public');
        
        return response()->json([
            'status' => 'success',
            'file_path' => $path,
            'url' => \Illuminate\Support\Facades\Storage::url($path)
        ]);
    }

    /**
     * Endpoint for Multimodal Live API Mode.
     * Returns the API key and system instructions securely to authenticated admins.
     */
    public function liveCredentials(Request $request)
    {
        if (!auth()->check() && !auth('sanctum')->check() && !auth('admin')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $aiAgent = null;
        if ($request->has('agent_id') && !empty($request->agent_id)) {
            $aiAgent = \App\Models\Ai\AiAgent::find($request->agent_id);
        }
        if (!$aiAgent && auth('admin')->check()) {
            $aiAgent = auth('admin')->user()->ai_agent;
        }
        if (!$aiAgent) {
            $aiAgent = \App\Models\Ai\AiAgent::first();
        }
        
        $voiceName = $aiAgent ? $aiAgent->tts_voice : 'Puck';
        $agentName = $aiAgent ? $aiAgent->name : 'Funkira';
        
        $payload = [];
        $systemInstruction = "";
        if ($aiAgent) {
            $payload = \App\Services\AI\AiAgentService::getAgentPayload($aiAgent);
            $systemInstruction = $payload['system_prompt'] ?? '';
        } else {
            $systemInstruction = 'Du bist Funkira, die KI-Assistentin des Seelenfunke Dashboards. Antworte immer kurz, präzise und freundlich.';
        }

        $map = \App\Services\AI\AIFunctionsRegistry::getAdminNavigationMap();

        $navMap = "Verfügbare Admin-Routen:\n";
        foreach ($map as $path => $name) {
            $navMap .= "- " . $name . " => " . $path . "\n";
        }

        $systemInstruction = "System-Prompt:\n" . $systemInstruction . "\n\nDu bist " . $agentName . ". " .
            "Nutze die Tools, um Daten abzufragen oder Aktionen auszuführen. " .
            "WICHTIG ZUR NAVIGATION: Wenn du das Tool `open_nav_item` einsetzt, wähle IMMER nur eine exakte Route aus dieser Liste: \n" . $navMap;

        // Historie anhängen, damit der Live Modus sich bei einem Neustart erinnert
        $sessionId = $request->input('chat_session_id');
        if (!$sessionId) {
            $sessionId = auth()->check() ? 'user_' . auth()->id() : session()->getId();
        }
        
        if ($sessionId) {
            $history = \App\Models\Ai\AiChatMemory::where('session_id', $sessionId)->orderBy('created_at', 'desc')->take(15)->get()->reverse();
            if ($history->count() > 0) {
                $historyText = "\n\n--- BISHERIGER CHAT-VERLAUF DIESER SESSION (ZUR ERINNERUNG) ---\n";
                foreach ($history as $msg) {
                    $roleName = strtoupper($msg->role);
                    $historyText .= "[{$roleName}]: " . $msg->content . "\n";
                }
                $systemInstruction .= $historyText . "\n--- ENDE CHAT-VERLAUF ---\nSetze das Gespräch natürlich fort.";
            }
        }

        // Retrieve the API Key
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return response()->json(['error' => 'No Gemini API Key configured.'], 500);
        }

        if ($aiAgent && array_key_exists('tools', $payload)) {
            $schemaTools = $payload['tools'] ?: [];
        } else {
            $schemaTools = \App\Services\AI\AIFunctionsRegistry::getSchema();
        }
        
        $removeAdditionalProperties = function ($obj) use (&$removeAdditionalProperties) {
            if (!is_object($obj) && !is_array($obj)) return;
            
            foreach ($obj as $key => $value) {
                if (is_object($value) || is_array($value)) {
                    $removeAdditionalProperties($value);
                }
            }
            if (is_object($obj)) {
                if (property_exists($obj, 'additionalProperties')) {
                    unset($obj->additionalProperties);
                }
                if (property_exists($obj, 'type') && is_string($obj->type)) {
                    $obj->type = strtoupper($obj->type);
                }
            } elseif (is_array($obj)) {
                if (array_key_exists('additionalProperties', $obj)) {
                    unset($obj['additionalProperties']);
                }
                if (array_key_exists('type', $obj) && is_string($obj['type'])) {
                    $obj['type'] = strtoupper($obj['type']);
                }
            }
        };

        $functionDeclarations = [];
        foreach ($schemaTools as $tool) {
            if (isset($tool['function'])) {
                // Decode without 'true' to preserve empty objects as {}
                $func = json_decode(json_encode($tool['function']));
                $removeAdditionalProperties($func);
                $functionDeclarations[] = $func;
            }
        }

        $token = \Illuminate\Support\Str::random(40);
        
        // Cache credentials for 5 minutes
        \Illuminate\Support\Facades\Cache::put('gemini_live_token_' . $token, [
            'api_key' => $apiKey,
            'system_instruction' => $systemInstruction,
            'voice_name' => $voiceName,
            'tools' => [['functionDeclarations' => $functionDeclarations]],
        ], now()->addMinutes(5));

        return response()->json([
            'token' => $token,
            'ws_url' => config('services.gemini.proxy_ws_url') ?: 'ws://' . request()->getHost() . ':8089/gemini-live',
            'system_instruction' => $systemInstruction,
            'voice_name' => $voiceName,
            'tools' => [['functionDeclarations' => $functionDeclarations]],
        ]);
    }

    /**
     * Verifies the websocket token and returns the actual credentials.
     * Only accessed internally by the Node.js server.
     */
    public function verifyToken(Request $request)
    {
        $token = $request->input('token');
        if (empty($token)) {
            return response()->json(['error' => 'Token missing'], 400);
        }

        $cacheKey = 'gemini_live_token_' . $token;
        $data = \Illuminate\Support\Facades\Cache::pull($cacheKey);

        if (!$data) {
            return response()->json(['error' => 'Invalid or expired token'], 403);
        }

        return response()->json($data);
    }
}

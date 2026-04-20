<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use App\Services\AI\GeminiAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramAgentController extends Controller
{
    /**
     * Handle incoming webhooks from Telegram for a specific agent.
     */
    public function handleWebhook(Request $request, $token)
    {
        // 1. Identify the Agent by Token
        $agent = AiAgent::where('telegram_bot_token', $token)->where('is_active', true)->first();

        if (!$agent) {
            Log::warning("Telegram Webhook hit with invalid or inactive token: " . $token);
            return response()->json(['status' => 'error', 'message' => 'Agent not found'], 404);
        }

        // 2. Parse Telegram Payload
        $update = $request->all();
        
        $message = $update['message'] ?? null;
        if (!$message || !isset($message['chat']['id'])) {
            return response()->json(['status' => 'ignored', 'message' => 'No chat info.']);
        }
        
        $chatId = $message['chat']['id'];
        $firstName = $message['from']['first_name'] ?? 'User';
        $text = $message['text'] ?? '';
        
        $localUploads = [];
        $userSentVoice = false;

        // Process attachments (Photo, Document, Voice)
        try {
            if (isset($message['photo'])) {
                // Get highest resolution photo (last in array)
                $photo = end($message['photo']);
                $localUploads[] = $this->processTelegramFile($token, $photo['file_id'], 'image/jpeg');
            } elseif (isset($message['document'])) {
                $localUploads[] = $this->processTelegramFile($token, $message['document']['file_id'], $message['document']['mime_type'] ?? 'application/octet-stream');
            } elseif (isset($message['voice']) || isset($message['audio'])) {
                $userSentVoice = true;
                $audioObj = $message['voice'] ?? $message['audio'];
                $audioFile = $this->processTelegramFile($token, $audioObj['file_id'], $audioObj['mime_type'] ?? 'audio/ogg');
                $localUploads[] = $audioFile;
                
                // STT: Transcribe Audio using Gemini API natively if no text is provided
                if (empty($text)) {
                    $text = $this->transcribeAudioGemini($audioFile['path'], $audioFile['mime']);
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to process Telegram attachment", ['error' => $e->getMessage()]);
            $this->sendTelegramMessage($token, $chatId, "⚠️ *Fehler beim Laden der Datei:* " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Attachment failed']);
        }

        // If after trying to extract text and files we have neither...
        if (empty(trim($text)) && empty($localUploads)) {
            return response()->json(['status' => 'ignored', 'message' => 'No valid text or supported attachment.']);
        }

        // Support /start command simply as a greeting
        if ($text === '/start') {
            $text = "Hallo! Wer bist du und was kannst du?";
        }

        // 2.5 Security: Check Whitelist (Zero Trust by Default)
        $allowedIds = $agent->telegram_allowed_chat_ids ?? [];
        $isPublic = in_array('*', $allowedIds, true);
        
        if (!$isPublic && !in_array((string)$chatId, $allowedIds, true)) {
            Log::warning("Unauthorized Telegram Access Attempt", ['chat_id' => $chatId, 'agent' => $agent->name]);
            $rejectMessage = "⛔️ *Sicherheitsbereich*\n\nDu bist nicht berechtigt, mit diesem Agenten zu kommunizieren.\n\n_System-Log:_ Deine Telegram-ID lautet `" . $chatId . "`.\nBitte informiere den System-Administrator, damit er diese ID in die Agenten-Whitelist aufnehmen kann.";
            $this->sendTelegramMessage($token, $chatId, $rejectMessage);
            return response()->json(['status' => 'unauthorized', 'message' => 'Chat ID not whitelisted']);
        }

        // 3. Map the Session ID for Long-term Memory
        // Instead of the browser's session()->getId(), we use the Telegram Chat ID.
        // This ensures the agent remembers the context for this specific Telegram user indefinitely!
        $sessionId = 'telegram_chat_' . $chatId;

        // Ensure we temporarily bind this session ID so underlying services (if they use session()->getId() dynamically) 
        // fallback to this one. (AiChatMemory uses raw session ID. We should pass it or mock the session).
        // A cleaner way is just to manually create the memory record and load history.
        
        try {
            $userCtx = [
                'name' => 'Telegram User',
                'color' => 'gray-400',
                'icon' => 'user'
            ];
            
            if (!empty($localUploads)) {
                $userCtx['local_uploads'] = $localUploads;
            }

            // Save User Message to Memory
            AiChatMemory::create([
                'session_id' => $sessionId,
                'role' => 'user',
                'content' => $text,
                'context_data' => $userCtx,
            ]);

            // Load History for this specific Telegram Chat
            $history = AiChatMemory::where('session_id', $sessionId)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($mem) {
                    $m = [
                        'role' => $mem->role,
                        'content' => $mem->content
                    ];
                    // Optional: tool context
                    if ($mem->role === 'tool' && !empty($mem->context_data)) {
                        $m['content'] = json_encode($mem->context_data, JSON_UNESCAPED_UNICODE);
                    }
                    return $m;
                })
                ->toArray();

            // Limit history to last 50 messages to prevent token limits
            if (count($history) > 50) {
                $history = array_slice($history, -50);
            }

            // 4. Initialize the Agent Brain
            $brain = new GeminiAgent($agent);
            
            // Add a dynamic prompt to clarify we are in Telegram
            $brain->setDynamicSystemPrompt("WICHTIG: Du chattest aktuell über die Telegram Messenger API mit einem Nutzer namens '{$firstName}'. Nutze Emojis und halte Antworten kompakt wie in einem Messenger. Keine Markdown-Tabellen, da Telegram diese nicht perfekt rendert. Du darfst und sollst System-Werkzeuge ausführen, das Resultat wird dem Nutzer per Telegram geschickt.");

            // Wir können noch einen temporären Session-Mock bauen, falls interne Tools session()->getId() brauchen.
            session()->setId($sessionId);
            session()->start();

            // 5. Ask the AI (Executes Loop & Tool Calling automatically!)
            $responsePayload = $brain->ask($history);
            $textResponse = $responsePayload['response'] ?? 'Ich konnte keine Antwort formulieren.';

            // Save AI Response to Memory
            AiChatMemory::create([
                'session_id' => $sessionId,
                'role' => 'assistant',
                'content' => $textResponse,
            ]);

            // 6. Send Response back via Telegram HTTP API
            
            $ttsGenerated = false;
            // Falls der User eine Voice-Nachricht geschickt hat und der Agent TTS aktiviert hat -> Antworte per Voice
            if ($userSentVoice && $agent->tts_enabled && $agent->tts_provider && $agent->tts_provider !== 'none') {
                try {
                    $audioPath = $this->generateTtsAudio($agent, $textResponse);
                    if ($audioPath) {
                        $this->sendTelegramVoice($token, $chatId, $audioPath);
                        $ttsGenerated = true;
                    }
                } catch (\Exception $e) {
                    Log::error("TTS Response for Telegram failed", ['error' => $e->getMessage()]);
                    // Fallback to text
                }
            }

            // Fallback: If TTS wasn't generated/failed, just send text
            if (!$ttsGenerated) {
                $this->sendTelegramMessage($token, $chatId, $textResponse);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error("Telegram Agent Loop Failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->sendTelegramMessage($token, $chatId, "⚠️ *System Alert:* Meine kognitiven Routinen sind abgestürzt. \n\nFehler: " . substr($e->getMessage(), 0, 100));
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Splits long messages if needed and sends them via Telegram.
     */
    private function sendTelegramMessage($token, $chatId, $text)
    {
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        // Telegram limit is 4096 chars.
        $chunks = str_split($text, 4000);

        foreach ($chunks as $chunk) {
            Http::post($url, [
                'chat_id' => $chatId,
                'text' => $chunk,
                'parse_mode' => 'Markdown', // Allow basic bold/italics
            ]);
        }
    }

    /**
     * Sends an audio file back to Telegram as an authentic Voice Message
     */
    private function sendTelegramVoice($token, $chatId, $audioPath)
    {
        $url = "https://api.telegram.org/bot{$token}/sendVoice";
        
        $response = Http::attach(
            'voice', file_get_contents($audioPath), basename($audioPath)
        )->post($url, [
            'chat_id' => $chatId,
        ]);
        
        if (!$response->successful()) {
            throw new \Exception("Telegram sendVoice failed: " . $response->body());
        }
        
        // Clean up tmp file
        @unlink($audioPath);
    }

    /**
     * Downloads a file from Telegram and saves it to local temporary uploads.
     */
    private function processTelegramFile($token, $fileId, $mimeType)
    {
        // 1. Get File Path
        $url = "https://api.telegram.org/bot{$token}/getFile?file_id={$fileId}";
        $response = Http::get($url);
        if (!$response->successful() || !$response->json('ok')) {
             throw new \Exception("Could not get file info from Telegram API.");
        }
        
        $filePath = $response->json('result.file_path');
        
        // 2. Download File
        $downloadUrl = "https://api.telegram.org/file/bot{$token}/{$filePath}";
        $fileContent = Http::get($downloadUrl)->body();
        
        // 3. Save to Public Storage mimicking Standard UI Uploads
        // Extract extension from file_path
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $filename = 'telegram_' . uniqid() . '.' . ($ext ?: 'bin');
        $savePath = 'telegram_uploads/' . $filename;
        
        \Illuminate\Support\Facades\Storage::disk('public')->put($savePath, $fileContent);
        
        return [
            'path' => $savePath,
            'name' => basename($filePath),
            'mime' => $mimeType
        ];
    }

    /**
     * Uses Gemini to perform Native STT on voice messages.
     */
    private function transcribeAudioGemini($relativePath, $mimeType)
    {
        $fullPath = storage_path('app/public/' . $relativePath);
        if (!file_exists($fullPath)) return "";
        
        $base64 = base64_encode(file_get_contents($fullPath));
        
        $baseUrl = config('services.gemini.url') ?: 'https://generativelanguage.googleapis.com/v1beta/openai/';
        $apiKey = config('services.gemini.key');
        
        // Use the native Gemini endpoint since OpenAI wrapper doesn't support ogg audio inline yet
        // Extract base URI
        $nativeUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
        
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => "Transkribiere bitte diese Sprachnachricht. Antworte AUSSCHLIESSLICH mit dem exakten, abgetippten Text des Nutzers, ohne Kommentare oder Zusätze."],
                        ["inlineData" => [
                            "mimeType" => $mimeType,
                            "data" => $base64
                        ]]
                    ]
                ]
            ]
        ];
        
        $response = Http::timeout(30)->post($nativeUrl, $payload);
        if ($response->successful()) {
            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? "";
        }
        
        Log::warning("Gemini STT Failed: " . $response->body());
        return "";
    }

    /**
     * Generates a TTS Audio File locally or via external provider
     */
    private function generateTtsAudio($agent, $text)
    {
        if ($agent->tts_provider === 'toni_xttsv2') {
            $ttsUrl = $agent->tts_api_url ?: 'http://127.0.0.1:8020';
            $voice = $agent->tts_voice ?: 'tania';
            
            // Assuming Toni provides a /tts or /api/tts endpoint generating a Wave file
            $endpoint = rtrim($ttsUrl, '/') . '/api/tts';
            
            $response = Http::timeout(60)->get($endpoint, [
                'text' => strip_tags($text),
                'language' => 'de',
                'speaker' => $voice
            ]);
            
            if ($response->successful()) {
                $tmpPath = sys_get_temp_dir() . '/tts_' . uniqid() . '.wav';
                file_put_contents($tmpPath, $response->body());
                return $tmpPath;
            }
        }
        
        // Native Gemini TTS Fallback (if applicable via their new endpoints, though Gemini text-to-speech is still experimental/undocumented in many Wrappers)
        // For now, if no Toni, we throw
        throw new \Exception("Unsupported or failing TTS Provider.");
    }
}

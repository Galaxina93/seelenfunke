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
        
        // Telegram can send various updates (edited_message, inline_query, etc). We only care about standard text messages.
        if (!isset($update['message']['text']) || !isset($update['message']['chat']['id'])) {
            return response()->json(['status' => 'ignored', 'message' => 'Not a text message.']);
        }

        $chatId = $update['message']['chat']['id'];
        $text = $update['message']['text'];
        $firstName = $update['message']['from']['first_name'] ?? 'User';

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
            // Save User Message to Memory
            AiChatMemory::create([
                'session_id' => $sessionId,
                'role' => 'user',
                'content' => $text,
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
            $this->sendTelegramMessage($token, $chatId, $textResponse);

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
}

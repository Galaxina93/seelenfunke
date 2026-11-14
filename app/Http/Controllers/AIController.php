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
        $tools = AIFunctionsRegistry::getSchema();
        if ((\App\Services\AI\AiAuthHelper::check() || \App\Services\AI\AiAuthHelper::isExternalPartner() || \Illuminate\Support\Facades\Auth::guard('employee')->check()) && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            $isInternalEmployee = \App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isExternalPartner();
            $tools = array_values(array_filter($tools, function ($tool) use ($isInternalEmployee) {
                $name = $tool['function']['name'] ?? '';
                if ($name === '') {
                    return false;
                }
                if (!$isInternalEmployee) {
                    return str_starts_with($name, 'support_') || $name === 'system_get_current_time';
                }
                return !AIFunctionsRegistry::isToolRestrictedForEmployee($name);
            }));
        }

        return response()->json([
            'status' => 'success',
            'tools' => $tools,
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
        $isAdmin = \App\Services\AI\AiAuthHelper::isAdmin();

        if (!$isAdmin) {
            $isInternalEmployee = \App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isExternalPartner();
            if (!$isInternalEmployee) {
                $isPublicAllowed = str_starts_with((string) $functionName, 'support_') || $functionName === 'system_get_current_time';
                if (!$isPublicAllowed) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Zugriff verweigert: Werkzeug '{$functionName}' ist für externe Rollen gesperrt.",
                    ], 403);
                }
            } elseif (AIFunctionsRegistry::isToolRestrictedForEmployee((string) $functionName)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Zugriff verweigert: Werkzeug '{$functionName}' erfordert Administrator-Rechte.",
                ], 403);
            }
        }

        $sessionId = $request->input('chat_session_id') ?: $request->input('session_id');
        $currentUserId = \App\Services\AI\AiAuthHelper::getUserId();
        if ($sessionId && !$isAdmin) {
            $ownedSession = $currentUserId && \App\Models\Ai\AiChatSession::where('id', $sessionId)
                ->where('user_id', $currentUserId)
                ->exists();
            if (!$ownedSession) {
                $sessionId = null;
            }
        }
        if (!$sessionId && \App\Services\AI\AiAuthHelper::check()) {
            $userId = $currentUserId;
            $latestSession = \App\Models\Ai\AiChatSession::where('user_id', $userId)
                ->where('is_archived', false)
                ->orderBy('updated_at', 'desc')
                ->first();
            if ($latestSession) {
                $sessionId = $latestSession->id;
            }
        }
        if ($sessionId) {
            config(['ai.current_session_id' => $sessionId]);
        }

        $agentId = $request->input('agent_id');
        $agent = null;
        if ($agentId) {
            $agent = \App\Models\Ai\AiAgent::where('is_active', true)->find($agentId);
            if ($agent && !$isAdmin && $this->isAdminOnlyAgent($agent)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Zugriff verweigert: Die Admin-KI Funkira ist ausschließlich für Administratoren freigegeben.',
                ], 403);
            }
        }
        if (!$agent && \App\Services\AI\AiAuthHelper::check()) {
            $user = \App\Services\AI\AiAuthHelper::getUser();
            $candidateAgent = $user?->ai_agent;
            if ($candidateAgent && $candidateAgent->is_active && ($isAdmin || !$this->isAdminOnlyAgent($candidateAgent))) {
                $agent = $candidateAgent;
            }
        }
        if (!$agent) {
            if ($isAdmin) {
                $agent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first()
                    ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();
            } else {
                $agent = \App\Models\Ai\AiAgent::where('name', 'Funki')->where('is_active', true)->first()
                    ?? \App\Models\Ai\AiAgent::where('is_active', true)->whereRaw('LOWER(name) != ?', ['funkira'])->first();
            }
        }

        try {
            // Forward execution to the registry
            $result = AIFunctionsRegistry::execute($functionName, $args, $agent);

            // Persist tool execution into chat memory if session is active
            if ($sessionId && class_exists(\App\Models\Ai\AiChatMemory::class)) {
                try {
                    \App\Models\Ai\AiChatMemory::create([
                        'session_id' => $sessionId,
                        'role' => 'tool',
                        'content' => 'Werkzeug: ' . $functionName,
                        'context_data' => [
                            'name' => 'System Execution',
                            'tool_name' => $functionName,
                            'args' => $args,
                            'result' => $result
                        ]
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Could not persist tool memory in live execution: " . $e->getMessage());
                }
            }

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
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        // Clean history from frontend specific keys (name, color, icon, etc.) to prevent API schema validation errors
        $isAdmin = \App\Services\AI\AiAuthHelper::isAdmin();
        $allowedRoles = $isAdmin ? ['user', 'assistant', 'system', 'tool'] : ['user', 'assistant', 'tool'];
        $cleanHistory = [];
        foreach ($history as $msg) {
            $content = $msg['content'] ?? '';
            $rawRole = strtolower(trim((string) ($msg['role'] ?? 'user')));
            $cleanMsg = [
                'role' => in_array($rawRole, $allowedRoles, true) ? $rawRole : 'user',
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
                if (str_starts_with($imgUrl, 'data:image/')) {
                    $contentArr[] = ['type' => 'image_url', 'image_url' => ['url' => $imgUrl]];
                }
                $cleanMsg['content'] = $contentArr;
            } else if (is_string($content) && str_contains($content, '[SYSTEM_IMAGE_PATH]: ')) {
                $parts = explode('[SYSTEM_IMAGE_PATH]: ', $content);
                $textPart = trim($parts[0]);
                $imgPath = ltrim(str_replace('\\', '/', trim($parts[1] ?? '')), '/');

                $contentArr = [];
                if (!empty($textPart)) {
                    $contentArr[] = ['type' => 'text', 'text' => $textPart];
                }

                $ext = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION));
                $isSafeImagePath = $imgPath !== ''
                    && !str_contains($imgPath, '..')
                    && !str_contains($imgPath, "\0")
                    && str_starts_with($imgPath, 'agenten/workspace/')
                    && in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);

                if ($isSafeImagePath) {
                    $imgDisk = null;
                    if (\Illuminate\Support\Facades\Storage::disk('workspace')->exists($imgPath)) {
                        $imgDisk = 'workspace';
                    } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($imgPath)) {
                        $imgDisk = 'public';
                    }
                    if ($imgDisk !== null) {
                        $mimeType = \Illuminate\Support\Facades\Storage::disk($imgDisk)->mimeType($imgPath);
                        if (is_string($mimeType) && str_starts_with($mimeType, 'image/')) {
                            $base64 = base64_encode(\Illuminate\Support\Facades\Storage::disk($imgDisk)->get($imgPath));
                            $dataUrl = "data:{$mimeType};base64,{$base64}";
                            $contentArr[] = ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]];
                        }
                    }
                }
                
                $cleanMsg['content'] = !empty($contentArr) ? $contentArr : $textPart;
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
            $aiAgent = \App\Models\Ai\AiAgent::where('is_active', true)->find($agentId);
            if ($aiAgent && !$isAdmin && $this->isAdminOnlyAgent($aiAgent)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Zugriff verweigert: Die Admin-KI Funkira ist ausschließlich für Administratoren freigegeben.',
                ], 403);
            }
        } else {
            if ($isAdmin) {
                $aiAgent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first()
                    ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();
            } else {
                $aiAgent = \App\Models\Ai\AiAgent::where('name', 'Funki')->where('is_active', true)->first()
                    ?? \App\Models\Ai\AiAgent::where('is_active', true)->whereRaw('LOWER(name) != ?', ['funkira'])->first();
            }
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
        $currentUserId = \App\Services\AI\AiAuthHelper::getUserId();

        if ($sessionId && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            $ownedSession = $currentUserId && \App\Models\Ai\AiChatSession::where('id', $sessionId)
                ->where('user_id', $currentUserId)
                ->exists();
            if (!$ownedSession) {
                $sessionId = null;
            }
        }
        
        if (!$sessionId) {
            $sessionId = session()->getId();
            if ($currentUserId) {
                $lastSession = \App\Models\Ai\AiChatSession::where('user_id', $currentUserId)
                                 ->orderBy('updated_at', 'desc')
                                 ->first();
                if ($lastSession) {
                    $sessionId = $lastSession->id;
                } else {
                    $newSession = \App\Models\Ai\AiChatSession::create(['user_id' => $currentUserId, 'title' => 'Push-to-Talk Chat']);
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

        if ($ttsEnabled && !empty($result['response_raw'] ?? $result['response']) && $aiAgent && $ttsProvider === 'gemini_native') {
            try {
                $cleanText = (string) ($result['response_raw'] ?? $result['response']);

                // Extract <speak> tag content if present
                if (preg_match('/<speak>(.*?)<\/speak>/is', $cleanText, $matches)) {
                    $cleanText = trim($matches[1]);
                } else {
                    // Fallback to full text but strip markdown/HTML tags
                    $cleanText = strip_tags($cleanText);
                }

                $voiceName = $aiAgent->tts_voice ?: 'Puck';
                $synthesizedWav = \App\Services\AI\GeminiTtsService::synthesizeWav($cleanText, $voiceName);
                if (is_string($synthesizedWav) && $synthesizedWav !== '') {
                    $base64Audio = $synthesizedWav;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Gemini Native TTS Fetch failed: " . \App\Services\System\SystemErrorLogger::sanitizeSensitiveText($e->getMessage()));
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
        if (!\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee() && !auth('admin')->check()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,webp|max:10240' // max 10MB
        ]);

        $file = $request->file('image');

        $dir = 'agenten/workspace/Dokumente/Snapshots';
        if (!\Illuminate\Support\Facades\Storage::disk('workspace')->exists($dir)) {
            \Illuminate\Support\Facades\Storage::disk('workspace')->makeDirectory($dir);
        }

        $filename = 'snapshot_' . date('Y-m-d_H-i-s') . '_' . \Illuminate\Support\Str::random(8) . '.jpg';
        $path = $file->storeAs($dir, $filename, 'workspace');

        return response()->json([
            'status' => 'success',
            'file_path' => $path,
            'url' => '/api/funki/files/download?path=' . rawurlencode($path),
        ]);
    }

    /**
     * Endpoint for Multimodal Live API Mode.
     * Returns the API key and system instructions securely to authenticated admins.
     */
    public function liveCredentials(Request $request)
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            $status = (\App\Services\AI\AiAuthHelper::check() || \App\Services\AI\AiAuthHelper::isExternalPartner() || auth('sanctum')->check() || auth('employee')->check()) ? 403 : 401;
            return response()->json(['error' => 'Unauthorized'], $status);
        }

        $isAdminUser = \App\Services\AI\AiAuthHelper::isAdmin();
        $aiAgent = null;
        if ($request->has('agent_id') && !empty($request->agent_id)) {
            $aiAgent = \App\Models\Ai\AiAgent::where('is_active', true)->find($request->agent_id);
            if ($aiAgent && !$isAdminUser && $this->isAdminOnlyAgent($aiAgent)) {
                return response()->json([
                    'status' => 'error',
                    'error' => 'Forbidden',
                    'message' => 'Zugriff verweigert: Die Admin-KI Funkira ist ausschließlich für Administratoren freigegeben.',
                ], 403);
            }
        }
        if (!$aiAgent && $isAdminUser) {
            $user = auth()->user() ?: auth('admin')->user();
            $candidateAgent = $user ? $user->ai_agent : null;
            if ($candidateAgent && $candidateAgent->is_active) {
                $aiAgent = $candidateAgent;
            }
        }
        if (!$aiAgent) {
            if ($isAdminUser) {
                $aiAgent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first()
                    ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();
            } else {
                $aiAgent = \App\Models\Ai\AiAgent::where('name', 'Funki')->where('is_active', true)->first()
                    ?? \App\Models\Ai\AiAgent::where('is_active', true)->whereRaw('LOWER(name) != ?', ['funkira'])->first();
            }
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

        $userLabel = $isAdminUser ? 'der Nutzerin (Alina)' : 'dem Mitarbeiter / Nutzer';
        $systemInstruction = "STRIKTE SPRACHREGEL & OPERATIVES VERHALTEN (HÖCHSTE PRIORITÄT):\n" .
            "- Du sprichst und antwortest ZU 100% AUSSCHLIESSLICH AUF DEUTSCH mit {$userLabel}.\n" .
            "- Gib NIEMALS englische Sätze, Systemmeldungen, Statusbestätigungen oder Einleitungen aus (z.B. STRENG VERBOTEN: 'Confirming Operational Context', 'I acknowledge...', 'Understood').\n" .
            "- Bestätige NIEMALS deine Anweisungen, Prompt oder Systemrolle! Fange sofort direkt und freundlich auf Deutsch an zu sprechen.\n\n" .
            "System-Prompt:\n" . $systemInstruction . "\n\nDu bist " . $agentName . ".\n" .
            "WICHTIG FÜR DEN ECHTZEIT-SPRACHMODUS (wie in der mobilen Google Gemini App):\n" .
            "- Du führst ein flüssiges, direktes Sprachgespräch in Echtzeit.\n" .
            "- Antworte extrem schnell, direkt und prägnant (meist 1 bis 3 kurze Sätze auf den Punkt).\n" .
            "- Verwende in deiner Sprachantwort NIEMALS Markdown, Sternchen, Tabellen oder Listenpunkte.\n" .
            "- Rufe Werkzeuge nur auf, wenn der Benutzer dich explizit um eine konkrete Aktion bittet.\n\n" .
            "WICHTIG ZU HINTERGRUND-AUFGABEN & MASSEN-AKTIONEN:\n" .
            "- Wenn der Benutzer dir zeitaufwändige Aufgaben, Massen-Operationen oder Hintergrundjobs erteilt, bestätige dem Nutzer SOFORT in 1 kurzen Satz, dass die Aufgabe im Hintergrund läuft.\n\n" .
            "WICHTIG ZUR NAVIGATION: Wenn du das Tool `open_nav_item` einsetzt, wähle IMMER nur eine exakte Route aus dieser Liste: \n" . $navMap . "\n\n" .
            "BUCHHALTUNGS-REGEL FÜR AUSGABEN (HÖCHSTE PRIORITÄT):\n" .
            "- Alle Sonderausgaben, Kosten, Belege und Rechnungen (z.B. Zahnarzt, Material, Tanken, Software etc.) sind im Buchhaltungs-System ZWINGEND als NEGATIVER Betrag mit Minuszeichen (z.B. -110.32) zu verbuchen.\n" .
            "- Positive Beträge gelten im System als Einnahmen. Eine Ausgabe MUSS daher stets als Minusbetrag an `finance_create_expense` übergeben werden!\n\n" .
            "STRIKTES GEBOT: Antworte und sprich AUSSCHLIESSLICH auf Deutsch!";

        // Historie anhängen, damit der Live Modus sich bei einem Neustart erinnert
        $sessionId = $request->input('chat_session_id');
        $currentUserId = \App\Services\AI\AiAuthHelper::getUserId();
        if ($sessionId && !$isAdminUser) {
            $ownedSession = $currentUserId && \App\Models\Ai\AiChatSession::where('id', $sessionId)
                ->where('user_id', $currentUserId)
                ->exists();
            if (!$ownedSession) {
                $sessionId = null;
            }
        }
        if (!$sessionId && \App\Services\AI\AiAuthHelper::check()) {
            $userId = $currentUserId;
            $latestSession = \App\Models\Ai\AiChatSession::where('user_id', $userId)
                ->where('is_archived', false)
                ->orderBy('updated_at', 'desc')
                ->first();
            if ($latestSession) {
                $sessionId = $latestSession->id;
            }
        }
        if (!$sessionId) {
            $sessionId = session()->getId();
        }
        
        if ($sessionId) {
            $history = \App\Models\Ai\AiChatMemory::where('session_id', $sessionId)->orderBy('created_at', 'desc')->take(20)->get()->reverse();
            if ($history->count() > 0) {
                $historyText = "\n\n--- BISHERIGER CHAT-VERLAUF DIESER SESSION (ZUR ERINNERUNG) ---\n";
                foreach ($history as $msg) {
                    $roleName = strtoupper($msg->role);
                    $authorName = $msg->context_data['name'] ?? ucfirst($msg->role);
                    $historyText .= "[{$roleName} / {$authorName}]: " . $msg->content . "\n";
                }
                $systemInstruction .= $historyText . "\n--- ENDE CHAT-VERLAUF ---\nSetze das Gespräch natürlich fort. Beziehe dich bei Fragen zum Chatverlauf auf diese Einträge.";
            }
        }

        // Retrieve the API Key
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            \App\Services\System\SystemErrorLogger::recordCrossPlatformEvent([
                'platform' => 'portal',
                'severity' => 'error',
                'component' => 'ai_live_credentials',
                'title' => 'Gemini Live API-Key fehlt in der Konfiguration',
                'message' => 'Aufruf von /api/ai/live-credentials schlug fehl, da config(services.gemini.key) leer ist.',
                'context' => ['env' => (string) config('app.env')],
            ]);
            return response()->json(['error' => 'No Gemini API Key configured.'], 500, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        }

        $isFunkiraAgent = $aiAgent && (
            method_exists(\App\Services\AI\FunkiraSkillPoolService::class, 'isFunkira')
                ? \App\Services\AI\FunkiraSkillPoolService::isFunkira($aiAgent)
                : strcasecmp((string) ($aiAgent->name ?? ''), 'Funkira') === 0
        );

        if ($aiAgent && array_key_exists('tools', $payload) && !$isFunkiraAgent) {
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

        // Für den Echtzeit-Sprachmodus (Gemini Live) optimieren wir die Tools auf die hochrelevanten Sprach- und Interaktions-Funktionen,
        // um Token-Overhead und Inferenz-Latenzen auf TPU-Seite minimal zu halten (Reaktionszeit wie in der mobilen Gemini-App).
        $voiceAllowedTools = [
            'system_open_nav_item', 'system_open_zentrum', 'system_close_zentrum', 'system_close_ui', 
            'system_trigger_ui_element', 'system_search_web', 'system_read_web_url',
            'task_get_all', 'task_get_lists', 'task_create', 'task_add_subtask', 'task_update', 'task_complete',
        ];
        if ($isAdminUser) {
            $voiceAllowedTools = array_merge($voiceAllowedTools, [
                'system_get_health',
                'calendar_get_events', 'calendar_create_event', 'calendar_send_ics_email', 'calendar_update_event', 'calendar_delete_event',
                'routine_get_day_routines', 'routine_get_evaluation', 'routine_log_progress', 'routine_log_step', 'routine_set_sick_day',
                'routine_create', 'routine_update', 'routine_delete', 'routine_manage_steps',
                'email_send_message', 'mail_list_pending', 'mail_read',
                'contact_search', 'contact_get_all',
                'finance_get_liquidity_status', 'finance_get_fixed_costs', 'finance_create_expense',
                'order_get_current_express_status', 'order_get_urgent_tasks', 'order_get_details',
                'product_check_inventory', 'brain_search',
            ]);
        }
        $filteredDeclarations = [];
        foreach ($functionDeclarations as $decl) {
            if (in_array($decl->name, $voiceAllowedTools, true)) {
                $filteredDeclarations[] = $decl;
            }
        }
        if (!empty($filteredDeclarations)) {
            $functionDeclarations = $filteredDeclarations;
        } else {
            $functionDeclarations = array_slice($functionDeclarations, 0, 20);
        }

        $token = \Illuminate\Support\Str::random(40);
        
        $user = auth()->user() ?: auth('admin')->user() ?: \App\Services\AI\AiAuthHelper::getUser();
        
        // Cache credentials for 5 minutes
        \Illuminate\Support\Facades\Cache::put('gemini_live_token_' . $token, [
            'api_key' => $apiKey,
            'system_instruction' => $systemInstruction,
            'voice_name' => $voiceName,
            'tools' => [['functionDeclarations' => $functionDeclarations]],
            'chat_session_id' => $sessionId,
            'agent_id' => $aiAgent ? $aiAgent->id : null,
            'agent_name' => $agentName,
            'user_id' => $user ? $user->id : null,
            'user_name' => $user ? $user->first_name : 'User',
            'user_picture' => ($user && $user->profile) ? $user->profile->photo_path : null,
        ], now()->addMinutes(5));

        $configuredWsUrl = config('services.gemini.proxy_ws_url');
        $requestHost = request()->getHost();
        if ($configuredWsUrl && !in_array($requestHost, ['127.0.0.1', 'localhost', '::1']) && (str_contains($configuredWsUrl, '127.0.0.1') || str_contains($configuredWsUrl, 'localhost'))) {
            $wsUrl = str_replace(['127.0.0.1', 'localhost'], $requestHost, $configuredWsUrl);
        } else {
            $wsUrl = $configuredWsUrl ?: 'ws://' . $requestHost . ':8089/gemini-live';
        }

        $isStrictAdmin = $isAdminUser
            || ($request->user() instanceof \App\Models\Admin\Admin)
            || (auth('admin')->user() instanceof \App\Models\Admin\Admin);

        $wantsDirect = $isStrictAdmin && (
            $request->query('fallback') === 'direct'
            || in_array($request->query('client'), ['android_native', 'android_secretary', 'minipc'], true)
        );

        $originUrl = rtrim((string) (config('app.url') ?: ($request->getSchemeAndHttpHost())), '/');

        $responsePayload = [
            'token' => $token,
            'chat_session_id' => $sessionId,
            'ws_url' => $wsUrl,
            'origin_url' => $originUrl,
            'system_instruction' => $systemInstruction,
            'voice_name' => $voiceName,
            'agent_id' => $aiAgent ? $aiAgent->id : null,
            'agent_name' => $agentName,
            'tools' => [['functionDeclarations' => $functionDeclarations]],
        ];

        if ($wantsDirect) {
            $responsePayload['direct_ws_url'] = 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=' . rawurlencode((string) $apiKey);
        }

        return response()->json($responsePayload, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Lightweight health & latency ping for the "Live-AI" side widget.
     * Measures HTTP /health or TCP latency to the Node.js WebSocket bridge without false loopback overwrites.
     */
    public function bridgePing(Request $request)
    {
        $wsUrl = config('services.gemini.proxy_ws_url')
            ?: config('services.ai.gemini_proxy_ws_url')
            ?: 'ws://127.0.0.1:8089/gemini-live';

        $parsed = parse_url($wsUrl);
        $scheme = strtolower((string) ($parsed['scheme'] ?? 'ws'));
        $host = (string) ($parsed['host'] ?? '127.0.0.1');
        $port = (int) ($parsed['port'] ?? ($scheme === 'wss' ? 443 : 8089));
        $isLoopbackHost = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);

        $candidates = [[$host, $port, $scheme]];
        if ($isLoopbackHost) {
            if ($port !== 8081) {
                $candidates[] = ['127.0.0.1', 8081, 'ws'];
            }
            if ($port !== 8089) {
                $candidates[] = ['127.0.0.1', 8089, 'ws'];
            }
        }

        $isOnline = false;
        $bridgePingMs = null;
        $bridgeHealth = null;
        $primaryError = null;

        foreach ($candidates as $idx => [$candHost, $candPort, $candScheme]) {
            $httpScheme = ($candScheme === 'wss' || $candPort === 443) ? 'https' : 'http';
            $portSuffix = (($httpScheme === 'https' && $candPort === 443) || ($httpScheme === 'http' && $candPort === 80))
                ? ''
                : ":{$candPort}";
            $healthUrl = "{$httpScheme}://{$candHost}{$portSuffix}/health";

            for ($attempt = 1; $attempt <= ($idx === 0 ? 2 : 1); $attempt++) {
                $startNs = hrtime(true);
                try {
                    $healthRes = \Illuminate\Support\Facades\Http::connectTimeout(1.8)
                        ->timeout(2.5)
                        ->acceptJson()
                        ->get($healthUrl);

                    if ($healthRes->successful()) {
                        $bridgePingMs = max(1, (int) round((hrtime(true) - $startNs) / 1_000_000));
                        $bridgeHealth = $healthRes->json();
                        $isOnline = true;
                        break 2;
                    }
                    if ($idx === 0) {
                        $primaryError = 'HTTP ' . $healthRes->status();
                    }
                } catch (\Throwable $httpEx) {
                    $errText = $httpEx->getMessage();
                    if ($idx === 0) {
                        $primaryError = $errText;
                    }
                    // Fallback auf rohen Socket-Check (z.B. lokaler Dev-Server ohne /health)
                    $sockTarget = ($candScheme === 'wss' || $candPort === 443) ? "tls://{$candHost}" : $candHost;
                    $fp = @fsockopen($sockTarget, $candPort, $errno, $errstr, 1.5);
                    if ($fp) {
                        $bridgePingMs = max(1, (int) round((hrtime(true) - $startNs) / 1_000_000));
                        fclose($fp);
                        $isOnline = true;
                        break 2;
                    }
                    if ($idx === 0 && !empty($errstr)) {
                        $primaryError = "[{$errno}] {$errstr}";
                    }
                    if ($attempt === 1 && str_contains(strtolower($primaryError ?? ''), 'temporarily unavailable')) {
                        usleep(250_000);
                        continue;
                    }
                }
            }
        }

        $lastIssue = is_array($bridgeHealth) ? ($bridgeHealth['last_issue'] ?? null) : null;
        if (!$isOnline && !$isLoopbackHost && !app()->runningUnitTests()) {
            \App\Services\System\SystemErrorLogger::recordCrossPlatformEvent([
                'platform' => 'portal',
                'severity' => 'warning',
                'component' => 'ai_bridge_ping',
                'title' => 'Live-AI WebSocket-Bridge nicht erreichbar (' . $host . ')',
                'message' => 'Health-Check auf ' . $wsUrl . ' fehlgeschlagen: ' . ($primaryError ?: 'Unbekannter Verbindungsfehler'),
                'context' => [
                    'ws_url' => (string) $wsUrl,
                    'host' => $host,
                    'port' => (string) $port,
                    'error' => (string) ($primaryError ?? ''),
                ],
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'online' => $isOnline,
            'bridge_ping_ms' => $bridgePingMs,
            'bridge_version' => $bridgeHealth['bridge_version'] ?? ($isOnline ? 'v1.3-stable' : null),
            'uptime_sec' => $bridgeHealth['uptime_sec'] ?? $bridgeHealth['uptime_seconds'] ?? null,
            'active_live_sessions' => $bridgeHealth['active_live_sessions'] ?? 0,
            'memory_rss_mb' => $bridgeHealth['memory_rss_mb'] ?? $bridgeHealth['memory_mb'] ?? null,
            'last_issue' => $lastIssue,
            'error' => $isOnline ? null : $primaryError,
            'checked_at' => now()->format('H:i:s'),
        ], 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Verifies the websocket token and returns the actual credentials.
     * Only accessed internally by the Node.js server.
     */
    public function verifyToken(Request $request)
    {
        if (!\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($request)) {
            $hasSecretHeader = $request->headers->has('X-Bridge-Secret') || $request->headers->has('X-AI-Proxy-Secret');
            $hasConfiguredSecret = !empty(config('services.gemini.bridge_secret')) || !empty(config('services.ai.proxy_secret'));
            \App\Services\System\SystemSecurityService::logEvent(
                'security:bridge_verify_unauthorized',
                'Unautorisierter Zugriff auf Bridge-Token-Endpunkt (Code 4003 Ursache)',
                'Zugriff auf /api/ai/verify-token von IP ' . $request->ip() . ' abgelehnt (Header gesendet: ' . ($hasSecretHeader ? 'ja' : 'nein') . ', Server-Secret konfiguriert: ' . ($hasConfiguredSecret ? 'ja' : 'nein') . ').',
                'error'
            );
            \App\Services\System\SystemErrorLogger::recordCrossPlatformEvent([
                'platform' => 'portal',
                'severity' => 'error',
                'component' => 'ai_bridge_verify',
                'title' => 'Bridge Token-Verifizierung abgelehnt (HTTP 403 / Code 4003)',
                'message' => 'Node-Bridge oder externer Aufrufer scheiterte an SystemSecurityService::isTrustedBridgeRequest.',
                'context' => [
                    'client_ip' => (string) $request->ip(),
                    'has_secret_header' => $hasSecretHeader ? 'true' : 'false',
                    'has_configured_secret' => $hasConfiguredSecret ? 'true' : 'false',
                    'config_cached' => app()->configurationIsCached() ? 'true' : 'false',
                    'x_forwarded_for' => $request->headers->has('X-Forwarded-For') ? 'present' : 'absent',
                ],
            ]);
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $token = $request->input('token');
        if (empty($token) || !is_string($token)) {
            return response()->json(['error' => 'Token missing'], 400);
        }

        $cacheKey = 'gemini_live_token_' . $token;
        $data = \Illuminate\Support\Facades\Cache::pull($cacheKey);

        if (!$data) {
            \App\Services\System\SystemErrorLogger::recordCrossPlatformEvent([
                'platform' => 'portal',
                'severity' => 'warning',
                'component' => 'ai_bridge_verify',
                'title' => 'Abgelaufenes oder unbekanntes Gemini-Live-Einmaltoken',
                'message' => 'Token-Lookup in Cache lieferte keinen Treffer (TTL 5 Min. überschritten oder mehrfach eingelöst).',
                'context' => [
                    'cache_driver' => (string) config('cache.default'),
                ],
            ]);
            return response()->json(['error' => 'Invalid or expired token'], 403);
        }

        return response()->json($data);
    }

    /**
     * Strukturiertes Diagnose-Logging für Live-AI Web- & Client-Verbindungsfehler inkl. Auto-Heal-Protokollierung.
     */
    public function clientErrorLog(Request $request)
    {
        $validated = $request->validate([
            'severity' => 'nullable|string|in:error,warning,info',
            'component' => 'required|string|max:120',
            'title' => 'required|string|max:240',
            'message' => 'required|string|max:3500',
            'stacktrace' => 'nullable|string|max:8000',
            'auto_healed' => 'nullable|boolean',
            'heal_summary' => 'nullable|string|max:500',
            'context' => 'nullable|array',
        ]);

        $entry = \App\Services\System\SystemErrorLogger::recordCrossPlatformEvent([
            'platform' => 'portal',
            'severity' => $validated['severity'] ?? 'error',
            'component' => $validated['component'],
            'title' => $validated['title'],
            'message' => $validated['message'],
            'stack' => $validated['stacktrace'] ?? null,
            'auto_healed' => (bool) ($validated['auto_healed'] ?? false),
            'auto_heal_action' => $validated['heal_summary'] ?? null,
            'device' => substr((string) $request->userAgent(), 0, 160),
            'context' => is_array($validated['context'] ?? null) ? $validated['context'] : [],
        ]);

        return response()->json([
            'status' => 'ok',
            'log_id' => $entry?->id,
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    /**
     * Persists a live transcript (assistant or user) directly from the WebSocket bridge
     * with smart deduplication and extension updates.
     */
    public function saveLiveTranscript(Request $request)
    {
        if (!\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($request)) {
            \App\Services\System\SystemSecurityService::logEvent(
                'security:bridge_transcript_unauthorized',
                'Unautorisierter Transcript-Injection-Versuch blockiert',
                'Externer Zugriffsversuch auf /api/ai/save-live-transcript von IP ' . $request->ip() . ' abgewehrt.',
                'error'
            );
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $sessionId = (string) $request->input('session_id', '');
        $role = (string) $request->input('role', 'assistant');
        $content = trim((string) $request->input('content', ''));
        $agentId = $request->input('agent_id');
        $agentName = $request->input('agent_name');
        $userId = $request->input('user_id');

        if (empty($content) || empty($sessionId)) {
            return response()->json(['error' => 'Missing session_id or content'], 400);
        }

        if (strlen($content) > 50000 || strlen($sessionId) > 100) {
            return response()->json(['error' => 'Payload limits exceeded (content max 50000 chars, session_id max 100 chars)'], 422);
        }

        // Security: Never allow external injection of 'system' role prompts into AiChatMemory
        if (!in_array($role, ['user', 'assistant'], true)) {
            $role = 'assistant';
        }

        // Security: Prevent cross-user session transcript injection if session belongs to a different user
        $targetSession = \App\Models\Ai\AiChatSession::find($sessionId);
        if ($targetSession && !empty($targetSession->user_id) && !empty($userId) && (string) $targetSession->user_id !== (string) $userId) {
            return response()->json(['error' => 'Session ownership mismatch'], 403);
        }

        // Deduplication check: inspect recent messages for this session
        $recent = \App\Models\Ai\AiChatMemory::where('session_id', $sessionId)
            ->where('role', $role)
            ->where('created_at', '>=', now()->subSeconds(20))
            ->orderBy('id', 'desc')
            ->first();

        if ($recent) {
            $existingText = trim($recent->content);

            // Case 1: Exact duplicate within 20s -> skip
            if ($existingText === $content) {
                return response()->json([
                    'status' => 'skipped_duplicate',
                    'id' => $recent->id,
                    'content' => $recent->content
                ]);
            }

            // Case 2: New text extends the existing text (e.g. earlier partial chunk) -> update to complete
            if (str_starts_with($content, $existingText) && strlen($content) > strlen($existingText)) {
                $recent->update(['content' => $content]);
                \App\Models\Ai\AiChatSession::where('id', $sessionId)->update(['updated_at' => now()]);
                try {
                    broadcast(new \App\Events\AiFrontendEvent('chat-memory-updated', [
                        'session_id' => $sessionId,
                        'message_id' => $recent->id,
                        'role' => $role,
                        'content' => $content
                    ]));
                } catch (\Throwable $e) {
                    // Silently continue if pusher/echo server is offline
                }
                return response()->json([
                    'status' => 'updated_extended',
                    'id' => $recent->id,
                    'content' => $recent->content
                ]);
            }

            // Case 3: Existing text already includes the new text (late partial) -> skip
            if (str_starts_with($existingText, $content) && strlen($existingText) >= strlen($content)) {
                return response()->json([
                    'status' => 'skipped_partial',
                    'id' => $recent->id,
                    'content' => $recent->content
                ]);
            }
        }

        // Build context data
        $contextData = [];
        if ($role === 'user') {
            $user = null;
            if (!empty($userId)) {
                $user = \App\Models\Customer\Customer::find($userId)
                    ?? \App\Models\Admin\Admin::find($userId)
                    ?? \App\Models\System\SystemUser::find($userId);
            }
            if (!$user) {
                $user = \App\Services\AI\AiAuthHelper::getUser();
            }
            $contextData = [
                'name' => $user ? ($user->first_name ?? $user->name ?? 'User') : 'User',
                'color' => 'gray-400',
                'icon' => 'user',
                'is_live_mode' => true,
                'is_live_audio' => true,
                'profile_picture' => ($user && isset($user->profile) && $user->profile) ? ($user->profile->photo_path ?? null) : null
            ];
        } else {
            $agent = null;
            if (!empty($agentId)) {
                $agent = \App\Models\Ai\AiAgent::find($agentId);
            }
            if (!$agent && !empty($agentName)) {
                $agent = \App\Models\Ai\AiAgent::where('name', $agentName)->first();
            }
            if (!$agent) {
                $agent = \App\Models\Ai\AiAgent::where('is_in_chat', true)->first();
            }
            $contextData = [
                'name' => $agent ? $agent->name : ($agentName ?: 'Funkira'),
                'color' => $agent ? $agent->color : 'purple-500',
                'icon' => 'robot',
                'is_live_audio' => true,
                'profile_picture' => $agent ? $agent->profile_picture : null
            ];
        }

        $memory = \App\Models\Ai\AiChatMemory::create([
            'session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'context_data' => $contextData,
        ]);

        \App\Models\Ai\AiChatSession::where('id', $sessionId)->update(['updated_at' => now()]);

        try {
            broadcast(new \App\Events\AiFrontendEvent('chat-memory-updated', [
                'session_id' => $sessionId,
                'message_id' => $memory->id,
                'role' => $role,
                'content' => $memory->content
            ]));
        } catch (\Throwable $e) {
            // Silently continue if pusher/echo server is offline
        }

        return response()->json([
            'status' => 'created',
            'id' => $memory->id,
            'content' => $memory->content
        ]);
    }

    /**
     * Return recent chat history for the authenticated user / session.
     */
    public function history(Request $request)
    {
        $userId = \App\Services\AI\AiAuthHelper::getUserId() ?: auth()->id();
        $isAdminUser = \App\Services\AI\AiAuthHelper::isAdmin();
        $sessionId = $request->input('chat_session_id');

        if ($sessionId && !$isAdminUser) {
            $ownedSession = $userId && \App\Models\Ai\AiChatSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->exists();
            if (!$ownedSession) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kein Zugriff auf diese Chat-Session.'
                ], 403);
            }
        }

        if (!$sessionId && $userId) {
            $lastSession = \App\Models\Ai\AiChatSession::where('user_id', $userId)
                ->orderBy('updated_at', 'desc')
                ->first();
            if ($lastSession) {
                $sessionId = $lastSession->id;
            }
        }

        if (!$sessionId) {
            $sessionId = session()->getId();
        }

        $limit = min(100, max(5, intval($request->input('limit', 30))));
        $messages = [];

        if ($sessionId) {
            $records = AiChatMemory::where('session_id', $sessionId)
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get()
                ->reverse();

            foreach ($records as $rec) {
                $messages[] = [
                    'id' => (string) $rec->id,
                    'role' => $rec->role,
                    'content' => $rec->content,
                    'agent_name' => $rec->context_data['name'] ?? null,
                    'created_at' => $rec->created_at ? $rec->created_at->toIso8601String() : null,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'session_id' => $sessionId,
            'messages' => $messages
        ]);
    }

    /**
     * Prüft, ob ein KI-Agent die exklusive Admin-Instanz (Funkira) ist.
     */
    private function isAdminOnlyAgent(\App\Models\Ai\AiAgent $agent): bool
    {
        if (method_exists(\App\Services\AI\FunkiraSkillPoolService::class, 'isFunkira')) {
            return \App\Services\AI\FunkiraSkillPoolService::isFunkira($agent);
        }

        return strcasecmp(trim((string) ($agent->name ?? '')), 'Funkira') === 0;
    }
}

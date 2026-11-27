<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ai\AiChatMemory;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Http\Request;

class AIController extends Controller
{
    /**
     * Detects canned Google Gemini post-training disclaimer refusals (e.g. "Ich bin nur ein Sprachmodell und kann dabei nicht helfen")
     * so they never poison AiChatMemory or Live session history.
     */
    public static function isCannedModelRefusal(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        $norm = mb_strtolower(trim($text), 'UTF-8');
        $phrases = [
            'kann dabei nicht helfen',
            'kann ich dabei nicht helfen',
            'kann dir dabei nicht helfen',
            'kann ich leider nicht helfen',
            'ich bin nur ein sprachmodell',
            'ich bin ein sprachmodell',
            'als sprachmodell kann ich',
            'als ki-sprachmodell',
            'als ki-modell kann ich',
            'als künstliche intelligenz kann ich',
            'just a language model',
            'as an ai language model',
            'cannot help with that',
            "can't help with that",
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($norm, $phrase)) {
                return true;
            }
        }

        if (str_contains($norm, 'sprachmodell') && (
            str_contains($norm, 'nicht helfen') ||
            str_contains($norm, 'nur ein') ||
            str_contains($norm, 'kann ich nicht') ||
            str_contains($norm, 'keinen körper')
        )) {
            return true;
        }

        return false;
    }

    /**
     * Replaces any generic language-model refusal with an honest, concrete German explanation of the technical/permission cause.
     */
    public static function buildConcreteRefusalReplacement(?string $userPrompt = null, bool $isEmployeeRestricted = false): string
    {
        $u = mb_strtolower(trim((string) $userPrompt), 'UTF-8');

        if ($isEmployeeRestricted) {
            return 'Dafür reicht deine aktuelle Mitarbeiter-Berechtigung im Workspace leider nicht aus. Diese Funktion ist für Inhaberin Alina freigeschaltet.';
        }

        if (str_contains($u, 'gesicht') || str_contains($u, 'wer steht') || str_contains($u, 'wer ist auf dem foto') || str_contains($u, 'person erkennen')) {
            return 'Echte Gesichter auf Kamerabildern werte ich aus Datenschutzgründen nicht in der Sprach-Cloud aus. Unsere Personen- und Stimmerkennung läuft rein lokal auf dem Werkstatt-Mini-PC!';
        }

        return 'Für diese Aktion fehlt mir gerade die passende direkte Schnittstelle bzw. ein Sicherheitsfilter der Sprach-Engine hat blockiert. Sag mir gern kurz, ob ich es stattdessen als Aufgabe oder Notiz für dich festhalten soll!';
    }

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
        if ($request->hasSession()) {
            try {
                $request->session()->save();
            } catch (\Throwable) {
            }
        }
        $currentUserId = \App\Services\AI\AiAuthHelper::getUserId();
        if ($sessionId && !$isAdmin) {
            $ownedSession = $currentUserId && \App\Models\Ai\AiChatSession::where('id', $sessionId)
                ->where('user_id', $currentUserId)
                ->exists();
            if (!$ownedSession) {
                $sessionId = null;
            }
        }
        if (!$sessionId && $currentUserId && \App\Services\AI\AiAuthHelper::check()) {
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

        $agent = $this->resolveRequestedAgent($request);
        if ($agent && !$isAdmin && $this->isAdminOnlyAgent($agent)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Zugriff verweigert: Die Admin-KI Funkira ist ausschließlich für Administratoren freigegeben.',
            ], 403);
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
                    ?? \App\Models\Ai\AiAgent::where('is_active', true)->whereRaw('LOWER(name) NOT IN (?, ?, ?)', ['funkira', 'falcone', 'falcon'])->first();
            }
        }

        $execChannel = (string) ($request->input('channel') ?: 'web_portal');
        $execVisitorLocked = ($execChannel === 'workshop_minipc')
            && !$request->boolean('camera_alina_verified', true)
            && !$request->boolean('voice_alina_verified', true);

        try {
            // Forward execution to the registry
            $result = AIFunctionsRegistry::execute($functionName, $args, $agent);

            // Deterministisches Tool-Fehler-Signal für Verhaltensanalyse erfassen
            if (is_array($result) && (($result['status'] ?? null) === 'error' || !empty($result['error']))) {
                \App\Services\AI\AiBehaviorAnalysisService::recordToolErrorSignal(
                    (string) $functionName,
                    (string) ($result['message'] ?? $result['error'] ?? 'Werkzeug meldete Fehlerstatus'),
                    $agent,
                    $execChannel,
                    [
                        'session_id' => $sessionId,
                        'visitor_sandbox_locked' => $execVisitorLocked,
                    ]
                );
            }

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
            \App\Services\AI\AiBehaviorAnalysisService::recordToolErrorSignal(
                (string) $functionName,
                $e->getMessage(),
                $agent,
                $execChannel,
                [
                    'session_id' => $sessionId,
                    'visitor_sandbox_locked' => $execVisitorLocked,
                ]
            );
            // Function not found
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);

        } catch (\Exception $e) {
            \App\Services\AI\AiBehaviorAnalysisService::recordToolErrorSignal(
                (string) $functionName,
                $e->getMessage(),
                $agent,
                $execChannel,
                [
                    'session_id' => $sessionId,
                    'visitor_sandbox_locked' => $execVisitorLocked,
                ]
            );
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
            'prompt' => 'sometimes|nullable|string',
            'history' => 'sometimes|array',
            'client_message_id' => 'sometimes|nullable|string|max:100',
            'chat_session_id' => 'sometimes|nullable|string|max:100',
            'skip_tts' => 'sometimes|boolean',
        ]);

        $chatStartedAt = microtime(true);
        $rawClientMsgId = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) $request->input('client_message_id', '')));
        $currentUserIdForIdem = (string) (\App\Services\AI\AiAuthHelper::getUserId() ?? 'anon');
        $idemResultKey = $rawClientMsgId !== ''
            ? 'ai_chat_idem_res_' . sha1($currentUserIdForIdem . '|' . $rawClientMsgId)
            : null;
        $idemInflightKey = $rawClientMsgId !== ''
            ? 'ai_chat_idem_lock_' . sha1($currentUserIdForIdem . '|' . $rawClientMsgId)
            : null;

        if ($idemResultKey !== null) {
            try {
                $cachedPayload = \Illuminate\Support\Facades\Cache::get($idemResultKey);
                if (is_array($cachedPayload)) {
                    $cachedPayload['deduplicated'] = true;
                    return response()->json($cachedPayload);
                }

                // Falls derselbe Request nach einem mobilen Funkloch noch aktiv auf dem Server arbeitet,
                // warte kurz auf das Ergebnis statt eine doppelte Generierung anzustoßen.
                if ($idemInflightKey !== null && \Illuminate\Support\Facades\Cache::has($idemInflightKey)) {
                    $waitUntil = microtime(true) + 25.0;
                    while (microtime(true) < $waitUntil) {
                        usleep(250000);
                        $readyPayload = \Illuminate\Support\Facades\Cache::get($idemResultKey);
                        if (is_array($readyPayload)) {
                            $readyPayload['deduplicated'] = true;
                            return response()->json($readyPayload);
                        }
                        if (!\Illuminate\Support\Facades\Cache::has($idemInflightKey)) {
                            break;
                        }
                    }
                }

                if ($idemInflightKey !== null) {
                    \Illuminate\Support\Facades\Cache::put($idemInflightKey, true, 90);
                }
            } catch (\Throwable) {
            }
        }

        $history = $request->input('history', []);
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        // Clean history from frontend specific keys (name, color, icon, etc.) and legacy client error placeholders
        $isAdmin = \App\Services\AI\AiAuthHelper::isAdmin();
        $allowedRoles = $isAdmin ? ['user', 'assistant', 'system', 'tool'] : ['user', 'assistant', 'tool'];
        $cleanHistory = [];
        foreach ($history as $msg) {
            $content = $msg['content'] ?? '';
            $rawRole = strtolower(trim((string) ($msg['role'] ?? 'user')));
            if ($rawRole === 'assistant' && is_string($content) && trim($content) === '[Fehler beim Laden der Antwort]') {
                continue;
            }
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

        // Append explicit prompt whenever provided and not already the trailing user message
        $rawPrompt = trim((string) $request->input('prompt', ''));
        if ($rawPrompt !== '') {
            $lastMsg = !empty($history) ? end($history) : null;
            $lastRole = is_array($lastMsg) ? ($lastMsg['role'] ?? '') : '';
            $lastContent = is_array($lastMsg) && is_string($lastMsg['content'] ?? null) ? trim($lastMsg['content']) : '';
            if ($lastRole !== 'user' || $lastContent !== $rawPrompt) {
                $history[] = [
                    'role' => 'user',
                    'content' => $rawPrompt,
                ];
            }
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
            $aiAgent = $this->resolveRequestedAgent($request);
            if ($aiAgent && !$isAdmin && $this->isAdminOnlyAgent($aiAgent)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Zugriff verweigert: Die Admin-KI Funkira ist ausschließlich für Administratoren freigegeben.',
                ], 403);
            }
            if (!$aiAgent) {
                if ($isAdmin) {
                    $aiAgent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first()
                        ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();
                } else {
                    $aiAgent = \App\Models\Ai\AiAgent::where('name', 'Funki')->where('is_active', true)->first()
                        ?? \App\Models\Ai\AiAgent::where('is_active', true)->whereRaw('LOWER(name) != ?', ['funkira'])->first();
                }
            }
        }
        
        if (!$aiAgent) {
            return response()->json(['status' => 'error', 'message' => 'No AI Agent found in database.'], 500);
        }

        if ($isAdmin && \App\Services\AI\FunkiraSkillPoolService::isExecutiveRoot($aiAgent)) {
            $dynamicSystemPrompt .= "\n\n" . $this->buildSubdomainReadinessDirective();
            $blockadeCtx = $this->buildPendingAgBlockadeContext();
            if ($blockadeCtx['directive'] !== '') {
                $dynamicSystemPrompt .= "\n\n" . $blockadeCtx['directive'];
            }
        }

        $agent = \App\Services\AI\AiAgentFactory::make($aiAgent);
        
        if (method_exists($agent, 'setDynamicSystemPrompt')) {
            $agent->setDynamicSystemPrompt($dynamicSystemPrompt);
        }

        // Ermittle Session-ID bereits VOR $agent->ask(), damit alle Werkzeug-Logs in derselben Session landen
        $sessionId = $request->input('chat_session_id');
        $currentUserId = \App\Services\AI\AiAuthHelper::getUserId();

        try {
            if ($sessionId && !\App\Services\AI\AiAuthHelper::isAdmin()) {
                $ownedSession = $currentUserId && \App\Models\Ai\AiChatSession::where('id', $sessionId)
                    ->where('user_id', $currentUserId)
                    ->exists();
                if (!$ownedSession) {
                    $sessionId = null;
                }
            }

            if (!$sessionId) {
                $sessionId = $request->hasSession() ? $request->session()->getId() : null;
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

            if ($sessionId) {
                config(['ai.current_session_id' => $sessionId]);
            }
        } catch (\Throwable $sessEx) {
            \Illuminate\Support\Facades\Log::warning('AIController::chat session lookup warning: ' . $sessEx->getMessage());
        }

        $inferredChannel = (string) ($request->input('channel') ?: (
            str_contains(strtolower((string) $request->userAgent()), 'okhttp') ? 'android_app' : 'funkenwerk_desktop'
        ));
        $chatPresenceMeta = [
            'channel' => $inferredChannel,
            'active_route' => $request->input('active_route'),
            'is_voice_mode' => $request->boolean('is_voice_mode', false),
        ];
        if ($request->has('camera_alina_verified')) {
            $chatPresenceMeta['camera_alina_verified'] = $request->boolean('camera_alina_verified');
        }
        if ($request->has('voice_alina_verified')) {
            $chatPresenceMeta['voice_alina_verified'] = $request->boolean('voice_alina_verified');
        }
        if (is_array($request->input('detected_persons'))) {
            $chatPresenceMeta['detected_persons'] = $request->input('detected_persons');
        }
        $chatPresence = \App\Services\AI\FunkiraPresenceContext::build($chatPresenceMeta, $request);

        if (!empty($chatPresence['visitor_sandbox_locked'])) {
            return response()->json([
                'status' => 'success',
                'chat_session_id' => $sessionId,
                'agent_name' => 'Funkira',
                'tts_enabled' => false,
                'response' => 'Hallo im Seelenfunke-Atelier! Da Alina gerade nicht persönlich im Kamerabild ist, ist mein geschützter Besuchermodus aktiv. Interne Firmendaten sind gesperrt, aber ich richte ihr gerne aus, dass jemand da war.',
                'history' => $history,
                'context_data' => ['visitor_sandbox_locked' => true],
                'events_data' => [],
            ]);
        }

        if (method_exists($agent, 'setDynamicSystemPrompt')) {
            $presenceHeader = \App\Services\AI\FunkiraPresenceContext::toPromptHeader($chatPresence, $aiAgent);
            $deptKnowledge = \App\Services\AI\FunkiraTicketRouter::loadFlatDepartmentKnowledge($aiAgent?->ai_department_id);
            $promptParts = [$presenceHeader, $deptKnowledge];
            if (!\App\Services\AI\AiBehaviorAnalysisService::isAgentExcluded($aiAgent) && trim($dynamicSystemPrompt) !== '') {
                $promptParts[] = $dynamicSystemPrompt;
            }
            $agent->setDynamicSystemPrompt(implode("\n\n", array_filter($promptParts, static fn ($p) => trim((string) $p) !== '')));
        }

        $executedChatTools = [];
        try {
            $result = $agent->ask($history, function (array $evt) use (&$executedChatTools) {
                if (($evt['type'] ?? '') === 'tool_call' && !empty($evt['tool'])) {
                    $executedChatTools[] = (string) $evt['tool'];
                }
            });
        } catch (\Throwable $askEx) {
            if ($idemInflightKey !== null) {
                try {
                    \Illuminate\Support\Facades\Cache::forget($idemInflightKey);
                } catch (\Throwable) {
                }
            }
            \Illuminate\Support\Facades\Log::error('AIController::chat inference failed: ' . \App\Services\System\SystemErrorLogger::sanitizeSensitiveText($askEx->getMessage()));
            return response()->json([
                'status' => 'error',
                'message' => 'Die KI-Verarbeitung ist vorübergehend gestört. Bitte versuche es in einem kurzen Moment erneut.',
            ], 503);
        }

        $qualityGateResult = null;
        if (!empty($result['response']) && ($aiAgent?->name ?? 'Funkira') !== 'Funki') {
            $qualityGateResult = \App\Services\AI\AiOutputQualityGate::inspectAndClean((string) $result['response'], $executedChatTools);
            $result['response'] = $qualityGateResult['text'];
            $result['response_raw'] = $qualityGateResult['text'];
        }

        // Was hat der User gesagt? (Finde die neuste User-Nachricht)
        $userMsg = collect($history)->reverse()->firstWhere('role', 'user');
        $dbContent = '';
        $assistantMemoryId = null;
        $userMemoryId = null;
        $cannedRefusalDetected = false;
        $prevAssistantExcerpt = null;

        try {
            $chatSession = $sessionId ? \App\Models\Ai\AiChatSession::find($sessionId) : null;
            $user = $chatSession ? $chatSession->user : (auth()->user() ?? null);

            if ($sessionId) {
                $prevAssistantExcerpt = AiChatMemory::where('session_id', $sessionId)
                    ->where('role', 'assistant')
                    ->latest('created_at')
                    ->value('content');
            }

            $userCtx = [
                'name' => $user ? ($user->first_name ?? 'User') : 'User',
                'color' => 'gray-400',
                'icon' => 'user',
                'profile_picture' => ($user && $user->profile) ? $user->profile->photo_path : null,
            ];
            if ($rawClientMsgId !== '') {
                $userCtx['client_message_id'] = $rawClientMsgId;
            }

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
                if ($sessionId) {
                    $createdUserMem = AiChatMemory::create([
                        'session_id' => $sessionId,
                        'role' => 'user',
                        'content' => (string) $dbContent,
                        'context_data' => $userCtx,
                    ]);
                    $userMemoryId = $createdUserMem->id;
                }
            }

            $assistantCtx = [
                'name' => $aiAgent ? $aiAgent->name : 'Funkira',
                'color' => $aiAgent ? $aiAgent->color : 'emerald-500',
                'icon' => 'robot',
                'profile_picture' => $aiAgent ? $aiAgent->profile_picture : null,
            ];
            if ($rawClientMsgId !== '') {
                $assistantCtx['client_message_id'] = $rawClientMsgId;
            }

            // Was hat die KI final geantwortet?
            if (!empty($result['response'])) {
                if (self::isCannedModelRefusal((string) $result['response'])) {
                    $cannedRefusalDetected = true;
                    $isEmpOnly = \App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin();
                    $klartext = self::buildConcreteRefusalReplacement((string) ($dbContent ?? ''), $isEmpOnly);
                    $result['response'] = $klartext;
                    $result['response_raw'] = $klartext;
                } elseif ($sessionId) {
                    $createdAssistantMem = AiChatMemory::create([
                        'session_id' => $sessionId,
                        'role' => 'assistant',
                        'content' => $result['response'],
                        'context_data' => $assistantCtx,
                    ]);
                    $assistantMemoryId = $createdAssistantMem->id;
                }
            }

            // 0-Token Verhaltensanalyse (implizites Lob/Kritik + Quality-Gate-Signale, Funki & Besuchermodus strikt ausgeschlossen)
            $chatLatencyMs = (int) round((microtime(true) - $chatStartedAt) * 1000);
            $behaviorChannel = (string) ($chatPresence['channel'] ?? 'web_portal');
            if ($dbContent !== '') {
                \App\Services\AI\AiBehaviorAnalysisService::scanAndRecordImplicitMessage(
                    (string) $dbContent,
                    $aiAgent,
                    $behaviorChannel,
                    [
                        'session_id' => $sessionId,
                        'ai_chat_memory_id' => $userMemoryId,
                        'executed_tools' => $executedChatTools,
                        'latency_ms' => $chatLatencyMs,
                        'environment_tier' => $chatPresence['environment_tier'] ?? null,
                        'agent_excerpt' => $prevAssistantExcerpt,
                        'visitor_sandbox_locked' => !empty($chatPresence['visitor_sandbox_locked']),
                    ]
                );
            }
            if ($qualityGateResult !== null || $cannedRefusalDetected) {
                $gatePayload = is_array($qualityGateResult) ? $qualityGateResult : [];
                $gatePayload['canned_refusal_detected'] = $cannedRefusalDetected;
                \App\Services\AI\AiBehaviorAnalysisService::recordQualityGateSignal(
                    $gatePayload,
                    $aiAgent,
                    $behaviorChannel,
                    [
                        'session_id' => $sessionId,
                        'ai_chat_memory_id' => $assistantMemoryId,
                        'executed_tools' => $executedChatTools,
                        'latency_ms' => $chatLatencyMs,
                        'environment_tier' => $chatPresence['environment_tier'] ?? null,
                        'user_excerpt' => (string) $dbContent,
                        'agent_excerpt' => (string) ($result['response'] ?? ''),
                        'visitor_sandbox_locked' => !empty($chatPresence['visitor_sandbox_locked']),
                    ]
                );
            }
        } catch (\Throwable $memEx) {
            \Illuminate\Support\Facades\Log::warning('AIController::chat memory persistence warning: ' . $memEx->getMessage());
        }

        // Generiere Audio (Base64) falls möglich und innerhalb des mobilen Antwortzeit-Budgets
        $base64Audio = null;

        $ttsProvider = $aiAgent ? $aiAgent->tts_provider : 'none';
        $ttsEnabled = $aiAgent ? (bool) $aiAgent->tts_enabled : false;
        $skipTtsRequested = $request->boolean('skip_tts', false);

        if ($ttsProvider === 'none' || $skipTtsRequested) {
            $ttsEnabled = false;
        }

        $elapsedBeforeTtsSec = microtime(true) - $chatStartedAt;
        if ($ttsEnabled && $elapsedBeforeTtsSec < 22.0 && !empty($result['response_raw'] ?? $result['response']) && $aiAgent && $ttsProvider === 'gemini_native') {
            try {
                $cleanText = (string) ($result['response_raw'] ?? $result['response']);

                // Extract <speak> tag content if present
                if (preg_match('/<speak>(.*?)<\/speak>/is', $cleanText, $matches)) {
                    $cleanText = trim($matches[1]);
                } else {
                    // Fallback to full text but strip markdown/HTML tags
                    $cleanText = strip_tags($cleanText);
                }

                $voiceName = $aiAgent->tts_voice ?: 'Aoede';
                $synthesizedWav = \App\Services\AI\GeminiTtsService::synthesizeWav($cleanText, $voiceName, 8);
                if (is_string($synthesizedWav) && $synthesizedWav !== '') {
                    $base64Audio = $synthesizedWav;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Gemini Native TTS Fetch failed: " . \App\Services\System\SystemErrorLogger::sanitizeSensitiveText($e->getMessage()));
            }
        }

        $responsePayload = [
            'status' => 'success',
            'chat_session_id' => $sessionId,
            'memory_id' => $assistantMemoryId,
            'agent_name' => $aiAgent ? $aiAgent->name : 'Funkira',
            'tts_enabled' => $ttsEnabled,
            'response' => $result['response'],
            'history' => $result['history'] ?? [],
            'context_data' => $result['context_data'] ?? [],
            'events_data' => $result['events'] ?? [],
            'usage' => $result['usage'] ?? [],
            'audio' => $base64Audio,
        ];

        if ($idemResultKey !== null) {
            try {
                \Illuminate\Support\Facades\Cache::put($idemResultKey, $responsePayload, 600);
                if ($idemInflightKey !== null) {
                    \Illuminate\Support\Facades\Cache::forget($idemInflightKey);
                }
            } catch (\Throwable) {
            }
        }

        return response()->json($responsePayload);
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
        $aiAgent = $this->resolveRequestedAgent($request);
        if ($aiAgent && !$isAdminUser && $this->isAdminOnlyAgent($aiAgent)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Forbidden',
                'message' => 'Zugriff verweigert: Die Admin-KI Funkira ist ausschließlich für Administratoren freigegeben.',
            ], 403);
        }
        if (!$aiAgent && $isAdminUser) {
            $user = \App\Services\AI\AiAuthHelper::getUser() ?: auth()->user() ?: auth('admin')->user();
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
                    ?? \App\Models\Ai\AiAgent::where('is_active', true)->whereRaw('LOWER(name) NOT IN (?, ?, ?)', ['funkira', 'falcone', 'falcon'])->first();
            }
        }
        
        $voiceName = $aiAgent ? ($aiAgent->tts_voice ?: 'Aoede') : 'Aoede';
        $agentName = $aiAgent ? $aiAgent->name : 'Funkira';
        $isFunkiAgent = $aiAgent && strcasecmp(trim((string) ($aiAgent->name ?? '')), 'Funki') === 0;
        
        $payload = [];
        $systemInstruction = "";
        if ($aiAgent) {
            $payload = \App\Services\AI\AiAgentService::getAgentPayload($aiAgent);
            $systemInstruction = $payload['system_prompt'] ?? '';
        } else {
            $systemInstruction = 'Du bist Funkira, die zentrale Stabschefin von Mein-Seelenfunke. Antworte immer kurz, präzise und freundlich.';
        }

        $explicitChannel = trim((string) $request->input('channel', ''));
        $clientParam = strtolower(trim((string) $request->query('client', '')));
        $hasBearerAuth = !empty($request->bearerToken());
        if ($explicitChannel === '') {
            if ($clientParam === 'minipc') {
                $explicitChannel = 'workshop_minipc';
            } elseif (in_array($clientParam, ['android_native', 'android_secretary'], true) || ($hasBearerAuth && !$request->hasSession())) {
                $explicitChannel = 'android_live_call';
            } else {
                $explicitChannel = 'live_widget';
            }
        }
        $resolvedActiveRoute = trim((string) $request->input('active_route', ''));
        if ($resolvedActiveRoute === '') {
            $refererPath = parse_url((string) $request->headers->get('referer', ''), PHP_URL_PATH);
            if (is_string($refererPath) && $refererPath !== '') {
                $resolvedActiveRoute = $refererPath;
            }
        }

        $livePresence = \App\Services\AI\FunkiraPresenceContext::build([
            'channel' => $explicitChannel,
            'active_route' => $resolvedActiveRoute !== '' ? $resolvedActiveRoute : null,
            'camera_alina_verified' => $request->input('camera_alina_verified', true),
            'voice_alina_verified' => $request->input('voice_alina_verified', true),
            'detected_persons' => $request->input('detected_persons', []),
            'is_voice_mode' => true,
        ], $request);
        $presenceHeader = \App\Services\AI\FunkiraPresenceContext::toPromptHeader($livePresence, $aiAgent);
        $locationGuard = "VERBINDLICHER STANDORT- & KANAL-KONTEXT: Dein aktueller Kommunikationskanal ist '{$livePresence['channel_label']}' (technischer Kanal: '{$livePresence['channel']}'). "
            . ($livePresence['channel'] === 'live_widget' || $livePresence['channel'] === 'web_portal'
                ? "Du sprichst mit Alina im Webportal Live AI Widget (NICHT im 3D-Atelier und NICHT in der mobilen 3D-App!). Nenne bei Fragen zu deinem Standort oder Kanal immer klar 'Webportal Live AI Widget'" . ($resolvedActiveRoute !== '' ? " auf der Seite '{$resolvedActiveRoute}'" : '') . "."
                : "Nenne bei Fragen zu deinem aktuellen Kanal oder Standort immer wahrheitsgemäß '{$livePresence['channel_label']}'.");
        $systemInstruction = $presenceHeader . "\n" . $locationGuard . "\n\n" . $systemInstruction;
        $systemInstruction = str_replace([" \u{2013} ", " \u{2014} "], ' - ', $systemInstruction);

        $map = \App\Services\AI\AIFunctionsRegistry::getAdminNavigationMap();

        $navMap = "Verfügbare Admin-Routen:\n";
        foreach ($map as $path => $name) {
            $navMap .= "- " . $name . " => " . $path . "\n";
        }

        $userLabel = $isAdminUser ? 'der Nutzerin (Alina)' : 'dem Mitarbeiter / Nutzer';
        $systemInstruction = "STRIKTE SPRACHREGEL & OPERATIVES VERHALTEN (HÖCHSTE PRIORITÄT):\n" .
            "- Du sprichst und antwortest ZU 100% AUSSCHLIESSLICH AUF DEUTSCH mit {$userLabel}.\n" .
            "- Gib NIEMALS englische Sätze, Systemmeldungen, Statusbestätigungen oder Einleitungen aus (z.B. STRENG VERBOTEN: 'Confirming Operational Context', 'I acknowledge...', 'Understood').\n" .
            "- ABSOLUTES VERBOT VON SPRACHMODELL-FLOSKELN & PFLICHT ZUR KLARTEXT-DIAGNOSE: Antworte NIEMALS mit Standard-Ablehnungen wie 'Ich bin nur ein Sprachmodell und kann dabei nicht helfen' oder 'Als Sprachmodell kann ich das nicht'! Wenn etwas technisch, wegen abgelaufener Anmeldung, fehlendem Werkzeug oder Berechtigungen gerade nicht klappt, nenne IMMER in 1 bis 2 kurzen, ehrlichen Sätzen den EXAKTEN konkreten Grund und wie man es löst!\n" .
            "- Bestätige NIEMALS deine Anweisungen, Prompt oder Systemrolle! Fange sofort direkt und freundlich auf Deutsch an zu sprechen.\n\n" .
            "System-Prompt:\n" . $systemInstruction . "\n\nDu bist " . $agentName . ".\n" .
            "WICHTIG FÜR DEN ECHTZEIT-SPRACHMODUS (wie in der mobilen Google Gemini App):\n" .
            "- Du führst ein flüssiges, direktes Sprachgespräch in Echtzeit.\n" .
            "- Antworte militärisch-knackig und extrem präzise (maximal 1 bis 3 kurze Sätze auf den Punkt, null Füllwörter, null Floskeln, niemals doppelt ansetzen oder dieselbe Aussage wiederholen).\n" .
            "- KEINE VORAB-SPRACHE VOR WERKZEUGAUFRUFEN: Wenn du ein Werkzeug aufrufst, sprich VOR dem Werkzeugaufruf KEINEN Satz und KEINE Begrüßung aus! Führe das Werkzeug sofort stumm aus und antworte erst NACH Erhalt des Werkzeug-Ergebnisses EINMALIG und klar.\n" .
            "- Verwende in deiner Sprachantwort NIEMALS Markdown, Sternchen, Tabellen oder Listenpunkte.\n" .
            "- Rufe Werkzeuge nur auf, wenn der Benutzer dich explizit um eine konkrete Aktion bittet.\n" .
            "- PRIORISIERTE PAKETBÜNDELUNG & DIREKTLEITUNG ZU ANTIGRAVITY: Bündle mehrere technische Anforderungen, Bugs oder Ideen samt optionalem Fotoanhang vorab priorisiert ins Sammelpaket (`falcon_stage_to_package` mit `priority='urgent'|'high'|'normal'`, `attach_captured_photo=true`) statt Einzel-Aufträge zu spammen. Nutze `ag_send_to_antigravity` / `falcon_send_to_antigravity` ausschließlich für explizite Sofort-Einzelaufträge in den passenden Kanal (`shop`, `android`, `minipc`, `buchhaltung`, `invest` für Steinhauer Invest / Steini -> Invest-Chat, `leitung` für Seelenfunke Standard) und `ag_check_antigravity_status` für Statusabfragen.\n" .
            "- HINTERLEGTE FOTOS / DATEIEN IN DER APP (STRIKTES KAMERA-VERBOT): Wenn bereits ein Foto, Screenshot oder eine Datei in der App hinterlegt/ausgewählt wurde oder Alina sagt 'Ich habe schon ein Foto/Bild hinterlegt', öffne NIEMALS die Kamera und rufe KEIN Foto-Aufnahme-Werkzeug (`falcon_take_photo`, `ui_analyze_camera` o. Ä.) auf! Nutze direkt das bereits hinterlegte Bild.\n\n" .
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
            $sessionId = $request->hasSession() ? $request->session()->getId() : session()->getId();
        }
        if ($request->hasSession()) {
            try {
                $request->session()->save();
            } catch (\Throwable) {
            }
        }
        
        if ($sessionId) {
            $rawHistory = \App\Models\Ai\AiChatMemory::where('session_id', $sessionId)
                ->whereIn('role', ['user', 'assistant'])
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->take(24)
                ->get()
                ->reverse()
                ->values();
            $isFallbackHistory = false;

            // Falls eine neue Live-Call-Session (z.B. android_live_<uuid>) noch 0 Eintraege hat,
            // uebernehmen wir nahtlos die juengsten Gespraechs-Turns desselben Nutzers (max. 12h alt),
            // damit der Gespraechskontext zwischen Telefonaten und Chat nicht abreisst.
            if ($rawHistory->isEmpty() && $currentUserId && !$isFunkiAgent) {
                $cutoff = now()->subHours(12);
                $latestUserSessionId = \App\Models\Ai\AiChatSession::where('user_id', $currentUserId)
                    ->where('id', '!=', $sessionId)
                    ->where('is_archived', false)
                    ->whereHas('messages', function ($q) use ($cutoff) {
                        $q->whereIn('role', ['user', 'assistant'])
                            ->where('created_at', '>=', $cutoff);
                    })
                    ->orderBy('updated_at', 'desc')
                    ->value('id');

                $latestLiveMemory = \App\Models\Ai\AiChatMemory::where('session_id', '!=', $sessionId)
                    ->where('session_id', 'like', 'android_live_%')
                    ->whereIn('role', ['user', 'assistant'])
                    ->where('created_at', '>=', $cutoff)
                    ->where(function ($q) use ($currentUserId, $isAdminUser) {
                        $q->where('context_data->user_id', (string) $currentUserId);
                        if ($isAdminUser) {
                            $q->orWhereNull('context_data->user_id');
                        }
                    })
                    ->orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                $fallbackSessionId = null;
                if ($latestUserSessionId && $latestLiveMemory) {
                    $sessionLatestAt = \App\Models\Ai\AiChatMemory::where('session_id', $latestUserSessionId)
                        ->whereIn('role', ['user', 'assistant'])
                        ->max('created_at');
                    $liveTs = $latestLiveMemory->created_at?->getTimestamp() ?? 0;
                    $sessTs = $sessionLatestAt ? strtotime((string) $sessionLatestAt) : 0;
                    $fallbackSessionId = $liveTs >= $sessTs ? (string) $latestLiveMemory->session_id : (string) $latestUserSessionId;
                } elseif ($latestLiveMemory) {
                    $fallbackSessionId = (string) $latestLiveMemory->session_id;
                } elseif ($latestUserSessionId) {
                    $fallbackSessionId = (string) $latestUserSessionId;
                }

                if ($fallbackSessionId !== null && $fallbackSessionId !== '') {
                    $rawHistory = \App\Models\Ai\AiChatMemory::where('session_id', $fallbackSessionId)
                        ->whereIn('role', ['user', 'assistant'])
                        ->where('created_at', '>=', $cutoff)
                        ->orderBy('created_at', 'desc')
                        ->orderBy('id', 'desc')
                        ->take(14)
                        ->get()
                        ->reverse()
                        ->values();
                    $isFallbackHistory = $rawHistory->isNotEmpty();
                }
            }

            $cleanHistory = [];
            foreach ($rawHistory as $msg) {
                $contentText = trim((string) ($msg->content ?? ''));
                if ($contentText === '') {
                    continue;
                }
                if ($msg->role === 'assistant' && self::isCannedModelRefusal($contentText)) {
                    if (!empty($cleanHistory) && end($cleanHistory)->role === 'user') {
                        array_pop($cleanHistory);
                    }
                    continue;
                }
                $cleanHistory[] = $msg;
            }
            $cleanHistory = array_slice($cleanHistory, -20);
            if (count($cleanHistory) > 0) {
                $historyHeader = $isFallbackHistory
                    ? "\n\n--- LETZTER GESPRÄCHSVERLAUF (ZUR NAHTLOSEN FORTSETZUNG) ---\n"
                    : "\n\n--- BISHERIGER CHAT-VERLAUF DIESER SESSION (ZUR ERINNERUNG) ---\n";
                $historyText = $historyHeader;
                foreach ($cleanHistory as $msg) {
                    $roleName = strtoupper((string) $msg->role);
                    $ctx = is_array($msg->context_data) ? $msg->context_data : [];
                    $authorName = (string) ($ctx['name'] ?? ucfirst((string) $msg->role));
                    $historyText .= "[{$roleName} / {$authorName}]: " . mb_substr((string) $msg->content, 0, 1200) . "\n";
                }
                $systemInstruction .= $historyText . "\n--- ENDE CHAT-VERLAUF ---\nSetze das Gespräch natürlich fort. Beziehe dich bei Fragen zum Chatverlauf auf diese Einträge.";
            }
        }

        // Retrieve Vertex AI / Gemini Live credentials
        $apiKey = config('services.gemini.key');

        if (!\App\Services\AI\VertexAiService::hasCredentials()) {
            \App\Services\System\SystemErrorLogger::recordCrossPlatformEvent([
                'platform' => 'portal',
                'severity' => 'error',
                'component' => 'ai_live_credentials',
                'title' => 'Gemini/Vertex Live Zugangsdaten fehlen in der Konfiguration',
                'message' => 'Aufruf von /api/ai/live-credentials schlug fehl, da weder Vertex AI Service Account noch config(services.gemini.key) aktiv ist.',
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

        // Für den Echtzeit-Sprachmodus (Gemini Live) optimieren wir die Tools VOR dem JSON-Deep-Clone auf die hochrelevanten
        // Sprach- und Interaktions-Funktionen und cachen die bereinigten Deklarationen (spart ~80% CPU/Serialisierung beim Verbindungsaufbau).
        $blockadeContext = ['items' => [], 'directive' => '', 'proactive_kick_prompt' => null];
        if ($isAdminUser && !$isFunkiAgent) {
            $systemInstruction .= "\n\n" . $this->buildSubdomainReadinessDirective();
            $blockadeContext = $this->buildPendingAgBlockadeContext();
            if ($blockadeContext['directive'] !== '') {
                $systemInstruction .= "\n\n" . $blockadeContext['directive'];
            }
            if (class_exists(\App\Models\Ai\AiWorkspaceDocument::class) && \Illuminate\Support\Facades\Schema::hasTable('ai_workspace_documents')) {
                $latestLiveScans = \Illuminate\Support\Facades\Cache::remember('ai_workspace_latest_3_docs', 30, function () {
                    $scans = \App\Models\Ai\AiWorkspaceDocument::onlyScans()->orderBy('created_at', 'desc')->take(2)->get();
                    $others = \App\Models\Ai\AiWorkspaceDocument::orderBy('created_at', 'desc')->take(3)->get();
                    return $scans->concat($others)->unique('id')->sortByDesc(fn ($d) => $d->created_at?->getTimestamp() ?? 0)->take(3)->values();
                });
                if ($latestLiveScans->isNotEmpty()) {
                    $scanLines = ["[ZULETZT EINGESCANNTE / ERFASSTE DOKUMENTE (CHRONOLOGISCH)]:"];
                    foreach ($latestLiveScans as $idx => $d) {
                        $scanTime = $d->created_at ? $d->created_at->format('d.m.Y H:i') : 'k.A.';
                        $tagLabel = (method_exists($d, 'isScanDocument') && $d->isScanDocument()) ? 'SCAN' : 'DOKUMENT';
                        $scanLines[] = ($idx + 1) . ". [{$tagLabel}] {$d->title} ({$d->filename}, am {$scanTime}, Pfad: {$d->file_path})";
                    }
                    $systemInstruction .= "\n\n" . implode("\n", $scanLines);
                }
            }
            if (class_exists(\App\Services\AI\ConceptLifecycleService::class)) {
                $openConceptsLive = \App\Services\AI\ConceptLifecycleService::buildCompactOpenConceptsContext();
                if ($openConceptsLive !== '') {
                    $systemInstruction .= "\n\n" . $openConceptsLive;
                }
            }
        }

        $voiceAllowedTools = [
            'system_open_nav_item', 'system_open_zentrum', 'system_close_zentrum', 'system_close_ui',
            'system_trigger_ui_element',
            'task_get_all', 'task_get_lists', 'task_create', 'task_add_subtask', 'task_update', 'task_complete',
        ];
        if ($isAdminUser && !$isFunkiAgent) {
            $voiceAllowedTools = array_merge($voiceAllowedTools, [
                'ag_resolve_pending_blockade',
                'ag_list_pending_blockades',
                'ag_send_to_antigravity',
                'ag_check_antigravity_status',
                'system_search_web',
                'system_read_web_url',
                'system_get_server_status',
                'system_get_health',
                'system_get_error_sentinel',
                'master_get_next_best_actions',
                'funkira_equip_skill_pool',
                'calendar_get_events', 'calendar_create_event', 'calendar_send_ics_email', 'calendar_update_event', 'calendar_delete_event',
                'routine_get_day_routines', 'routine_get_evaluation', 'routine_log_progress', 'routine_log_step', 'routine_set_sick_day',
                'routine_create', 'routine_update', 'routine_delete', 'routine_manage_steps',
                'email_send_message', 'mail_list_pending', 'mail_read',
                'contact_search', 'contact_get_all',
                'finance_get_liquidity_status', 'finance_get_fixed_costs', 'finance_create_expense',
                'order_get_current_express_status', 'order_get_urgent_tasks', 'order_get_details',
                'product_check_inventory', 'brain_search', 'concept_manage', 'workspace_get_latest_uploads',
                'task_read_file', 'task_delete', 'task_list_add', 'task_list_update', 'task_list_delete',
            ]);
        }

        $allowedMap = array_fill_keys($voiceAllowedTools, true);
        $useAgentSpecificTools = ($aiAgent && array_key_exists('tools', $payload) && !$isFunkiraAgent);
        $declCacheKey = 'gemini_live_decls_v3_' . ($useAgentSpecificTools ? ('agent_' . $aiAgent->id) : 'registry') . '_' . substr(sha1(implode(',', $voiceAllowedTools)), 0, 16);

        $functionDeclarations = \Illuminate\Support\Facades\Cache::remember($declCacheKey, 600, function () use ($useAgentSpecificTools, $payload, $allowedMap) {
            $schemaTools = $useAgentSpecificTools
                ? ($payload['tools'] ?: [])
                : \App\Services\AI\AIFunctionsRegistry::getSchema();

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

            $matched = [];
            $fallbackFirst20 = [];
            foreach ($schemaTools as $tool) {
                if (!isset($tool['function'])) {
                    continue;
                }
                $fnName = (string) ($tool['function']['name'] ?? '');
                $isAllowed = isset($allowedMap[$fnName]);
                if (!$isAllowed && count($fallbackFirst20) >= 20) {
                    continue;
                }
                $func = json_decode(json_encode($tool['function']));
                $removeAdditionalProperties($func);
                if ($isAllowed) {
                    $matched[] = $func;
                } elseif (count($fallbackFirst20) < 20) {
                    $fallbackFirst20[] = $func;
                }
            }

            return !empty($matched) ? $matched : $fallbackFirst20;
        });

        $token = \Illuminate\Support\Str::random(40);
        
        $user = auth()->user() ?: auth('admin')->user() ?: \App\Services\AI\AiAuthHelper::getUser();
        
        $liveEndpoint = \App\Services\AI\VertexAiService::resolveLiveWebSocketEndpoint();

        // Cache credentials for 5 minutes (written redundantly to default + file store so transient Redis failovers never invalidate Live-Call handshakes)
        $liveTokenPayload = [
            'api_key' => $apiKey,
            'is_vertex' => $liveEndpoint['is_vertex'],
            'vertex_access_token' => $liveEndpoint['is_vertex'] ? $liveEndpoint['token'] : null,
            'vertex_host' => $liveEndpoint['host'],
            'vertex_path' => $liveEndpoint['path'],
            'live_model' => $liveEndpoint['model'],
            'system_instruction' => $systemInstruction,
            'voice_name' => $voiceName,
            'tools' => [['functionDeclarations' => $functionDeclarations]],
            'chat_session_id' => $sessionId,
            'agent_id' => $aiAgent ? $aiAgent->id : null,
            'agent_name' => $agentName,
            'user_id' => $user ? $user->id : null,
            'user_name' => $user ? $user->first_name : 'User',
            'user_picture' => ($user && $user->profile) ? $user->profile->photo_path : null,
            'pending_ag_blockades' => $blockadeContext['items'],
            'proactive_kick_prompt' => $blockadeContext['proactive_kick_prompt'],
        ];
        $liveTokenCacheKey = 'gemini_live_token_' . $token;
        $liveTokenTtl = now()->addMinutes(5);
        try {
            \Illuminate\Support\Facades\Cache::put($liveTokenCacheKey, $liveTokenPayload, $liveTokenTtl);
        } catch (\Throwable) {
        }
        try {
            if (config('cache.default') !== 'file') {
                \Illuminate\Support\Facades\Cache::store('file')->put($liveTokenCacheKey, $liveTokenPayload, $liveTokenTtl);
            }
        } catch (\Throwable) {
        }

        $configuredWsUrl = config('services.gemini.proxy_ws_url');
        $requestHost = request()->getHost();
        if ($configuredWsUrl && !in_array($requestHost, ['127.0.0.1', 'localhost', '::1']) && (str_contains($configuredWsUrl, '127.0.0.1') || str_contains($configuredWsUrl, 'localhost'))) {
            $wsUrl = str_replace(['127.0.0.1', 'localhost'], $requestHost, $configuredWsUrl);
        } else {
            $wsUrl = $configuredWsUrl ?: 'ws://' . $requestHost . ':8089/gemini-live';
        }

        $isStrictAdmin = $isAdminUser
            || ($request->user() instanceof \App\Models\Admin\Admin)
            || (auth('admin')->user() instanceof \App\Models\Admin\Admin)
            || (config('auth.guards.sanctum') && auth('sanctum')->user() instanceof \App\Models\Admin\Admin);

        $wantsDirect = $isStrictAdmin && (
            $request->query('fallback') === 'direct'
            || in_array($request->query('client'), ['android_native', 'android_secretary', 'minipc'], true)
        );

        $originUrl = rtrim((string) (config('app.url') ?: ($request->getSchemeAndHttpHost())), '/');

        $responsePayload = [
            'token' => $token,
            'chat_session_id' => $sessionId,
            'ws_url' => $wsUrl,
            'live_model' => $liveEndpoint['model'],
            'origin_url' => $originUrl,
            'system_instruction' => $systemInstruction,
            'voice_name' => $voiceName,
            'agent_id' => $aiAgent ? $aiAgent->id : null,
            'agent_name' => $agentName,
            'tools' => [['functionDeclarations' => $functionDeclarations]],
        ];

        if ($isAdminUser && !$isFunkiAgent) {
            $responsePayload['pending_ag_blockades'] = $blockadeContext['items'];
            $responsePayload['proactive_kick_prompt'] = $blockadeContext['proactive_kick_prompt'];
            $liveBehaviorBlock = \App\Services\AI\AiBehaviorAnalysisService::buildActiveBehaviorDirectivesBlock(
                (string) ($livePresence['channel'] ?? 'android_live_call'),
                $aiAgent,
                !empty($livePresence['visitor_sandbox_locked'])
            );
            if ($liveBehaviorBlock !== '') {
                $responsePayload['behavior_prompt_block'] = $liveBehaviorBlock;
            }
        }

        if ($wantsDirect) {
            $responsePayload['direct_ws_url'] = $liveEndpoint['direct_ws_url'];
        }

        if ($isStrictAdmin) {
            $vertexBundle = \App\Services\AI\VertexAiService::buildEphemeralClientBundle();
            if ($vertexBundle !== null) {
                $responsePayload['vertex_ephemeral'] = $vertexBundle;
                $responsePayload['access_token'] = $vertexBundle['access_token'];
                $responsePayload['expires_in_seconds'] = $vertexBundle['expires_in_seconds'];
                $responsePayload['project_id'] = $vertexBundle['project_id'];
                $responsePayload['rest_models_base_url'] = $vertexBundle['rest_models_base_url'];
                $responsePayload['live_ws_url'] = $vertexBundle['live_ws_url'];
            }
        }

        return response()->json($responsePayload, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Kurzlebiger Zero-Secret Vertex-OAuth2-Token-Broker für authentifizierte Administratoren (z. B. Android-App).
     * Gibt niemals den Service-Account-Schlüssel heraus, sondern nur einen ~50 Min. gültigen ya29.* Bearer-Token
     * samt Vertex-Endpunkten.
     */
    public function vertexEphemeral(Request $request)
    {
        $user = $request->user() ?: auth('sanctum')->user() ?: auth('admin')->user();
        $isStrictAdmin = \App\Services\AI\AiAuthHelper::isAdmin()
            || ($user instanceof \App\Models\Admin\Admin);

        if (!$isStrictAdmin) {
            $status = (\App\Services\AI\AiAuthHelper::check()
                || \App\Services\AI\AiAuthHelper::isExternalPartner()
                || auth('sanctum')->check()
                || auth('employee')->check()) ? 403 : 401;

            return response()->json([
                'status' => 'error',
                'error' => $status === 403 ? 'Forbidden' : 'Unauthorized',
                'message' => 'Zugriff verweigert: Kurzlebige Vertex-Tokens sind ausschließlich für Administratoren freigegeben.',
            ], $status, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        }

        $forceRefresh = (bool) $request->boolean('refresh', false);
        $bundle = \App\Services\AI\VertexAiService::buildEphemeralClientBundle($forceRefresh);

        if ($bundle === null) {
            return response()->json([
                'status' => 'error',
                'error' => 'VertexUnavailable',
                'message' => 'Vertex AI Service Account ist aktuell nicht aktiv oder OAuth2-Token konnte nicht bezogen werden.',
            ], 503, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        }

        return response()->json(array_merge(['status' => 'ok'], $bundle), 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Erzeugt den proaktiven und beharrlichen System-Kontext bei offenen Antigravity-Freigaben / Rückfragen.
     *
     * @return array{items: array<int, array<string, mixed>>, directive: string, proactive_kick_prompt: string|null}
     */
    private function buildPendingAgBlockadeContext(): array
    {
        try {
            $inbox = app(\App\Services\System\AntigravityDirectInboxService::class);
            $blockades = $inbox->getPendingBlockades();
        } catch (\Throwable) {
            $blockades = [];
        }

        if (empty($blockades)) {
            return [
                'items' => [],
                'directive' => '',
                'proactive_kick_prompt' => null,
            ];
        }

        $count = count($blockades);
        $lines = [
            '=== AKTIVER FREIGABE- & RÜCKFRAGE-STAU BEI ANTIGRAVITY (HÖCHSTE PRIORITÄT) ===',
            "ACHTUNG: Aktuell warten {$count} Aufgabe(n) bzw. Konzept(e) von Antigravity dringend auf eine Freigabe oder Information von Alina:",
        ];

        foreach ($blockades as $idx => $item) {
            $num = $idx + 1;
            $id = (string) ($item['id'] ?? '');
            $channel = (string) ($item['channel'] ?? 'leitung');
            $title = (string) ($item['title'] ?? '');
            $question = (string) ($item['pending_question'] ?? $item['ag_response_summary'] ?? 'Wartet auf dein Go.');
            $mins = (int) ($item['waiting_minutes'] ?? 0);
            $lines[] = "{$num}. [ID: {$id} | Kanal: {$channel} | Wartet seit: {$mins} Min.] Titel: \"{$title}\" | Offene Rückfrage / Konzept-Status: \"{$question}\"";
        }

        $lines[] = '';
        $lines[] = 'VERBINDLICHES PFLICHT-VERHALTEN FÜR DICH ALS FALCONE:';
        $lines[] = '1. PROAKTIVER SOFORT-HINWEIS: Sprich diesen offenen Freigabe- oder Informations-Stau SOFORT zu Gesprächsbeginn von dir aus aktiv an! Nenne Alina kurz den Titel und die exakte offene Frage von Antigravity.';
        $lines[] = '2. BEHARRLICH AUF ANTWORT BESTEHEN: Bestehe freundlich, aber beharrlich und unnachgiebig auf einer klaren Antwort, falls es irgendwo hakt! Wenn Alina ausweicht oder das Thema wechselt, ohne die Blockade zu klären, erinnere sie aktiv daran, dass Antigravity genau an dieser Stelle blockiert ist und auf ihre Entscheidung wartet, bis sie entweder ihr "Go" bzw. die fehlende Information gibt, "Verwerfen" sagt oder ausdrücklich "Später erinnern" anweist.';
        $lines[] = '3. SOFORTIGE STAU-AUFLÖSUNG PER WERKZEUG: Sobald Alina antwortet oder ihr Go gibt, rufe SOFORT das Werkzeug `ag_resolve_pending_blockade` (mit `item_id`, `decision` = `approved` / `info_provided` / `dismissed` / `deferred` und `answer_text` = Alinas exakte Antwort) auf, damit ihre Entscheidung direkt in den richtigen Antigravity-Kanal zugestellt wird und die Blockade verschwindet!';
        $lines[] = '=== ENDE FREIGABE-STAU-DIREKTIVE ===';

        $first = $blockades[0];
        $firstTitle = (string) ($first['title'] ?? '');
        $firstQuestion = mb_substr((string) ($first['pending_question'] ?? 'Wartet auf deine Freigabe.'), 0, 260);
        $kickPrompt = "SYSTEM-STARTIMPULS FÜR FALCONE: Begrüße Alina kurz und sprich SOFORT von dir aus die offene Antigravity-Blockade an "
            . "({$count} wartende Freigabe(n), zuerst im Kanal '{$first['channel']}': \"{$firstTitle}\", Rückfrage: \"{$firstQuestion}\"). "
            . "Bestehe freundlich und beharrlich auf einer klaren Entscheidung oder Antwort, damit du sie sofort per ag_resolve_pending_blockade an Antigravity übermitteln kannst.";

        return [
            'items' => $blockades,
            'directive' => implode("\n", $lines),
            'proactive_kick_prompt' => $kickPrompt,
        ];
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
        if (!is_string($token) || trim($token) === '' || strlen($token) > 128 || preg_match('/\s/', $token)) {
            return response()->json(['error' => 'Token missing'], 400);
        }
        $token = trim($token);

        $cacheKey = 'gemini_live_token_' . $token;
        $data = null;
        try {
            $data = \Illuminate\Support\Facades\Cache::pull($cacheKey);
        } catch (\Throwable) {
        }
        try {
            $fileData = \Illuminate\Support\Facades\Cache::store('file')->pull($cacheKey);
            if (!$data && $fileData) {
                $data = $fileData;
            }
        } catch (\Throwable) {
        }

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
        $sanctumAdmin = auth('sanctum')->user();
        $isAuthorizedAdminClient = ($sanctumAdmin instanceof \App\Models\Admin\Admin)
            || \App\Services\AI\AiAuthHelper::isAdmin();

        if (!\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($request) && !$isAuthorizedAdminClient) {
            \App\Services\System\SystemSecurityService::logEvent(
                'security:bridge_transcript_unauthorized',
                'Unautorisierter Transcript-Injection-Versuch blockiert',
                'Externer Zugriffsversuch auf /api/ai/save-live-transcript von IP ' . $request->ip() . ' abgewehrt.',
                'warning'
            );
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $sessionId = trim((string) ($request->input('session_id') ?: $request->input('chat_session_id', '')));
        $role = trim((string) $request->input('role', 'assistant'));
        $content = trim((string) $request->input('content', ''));
        $rawAgentId = $request->input('agent_id');
        $agentId = is_string($rawAgentId) && trim($rawAgentId) !== '' ? trim($rawAgentId) : null;
        $rawAgentName = $request->input('agent_name');
        $agentName = is_string($rawAgentName) && trim($rawAgentName) !== '' ? trim($rawAgentName) : null;
        $rawUserId = $request->input('user_id');
        $userId = (is_string($rawUserId) && trim($rawUserId) !== '')
            ? trim($rawUserId)
            : ($sanctumAdmin instanceof \App\Models\Admin\Admin
                ? (string) $sanctumAdmin->id
                : (\App\Services\AI\AiAuthHelper::getUserId() ? (string) \App\Services\AI\AiAuthHelper::getUserId() : null));

        if ($content === '' || $sessionId === '') {
            return response()->json(['error' => 'Missing session_id or content'], 400);
        }

        if (strlen($content) > 50000 || strlen($sessionId) > 100) {
            return response()->json(['error' => 'Payload limits exceeded (content max 50000 chars, session_id max 100 chars)'], 422);
        }

        // Security: Never allow external injection of 'system' role prompts into AiChatMemory
        if (!in_array($role, ['user', 'assistant'], true)) {
            $role = 'assistant';
        }

        // Never poison AiChatMemory with canned model refusals ("Ich bin nur ein Sprachmodell...")
        if ($role === 'assistant' && self::isCannedModelRefusal($content)) {
            return response()->json([
                'status' => 'skipped_refusal',
                'content' => ''
            ]);
        }

        $transcriptChannel = \App\Services\AI\AiBehaviorAnalysisService::normalizeChannel(
            (string) ($request->input('channel') ?: 'android_live_call')
        );

        // Security: Prevent cross-user session transcript injection if session belongs to a different user
        $targetSession = \App\Models\Ai\AiChatSession::find($sessionId);
        if ($targetSession && !empty($targetSession->user_id) && !empty($userId) && (string) $targetSession->user_id !== (string) $userId) {
            return response()->json(['error' => 'Session ownership mismatch'], 403);
        }
        if (!$targetSession && !empty($userId) && \Illuminate\Support\Str::isUuid($sessionId)) {
            try {
                $targetSession = \App\Models\Ai\AiChatSession::firstOrCreate(
                    ['id' => $sessionId],
                    [
                        'user_id' => $userId,
                        'title' => $transcriptChannel === 'android_live_call' ? 'Android Live-Call' : 'Live-AI Gespräch',
                        'is_archived' => false,
                    ]
                );
            } catch (\Throwable) {
            }
        }
        $transcriptVisitorLocked = ($transcriptChannel === 'workshop_minipc')
            && !$request->boolean('camera_alina_verified', true)
            && !$request->boolean('voice_alina_verified', true);

        $captureTranscriptBehavior = function (string $memId, string $textContent) use ($role, $sessionId, $agentId, $agentName, $transcriptChannel, $transcriptVisitorLocked): void {
            if ($role !== 'user' || $transcriptVisitorLocked) {
                return;
            }
            try {
                $targetAgent = !empty($agentId)
                    ? \App\Models\Ai\AiAgent::find($agentId)
                    : (!empty($agentName) ? \App\Models\Ai\AiAgent::where('name', $agentName)->first() : \App\Models\Ai\AiAgent::where('name', 'Funkira')->first());
                $prevAssistant = \App\Models\Ai\AiChatMemory::where('session_id', $sessionId)
                    ->where('role', 'assistant')
                    ->latest('created_at')
                    ->value('content');

                \App\Services\AI\AiBehaviorAnalysisService::scanAndRecordImplicitMessage(
                    $textContent,
                    $targetAgent,
                    $transcriptChannel,
                    [
                        'agent_name' => $agentName,
                        'session_id' => $sessionId,
                        'ai_chat_memory_id' => $memId,
                        'agent_excerpt' => $prevAssistant,
                        'visitor_sandbox_locked' => $transcriptVisitorLocked,
                    ]
                );
            } catch (\Throwable) {
            }
        };

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
                $captureTranscriptBehavior((string) $recent->id, $content);
                \App\Models\Ai\AiChatSession::where('id', $sessionId)->update(['updated_at' => now()]);
                \App\Services\System\RealtimeBroadcastService::dispatch(
                    new \App\Events\AiFrontendEvent('chat-memory-updated', [
                        'session_id' => $sessionId,
                        'message_id' => $recent->id,
                        'role' => $role,
                        'content' => $content
                    ]),
                    'AIController::chatMemoryExtended'
                );
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
                'user_id' => $userId ? (string) $userId : null,
                'channel' => $transcriptChannel,
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
                'user_id' => $userId ? (string) $userId : null,
                'channel' => $transcriptChannel,
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

        $captureTranscriptBehavior((string) $memory->id, $content);

        \App\Models\Ai\AiChatSession::where('id', $sessionId)->update(['updated_at' => now()]);

        \App\Services\System\RealtimeBroadcastService::dispatch(
            new \App\Events\AiFrontendEvent('chat-memory-updated', [
                'session_id' => $sessionId,
                'message_id' => $memory->id,
                'role' => $role,
                'content' => $memory->content
            ]),
            'AIController::chatMemoryCreated'
        );

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
            $sessionId = $request->hasSession() ? $request->session()->getId() : session()->getId();
        }
        if ($request->hasSession()) {
            try {
                $request->session()->save();
            } catch (\Throwable) {
            }
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
     * Löst einen explizit angeforderten aktiven KI-Agenten über agent_id, agent_slug oder agent_name auf.
     */
    private function resolveRequestedAgent(Request $request): ?\App\Models\Ai\AiAgent
    {
        $rawId = trim((string) $request->input('agent_id', ''));
        if ($rawId !== '') {
            $byId = \App\Models\Ai\AiAgent::where('is_active', true)->find($rawId);
            if ($byId) {
                return $byId;
            }
            $byIdAsName = $this->findActiveAgentByNameOrSlug($rawId);
            if ($byIdAsName) {
                return $byIdAsName;
            }
        }

        foreach (['agent_slug', 'agent_name'] as $param) {
            $rawVal = trim((string) $request->input($param, ''));
            if ($rawVal !== '') {
                $found = $this->findActiveAgentByNameOrSlug($rawVal);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function findActiveAgentByNameOrSlug(string $value): ?\App\Models\Ai\AiAgent
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if ($normalized === '') {
            return null;
        }

        $candidates = [$normalized];
        $deSlugged = str_replace(['-', '_'], ' ', $normalized);
        if ($deSlugged !== $normalized) {
            $candidates[] = $deSlugged;
        }
        // Legacy-Anfragen für Falcone/Falcon werden nahtlos auf Funkira gemappt
        if (in_array($normalized, ['falcon', 'falcone'], true)) {
            $candidates = ['funkira'];
        }

        foreach (array_values(array_unique($candidates)) as $cand) {
            $agent = \App\Models\Ai\AiAgent::where('is_active', true)
                ->whereRaw('LOWER(name) = ?', [$cand])
                ->first();
            if ($agent) {
                return $agent;
            }
        }

        return null;
    }

    /**
     * Prüft, ob ein KI-Agent die exklusive Admin-Stabschefin Funkira ist.
     */
    private function isAdminOnlyAgent(\App\Models\Ai\AiAgent $agent): bool
    {
        if (method_exists(\App\Services\AI\FunkiraSkillPoolService::class, 'isExecutiveRoot')) {
            return \App\Services\AI\FunkiraSkillPoolService::isExecutiveRoot($agent);
        }

        return strtolower(trim((string) ($agent->name ?? ''))) === 'funkira';
    }

    public function listUniverseTickets(Request $request)
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            return response()->json(['status' => 'error', 'message' => 'Zugriff verweigert.'], 403);
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('ai_universe_tickets')) {
            return response()->json([
                'status' => 'success',
                'tickets' => [],
                'counts' => ['waiting_for_alina' => 0, 'in_progress' => 0, 'completed' => 0],
            ]);
        }

        \App\Services\AI\AntigravityUniverseTicketBridge::reconcileIfNeeded(20);

        $isAdmin = \App\Models\Ai\AiUniverseTicket::isStrictAdminActor();
        $status = (string) $request->query('status', 'open');
        $scope = (string) $request->query('scope', 'all');
        $hasBizCol = \Illuminate\Support\Facades\Schema::hasColumn('ai_universe_tickets', 'is_business');

        $query = \App\Models\Ai\AiUniverseTicket::with(['department:id,name,color', 'agent:id,name,color', 'messages'])
            ->visibleToCurrentActor();

        if ($hasBizCol && $isAdmin) {
            if ($scope === 'business') {
                $query->where('is_business', true);
            } elseif ($scope === 'private') {
                $query->where('is_business', false);
            }
        }

        if ($status === 'waiting') {
            $query->where('status', \App\Models\Ai\AiUniverseTicket::STATUS_WAITING_FOR_ALINA);
        } elseif ($status === 'completed') {
            $query->where('status', \App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED);
        } elseif ($status === 'open') {
            $query->whereNotIn('status', [
                \App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED,
                \App\Models\Ai\AiUniverseTicket::STATUS_ARCHIVED,
            ]);
        }

        $tickets = $query->orderByDesc('last_activity_at')->limit(50)->get();

        return response()->json([
            'status' => 'success',
            'environment_tier' => \App\Services\AI\FunkiraPresenceContext::resolveEnvironmentTier(null, $request),
            'notebook_online' => \App\Services\AI\FunkiraPresenceContext::isAntigravityNotebookOnline(),
            'counts' => [
                'waiting_for_alina' => \App\Models\Ai\AiUniverseTicket::visibleToCurrentActor()->where('status', \App\Models\Ai\AiUniverseTicket::STATUS_WAITING_FOR_ALINA)->count(),
                'in_progress' => \App\Models\Ai\AiUniverseTicket::visibleToCurrentActor()->whereIn('status', [
                    \App\Models\Ai\AiUniverseTicket::STATUS_NEW,
                    \App\Models\Ai\AiUniverseTicket::STATUS_IN_PROGRESS,
                    \App\Models\Ai\AiUniverseTicket::STATUS_WAITING_FOR_TRESOR,
                    \App\Models\Ai\AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK,
                ])->count(),
                'completed' => \App\Models\Ai\AiUniverseTicket::visibleToCurrentActor()->where('status', \App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED)->count(),
                'private_open' => ($hasBizCol && $isAdmin)
                    ? \App\Models\Ai\AiUniverseTicket::where('is_business', false)->whereNotIn('status', [
                        \App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED,
                        \App\Models\Ai\AiUniverseTicket::STATUS_ARCHIVED,
                    ])->count()
                    : 0,
            ],
            'tickets' => $tickets,
        ]);
    }

    public function dispatchUniverseMessage(Request $request)
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            return response()->json(['status' => 'error', 'message' => 'Zugriff verweigert.'], 403);
        }

        $data = $request->validate([
            'prompt' => 'required|string|max:4000',
            'channel' => 'nullable|string|max:40',
            'ticket_id' => 'nullable|string|max:64',
            'active_route' => 'nullable|string|max:180',
            'camera_alina_verified' => 'nullable|boolean',
            'voice_alina_verified' => 'nullable|boolean',
            'detected_persons' => 'nullable|array|max:8',
            'is_voice_mode' => 'nullable|boolean',
        ]);

        $rawPresence = [
            'channel' => $data['channel'] ?? 'android_app',
            'active_route' => $data['active_route'] ?? null,
            'is_voice_mode' => !empty($data['is_voice_mode']),
        ];
        if (array_key_exists('camera_alina_verified', $data)) {
            $rawPresence['camera_alina_verified'] = (bool) $data['camera_alina_verified'];
        }
        if (array_key_exists('voice_alina_verified', $data)) {
            $rawPresence['voice_alina_verified'] = (bool) $data['voice_alina_verified'];
        }
        if (isset($data['detected_persons'])) {
            $rawPresence['detected_persons'] = $data['detected_persons'];
        }

        $result = \App\Services\AI\FunkiraTicketRouter::dispatchMessage(
            $data['prompt'],
            $rawPresence,
            $data['ticket_id'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'mode' => $result['mode'] ?? 'ticket',
            'reply' => $result['reply'] ?? '',
            'ticket' => $result['ticket'] ?? null,
            'presence' => $result['presence'] ?? null,
        ]);
    }

    public function performUniverseTicketAction(Request $request, string $id)
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Nur Administratoren dürfen Ticket-Freigaben auslösen.'], 403);
        }

        $data = $request->validate([
            'action' => 'required|string|max:40',
            'channel' => 'nullable|string|max:40',
            'note' => 'nullable|string|max:500',
        ]);

        $ticket = \App\Models\Ai\AiUniverseTicket::where('id', $id)
            ->orWhere('ticket_number', strtoupper($id))
            ->firstOrFail();

        $res = \App\Services\AI\FunkiraTicketRouter::performTicketAction($ticket, $data['action'], [
            'channel' => $data['channel'] ?? 'android_app',
            'note' => $data['note'] ?? '',
        ]);

        return response()->json($res);
    }

    public function pollTresorQueue()
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Nur für Admin Tresor-Worker.'], 403);
        }

        \App\Services\AI\FunkiraPresenceContext::markTresorWorkerOnline();

        if (!\Illuminate\Support\Facades\Schema::hasTable('ai_universe_tickets')) {
            return response()->json(['status' => 'success', 'orders' => []]);
        }

        $orders = \App\Models\Ai\AiUniverseTicket::query()
            ->where('status', \App\Models\Ai\AiUniverseTicket::STATUS_WAITING_FOR_TRESOR)
            ->where('ticket_type', 'procurement')
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'orders' => $orders,
        ]);
    }

    public function completeTresorOrder(Request $request, string $id)
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Nur für Admin Tresor-Worker.'], 403);
        }

        $data = $request->validate([
            'success' => 'required|boolean',
            'final_gross_cents' => 'nullable|integer|min:0',
            'summary' => 'required|string|max:1000',
        ]);

        $ticket = \App\Models\Ai\AiUniverseTicket::where('id', $id)
            ->orWhere('ticket_number', strtoupper($id))
            ->firstOrFail();

        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $maxAllowed = (int) ($payload['max_allowed_checkout_cents'] ?? 0);

        if ($data['success'] && isset($data['final_gross_cents']) && $maxAllowed > 0 && $data['final_gross_cents'] > $maxAllowed) {
            $ticket->update([
                'status' => \App\Models\Ai\AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
                'last_activity_at' => now(),
            ]);
            \App\Models\Ai\AiUniverseTicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_role' => 'agent',
                'sender_name' => 'Einkaufi',
                'channel' => 'funkenwerk_desktop',
                'content' => 'Sicherheitsabbruch im Tresor-Checkout: Live-Warenkorbpreis überschritt die 5 % Toleranzgrenze.',
            ]);
            return response()->json(['status' => 'aborted_price_guard', 'ticket' => $ticket->fresh(['messages'])], 422);
        }

        $newStatus = $data['success']
            ? \App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED
            : \App\Models\Ai\AiUniverseTicket::STATUS_WAITING_FOR_ALINA;

        \App\Models\Ai\AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'agent',
            'sender_name' => 'Einkaufi',
            'channel' => 'funkenwerk_desktop',
            'content' => \App\Services\AI\AiOutputQualityGate::sanitizeText($data['summary']),
            'executed_tools' => ['tresor_isolated_browser_checkout'],
        ]);

        $ticket->update([
            'status' => $newStatus,
            'completed_at' => $data['success'] ? now() : null,
            'messages_count' => $ticket->messages()->count(),
            'last_activity_at' => now(),
        ]);

        if ($data['success'] && !empty($payload['item_id']) && \Illuminate\Support\Facades\Schema::hasTable('ai_learned_preferences')) {
            $itemName = (string) ($payload['item_name'] ?? $ticket->title);
            $slugKey = 'procurement:' . strtolower(preg_replace('/[^a-z0-9äöüß]+/iu', '_', $itemName) ?? 'item');
            \App\Models\Ai\AiLearnedPreference::updateOrCreate(
                ['scope_key' => $slugKey],
                [
                    'ai_department_id' => $ticket->ai_department_id,
                    'subject_label' => $itemName,
                    'rule_summary' => "Bevorzugter Stamm-Artikel bei {$payload['supplier_name']} ({$payload['packs']} Gebinde via {$payload['reorder_url']}).",
                    'structured_value' => [
                        'packaging_item_id' => $payload['item_id'],
                        'preferred_packs' => (int) ($payload['packs'] ?? 1),
                        'reorder_url' => $payload['reorder_url'] ?? '',
                    ],
                    'source_ticket_id' => $ticket->id,
                    'is_active' => true,
                ]
            );
        }

        return response()->json([
            'status' => 'success',
            'ticket' => $ticket->fresh(['messages']),
        ]);
    }

    public function antigravityHeartbeat()
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Nur für Admin Antigravity-Bridge.'], 403);
        }

        \App\Services\AI\FunkiraPresenceContext::markAntigravityNotebookOnline();

        $queued = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('ai_universe_tickets')) {
            // Reaktiviere geparkte Wartet-auf-Notebook Tickets automatisch auf In Arbeit
            \App\Models\Ai\AiUniverseTicket::where('status', \App\Models\Ai\AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK)
                ->where('assigned_to_antigravity', true)
                ->update([
                    'status' => \App\Models\Ai\AiUniverseTicket::STATUS_IN_PROGRESS,
                    'last_activity_at' => now(),
                ]);

            $queued = \App\Models\Ai\AiUniverseTicket::with('messages')
                ->where('assigned_to_antigravity', true)
                ->whereIn('status', [
                    \App\Models\Ai\AiUniverseTicket::STATUS_NEW,
                    \App\Models\Ai\AiUniverseTicket::STATUS_IN_PROGRESS,
                ])
                ->orderBy('created_at')
                ->get();
        }

        return response()->json([
            'status' => 'success',
            'notebook_online' => true,
            'tickets' => $queued,
        ]);
    }

    /**
     * Erfasst explizites 1-Klick-Lob oder Kritik (Daumen hoch/runter + Schnellgrund) aus Web, Live-Widget, Desktop oder App.
     */
    public function storeBehaviorFeedback(Request $request)
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            return response()->json(['status' => 'error', 'message' => 'Zugriff verweigert.'], 403);
        }

        $validated = $request->validate([
            'polarity' => 'required|string|in:praise,criticism,correction',
            'channel' => 'nullable|string|max:40',
            'category' => 'nullable|string|max:40',
            'reason_key' => 'nullable|string|max:240',
            'reason_note' => 'nullable|string|max:240',
            'agent_id' => 'nullable|string|max:64',
            'agent_name' => 'nullable|string|max:80',
            'session_id' => 'nullable|string|max:191',
            'ai_chat_memory_id' => 'nullable|string|max:64',
            'user_excerpt' => 'nullable|string|max:1200',
            'agent_excerpt' => 'nullable|string|max:1200',
            'camera_alina_verified' => 'nullable|boolean',
            'voice_alina_verified' => 'nullable|boolean',
        ]);

        $channel = \App\Services\AI\AiBehaviorAnalysisService::normalizeChannel($validated['channel'] ?? 'live_widget');
        $visitorLocked = ($channel === 'workshop_minipc')
            && !$request->boolean('camera_alina_verified', true)
            && !$request->boolean('voice_alina_verified', true);

        if ($visitorLocked) {
            return response()->json([
                'status' => 'ignored_visitor_sandbox',
                'message' => 'Besuchermodus aktiv: Feedback vom persönlichen Profil isoliert.',
            ]);
        }

        $agent = null;
        if (!empty($validated['agent_id'])) {
            $agent = \App\Models\Ai\AiAgent::find($validated['agent_id']);
        }
        if (!$agent && !empty($validated['agent_name'])) {
            $agent = \App\Models\Ai\AiAgent::where('name', $validated['agent_name'])->first();
        }
        if (\App\Services\AI\AiBehaviorAnalysisService::isAgentExcluded($agent, $validated['agent_name'] ?? null)) {
            return response()->json([
                'status' => 'ignored_immutable_agent',
                'message' => 'Support-Agent Funki ist vom internen Leitungs-Verhaltensprofil getrennt.',
            ]);
        }

        $record = \App\Services\AI\AiBehaviorAnalysisService::recordExplicitReaction(
            $agent,
            $channel,
            $validated['polarity'],
            $validated['category'] ?? null,
            $validated['reason_key'] ?? $validated['reason_note'] ?? null,
            [
                'agent_name' => $validated['agent_name'] ?? null,
                'session_id' => $validated['session_id'] ?? null,
                'ai_chat_memory_id' => $validated['ai_chat_memory_id'] ?? null,
                'user_excerpt' => $validated['user_excerpt'] ?? null,
                'agent_excerpt' => $validated['agent_excerpt'] ?? null,
                'visitor_sandbox_locked' => false,
            ]
        );

        return response()->json([
            'status' => 'success',
            'feedback_id' => $record?->id,
            'polarity' => $record?->polarity,
            'category' => $record?->category,
            'reason_note' => $record?->reason_note,
        ]);
    }

    /**
     * Liefert das aggregierte Verhaltensprofil & die Kanal-Matrix für Admin-Cockpits.
     */
    public function getBehaviorProfile(Request $request)
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Nur für Administratoren freigegeben.'], 403);
        }

        $windowDays = (int) $request->query('days', 30);
        $channel = (string) $request->query('channel', 'all');
        $agentId = $request->query('agent_id') ? (string) $request->query('agent_id') : null;

        $profile = \App\Services\AI\AiBehaviorAnalysisService::rebuildProfile($agentId, $channel, $windowDays);
        $matrix = \App\Services\AI\AiBehaviorAnalysisService::buildChannelMatrix($windowDays, $agentId);

        return response()->json([
            'status' => 'success',
            'profile' => $profile,
            'channel_matrix' => $matrix,
        ]);
    }

    /**
     * Verbindliche Direktive für Falcone & Funkira zur sofortigen, rückfragefreien Auskunft
     * über alle Seelenfunke-Domains und Subdomains.
     */
    private function buildSubdomainReadinessDirective(): string
    {
        $directive = "SUBDOMAIN- & SERVER-STATUS-DIREKTIVE (SOFORTIGE AUSKUNFT OHNE RÜCKFRAGEN):\n"
            . "- Sobald Alina nach einer Domain oder Subdomain (`mein-seelenfunke.de`, `www.mein-seelenfunke.de`, `stage.mein-seelenfunke.de`, `ws.mein-seelenfunke.de`, `api-live-bridge.mein-seelenfunke.de`) fragt, stelle NIEMALS Gegenfragen und bitte NIEMALS um Präzisierung!\n"
            . "- Rufe SOFORT `system_get_server_status` auf und nenne direkt in einem Guss alle Fakten aus `subdomains` bzw. `live_domains`: DNS/Ingress-Status (`dns_configured`), HTTP-/Wartungs-/SSL-Status sowie Antwortzeiten und den 4-Säulen-Status.";

        try {
            $inv = \App\Services\AI\AIFunctionsRegistry::loadSanitizedSmartHomeInventory();
            $shNames = array_map(static fn($s) => $s['label'] . ' (' . $s['closure_percent'] . '%)', $inv['shutters'] ?? []);
            $plNames = array_map(static fn($p) => $p['name'], $inv['plugs'] ?? []);
            $grLines = array_map(static function ($g) {
                $members = array_merge($g['shutter_targets'] ?? [], $g['plug_targets'] ?? []);
                return $g['name'] . ' [' . implode(', ', $members) . ']';
            }, $inv['groups'] ?? []);

            $shLine = !empty($shNames) ? implode(', ', $shNames) : 'Connexoon Window Somfy bereit';
            $plLine = !empty($plNames) ? implode(', ', $plNames) : 'Keine Steckdosen hinterlegt';
            $grLine = !empty($grLines) ? implode(' | ', $grLines) : 'Noch keine Gruppen hinterlegt';

            $directive .= "\n\nFUNKENWERK SMARTHOME & GRUPPEN (CONNEXOON WINDOW SOMFY + TAPO):\n"
                . "- Rollläden/Fenster: {$shLine}\n"
                . "- Steckdosen: {$plLine}\n"
                . "- Gruppen: {$grLine}\n"
                . "- Du kennst alle Einzelgeräte, Gruppen und kombinierte Befehle (z. B. 'Wohnzimmer und Terrassentür schließen' oder 'Gruppe Wohnbereich auf 50%'). Nutze auf Android im Live-Modus bevorzugt `falcon_smarthome_control` bzw. im Chat `smarthome_control` / `smarthome_get_devices_and_groups`.";
        } catch (\Throwable) {
        }

        return $directive;
    }
}

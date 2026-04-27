<?php

namespace App\Livewire\Shop\Ai\Traits;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiWorkspaceTask;
use App\Jobs\ProcessAiWorkspaceTask;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

trait ManagesAiChat
{
    public $currentChatSessionId = null;

    protected function getAiSessionId()
    {
        if (!$this->currentChatSessionId) {
            $this->loadDefaultChatSession();
        }
        return $this->currentChatSessionId;
    }

    public function loadDefaultChatSession()
    {
        if (!auth()->check()) {
            $this->currentChatSessionId = session()->getId();
            return;
        }

        $userId = auth()->id();
        $session = \App\Models\Ai\AiChatSession::where('user_id', $userId)
            ->where('is_archived', false)
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$session) {
            $session = \App\Models\Ai\AiChatSession::create([
                'user_id' => $userId,
                'title' => 'Neuer Chat',
            ]);
        }
        
        $this->currentChatSessionId = $session->id;
    }

    #[Computed]
    public function chatSessions()
    {
        return \App\Models\Ai\AiChatSession::where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    public function createNewChat()
    {
        $session = \App\Models\Ai\AiChatSession::create([
            'user_id' => auth()->id(),
            'title' => 'Neuer Chat',
        ]);
        $this->currentChatSessionId = $session->id;
        $this->messages; // trigger re-render
    }

    public function switchChat($id)
    {
        $this->currentChatSessionId = $id;
        $this->messages; // trigger re-render
    }

    public function deleteChats(array $ids)
    {
        \App\Models\Ai\AiChatSession::whereIn('id', $ids)->where('user_id', auth()->id())->delete();
        $this->currentChatSessionId = null;
        $this->loadDefaultChatSession();
    }

    public function archiveChat($id)
    {
        $chat = \App\Models\Ai\AiChatSession::where('id', $id)->where('user_id', auth()->id())->first();
        if ($chat) {
            $chat->update(['is_archived' => true]);
        }
    }

    public function updateChatTitle($title, $id = null)
    {
        if (!auth()->check()) return;
        $chatId = $id ?: $this->currentChatSessionId;
        if (!$chatId) return;
        
        $chat = \App\Models\Ai\AiChatSession::where('id', $chatId)->where('user_id', auth()->id())->first();
        if ($chat) {
            $chat->update(['title' => $title]);
        }
    }

    public $input = '';
    public $activeAgentIds = [];
    public $typingAgents = []; // Array of agent IDs currently typing
    public $attachments = []; // Files attached via @ mentions
    public $uploadedFiles = []; // Real uploaded files
    public $mentionQuery = '';
    public $mentionResults = [];
    public $forcedAgentIds = [];
    public $pendingRouterMessage = null;

    public function assignAgent($taskId, $agentId)
    {
        $task = AiWorkspaceTask::find($taskId);
        if ($task && $task->status === 'pending') {
            $task->update([
                'assigned_agent_id' => $agentId,
                'status' => 'assigned'
            ]);
            
            \App\Events\TaskUpdated::dispatch($task);
            ProcessAiWorkspaceTask::dispatch($task);
        }
    }

    #[Computed]
    public function messages()
    {
        $messages = [];
        $history = AiChatMemory::where('session_id', $this->getAiSessionId())
                               ->orderBy('created_at', 'asc')
                               ->get();

        if ($history->isNotEmpty()) {
            foreach ($history as $mem) {
                if ($mem->role === 'tool') {
                    $ctx = $mem->context_data ?? [];
                    $argsStr = json_encode($ctx['args'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $resStr = json_encode($ctx['result'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    
                    $markdown = "**⚙️ " . $mem->content . "**\n\n";
                    $markdown .= "<details><summary class='cursor-pointer text-cyan-400'>Parameter & Argumente</summary>\n\n```json\n{$argsStr}\n```\n\n</details>";
                    
                    if (strlen($resStr) < 2000) {
                        $markdown .= "<details><summary class='cursor-pointer text-emerald-400'>System-Antwort</summary>\n\n```json\n{$resStr}\n```\n\n</details>";
                    } else {
                        $markdown .= "<details><summary class='cursor-pointer text-emerald-400'>System-Antwort (Auszug)</summary>\n\n```json\n" . mb_substr($resStr, 0, 2000) . "...\n```\n\n</details>";
                    }

                    $messages[] = [
                        'role' => 'assistant',
                        'name' => 'System Execution',
                        'content' => $markdown,
                        'color' => 'purple-500',
                        'icon' => 'wrench-screwdriver',
                        'profile_picture' => null,
                        'attachments' => [],
                        'local_uploads' => [],
                    ];
                    continue;
                }

                $ctx = $mem->context_data ?? [];
                $messages[] = [
                    'role' => $mem->role,
                    'name' => $ctx['name'] ?? ucfirst($mem->role),
                    'content' => $mem->content,
                    'color' => $ctx['color'] ?? ($mem->role === 'user' ? 'gray-400' : 'emerald-500'),
                    'icon' => $ctx['icon'] ?? ($mem->role === 'user' ? 'user' : 'sparkles'),
                    'profile_picture' => $ctx['profile_picture'] ?? null,
                    'attachments' => $ctx['attachments'] ?? [],
                    'local_uploads' => $ctx['local_uploads'] ?? [],
                ];
            }
        }
        return $messages;
    }

    public function getAgentsProperty()
    {
        return AiAgent::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function getIsWorkerRunningProperty()
    {
        if (config('queue.default') === 'sync') {
            return true;
        }

        // Neue extrem zuverlässige Methode (funktioniert über Container-Grenzen hinweg)
        $cacheHit = \Illuminate\Support\Facades\Cache::has('ai-worker-heartbeat');
        if (!$cacheHit && \Illuminate\Support\Facades\Storage::disk('local')->exists('system/ai_worker_heartbeat.txt')) {
            $lastPing = (int)\Illuminate\Support\Facades\Storage::disk('local')->get('system/ai_worker_heartbeat.txt');
            if (now()->timestamp - $lastPing <= 45) {
                $cacheHit = true;
            }
        }
        
        if ($cacheHit) {
            return true;
        }

        // Alter Fallback auf Linux/Shell Ebene falls Cache fehlt
        if (function_exists('shell_exec') && !str_contains(ini_get('disable_functions'), 'shell_exec')) {
            $output = shell_exec('pgrep -f "[a]rtisan queue" 2>/dev/null');
            if (empty(trim($output))) {
                $output = shell_exec('ps ax 2>/dev/null | grep "artisan" | grep -i "queue" | grep -v grep');
            }
            return !empty(trim($output));
        }
        return true; 
    }

    #[Computed]
    public function getWorkerDiagnosticProperty()
    {
        $info = [];
        $info[] = "Treiber: " . config('queue.default');
        
        $cacheHit = \Illuminate\Support\Facades\Cache::has('ai-worker-heartbeat');
        $storageHit = false;
        if (\Illuminate\Support\Facades\Storage::disk('local')->exists('system/ai_worker_heartbeat.txt')) {
            $lastPing = (int)\Illuminate\Support\Facades\Storage::disk('local')->get('system/ai_worker_heartbeat.txt');
            if (now()->timestamp - $lastPing <= 45) {
                $storageHit = true;
            }
        }
        
        $info[] = "Cache-Signal: " . ($cacheHit ? 'OK' : 'Fehlt');
        $info[] = "NFS-Signal: " . ($storageHit ? 'OK' : 'Fehlt');
        
        if (function_exists('shell_exec') && !str_contains(ini_get('disable_functions'), 'shell_exec')) {
            $out1 = shell_exec('pgrep -f "[a]rtisan queue" 2>/dev/null');
            $info[] = "pgrep: " . (!empty(trim($out1)) ? 'Treffer' : 'Leer');
            
            $out2 = shell_exec('ps ax 2>/dev/null | grep "artisan" | grep -i "queue" | grep -v grep');
            $info[] = "ps: " . (!empty(trim($out2)) ? 'Treffer' : 'Leer');
        } else {
             $info[] = "Shell: Verboten";
        }
        
        return implode(" | ", $info);
    }

    public function toggleAgent($agentId)
    {
        $agent = AiAgent::find($agentId);
        if ($agent) {
            $agent->is_in_chat = !$agent->is_in_chat;
            $agent->save();
        }

        $this->activeAgentIds = AiAgent::where('is_in_chat', true)->pluck('id')->toArray();
        $this->forcedAgentIds = $this->activeAgentIds;
    }

    protected function saveMessageToDb($role, $content, $contextData)
    {
        AiChatMemory::create([
            'session_id' => $this->getAiSessionId(),
            'role' => $role,
            'content' => $content,
            'context_data' => $contextData,
        ]);
    }

    public function appendLiveChatMemory($role, $text)
    {
        $contextData = [];
        if ($role === 'user') {
            $user = auth()->check() ? auth()->user() : null;
            $contextData = [
                'name' => $user ? $user->first_name : 'User',
                'color' => 'gray-400',
                'icon' => 'user',
                'is_live_audio' => true,
                'profile_picture' => ($user && $user->profile) ? $user->profile->photo_path : null
            ];
        } else {
            $agentId = $this->agentId ?? ($this->activeAgentIds[0] ?? null);
            $agent = \App\Models\Ai\AiAgent::find($agentId);
            if (!$agent) {
                $agent = \App\Models\Ai\AiAgent::where('is_in_chat', true)->first();
            }
            $contextData = [
                'name' => $agent ? $agent->name : 'Funkira',
                'color' => $agent ? $agent->color : 'purple-500',
                'icon' => 'robot',
                'is_live_audio' => true,
                'profile_picture' => $agent ? $agent->profile_picture : null
            ];
        }

        $this->saveMessageToDb($role, $text, $contextData);
        unset($this->messages);
    }

    public function searchFilesForMention($query)
    {
        $this->mentionQuery = $query;
        if (strlen($query) < 2) {
            $this->mentionResults = [];
            return;
        }

        $basePath = base_path();
        $allowedDirs = ['app', 'config', 'resources', 'routes', 'database'];
        $results = [];

        foreach ($allowedDirs as $dir) {
            $path = $basePath . '/' . $dir;
            if (!is_dir($path)) continue;

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isDir()) continue;
                if (stripos($file->getFilename(), $query) !== false) {
                    $results[] = str_replace($basePath . '/', '', $file->getPathname());
                    if (count($results) >= 10) break 2;
                }
            }
        }
        
        $artifacts = $this->artifacts;
        foreach ($artifacts as $art) {
            if (str_starts_with(strtolower($query), 'plan') || stripos($art['filename'], $query) !== false) {
                array_unshift($results, 'storage/app/ai-artifacts/' . $this->getAiSessionId() . '/' . $art['filename']);
            }
        }

        $this->mentionResults = $results;
    }

    public function addAttachment($filePath)
    {
        if (!in_array($filePath, $this->attachments)) {
            $this->attachments[] = $filePath;
        }
        $this->mentionQuery = '';
        $this->mentionResults = [];
    }

    public function removeAttachment($idx)
    {
        if (isset($this->attachments[$idx])) {
            unset($this->attachments[$idx]);
            $this->attachments = array_values($this->attachments);
        }
    }

    public function removeUploadedFile($idx)
    {
        if (isset($this->uploadedFiles[$idx])) {
            unset($this->uploadedFiles[$idx]);
            $this->uploadedFiles = array_values($this->uploadedFiles);
        }
    }

    public function sendMessage()
    {
        if (trim($this->input) === '' && empty($this->attachments) && empty($this->uploadedFiles)) {
            return;
        }

        // Handle File Uploads First
        $localUploads = [];
        if (!empty($this->uploadedFiles)) {
            foreach ($this->uploadedFiles as $file) {
                $path = $file->store('agenten/workspace/chat-medien', 'public');
                $localUploads[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                ];
            }
        }

        $userCtx = [
            'name' => auth()->user()->first_name ?? 'User',
            'color' => 'gray-400',
            'icon' => 'user',
            'profile_picture' => (auth()->check() && auth()->user()->profile) ? auth()->user()->profile->photo_path : null,
        ];
        
        if (!empty($localUploads)) {
            $userCtx['local_uploads'] = $localUploads;
        }

        if (!empty($this->attachments)) {
            $userCtx['attachments'] = $this->attachments;
        }

        $this->saveMessageToDb('user', $this->input, $userCtx);

        $this->pendingRouterMessage = $this->input;

        $this->input = '';
        $this->attachments = [];
        $this->uploadedFiles = [];
        
        $this->typingAgents = array_merge($this->typingAgents, $this->activeAgentIds);
        
        $this->dispatch('start-auto-routing', targetComponentId: $this->getId());
    }

    #[On('start-auto-routing')]
    public function processAutoRouting($targetComponentId = null)
    {
        if ($targetComponentId !== null && $targetComponentId !== $this->getId()) {
            return;
        }

        $rawUserInput = $this->pendingRouterMessage;
        $this->pendingRouterMessage = null;

        if (!$rawUserInput) return;

        // Prevent parallel routing execution for the same message
        $lockKey = 'ai_routing_lock_' . $this->getAiSessionId() . '_' . md5($rawUserInput);
        $routingLock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);
        if (!$routingLock->get()) {
            return;
        }

        $routedIds = \App\Services\AI\AiAgentRouter::determineRequiredAgents($rawUserInput, $this->activeAgentIds);
        
        $finalIds = array_values(array_unique(array_merge($routedIds, $this->forcedAgentIds)));

        if (!empty($finalIds)) {
            $this->activeAgentIds = $finalIds;
            AiAgent::whereIn('id', $this->activeAgentIds)->update(['is_in_chat' => true]);
        }

        if (empty($this->activeAgentIds)) {
            $errCtx = [
                'name' => 'System',
                'color' => 'red-500',
                'icon' => 'exclamation-triangle',
                'profile_picture' => null,
            ];
            $errStr = 'FEHLER: Kein Agent für Verarbeitung ausgewählt und Auto-Routing schlug fehl. Bitte wähle mindestens einen Agenten im oberen Panel manuell aus.';
            $this->saveMessageToDb('assistant', $errStr, $errCtx);
            return;
        }

        $this->typingAgents = array_intersect($this->typingAgents, $this->activeAgentIds);
        $this->typingAgents = array_unique(array_merge($this->typingAgents, $this->activeAgentIds));

        $this->dispatch('start-ai-inference', targetComponentId: $this->getId(), agentIds: $this->activeAgentIds);
    }

    public function abortInference($agentId)
    {
        \Illuminate\Support\Facades\Cache::put('abort_ai_agent_' . $agentId, true, 60);
        $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
    }

    public function cancelTask($taskId)
    {
        $task = AiWorkspaceTask::find($taskId);
        if ($task && $task->assigned_agent_id) {
            \Illuminate\Support\Facades\Cache::put('abort_ai_agent_' . $task->assigned_agent_id, true, 60);
            
            $task->update([
                'status' => 'pending',
                'assigned_agent_id' => null,
            ]);
        }
    }

    public function pauseTask($taskId)
    {
        $task = \App\Models\Ai\AiWorkspaceTask::find($taskId);
        if ($task && $task->assigned_agent_id) {
            \Illuminate\Support\Facades\Cache::put('abort_ai_agent_' . $task->assigned_agent_id, true, 60);
            
            $task->update([
                'status' => 'paused',
            ]);
        }
    }

    public function pauseAllTasks()
    {
        $tasks = \App\Models\Ai\AiWorkspaceTask::where('status', 'processing')->get();
        foreach ($tasks as $task) {
            $this->pauseTask($task->id);
        }
    }

    #[On('start-ai-inference')]
    public function handleStartAiInference($targetComponentId = null, $agentIds = [])
    {
        if ($targetComponentId !== null && $targetComponentId !== $this->getId()) {
            return;
        }

        foreach ($agentIds as $id) {
            $this->processAgent($id);
        }
    }

    #[On('process-agent')]
    public function processAgent($agentId)
    {
        if (!in_array($agentId, $this->typingAgents)) return;

        // Prevent parallel or duplicate agent execution for the same message
        $lastUserMsg = AiChatMemory::where('session_id', $this->getAiSessionId())
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->first();
            
        $msgHash = $lastUserMsg ? md5($lastUserMsg->content) : 'empty';
        $lockKey = 'ai_inference_lock_' . $this->getAiSessionId() . '_' . $agentId . '_' . $msgHash;
        
        $inferenceLock = \Illuminate\Support\Facades\Cache::lock($lockKey, 30);
        if (!$inferenceLock->get()) {
            return;
        }

        $agent = AiAgent::find($agentId);
        if (!$agent) {
             $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
             return;
        }

        $fullDbHistory = AiChatMemory::where('session_id', $this->getAiSessionId())
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->reverse();

        $apiHistory = [];
        $collectedProjectFiles = [];
        $collectedTextUploads = [];

        foreach ($fullDbHistory as $mem) {
            if ($mem->role === 'tool') {
                $apiHistory[] = [
                    'role' => 'assistant',
                    'content' => "[SYSTEM-LOG: Du hast für diese User-Anfrage bereits ein Werkzeug eingesetzt. Das Resultat war: " . trim(strip_tags($mem->content)) . "]"
                ];
                continue;
            }

            $contentToAPI = $mem->content;
            $messageImages = [];

            if ($mem->role === 'user') {
                $ctx = $mem->context_data ?? [];

                if (!empty($ctx['attachments'])) {
                    $contentToAPI .= "\n\n[PROJEKT-DATEIEN IN DIESER NACHRICHT REFERENZIERT:]\n";
                    foreach ($ctx['attachments'] as $filePath) {
                        $fullPath = base_path($filePath);
                        if (file_exists($fullPath) && is_file($fullPath)) {
                            $lines = array_slice(file($fullPath), 0, 2000);
                            $code = rtrim(implode("", $lines));
                            $contentToAPI .= "\n--- DATEI: {$filePath} ---\n```\n{$code}\n```\n";
                        }
                    }
                }

                if (!empty($ctx['local_uploads'])) {
                    $upNames = collect($ctx['local_uploads'])->pluck('name')->implode(', ');
                    $contentToAPI .= "\n\n[LOKALE DATEI-UPLOADS IN DIESER NACHRICHT: " . $upNames . "]\n";

                    foreach ($ctx['local_uploads'] as $up) {
                        $mime = $up['mime'] ?? 'unknown';
                        $fullPath = storage_path('app/public/' . $up['path']);
                        if (!file_exists($fullPath)) {
                            $fullPath = storage_path('app/' . $up['path']);
                        }

                        if (file_exists($fullPath)) {
                            if (str_starts_with($mime, 'image/')) {
                                $base64 = base64_encode(file_get_contents($fullPath));
                                $messageImages[] = [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => 'data:' . $mime . ';base64,' . $base64
                                    ]
                                ];
                            } else {
                                $isText = str_starts_with($mime, 'text/') || in_array($mime, ['application/json', 'application/xml', 'application/javascript', 'application/x-httpd-php', 'application/csv']);
                                
                                if ($mime === 'application/pdf' && class_exists(\Smalot\PdfParser\Parser::class)) {
                                    try {
                                        $parser = new \Smalot\PdfParser\Parser();
                                        $pdf = $parser->parseFile($fullPath);
                                        $pdfText = $pdf->getText();
                                        $safePdfText = mb_convert_encoding(mb_substr($pdfText, 0, 40000, 'UTF-8'), 'UTF-8', 'UTF-8');
                                        $contentToAPI .= "\n--- DATEI: {$up['name']} (PDF TEXT-EXTRAKT) ---\n```\n" . $safePdfText . "\n```\n";
                                    } catch (\Exception $e) {
                                        $contentToAPI .= "\n--- DATEI: {$up['name']} ---\n[SYSTEM-HINWEIS: Dies ist eine PDF-Datei, aber das automatische Einlesen des Textes schlug fehl: " . $e->getMessage() . "]\n";
                                    }
                                } elseif ($isText) {
                                    $lines = array_slice(file($fullPath), 0, 2000);
                                    $safeText = mb_convert_encoding(rtrim(implode("", $lines)), 'UTF-8', 'UTF-8');
                                    $contentToAPI .= "\n--- DATEI: {$up['name']} ---\n```\n" . $safeText . "\n```\n";
                                } else {
                                    $contentToAPI .= "\n--- DATEI: {$up['name']} ---\n[SYSTEM-HINWEIS: Dies ist eine Datei vom Typ {$mime}. Binäre Dokumente, Audios, Videos oder proprietäre Formate können nicht direkt eingelesen werden. Teile dem Nutzer mit, dass die Datei erfolgreich in den Chat geladen wurde, du aber den Inhalt nicht direkt 'sehen' oder 'lesen' kannst, da dir die passenden Werkzeuge fehlen.]\n";
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($messageImages)) {
                $contentArray = [['type' => 'text', 'text' => $contentToAPI]];
                foreach ($messageImages as $img) {
                    $contentArray[] = $img;
                }
                $apiHistory[] = [
                    'role' => $mem->role,
                    'content' => $contentArray
                ];
            } else {
                $apiHistory[] = [
                    'role' => $mem->role,
                    'content' => $contentToAPI
                ];
            }
        }

        try {
            config(['ai.current_session_id' => $this->getAiSessionId()]);
            $apiService = \App\Services\AI\AiAgentFactory::make($agent);

            $response = $apiService->ask($apiHistory, function($event) use ($agentId) {
                if (($event['type'] ?? '') === 'tool_call') {
                    $toolName = $event['tool'] ?? 'System';
                    $html = '<div class="text-[10px] text-[var(--theme-color)] font-mono opacity-80 mt-1 flex items-center gap-1.5 animate-pulse"><svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Führe aus: ' . htmlspecialchars($toolName) . '</div>';
                    $this->stream('thought_' . $agentId, $html, false);
                    
                    $friendlyName = "Ich nutze ein Werkzeug.";
                    if (str_contains($toolName, 'write_artifact')) $friendlyName = "Ich generiere ein Dokument.";
                    elseif (str_contains($toolName, 'replace_file') || str_contains($toolName, 'edit')) $friendlyName = "Ich bearbeite eine Datei.";
                    elseif (str_contains($toolName, 'run_command')) $friendlyName = "Ich führe einen Befehl aus.";
                    elseif (str_contains($toolName, 'read_file') || str_contains($toolName, 'view')) $friendlyName = "Ich analysiere den Quellcode.";
                    elseif (str_contains($toolName, 'grep_search') || str_contains($toolName, 'search')) $friendlyName = "Ich suche im System.";
                    elseif (str_contains($toolName, 'list_dir')) $friendlyName = "Ich analysiere die Ordnerstruktur.";

                    // Dispatch speech event for Live Voice Feedback in UI
                    $this->dispatch('ai-speech-feedback', ['text' => $friendlyName]);
                    
                } elseif (($event['type'] ?? '') === 'thought_html') {
                    $this->stream('thought_' . $agentId, $event['html'], false);
                } elseif (($event['type'] ?? '') === 'text_chunk') {
                    $this->stream('answer_' . $agentId, $event['chunk'], false);
                }
            });
            $replyText = $response['response'] ?? '';

            if (!empty($response['events'])) {
                foreach ($response['events'] as $eventMsg) {
                    $evtType = $eventMsg['type'] ?? '';
                    if ($evtType === 'navigate' && !empty($eventMsg['url'])) {
                        $this->redirect($eventMsg['url'], navigate: true);
                        return; 
                    } elseif ($evtType === 'dispatch' || !empty($eventMsg['name'])) {
                        $name = $eventMsg['name'] ?? 'ai-global-event';
                        $detail = $eventMsg['detail'] ?? [];
                        $this->dispatch($name, payload: $detail);
                    }
                }
            }

            if (trim($replyText) === '') {
                $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
                return;
            }

            $ctx = [
                'name' => $agent->name,
                'color' => $agent->color,
                'icon' => $agent->icon,
                'profile_picture' => $agent->profile_picture,
            ];

            $this->saveMessageToDb('assistant', $replyText, $ctx);

        } catch (\Exception $e) {
            $errCtx = [
                'name' => 'System',
                'color' => 'red-500',
                'icon' => 'exclamation-triangle',
                'profile_picture' => null,
            ];
            $errStr = 'API Fehler [' . $agent->name . ']: ' . $e->getMessage();
            $this->saveMessageToDb('assistant', $errStr, $errCtx);
        }

        $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
        unset($this->artifacts);
    }

    public function clearChat()
    {
        AiChatMemory::where('session_id', $this->getAiSessionId())->delete();
        if (Storage::disk('local')->exists('agenten/ai-artifacts/' . $this->getAiSessionId())) {
            Storage::disk('local')->deleteDirectory('agenten/ai-artifacts/' . $this->getAiSessionId());
        }

        $this->typingAgents = [];
        $this->mount();
    }

    #[Computed]
    public function artifacts()
    {
        $sessionId = $this->getAiSessionId();
        $userId = auth()->id();

        $query = \App\Models\Ai\AiArtifact::query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $artifacts = $query->orderBy('updated_at', 'desc')->get();

        $artifactData = [];
        foreach ($artifacts as $artifact) {
            $artifactData[] = [
                'name' => str_replace('.md', '', $artifact->name),
                'filename' => $artifact->name,
                'content' => $artifact->content,
                'last_modified' => $artifact->updated_at->timestamp
            ];
        }

        return collect($artifactData);
    }

    #[Computed]
    public function globalFiles()
    {
        $sessionId = $this->getAiSessionId();
        $memories = AiChatMemory::where('session_id', $sessionId)->get();
        $allFiles = [];
        $seen = [];

        foreach ($memories as $mem) {
            $ctx = $mem->context_data ?? [];
            if (!empty($ctx['attachments'])) {
                foreach ($ctx['attachments'] as $att) {
                    if (!isset($seen[$att])) {
                        $allFiles[] = [
                            'type' => 'project_file',
                            'path' => $att,
                            'name' => basename($att),
                            'added_at' => $mem->created_at
                        ];
                        $seen[$att] = true;
                    }
                }
            }
            if (!empty($ctx['local_uploads'])) {
                foreach ($ctx['local_uploads'] as $up) {
                    $uniqueKey = $up['name'];
                    if (!isset($seen[$uniqueKey])) {
                        $allFiles[] = [
                            'type' => 'local_upload',
                            'path' => $up['path'],
                            'name' => basename($up['name']),
                            'mime' => $up['mime'] ?? 'unknown',
                            'added_at' => $mem->created_at
                        ];
                        $seen[$uniqueKey] = true;
                    }
                }
            }
        }

        if (!empty($this->uploadedFiles)) {
            foreach ($this->uploadedFiles as $tmpFile) {
                if ($tmpFile instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    $uniqueKey = 'tmp_' . $tmpFile->getClientOriginalName();
                    if (!isset($seen[$uniqueKey])) {
                        $allFiles[] = [
                            'type' => 'local_upload',
                            'path' => $tmpFile->getRealPath(),
                            'name' => $tmpFile->getClientOriginalName(),
                            'mime' => $tmpFile->getMimeType() ?? 'unknown',
                            'added_at' => now(),
                            'is_pending' => true,
                            'temporary_url' => (str_starts_with($tmpFile->getMimeType(), 'image/')) ? $tmpFile->temporaryUrl() : null,
                            'livewire_filename' => $tmpFile->getFilename()
                        ];
                        $seen[$uniqueKey] = true;
                    }
                }
            }
        }

        return collect($allFiles)->sortByDesc('added_at')->values();
    }

    public function removeGlobalFile($type, $path)
    {
        if ($type === 'local_upload') {
            $sessionId = $this->getAiSessionId();
            $memories = AiChatMemory::where('session_id', $sessionId)->get();

            foreach($memories as $mem) {
                $ctx = $mem->context_data ?? [];
                $changed = false;

                if (!empty($ctx['local_uploads'])) {
                    $filtered = array_filter($ctx['local_uploads'], fn($u) => $u['path'] !== $path);
                    if (count($filtered) !== count($ctx['local_uploads'])) {
                        $ctx['local_uploads'] = array_values($filtered);
                        $changed = true;
                    }
                }

                if ($changed) {
                    $mem->update(['context_data' => $ctx]);
                }
            }

            @unlink(storage_path('app/public/' . $path));
            @unlink(storage_path('app/' . $path));

        } elseif ($type === 'project_file') {
            $sessionId = $this->getAiSessionId();
            $memories = AiChatMemory::where('session_id', $sessionId)->get();

            foreach($memories as $mem) {
                $ctx = $mem->context_data ?? [];
                $changed = false;

                if (!empty($ctx['attachments'])) {
                    $filtered = array_filter($ctx['attachments'], fn($a) => $a !== $path);
                    if (count($filtered) !== count($ctx['attachments'])) {
                        $ctx['attachments'] = array_values($filtered);
                        $changed = true;
                    }
                }

                if ($changed) {
                    $mem->update(['context_data' => $ctx]);
                }
            }
        }
    }

    public function archiveTask($taskId)
    {
        $task = \App\Models\Ai\AiWorkspaceTask::find($taskId);
        if ($task) {
            $task->update(['status' => 'archived']);
        }
    }

    public function deleteTask($taskId)
    {
        $task = \App\Models\Ai\AiWorkspaceTask::find($taskId);
        if ($task) {
            $task->delete();
        }
    }

    public function restartTask($taskId)
    {
        $task = \App\Models\Ai\AiWorkspaceTask::find($taskId);
        if ($task && in_array($task->status, ['completed', 'archived', 'failed', 'paused'])) {
            $agentId = $task->assigned_agent_id;
            $task->update([
                'status' => $agentId ? 'processing' : 'pending',
                'response_content' => null,
                'completed_at' => null,
            ]);
            
            if ($agentId) {
                \Illuminate\Support\Facades\Bus::dispatch(new \App\Jobs\ProcessAiWorkspaceTask($task));
            }
        }
    }

    public function appendAndRestartTask($taskId, $addition)
    {
        if (empty(trim($addition))) return;
        
        $task = \App\Models\Ai\AiWorkspaceTask::find($taskId);
        if ($task && in_array($task->status, ['completed', 'archived', 'failed', 'paused'])) {
            $agentId = $task->assigned_agent_id;
            
            $newPrompt = $task->prompt . "\n\n--- Ergänzung / Retry ---\n" . trim($addition);
            
            $task->update([
                'prompt' => $newPrompt,
                'status' => $agentId ? 'processing' : 'pending',
                'response_content' => null,
                'completed_at' => null,
            ]);
            
            $meta = $task->ui_metadata ?? [];
            if (isset($meta['execution_plan'])) {
                unset($meta['execution_plan']);
                $task->update(['ui_metadata' => $meta]);
            }
            
            if ($agentId) {
                \Illuminate\Support\Facades\Bus::dispatch(new \App\Jobs\ProcessAiWorkspaceTask($task));
            }
        }
    }

    public function approvePlan($taskId)
    {
        $task = \App\Models\Ai\AiWorkspaceTask::find($taskId);
        if ($task && $task->status === 'awaiting_approval') {
            $task->update(['status' => 'processing']);
            \Illuminate\Support\Facades\Bus::dispatch(new \App\Jobs\ProcessAiWorkspaceTask($task));
        }
    }

    public function approvePlanAlways($taskId)
    {
        $task = \App\Models\Ai\AiWorkspaceTask::find($taskId);
        if ($task && $task->status === 'awaiting_approval') {
            $settings = \App\Models\Ai\AiUserWorkspaceSetting::first();
            if ($settings) {
                $settings->update(['auto_approve_execution_plan' => true]);
            } else {
                \App\Models\Ai\AiUserWorkspaceSetting::create(['auto_approve_execution_plan' => true]);
            }

            $task->update(['status' => 'processing']);
            \Illuminate\Support\Facades\Bus::dispatch(new \App\Jobs\ProcessAiWorkspaceTask($task));
        }
    }

    public function undoTask($taskId)
    {
        $task = \App\Models\Ai\AiWorkspaceTask::find($taskId);
        if ($task && $task->status === 'completed') {
            $undoPrompt = "Mache folgende Aufgabe rückgängig:\n\n" . $task->prompt;
            
            $newTask = \App\Models\Ai\AiWorkspaceTask::create([
                'prompt' => $undoPrompt,
                'status' => 'pending',
                'parent_task_id' => $task->id,
                'ui_metadata' => $task->ui_metadata,
            ]);
            
            if ($task->assigned_agent_id) {
                $newTask->update([
                    'assigned_agent_id' => $task->assigned_agent_id,
                    'status' => 'assigned'
                ]);
                \App\Events\TaskUpdated::dispatch($newTask);
                \Illuminate\Support\Facades\Bus::dispatch(new \App\Jobs\ProcessAiWorkspaceTask($newTask));
            }
        }
    }

    public function createTaskFromChat()
    {
        if (trim($this->input) === '' && empty($this->attachments) && empty($this->uploadedFiles)) return;

        $meta = [
            'attachments' => $this->attachments,
        ];

        $localUploads = [];
        if (!empty($this->uploadedFiles)) {
            foreach ($this->uploadedFiles as $file) {
                $path = $file->store('agenten/workspace/chat-medien', 'public');
                $localUploads[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType()
                ];
            }
            $meta['local_uploads'] = $localUploads;
        }

        $meta['session_id'] = $this->getAiSessionId();

        \App\Models\Ai\AiWorkspaceTask::create([
            'prompt' => trim($this->input),
            'status' => 'pending',
            'ui_metadata' => $meta
        ]);

        $this->input = '';
        $this->attachments = [];
        $this->uploadedFiles = [];
    }
}

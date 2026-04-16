<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;

use Livewire\Attributes\Layout;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiWorkspaceTask;
use App\Jobs\ProcessAiWorkspaceTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class AiWorkspace extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'Agenten';
    public $input = '';
    public $messages = [];
    public $activeAgentIds = [];
    public $typingAgents = []; // Array of agent IDs currently typing
    public $attachments = []; // Files attached via @ mentions
    public $uploadedFiles = []; // Real uploaded files
    public $mentionQuery = '';
    public $mentionResults = [];
    public string $activeWorkspaceView = 'workspace';
    public int $chatHeightPercent = 40;

    public function getListeners()
    {
        return [
            "echo:workspace,TaskUpdated" => '$refresh',
        ];
    }
    
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

    public function mount()
    {
        if (auth()->check()) {
            $setting = \App\Models\Ai\AiUserWorkspaceSetting::where('user_id', auth()->id())->first();
            if ($setting) {
                $this->chatHeightPercent = $setting->chat_height_percent;
            }
        }

        // Lade Chat-Historie aus der Datenbank
        $history = AiChatMemory::where('session_id', session()->getId())
                               ->orderBy('created_at', 'asc')
                               ->get();

        if ($history->isNotEmpty()) {
            foreach ($history as $mem) {
                // Ignore internal 'tool' role messages in UI rendering
                if ($mem->role === 'tool') continue;

                $ctx = $mem->context_data ?? [];
                $this->messages[] = [
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
            // Aktive Agenten aus dem Verlauf rekonstruieren? (Optional, aktuell überspringen)

            // Wähle standardmäßig den ersten verfügbaren aktiven Agenten aus
            $firstAgent = AiAgent::where('is_active', true)->first();
            if ($firstAgent && !in_array($firstAgent->id, $this->activeAgentIds)) {
                $this->activeAgentIds[] = $firstAgent->id;
            }
        } else {
            // Hole zum Start den ersten Agenten oder falle auf System zurück
            $firstAgent = AiAgent::where('is_active', true)->first();
            if ($firstAgent) {
                $this->activeAgentIds[] = $firstAgent->id;
                $this->saveMessageToDb('assistant', '> Gesicherter Chat aktiviert... Wie kann ich helfen?', [
                    'name' => $firstAgent->name,
                    'color' => $firstAgent->color,
                    'icon' => $firstAgent->icon,
                    'profile_picture' => $firstAgent->profile_picture,
                ]);
            } else {
                $this->saveMessageToDb('assistant', '> Gesicherter Chat aktiviert... Bitte Agenten aktivieren.', [
                    'name' => 'System',
                    'color' => 'emerald-500',
                    'icon' => 'sparkles',
                    'profile_picture' => null,
                ]);
            }

            // Lade die nun initial gespeicherten Nachrichten in die UI
            $this->messages = [];
            foreach (AiChatMemory::where('session_id', session()->getId())->get() as $mem) {
                if ($mem->role === 'tool') continue;
                $ctx = $mem->context_data ?? [];
                $this->messages[] = [
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

        if (function_exists('shell_exec') && !str_contains(ini_get('disable_functions'), 'shell_exec')) {
            // Methode 1: pgrep (Standard Linux)
            $output = shell_exec('pgrep -f "artisan queue" 2>/dev/null');
            
            if (empty(trim($output))) {
                // Methode 2: ps (Standard Shared Hosting / Alpine Container wie Mittwald)
                // Busybox kompatibel: Simples Piping anstelle von Regex
                $output = shell_exec('ps ax 2>/dev/null | grep "artisan" | grep -i "queue" | grep -v grep');
            }
            return !empty(trim($output));
        }
        return true; // Fallback wenn shell_exec() systemseitig verboten ist
    }

    public function toggleAgent($agentId)
    {
        if (in_array($agentId, $this->activeAgentIds)) {
            $this->activeAgentIds = array_values(array_diff($this->activeAgentIds, [$agentId]));
        } else {
            $this->activeAgentIds[] = $agentId;
        }
    }

    private function saveMessageToDb($role, $content, $contextData)
    {
        AiChatMemory::create([
            'session_id' => session()->getId(),
            'role' => $role,
            'content' => $content,
            'context_data' => $contextData,
        ]);
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

    public function removeAttachment($index)
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
        }
    }

    public function sendMessage()
    {
        if (trim($this->input) === '' && empty($this->attachments) && empty($this->uploadedFiles)) return;

        $userCtx = [
            'name' => auth()->check() ? auth()->user()->first_name : 'User',
            'color' => 'gray-400',
            'icon' => 'user',
            'profile_picture' => auth()->check() && auth()->user()->profile ? auth()->user()->profile->photo_path : null,
            'attachments' => $this->attachments,
        ];

        // Process real file uploads
        $localUploads = [];
        if (!empty($this->uploadedFiles)) {
            foreach ($this->uploadedFiles as $file) {
                // Store in public disk so that Storage::url() works in blade
                $path = $file->store('ai-chat-uploads', 'public');
                $localUploads[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType()
                ];
            }
            $userCtx['local_uploads'] = $localUploads;
        }

        // UI Update
        $this->messages[] = [
            'role' => 'user',
            'name' => $userCtx['name'],
            'content' => $this->input,
            'color' => $userCtx['color'],
            'icon' => $userCtx['icon'],
            'profile_picture' => $userCtx['profile_picture'],
            'attachments' => $this->attachments,
            'local_uploads' => $localUploads,
        ];

        // DB Save
        $this->saveMessageToDb('user', $this->input, $userCtx);

        $this->input = '';
        $this->attachments = [];
        $this->uploadedFiles = [];

        if (empty($this->activeAgentIds)) {
            $errCtx = [
                'name' => 'System',
                'color' => 'red-500',
                'icon' => 'exclamation-triangle',
                'profile_picture' => null,
            ];
            $errStr = 'FEHLER: Kein Agent für Verarbeitung ausgewählt. Bitte wähle mindestens einen Agenten im oberen Panel aus.';
            $this->messages[] = array_merge(['role' => 'assistant', 'content' => $errStr], $errCtx);
            $this->saveMessageToDb('assistant', $errStr, $errCtx);
            return;
        }

        // Aktiviere paralleles Tipping für alle gewählten Agenten
        $this->typingAgents = array_merge($this->typingAgents, $this->activeAgentIds);

        // Ping Frontend to dispatch parallel background processing
        $this->dispatch('start-ai-inference', agentIds: $this->activeAgentIds);
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
            
            // Optimistic UI update
            $task->update([
                'status' => 'pending',
                'assigned_agent_id' => null,
            ]);
        }
    }

    #[On('process-agent')]
    public function processAgent($agentId)
    {
        // Falls dieser Event mehrfach feuert, checken, ob er noch laden muss
        if (!in_array($agentId, $this->typingAgents)) return;

        $agent = AiAgent::find($agentId);
        if (!$agent) {
             $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
             return;
        }

        // Lade nur die letzten 5 Nachrichten (anstatt 20) für extrem schnelle API-Antworten und geringste Latenz
        $fullDbHistory = AiChatMemory::where('session_id', session()->getId())
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->reverse();

        $apiHistory = [];
        $collectedProjectFiles = [];
        $collectedTextUploads = [];

        foreach ($fullDbHistory as $mem) {
            // Übersetze alte Tool-Calls für den API Kontext in Klartext (Memory-Injection), damit die KI weiß, was sie schon getan hat.
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

                // Lade Projekt-Dateien genau dort, wo sie referenziert wurden (für Prompt-Caching und Timeline)
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

                // Sammle Uploads und hänge Text/Bilder direkt an diese spezifische Nachricht!
                if (!empty($ctx['local_uploads'])) {
                    $upNames = collect($ctx['local_uploads'])->pluck('name')->implode(', ');
                    $contentToAPI .= "\n\n[LOKALE DATEI-UPLOADS IN DIESER NACHRICHT: " . $upNames . "]\n";

                    foreach ($ctx['local_uploads'] as $up) {
                        $mime = $up['mime'] ?? 'unknown';
                        $fullPath = storage_path('app/public/' . $up['path']);
                        if (!file_exists($fullPath)) {
                            $fullPath = storage_path('app/' . $up['path']); // Fallback
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
                                $lines = array_slice(file($fullPath), 0, 2000);
                                $contentToAPI .= "\n--- DATEI: {$up['name']} ---\n```\n" . rtrim(implode("", $lines)) . "\n```\n";
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

        // --- MULTI-AGENT ROUTING INJECTION ---
        $multiAgentRule = '';
        if (count($this->activeAgentIds) > 1) {
            $roleName = $agent->role ? $agent->role->name : 'Spezialist';
            $multiAgentRule = "[MULTI-AGENT KOORDINATIONS-PROTOKOLL]\n" .
                "Dein Name ist {$agent->name}. Deine Rolle: {$roleName}. " .
                "Du befindest dich in einem Multi-Agent Chat. Weitere Kollegen hören zu. ".
                "WICHTIGE REGEL: Wenn die Anfrage des Users NICHT fachlich exakt in deinen Aufgabenbereich ({$roleName}) / zu deinen Werkzeugen passt, antworte ZWINGEND und NUR mit exakt '[SKIP]'. " .
                "Mache keine Ausnahmen! Du bearbeitest NUR Anfragen, für die du der Spezialist bist. ".
                "Wenn ein Kollege fachlich besser passt oder du die Antwort aus dem Chatverlauf ablesen müsstest beziehungsweise keine eigenen Werkzeuge hast, bist du nicht gemeint! Antworte dann still mit '[SKIP]'!";
        }

        try {
            $apiService = \App\Services\AI\AiAgentFactory::make($agent);
            if ($multiAgentRule) {
                $apiService->setDynamicSystemPrompt($multiAgentRule);
            }


            $response = $apiService->ask($apiHistory, function($event) use ($agentId) {
                if (($event['type'] ?? '') === 'tool_call') {
                    $toolName = $event['tool'] ?? 'System';
                    $html = '<div class="text-[10px] text-[var(--theme-color)] font-mono opacity-80 mt-1 flex items-center gap-1.5 animate-pulse"><svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Führe aus: ' . htmlspecialchars($toolName) . '</div>';
                    $this->stream('thought_' . $agentId, $html, false); // false = append to stream
                } elseif (($event['type'] ?? '') === 'thought_html') {
                    $this->stream('thought_' . $agentId, $event['html'], false);
                } elseif (($event['type'] ?? '') === 'text_chunk') {
                    $this->stream('answer_' . $agentId, $event['chunk'], false);
                }
            });
            $replyText = $response['response'] ?? '';

            // Frontend-Events (Navigation & UI Overlays) ausführen
            if (!empty($response['events'])) {
                foreach ($response['events'] as $eventMsg) {
                    $evtType = $eventMsg['type'] ?? '';
                    if ($evtType === 'navigate' && !empty($eventMsg['url'])) {
                        // Da wire:navigate läuft, machen wir serverseitigen Redirect
                        $this->redirect($eventMsg['url'], navigate: true);
                        return; // Chat muss nicht weiter rendern, da wir die Seite verlassen
                    } elseif ($evtType === 'dispatch' && !empty($eventMsg['name'])) {
                        $this->dispatch($eventMsg['name']);
                    }
                }
            }

            // Überprüfe auf Skipped Routing oder gänzlich leere Responses (Fast-Track UI Events)
            if (str_contains(strtoupper($replyText), '[SKIP]') || trim($replyText) === '') {
                // Agent ignoriert die Nachricht still (kein DB Eintrag, keine leere Sprechblase)
                $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
                return;
            }

            // Tracking is now automatically handled centrally in AiAgentFactory

            $ctx = [
                'name' => $agent->name,
                'color' => $agent->color,
                'icon' => $agent->icon,
                'profile_picture' => $agent->profile_picture,
            ];

            $this->saveMessageToDb('assistant', $replyText, $ctx);

            $this->messages[] = array_merge(['role' => 'assistant', 'content' => $replyText], $ctx);

        } catch (\Exception $e) {
            $errCtx = [
                'name' => 'System',
                'color' => 'red-500',
                'icon' => 'exclamation-triangle',
                'profile_picture' => null,
            ];
            $errStr = 'API Fehler [' . $agent->name . ']: ' . $e->getMessage();
            $this->messages[] = array_merge(['role' => 'assistant', 'content' => $errStr], $errCtx);
            $this->saveMessageToDb('assistant', $errStr, $errCtx);
        }

        // Remove from typing array
        $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
    }

    public function clearChat()
    {
        AiChatMemory::where('session_id', session()->getId())->delete();
        // Clear locally stored artifacts when chat is wiped
        if (Storage::disk('local')->exists('ai-artifacts/' . session()->getId())) {
            Storage::disk('local')->deleteDirectory('ai-artifacts/' . session()->getId());
        }
        $this->messages = [];
        $this->activeAgentIds = [];
        $this->typingAgents = [];
        $this->mount();
    }

    #[Computed]
    public function artifacts()
    {
        $sessionId = session()->getId();
        $path = 'ai-artifacts/' . $sessionId;
        if (!Storage::disk('local')->exists($path)) {
            return collect();
        }

        $files = Storage::disk('local')->files($path);
        $artifactData = [];

        foreach ($files as $file) {
            $artifactData[] = [
                'name' => str_replace('.md', '', basename($file)),
                'filename' => basename($file),
                'content' => Storage::disk('local')->get($file),
                'last_modified' => Storage::disk('local')->lastModified($file)
            ];
        }

        return collect($artifactData)->sortByDesc('last_modified')->values();
    }

    #[Computed]
    public function globalFiles()
    {
        $sessionId = session()->getId();
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

        // Füge temporäre Uploads (noch nicht abgesendet) hinzu
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
            $sessionId = session()->getId();
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

            // Optional: Hard-Delete from storage to save disk space
            @unlink(storage_path('app/public/' . $path));
            @unlink(storage_path('app/' . $path));

        } elseif ($type === 'project_file') {
            $sessionId = session()->getId();
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

    public $pingResults = [];

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
        if ($task && ($task->status === 'completed' || $task->status === 'archived')) {
            $agentId = $task->assigned_agent_id;
            $task->update([
                'status' => $agentId ? 'processing' : 'pending',
                'response_content' => null,
                'completed_at' => null,
            ]);
            
            if ($agentId) {
                // Restart immediately if agent was previously assigned
                \Illuminate\Support\Facades\Bus::dispatch(new \App\Jobs\ProcessAiWorkspaceTask($taskId));
            }
        }
    }

    public function createTaskFromChat()
    {
        if (trim($this->input) === '' && empty($this->attachments) && empty($this->uploadedFiles)) return;

        $meta = [
            'attachments' => $this->attachments,
        ];

        // Process real file uploads exactly as sendMessage does
        $localUploads = [];
        if (!empty($this->uploadedFiles)) {
            foreach ($this->uploadedFiles as $file) {
                // Store in public disk so that Storage::url() works in blade
                $path = $file->store('ai-chat-uploads', 'public');
                $localUploads[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType()
                ];
            }
            $meta['local_uploads'] = $localUploads;
        }

        \App\Models\Ai\AiWorkspaceTask::create([
            'prompt' => trim($this->input),
            'status' => 'pending',
            'ui_metadata' => $meta
        ]);

        $this->input = '';
        $this->attachments = [];
        $this->uploadedFiles = [];
    }

    public function syncAll()
    {
        $agents = AiAgent::where('is_active', true)->get();
        foreach ($agents as $agent) {
            $this->pingTest($agent->id);
        }
        // Ping Toni specifically as an external agent
        $this->pingToni();
    }

    public function pingTest($id)
    {
        $agent = AiAgent::find($id);
        if (!$agent) return;

        if (!$agent->is_active) {
            $this->pingResults[$id] = [
                'llm' => 'Inaktiv',
                'tts' => 'Inaktiv',
            ];
            return;
        }

        // Ping LLM
        $llmStatus = 'Fehler';
        try {
            $start = microtime(true);
            $modelStr = strtolower($agent->model ?? '');
            
            if (str_starts_with($modelStr, 'gemini')) {
                $llmUrl = config('services.gemini.url') ?: 'https://generativelanguage.googleapis.com/v1beta/openai/';
                $key = config('services.gemini.key');
            } else {
                $llmUrl = config('services.mittwald.url') ?: 'https://api.mittwald.example/v1';
                $key = config('services.mittwald.key');
            }
            
            $response = \Illuminate\Support\Facades\Http::timeout(3)->withToken($key)->get(rtrim($llmUrl, '/') . '/models');
            
            if ($response->successful() || $response->status() === 401 || $response->status() === 404) {
                $llmStatus = round((microtime(true) - $start) * 1000) . 'ms';
            }
        } catch (\Exception $e) {
            $llmStatus = 'Offline';
        }

        // Ping TTS
        $ttsStatus = !$agent->tts_enabled ? 'Deaktiviert' : 'Inaktiv';
        if ($agent->tts_enabled && $agent->tts_provider && $agent->tts_provider !== 'none') {
            try {
                $start = microtime(true);
                $ttsUrl = $agent->tts_api_url ?: 'http://127.0.0.1:8020';
                $response = \Illuminate\Support\Facades\Http::timeout(3)->get(rtrim($ttsUrl, '/') . '/languages');
                if ($response->successful() || $response->status() === 404) {
                    $ttsStatus = round((microtime(true) - $start) * 1000) . 'ms';
                } else {
                    $ttsStatus = 'Fehler';
                }
            } catch (\Exception $e) {
                $ttsStatus = 'Offline';
            }
        }

        $this->pingResults[$id] = [
            'llm' => $llmStatus,
            'tts' => $ttsStatus,
        ];
    }

    public function pingToni()
    {
        $toniUrl = env('TONI_AI_URL', 'http://192.168.188.32:8000');
        $llmStatus = 'Fehler';
        $ttsStatus = 'Fehler';
        
        try {
            $start = microtime(true);
            $response = \Illuminate\Support\Facades\Http::timeout(5)->withToken(env('TONI_AI_API_KEY'))->get($toniUrl . '/api/toni/config');
            if ($response->successful() || $response->status() === 401 || $response->status() === 404) {
                $llmStatus = round((microtime(true) - $start) * 1000) . 'ms';
                $ttsStatus = $llmStatus; // Toni runs everything in the same service
            }
        } catch (\Exception $e) {
            $llmStatus = 'Offline';
            $ttsStatus = 'Offline';
        }

        $this->pingResults['toni'] = [
            'llm' => $llmStatus,
            'tts' => $ttsStatus,
        ];
    }

    public function render()
    {
        return view('livewire.shop.ai.ai-workspace', [
            'tasks' => \App\Models\Ai\AiWorkspaceTask::with('agent')
                        ->where('status', '!=', 'archived')
                        ->latest('created_at')
                        ->get(),
            'agents' => $this->agents
        ]);
    }

    public function saveLayoutPercent($percent)
    {
        if (!auth()->check()) return;
        
        $percent = max(10, min(90, (int) $percent));
        $this->chatHeightPercent = $percent;
        
        \App\Models\Ai\AiUserWorkspaceSetting::updateOrCreate(
            ['user_id' => auth()->id()],
            ['chat_height_percent' => $percent]
        );
    }
}

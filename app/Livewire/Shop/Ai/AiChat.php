<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;

use Livewire\Attributes\Layout;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class AiChat extends Component
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

    public function mount()
    {
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

            // Wähle standardmäßig Funkira aus, wenn die Seite geladen wird
            $ceo = AiAgent::where('name', 'Funkira')->first();
            if ($ceo && !in_array($ceo->id, $this->activeAgentIds)) {
                $this->activeAgentIds[] = $ceo->id;
            }
        } else {
            // Hole zum Start den CEO (Funkira) oder falle auf System zurück
            $ceo = AiAgent::where('name', 'Funkira')->first();
            if ($ceo) {
                $this->activeAgentIds[] = $ceo->id;
                $this->saveMessageToDb('assistant', '> Gesicherter Chat aktiviert... Wie kann ich helfen?', [
                    'name' => $ceo->name,
                    'color' => $ceo->color,
                    'icon' => $ceo->icon,
                    'profile_picture' => $ceo->profile_picture,
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
        return AiAgent::where('is_active', true)->orderByRaw("CASE WHEN name = 'Funkira' THEN 0 ELSE 1 END")->orderBy('name')->get();
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

        // Kompletten DB Verlauf: Lade nur die letzten 20 Nachrichten für ultra-schnelle API-Antworten
        $fullDbHistory = AiChatMemory::where('session_id', session()->getId())
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->reverse();

        $apiHistory = [];
        $collectedProjectFiles = [];
        $collectedTextUploads = [];

        foreach ($fullDbHistory as $mem) {
            // Ignoriere alte Tool-Calls für den API Kontext. Das spart massiv Token und verhindert Schema-Fehler.
            if ($mem->role === 'tool') continue;

            $contentToAPI = $mem->content;
            $messageImages = [];
            
            if ($mem->role === 'user') {
                $ctx = $mem->context_data ?? [];
                
                // Sammle Projekt-Dateien aus der Historie (dedupliziert für den finalen Code-Kontext)
                if (!empty($ctx['attachments'])) {
                    $contentToAPI .= "\n\n[Der User hat in dieser Nachricht folgende Projekt-Dateien referenziert: " . implode(', ', $ctx['attachments']) . "]\n";
                    foreach ($ctx['attachments'] as $filePath) {
                        $collectedProjectFiles[$filePath] = $filePath;
                    }
                }
                
                // Sammle Uploads und hänge Bilder direkt an diese spezifische Nachricht!
                if (!empty($ctx['local_uploads'])) {
                    $upNames = collect($ctx['local_uploads'])->pluck('name')->implode(', ');
                    $contentToAPI .= "\n\n[Datei-Uploads in dieser Nachricht: " . $upNames . "]\n";
                    
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
                                // Text/Code Dateien weiterhin für den globalen Schluss sammeln
                                $collectedTextUploads[$up['path']] = $up;
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

        // --- GLOBAL FILE INJECTION AT THE END OF HISTORY ---
        $globalFileContext = "";

        if (!empty($collectedProjectFiles)) {
            $globalFileContext .= "\n\n=== AKTUELLE INHALTE DER REFERENZIERTEN PROJEKT-DATEIEN ===\n";
            foreach ($collectedProjectFiles as $filePath) {
                $fullPath = base_path($filePath);
                if (file_exists($fullPath) && is_file($fullPath)) {
                    $lines = array_slice(file($fullPath), 0, 2000);
                    $code = rtrim(implode("", $lines));
                    $globalFileContext .= "\n--- DATEI: {$filePath} ---\n```\n{$code}\n```\n";
                }
            }
        }
        
        if (!empty($collectedTextUploads)) {
            $globalFileContext .= "\n\n=== INHALTE DER LOKALEN TEXT-UPLOADS ===\n";
            foreach ($collectedTextUploads as $up) {
                $fullPath = storage_path('app/public/' . $up['path']);
                if (!file_exists($fullPath)) $fullPath = storage_path('app/' . $up['path']);
                if (file_exists($fullPath)) {
                    $lines = array_slice(file($fullPath), 0, 2000);
                    $globalFileContext .= "\n--- DATEI: {$up['name']} ---\n```\n" . rtrim(implode("", $lines)) . "\n```\n";
                }
            }
        }

        // Hänge den gesammelten Text/Code-Kontext an die LETZTE Nachricht an
        if (count($apiHistory) > 0 && !empty($globalFileContext)) {
            $lastIndex = count($apiHistory) - 1;
            
            // Wenn die letzte Nachricht bereits ein Array ist (weil sie Bilder enthält), füge den Text an
            if (is_array($apiHistory[$lastIndex]['content'])) {
                $apiHistory[$lastIndex]['content'][0]['text'] .= $globalFileContext;
            } else {
                $apiHistory[$lastIndex]['content'] .= $globalFileContext;
            }
        }

        // --- MULTI-AGENT ROUTING INJECTION ---
        $multiAgentRule = '';
        if (count($this->activeAgentIds) > 1) {
            $isFunkira = $agent->name === 'Funkira';
            $funkiraRule = $isFunkira 
                ? "Du BIST Funkira (CEO). Du bist die Leiterin. Wenn der User fachliche Anfragen stellt (z.B. Bestellungen, Buchhaltung, Produkte, Server), für die du KEINE eigenen Werkzeuge hast, VERWEISE NICHT auf Kollegen und sag nichts, sondern antworte AUSSCHLIESSLICH mit exakt '[SKIP]'. Deine Kollegen (die anderen Agenten) bearbeiten das dann! Du antwortest NUR auf CEO-Smalltalk, Begrüßungen, allgemeine System-Informationen oder wenn keine andere Fachabteilung zuständig ist."
                : "Du bist in einem Multi-Agent Chat. Es hören auch andere Agenten (z.B. Sales, Support, System) und Funkira (die CEO) zu. WICHTIGE REGEL: Wenn die Anfrage des Users NICHT exakt in deinen fachlichen Aufgabenbereich / zu deinen API-Werkzeugen passt, antworte ZWINGEND und NUR mit '[SKIP]'. Mache keine Ausnahmen! Du bearbeitest NUR Anfragen, für die du der absolute Spezialist bist. Wenn ein Kollege besser passt, hülle dich in Schweigen ('[SKIP]'). Bedenke: Wenn du eine Antwort aus dem Chatverlauf ablesen müsstest, anstatt ein Werkzeug zu nutzen, bist du sehr wahrscheinlich der FALSCHE Agent! Antworte dann mit '[SKIP]'!";

            $multiAgentRule = "[MULTI-AGENT KOORDINATIONS-PROTOKOLL]\n" . $funkiraRule;
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
                }
            });
            $replyText = $response['response'] ?? 'Ich konnte keine Antwort generieren.';

            // Überprüfe auf Skipped Routing
            if (str_contains(strtoupper($replyText), '[SKIP]')) {
                // Agent ignoriert die Nachricht still (keine Kosten im Frontend, kein DB Eintrag)
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

        \Log::info("GlobalFiles computed: found " . count($allFiles) . " files");

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

    public function render()
    {
        return view('livewire.shop.ai.ai-chat', [
            'agents' => $this->agents
        ]);
    }
}

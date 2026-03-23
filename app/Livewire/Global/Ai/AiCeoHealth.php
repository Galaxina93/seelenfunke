<?php

namespace App\Livewire\Global\Ai;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class AiCeoHealth extends Component
{
    use WithFileUploads;

    public $input = '';
    public $messages = [];
    public $agentId = null;
    public $typing = false;
    
    public $activeTab = 'chat'; // 'chat', 'plans', 'protocols', 'files'
    
    // Uploads
    public $currentPath = 'wiki/health';
    public $healthFiles = [];
    public $uploadedHealthFiles = [];

    public function mount()
    {
        // Load the Dr. Funki Agent
        $agent = AiAgent::where('name', 'Dr. Funki')->first();
        if ($agent) {
            $this->agentId = $agent->id;
            session()->put('current_ai_agent_id', $agent->id);
        }

        // Lade Chat-Historie aus der Datenbank
        $history = AiChatMemory::where('session_id', session()->getId() . '_health')
                               ->orderBy('created_at', 'asc')
                               ->get();
        
        if ($history->isNotEmpty()) {
            foreach ($history as $mem) {
                if ($mem->role === 'tool') continue;

                $ctx = $mem->context_data ?? [];
                $this->messages[] = [
                    'role' => $mem->role,
                    'name' => $ctx['name'] ?? ucfirst($mem->role),
                    'content' => $mem->content,
                    'color' => $ctx['color'] ?? ($mem->role === 'user' ? 'gray-400' : 'teal-500'),
                    'icon' => $ctx['icon'] ?? ($mem->role === 'user' ? 'user' : 'user-plus'),
                    'profile_picture' => $ctx['profile_picture'] ?? null,
                ];
            }
        } else {
            // Initial Welcome Message
            if ($agent) {
                $this->saveMessageToDb('assistant', '> Guten Tag! Ich bin Dr. Funki, Ihr persönlicher Hausarzt. Wie darf ich Ihnen heute helfen? Sie können hier rechts im Panel auch medizinische Befunde hochladen.', [
                    'name' => $agent->name,
                    'color' => $agent->color,
                    'icon' => $agent->icon,
                    'profile_picture' => $agent->profile_picture,
                ]);
            }
            // Lade die nun initial gespeicherten Nachrichten
            foreach (AiChatMemory::where('session_id', session()->getId() . '_health')->get() as $mem) {
                if ($mem->role === 'tool') continue;
                $ctx = $mem->context_data ?? [];
                $this->messages[] = [
                    'role' => $mem->role,
                    'name' => $ctx['name'] ?? ucfirst($mem->role),
                    'content' => $mem->content,
                    'color' => $ctx['color'] ?? 'teal-500',
                    'icon' => $ctx['icon'] ?? 'user-plus',
                    'profile_picture' => $ctx['profile_picture'] ?? null,
                ];
            }
        }

        $this->loadUploadedFiles();
    }

    private function saveMessageToDb($role, $content, $contextData)
    {
        AiChatMemory::create([
            'session_id' => session()->getId() . '_health',
            'role' => $role,
            'content' => $content,
            'context_data' => $contextData,
        ]);
    }

    public function sendMessage()
    {
        if (trim($this->input) === '') return;
        if (!$this->agentId) return;

        $userCtx = [
            'name' => auth()->check() ? auth()->user()->first_name : 'Patient (CEO)',
            'color' => 'gray-400',
            'icon' => 'user',
            'profile_picture' => auth()->check() && auth()->user()->profile ? auth()->user()->profile->photo_path : null,
        ];

        // UI Update
        $this->messages[] = [
            'role' => 'user',
            'name' => $userCtx['name'],
            'content' => $this->input,
            'color' => $userCtx['color'],
            'icon' => $userCtx['icon'],
            'profile_picture' => $userCtx['profile_picture'],
        ];

        // DB Save
        $this->saveMessageToDb('user', $this->input, $userCtx);

        $this->input = '';
        $this->typing = true;

        // Ping Frontend to dispatch background processing
        $this->dispatch('start-health-ai-inference');
    }

    #[On('process-health-agent')]
    public function processAgent()
    {
        if (!$this->typing || !$this->agentId) return;

        $agent = AiAgent::find($this->agentId);
        if (!$agent) {
             $this->typing = false;
             return;
        }

        $fullDbHistory = AiChatMemory::where('session_id', session()->getId() . '_health')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->reverse();
            
        $apiHistory = [];
        
        // Füge System Kontext für hochgeladene Dokumente hinzu (Rekursiv alle Ordner)
        $allSystemFiles = Storage::disk('public')->allFiles('wiki/health');
        if (!empty($allSystemFiles)) {
            $docList = collect($allSystemFiles)->map(fn($path) => basename($path))->implode(', ');
            $apiHistory[] = [
                'role' => 'system',
                'content' => "System Info: Es wurden folgende medizinische Dokumente hochgeladen und stehen über die Knowledge Base im Verzeichnis 'wiki/health' zur Verfügung: $docList. Nutze diese als Referenz bei passenden Fragen."
            ];
        }

        foreach ($fullDbHistory as $mem) {
            if ($mem->role === 'tool') continue;
            
            $apiHistory[] = [
                'role' => $mem->role,
                'content' => $mem->content
            ];
        }

        try {
            $apiService = new \App\Services\AI\MittwaldAgent($agent);
            
            $response = $apiService->ask($apiHistory);
            $replyText = $response['response'] ?? 'Ich konnte keine Antwort generieren.';

            if (class_exists(\App\Models\Ai\AiMetric::class) && isset($response['usage']) && isset($response['latency_ms'])) {
                try {
                    \App\Models\Ai\AiMetric::create([
                        'ai_agent_id' => $agent->id,
                        'type' => 'inference',
                        'input_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                        'output_tokens' => $response['usage']['completion_tokens'] ?? 0,
                        'total_time_ms' => $response['latency_ms'],
                        'is_success' => true
                    ]);
                } catch (\Exception $e) {}
            }

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

        $this->typing = false;
    }

    public function updatedHealthFiles()
    {
        $this->validate([
            'healthFiles.*' => 'max:10240', // 10MB Max per file
        ]);

        foreach ($this->healthFiles as $file) {
            $filename = $file->getClientOriginalName();
            $file->storeAs('public/' . $this->currentPath, $filename);
        }

        $this->healthFiles = []; 
        $this->loadUploadedFiles();
        
        $this->dispatch('health-files-updated', ['files' => $this->uploadedHealthFiles]);
        $this->dispatch('docs-uploaded'); // Notify frontend UI component
    }

    public function createFolder($folderName)
    {
        if(empty(trim($folderName))) return;
        $path = $this->currentPath . '/' . trim($folderName);
        if (!Storage::disk('public')->exists($path)) {
            Storage::disk('public')->makeDirectory($path);
            $this->loadUploadedFiles();
        }
    }

    public function deleteItem($path)
    {
        if (Storage::disk('public')->exists($path)) {
            if (is_dir(storage_path('app/public/' . $path))) {
                Storage::disk('public')->deleteDirectory($path);
            } else {
                Storage::disk('public')->delete($path);
            }
            $this->loadUploadedFiles();
            $this->dispatch('health-files-updated', ['files' => $this->uploadedHealthFiles]);
        }
    }

    public function openFolder($folderName)
    {
        $this->currentPath .= '/' . trim($folderName, '/');
        $this->loadUploadedFiles();
    }

    public function goUp()
    {
        if ($this->currentPath !== 'wiki/health') {
            $this->currentPath = dirname($this->currentPath);
            $this->loadUploadedFiles();
        }
    }

    public function loadUploadedFiles()
    {
        if (!Storage::disk('public')->exists('wiki/health')) {
            Storage::disk('public')->makeDirectory('wiki/health');
        }

        $files = Storage::disk('public')->files($this->currentPath);
        $dirs = Storage::disk('public')->directories($this->currentPath);
        
        $items = [];
        
        foreach($dirs as $dir) {
            $items[] = [
                'type' => 'folder',
                'name' => basename($dir),
                'path' => $dir,
                'size' => 0,
                'url' => null,
            ];
        }

        foreach($files as $file) {
            $items[] = [
                'type' => 'file',
                'name' => basename($file),
                'path' => $file,
                'size' => Storage::disk('public')->size($file),
                'url' => Storage::url($file),
            ];
        }
        
        $this->uploadedHealthFiles = $items;
    }

    public function clearChat()
    {
        AiChatMemory::where('session_id', session()->getId() . '_health')->delete();
        $this->messages = [];
        $this->mount();
    }

    public function selectTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        $plans = \App\Models\Ai\Health\AiHealthTreatmentPlan::with('items')->orderBy('created_at', 'desc')->get();
        $protocols = \App\Models\Ai\Health\AiHealthProtocol::orderBy('created_at', 'desc')->get();

        return view('livewire.global.ai.ai-ceo-health', [
            'plans' => $plans,
            'protocols' => $protocols,
        ])->layout('components.layouts.backend_layout');
    }
}

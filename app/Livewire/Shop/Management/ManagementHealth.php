<?php

namespace App\Livewire\Shop\Management;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ManagementHealth extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Leitung';

    use WithFileUploads, WithPagination;

    public $input = '';
    public $searchChat = '';
    public $messages = [];
    public $agentId = null;
    public $typing = false;

    public $activeTab = 'chat'; // 'chat', 'plans', 'protocols', 'files', 'medications'

    public $currentPath = 'leitung/gesundheit';
    public $healthFiles = [];
    public $uploadedHealthFiles = [];
    public $aiLiveState = [];

    protected $listeners = ['health-files-updated' => 'setHealthFiles'];

    public function updateLiveState()
    {
        if ($this->typing) {
            $this->aiLiveState = \Illuminate\Support\Facades\Cache::get('ai_live_state', []);
        } else {
            $this->aiLiveState = [];
        }
    }

    // Medications
    public $medicationIsLongTerm = false;

    // View Medication State
    public $viewingMedicationId = null;
    public $viewingMedication = null;

    public $showMedicationModal = false;
    public $medicationForm = [
        'id' => null,
        'name' => '',
        'description' => '',
        'active_ingredients' => '',
        'dosage' => '',
        'frequency' => '',
        'is_long_term' => false,
    ];

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
                    'id' => $mem->id,
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
                $initialDb = $this->saveMessageToDb('assistant', '> Guten Tag! Ich bin Dr. Funki, Ihr persönlicher Hausarzt. Wie darf ich Ihnen heute helfen?', [
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
                    'id' => $mem->id,
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
        return AiChatMemory::create([
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

        // DB Save
        $savedDb = $this->saveMessageToDb('user', $this->input, $userCtx);

        // UI Update
        $this->messages[] = [
            'id' => $savedDb->id,
            'role' => 'user',
            'name' => $userCtx['name'],
            'content' => $this->input,
            'color' => $userCtx['color'],
            'icon' => $userCtx['icon'],
            'profile_picture' => $userCtx['profile_picture'],
        ];

        $this->input = '';
        $this->typing = true;

        // Ping Frontend to dispatch background processing
        $this->dispatch('start-health-ai-inference');
    }

    public function repostMessage($id)
    {
        $mem = AiChatMemory::find($id);
        if ($mem && $mem->role === 'user') {
            $this->input = $mem->content;
            $this->sendMessage();
        }
    }

    public function continueFromMessage($id)
    {
        $mem = AiChatMemory::find($id);
        if ($mem && $mem->role === 'user') {
            $content = $mem->content;

            // Delete this message and all subsequent messages in the session
            AiChatMemory::where('session_id', session()->getId() . '_health')
                ->where('created_at', '>=', $mem->created_at)
                ->delete();

            // Completely rebuild the local component state from DB
            $this->messages = [];
            $history = AiChatMemory::where('session_id', session()->getId() . '_health')
                        ->orderBy('created_at', 'asc')
                        ->get();

            foreach ($history as $m) {
                if ($m->role === 'tool') continue;
                $ctx = $m->context_data ?? [];
                $this->messages[] = [
                    'id' => $m->id,
                    'role' => $m->role,
                    'name' => $ctx['name'] ?? ucfirst($m->role),
                    'content' => $m->content,
                    'color' => $ctx['color'] ?? ($m->role === 'user' ? 'gray-400' : 'teal-500'),
                    'icon' => $ctx['icon'] ?? ($m->role === 'user' ? 'user' : 'user-plus'),
                    'profile_picture' => $ctx['profile_picture'] ?? null,
                ];
            }

            // Now automatically send the old content as a fresh message
            $this->input = $content;
            $this->sendMessage();
        }
    }

    #[On('start-health-ai-inference')]
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
        $userName = auth()->check() ? trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) : 'Patient';

        // Füge System Kontext für hochgeladene Dokumente hinzu (Rekursiv alle Ordner)
        $allSystemFiles = Storage::disk('public')->allFiles('leitung/gesundheit');
        if (!empty($allSystemFiles)) {
            $docList = collect($allSystemFiles)->map(fn($path) => basename($path))->implode(', ');
            $apiHistory[] = [
                'role' => 'system',
                'content' => "System Info: Der Name deines Patienten ist {$userName}. Es wurden folgende medizinische Dokumente hochgeladen und stehen über die Knowledge Base im Verzeichnis 'Shop/Management/Health' zur Verfügung: $docList. Nutze diese als Referenz.
WICHTIGSTE REGEL FÜR DR. FUNKI: Sei brillant, präzise und absolut professionell. Du MUSST eine Mischung aus verständlicher Sprache und tiefem medizinischem Fachjargon (Fachbegriffe, Latein) nutzen.
Wenn du eine 'Diagnose & Zusammenfassung' für das PDF erstellst, erkläre die Sachlage klipp und klar.
FÜGE AM ENDE JEDER GRÖSSEREN DIAGNOSE/ZUSAMMENFASSUNG EINFACH EIN 'GLOSSAR' HINZU, in dem du alle von dir verwendeten medizinischen Fachwörter kurz und prägnant für den Laien erklärst.
Wenn du etwas untersuchen musst, recherchiere im Netz."
            ];
        } else {
            $apiHistory[] = [
                'role' => 'system',
                'content' => "System Info: Der Name deines Patienten ist {$userName}.
WICHTIGSTE REGEL FÜR DR. FUNKI: Sei brillant, präzise und absolut professionell. Du MUSST eine Mischung aus verständlicher Sprache und tiefem medizinischem Fachjargon (Fachbegriffe, Latein) nutzen.
FÜGE AM ENDE JEDER GRÖSSEREN DIAGNOSE/ZUSAMMENFASSUNG EINFACH EIN 'GLOSSAR' HINZU, in dem du alle von dir verwendeten medizinischen Fachwörter kurz und prägnant für den Laien erklärst."
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
            $apiService = \App\Services\AI\AiAgentFactory::make($agent);

            $response = $apiService->ask($apiHistory);
            $replyText = $response['response'] ?? 'Ich konnte keine Antwort generieren.';

            // Tracking is now automatically handled centrally in AiAgentFactory

            $ctx = [
                'name' => $agent->name,
                'color' => $agent->color,
                'icon' => $agent->icon,
                'profile_picture' => $agent->profile_picture,
            ];

            $savedAgentDb = $this->saveMessageToDb('assistant', $replyText, $ctx);
            $this->messages[] = array_merge(['id' => $savedAgentDb->id, 'role' => 'assistant', 'content' => $replyText], $ctx);

        } catch (\Exception $e) {
            $errCtx = [
                'name' => 'System',
                'color' => 'red-500',
                'icon' => 'exclamation-triangle',
                'profile_picture' => null,
            ];
            $errStr = 'API Fehler [' . $agent->name . ']: ' . $e->getMessage();
            $savedErrDb = $this->saveMessageToDb('assistant', $errStr, $errCtx);
            $this->messages[] = array_merge(['id' => $savedErrDb->id, 'role' => 'assistant', 'content' => $errStr], $errCtx);
        }

        $this->typing = false;
    }

    public $relativePaths = [];

    public function updatedHealthFiles()
    {
        $this->validate([
            'healthFiles.*' => 'max:10240', // 10MB Max per file
        ]);

        foreach ($this->healthFiles as $index => $file) {
            $filename = $file->getClientOriginalName();
            $relativePath = $this->relativePaths[$index] ?? $filename;
            
            // Generate full relative path logic, ensuring directories are created
            $destPath = $this->currentPath . '/' . dirname($relativePath);
            $destPath = str_replace('\\', '/', $destPath);
            $destPath = rtrim($destPath, '/.');
            
            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($destPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($destPath);
            }
            
            $file->storeAs($destPath, basename($relativePath), 'public');
        }

        $this->healthFiles = [];
        $this->relativePaths = [];

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
        if (Storage::disk('public')->exists($path) || in_array($path, Storage::disk('public')->directories(dirname($path)))) {
            if (in_array($path, Storage::disk('public')->directories(dirname($path)))) {
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
        if ($this->currentPath !== 'leitung/gesundheit') {
            $this->currentPath = dirname($this->currentPath);
            $this->loadUploadedFiles();
        }
    }

    public function loadUploadedFiles()
    {
        if (!Storage::disk('public')->exists('leitung/gesundheit')) {
            Storage::disk('public')->makeDirectory('leitung/gesundheit');
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

    public function togglePlanItem($itemId)
    {
        $item = \App\Models\Ai\AiHealthTreatmentItem::find($itemId);
        if ($item) {
            $item->is_completed = !$item->is_completed;
            $item->save();

            // Auto-complete the whole plan if all items are done
            $plan = $item->plan;
            if ($plan && $plan->status === 'active') {
                $totalItems = $plan->items()->count();
                $completedItems = $plan->items()->where('is_completed', true)->count();
                if ($totalItems > 0 && $totalItems === $completedItems) {
                    $plan->status = 'completed';
                    if (!$plan->result_evaluation) {
                        $plan->result_evaluation = 'Plan wurde automatisch durch Erledigung aller Schritte abgeschlossen.';
                    }
                    $plan->save();
                }
            }
        }
    }

    public function viewMedication($id)
    {
        $this->viewingMedication = \App\Models\Ai\AiHealthMedication::find($id);
        if ($this->viewingMedication) {
            $this->viewingMedicationId = $id;
        }
    }

    public function closeMedicationView()
    {
        $this->viewingMedicationId = null;
        $this->viewingMedication = null;
    }

    public function editMedication($id = null)
    {
        if ($id) {
            $med = \App\Models\Ai\AiHealthMedication::find($id);
            if ($med) {
                $this->medicationForm = $med->toArray();
            }
        } else {
            $this->medicationForm = [
                'id' => null,
                'name' => '',
                'description' => '',
                'active_ingredients' => '',
                'dosage' => '',
                'frequency' => '',
                'is_long_term' => false,
            ];
        }
        $this->showMedicationModal = true;
    }

    public function saveMedication()
    {
        $this->validate([
            'medicationForm.name' => 'required|string|max:255',
            'medicationForm.dosage' => 'nullable|string|max:255',
            'medicationForm.frequency' => 'nullable|string|max:255',
        ]);

        $data = $this->medicationForm;
        $data['user_id'] = auth()->id() ?? \App\Models\System\SystemUser::first()->id;

        if ($data['id']) {
            \App\Models\Ai\AiHealthMedication::find($data['id'])->update($data);
        } else {
            \App\Models\Ai\AiHealthMedication::create($data);
        }

        $this->showMedicationModal = false;
    }

    public function deleteMedication($id)
    {
        \App\Models\Ai\AiHealthMedication::destroy($id);
    }

    public function downloadPlanPdf($planId, \App\Services\Export\FileDownloadService $exportService)
    {
        return $exportService->downloadHealthTreatmentPlanPdf($planId);
    }

    public function render()
    {
        $plans = \App\Models\Ai\AiHealthTreatmentPlan::with('items')->orderBy('created_at', 'desc')->paginate(5, ['*'], 'plansPage');
        $protocols = \App\Models\Ai\AiHealthProtocol::with('treatmentPlan')->orderBy('created_at', 'desc')->paginate(5, ['*'], 'protocolsPage');
        $medications = \App\Models\Ai\AiHealthMedication::orderBy('name', 'asc')->get();

        $doctors = \App\Models\Management\ManagementContact::where('relation_type', 'like', '%arzt%')
            ->orWhere('relation_type', 'like', '%Praxis%')
            ->orderBy('is_favorite', 'desc')
            ->orderBy('last_name', 'asc')
            ->get();

        return view('livewire.shop.management.management-health', [
            'plans' => $plans,
            'protocols' => $protocols,
            'medications' => $medications,
            'doctors' => $doctors,
        ])->layout('components.layouts.backend_layout');
    }
}

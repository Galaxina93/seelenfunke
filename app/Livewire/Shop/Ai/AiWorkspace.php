<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Livewire\Shop\Ai\Traits\ManagesAiChat;
use App\Livewire\Shop\Ai\Traits\ManagesAiWorkspaceFiles;
use App\Livewire\Shop\Ai\Traits\ManagesHealthData;

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
    use ManagesAiChat;
    use ManagesAiWorkspaceFiles;
    use ManagesHealthData;

    public string $themingDepartment = 'Agenten';

    public string $activeWorkspaceView = 'workspace';
    public string $activeTab = 'chat';
    public int $chatHeightPercent = 40;
    public bool $autoApprovePlan = false;
    public array $pingResults = [];


    // AI Hosting Tariffs Management
    public $newPlanName = '';
    public ?int $newPlanTokens = null;
    public string $newPlanPrice = '0.00';
    public $newPlanDescription = '';
    public $newPlanFeatures = [['title' => '', 'description' => '']];
    public ?int $editingPlanId = null;

    public function mount()
    {
        if (auth()->check()) {
            $setting = \App\Models\Ai\AiUserWorkspaceSetting::where('user_id', auth()->id())->first();
            if ($setting) {
                $this->chatHeightPercent = $setting->chat_height_percent;
                $this->autoApprovePlan = (bool) $setting->auto_approve_execution_plan;
                if (!empty($setting->active_tab)) {
                    $this->activeTab = $setting->active_tab;
                }
            }
        }

        $this->loadFileManagerFiles();
        $this->loadHealthData();

        $this->activeAgentIds = AiAgent::where('is_in_chat', true)->pluck('id')->toArray();
        $this->forcedAgentIds = $this->activeAgentIds;

        $teamLeader = AiAgent::where('is_active', true)->whereHas('role', function($q) {
            $q->where('name', 'like', '%Teamleiter%');
        })->first();
        
        $fallbackAgent = $teamLeader ?? AiAgent::where('is_active', true)->first();

        if (empty($this->activeAgentIds) && $fallbackAgent) {
            $fallbackAgent->update(['is_in_chat' => true]);
            $this->activeAgentIds[] = $fallbackAgent->id;
            $this->forcedAgentIds[] = $fallbackAgent->id;
        }
        if (AiChatMemory::where('session_id', $this->getAiSessionId())->doesntExist()) {
            if ($fallbackAgent) {
                $this->saveMessageToDb('assistant', '> Gesicherter Chat aktiviert... Wie kann ich helfen?', [
                    'name' => $fallbackAgent->name,
                    'color' => $fallbackAgent->color,
                    'icon' => $fallbackAgent->icon,
                    'profile_picture' => $fallbackAgent->profile_picture,
                ]);
            } else {
                $this->saveMessageToDb('assistant', '> Gesicherter Chat aktiviert... Bitte Agenten aktivieren.', [
                    'name' => 'System',
                    'color' => 'emerald-500',
                    'icon' => 'sparkles',
                    'profile_picture' => null,
                ]);
            }
        }
    }

    #[On('echo:workspace,TaskUpdated')]
    public function refreshWorkspaceTasks()
    {
        // Just refresh the component
    }

    public function updatedActiveTab($value)
    {
        if (auth()->check()) {
            \App\Models\Ai\AiUserWorkspaceSetting::updateOrCreate(
                ['user_id' => auth()->id()],
                ['active_tab' => $value]
            );
        }
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

    public function updatedAutoApprovePlan($value)
    {
        if (!auth()->check()) return;
        
        \App\Models\Ai\AiUserWorkspaceSetting::updateOrCreate(
            ['user_id' => auth()->id()],
            ['auto_approve_execution_plan' => $value]
        );
    }

    #[Computed]
    public function aiPlans()
    {
        return \App\Models\System\SystemAiHostingPlan::all();
    }

    public function addFeatureRow()
    {
        $this->newPlanFeatures[] = ['title' => '', 'description' => ''];
    }

    public function removeFeatureRow($index)
    {
        unset($this->newPlanFeatures[$index]);
        $this->newPlanFeatures = array_values($this->newPlanFeatures);
    }

    public function editPlan($id)
    {
        $plan = \App\Models\System\SystemAiHostingPlan::find($id);
        if ($plan) {
            $this->editingPlanId = $plan->id;
            $this->newPlanName = $plan->name;
            $this->newPlanPrice = $plan->price_monthly;
            $this->newPlanTokens = $plan->token_limit;
            $this->newPlanDescription = $plan->description;
            $this->newPlanFeatures = $plan->features ?: [['title' => '', 'description' => '']];
        }
    }

    public function cancelEdit()
    {
        $this->editingPlanId = null;
        $this->newPlanName = '';
        $this->newPlanPrice = '0.00';
        $this->newPlanTokens = null;
        $this->newPlanDescription = '';
        $this->newPlanFeatures = [['title' => '', 'description' => '']];
    }

    public function saveNewPlan()
    {
        $features = array_filter($this->newPlanFeatures, function($f) {
            return !empty($f['title']);
        });

        if ($this->editingPlanId) {
            $plan = \App\Models\System\SystemAiHostingPlan::find($this->editingPlanId);
            if ($plan) {
                $plan->update([
                    'name' => $this->newPlanName,
                    'price_monthly' => $this->newPlanPrice,
                    'token_limit' => $this->newPlanTokens,
                    'description' => $this->newPlanDescription,
                    'features' => array_values($features),
                ]);
                session()->flash('message', 'Tarif erfolgreich aktualisiert.');
            }
        } else {
            \App\Models\System\SystemAiHostingPlan::create([
                'name' => $this->newPlanName,
                'price_monthly' => $this->newPlanPrice,
                'token_limit' => $this->newPlanTokens,
                'description' => $this->newPlanDescription,
                'features' => array_values($features),
                'is_active' => false,
            ]);
            session()->flash('message', 'Neuer Tarif erfolgreich angelegt.');
        }

        $this->cancelEdit();
    }

    public function setActivePlan($id)
    {
        \App\Models\System\SystemAiHostingPlan::where('id', '!=', $id)->update(['is_active' => false]);
        \App\Models\System\SystemAiHostingPlan::where('id', $id)->update(['is_active' => true]);
        session()->flash('message', 'Tarif wurde als aktiv gesetzt.');
    }

    public function deletePlan($id)
    {
        $plan = \App\Models\System\SystemAiHostingPlan::find($id);
        if ($plan && !$plan->is_active) {
            $plan->delete();
            session()->flash('message', 'Tarif gelöscht.');
        }
    }
}

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
    #[\Livewire\Attributes\Locked]
    public ?int $editingPlanId = null;

    public function boot(): void
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren haben Zugriff auf den KI-Workspace.');
        }
    }

    public function mount()
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren haben Zugriff auf den KI-Workspace.');
        }

        if (\App\Services\AI\AiAuthHelper::check()) {
            $setting = \App\Models\Ai\AiUserWorkspaceSetting::where('user_id', \App\Services\AI\AiAuthHelper::getUserId())->first();
            if ($setting) {
                $this->chatHeightPercent = $setting->chat_height_percent;
                $this->autoApprovePlan = (bool) $setting->auto_approve_execution_plan;
                if (!empty($setting->active_tab) && in_array($setting->active_tab, ['workspace', 'chat', 'files', 'health', 'workflows', 'cronjobs'], true)) {
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

    #[On('echo-private:workspace,TaskUpdated')]
    public function refreshWorkspaceTasks()
    {
        // Just refresh the component
    }

    #[On('chat-memory-updated')]
    public function handleChatMemoryUpdated()
    {
        unset($this->messages);
    }

    public function updatedActiveTab($value)
    {
        $allowedTabs = ['workspace', 'chat', 'files', 'health', 'workflows', 'cronjobs', 'tasks', 'plans', 'logs', 'settings'];
        if (!in_array($value, $allowedTabs, true)) {
            $this->activeTab = 'chat';
            $value = 'chat';
        }

        if (\App\Services\AI\AiAuthHelper::check()) {
            \App\Models\Ai\AiUserWorkspaceSetting::updateOrCreate(
                ['user_id' => \App\Services\AI\AiAuthHelper::getUserId()],
                ['active_tab' => $value]
            );
        }
    }

    protected function sanitizeExternalTtsUrl(?string $url): string
    {
        $default = 'https://generativelanguage.googleapis.com';
        if (empty($url) || !is_string($url)) {
            return $default;
        }

        $parsed = parse_url(trim($url));
        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = strtolower(trim($parsed['host'] ?? '', '[]'));
        if ($scheme !== 'https' || $host === '') {
            return $default;
        }

        if (
            in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || preg_match('/^(127\.|10\.|192\.168\.|169\.254\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host)
        ) {
            return $default;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $default;
        }

        return rtrim(trim($url), '/');
    }
    
    public function syncAll()
    {
        $agents = AiAgent::where('is_active', true)->get();
        if ($agents->isEmpty()) return;

        // Ping LLM once globally (shared Gemini provider infrastructure)
        $start = microtime(true);
        $llmUrl = config('services.gemini.url') ?: 'https://generativelanguage.googleapis.com/v1beta/openai/';
        $key = config('services.gemini.key');
        
        $llmStatus = 'Offline';
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(3)->withToken($key)->get(rtrim($llmUrl, '/') . '/models');
            if ($response->successful() || $response->status() === 401 || $response->status() === 404) {
                $llmStatus = round((microtime(true) - $start) * 1000) . 'ms';
            } else {
                $llmStatus = 'Fehler';
            }
        } catch (\Exception $e) {
            $llmStatus = 'Offline';
        }

        // Parallel pool for active TTS endpoints
        $ttsAgents = $agents->filter(fn($a) => $a->tts_enabled && $a->tts_provider && $a->tts_provider !== 'none');
        $ttsResponses = [];

        if ($ttsAgents->isNotEmpty()) {
            try {
                $ttsResponses = \Illuminate\Support\Facades\Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($ttsAgents) {
                    foreach ($ttsAgents as $a) {
                        $ttsUrl = $this->sanitizeExternalTtsUrl($a->tts_api_url);
                        $pool->as('tts_' . $a->id)->timeout(3)->get($ttsUrl . '/v1beta/models');
                    }
                });
            } catch (\Exception $e) {
                $ttsResponses = [];
            }
        }

        foreach ($agents as $agent) {
            $ttsStatus = !$agent->tts_enabled ? 'Deaktiviert' : 'Inaktiv';
            if ($agent->tts_enabled && $agent->tts_provider && $agent->tts_provider !== 'none') {
                $resp = $ttsResponses['tts_' . $agent->id] ?? null;
                if ($resp instanceof \Illuminate\Http\Client\Response && ($resp->successful() || in_array($resp->status(), [401, 403, 404]))) {
                    $ttsStatus = 'OK';
                } elseif ($resp instanceof \Exception) {
                    $ttsStatus = 'Offline';
                } else {
                    $ttsStatus = 'Fehler';
                }
            }

            $this->pingResults[$agent->id] = [
                'llm' => $llmStatus,
                'tts' => $ttsStatus,
            ];
        }
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
            $llmUrl = config('services.gemini.url') ?: 'https://generativelanguage.googleapis.com/v1beta/openai/';
            $key = config('services.gemini.key');
            
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
                $ttsUrl = $this->sanitizeExternalTtsUrl($agent->tts_api_url);
                $response = \Illuminate\Support\Facades\Http::timeout(3)->get($ttsUrl . '/v1beta/models');
                if ($response->successful() || $response->status() === 401 || $response->status() === 403 || $response->status() === 404) {
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
        if (!\App\Services\AI\AiAuthHelper::check()) return;
        
        $percent = max(10, min(90, (int) $percent));
        $this->chatHeightPercent = $percent;
        
        \App\Models\Ai\AiUserWorkspaceSetting::updateOrCreate(
            ['user_id' => \App\Services\AI\AiAuthHelper::getUserId()],
            ['chat_height_percent' => $percent]
        );
    }

    public function updatedAutoApprovePlan($value)
    {
        if (!\App\Services\AI\AiAuthHelper::check()) return;
        
        \App\Models\Ai\AiUserWorkspaceSetting::updateOrCreate(
            ['user_id' => \App\Services\AI\AiAuthHelper::getUserId()],
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
        if (count($this->newPlanFeatures) >= 20) {
            return;
        }
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
        $this->validate([
            'newPlanName' => 'required|string|max:120',
            'newPlanPrice' => 'required|numeric|min:0|max:999999',
            'newPlanTokens' => 'nullable|integer|min:0',
            'newPlanDescription' => 'nullable|string|max:2000',
            'newPlanFeatures' => 'nullable|array|max:20',
        ]);

        $features = array_filter(array_slice((array) $this->newPlanFeatures, 0, 20), function ($f) {
            return is_array($f) && !empty(trim((string) ($f['title'] ?? '')));
        });
        $features = array_map(function ($f) {
            return [
                'title' => mb_substr(trim((string) ($f['title'] ?? '')), 0, 120),
                'description' => mb_substr(trim((string) ($f['description'] ?? '')), 0, 500),
            ];
        }, array_values($features));

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

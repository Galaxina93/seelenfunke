<?php

namespace App\Livewire\Shop\Ai;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use App\Models\System\SystemLog;
use App\Livewire\Shop\Ai\Traits\ManagesAiChat;

class AiWidget extends Component
{
    use WithFileUploads;
    use ManagesAiChat;

    public string $themingDepartment = 'Agenten';
    public $agentId = null;
    public $widgetConfig = null;
    
    // UI State for AlpineJS Entangle
    public $isMapFocus = false;
    public $isMapMode = false;
    public $isFlightDataActive = false;
    
    // UI Pool for AI context (Managed exclusively in Alpine.js frontend)
    
    public function mount($agentId = null)
    {
        $this->loadDefaultChatSession();
        $this->agentId = $agentId;
        
        $this->activeAgentIds = \App\Models\Ai\AiAgent::where('is_in_chat', true)->pluck('id')->toArray();
        $this->forcedAgentIds = $this->activeAgentIds;

        // Ensure we have at least one fallback agent initialized if none are in chat yet
        if (empty($this->activeAgentIds)) {
            $fallbackAgent = \App\Models\Ai\AiAgent::where('is_active', true)->first();
            if ($fallbackAgent) {
                $fallbackAgent->update(['is_in_chat' => true]);
                $this->activeAgentIds[] = $fallbackAgent->id;
                $this->forcedAgentIds[] = $fallbackAgent->id;
            }
        }

        if (auth()->check()) {
            $this->widgetConfig = \App\Models\Ai\AiWidgetConfig::firstOrCreate(
                ['user_id' => auth()->id()],
                [
                    'ai_agent_id' => $this->agentId ?? (isset($this->activeAgentIds[0]) ? $this->activeAgentIds[0] : null),
                    'volume' => 15,
                    'continuous_mode' => false,
                    'require_wake_word' => false,
                    'allow_voice_interruption' => true
                ]
            );
            if (!$this->agentId && $this->widgetConfig->ai_agent_id) {
                $this->agentId = $this->widgetConfig->ai_agent_id;
            }
        }
    }

    #[On('log-widget-error')]
    public function logWidgetError($message)
    {
        if (class_exists(SystemLog::class)) {
            SystemLog::create([
                'title' => 'Widget Fehler',
                'type' => 'Funkira Widget',
                'action_id' => 'widget:error',
                'message' => $message ?? 'Unbekannter Fehler',
                'status' => 'error'
            ]);
        }
    }

    public function getListeners()
    {
        return [
            "echo:workspace,AiWidgetSpeechEvent" => 'handleSpeechEvent',
            "echo:workspace,.App\\Events\\AiWidgetSpeechEvent" => 'handleSpeechEvent',
            "echo:workspace,AiFrontendEvent" => 'handleFrontendEvent',
            "echo:workspace,.App\\Events\\AiFrontendEvent" => 'handleFrontendEvent',
        ];
    }

    public function handleSpeechEvent($payload)
    {
        if (isset($payload['text'])) {
            $this->dispatch('ai-speech-feedback', text: $payload['text']);
        }
    }

    public function handleFrontendEvent($payload)
    {
        if (isset($payload['name'])) {
            $this->dispatch($payload['name'], payload: $payload['detail'] ?? []);
        }
    }

    public function updatedAgentId($value)
    {
        if (auth()->check() && $this->widgetConfig) {
            $this->widgetConfig->update(['ai_agent_id' => empty($value) ? null : $value]);
        }

        if (!empty($value)) {
            $this->activeAgentIds = [$value];
            $this->forcedAgentIds = [$value];
        }

        $agent = \App\Models\Ai\AiAgent::find($value);
        if ($agent) {
            $this->dispatch('agent-changed', 
                color: $agent->color,
                name: $agent->name,
                wakeWord: strtolower($agent->wake_word ?? $agent->name),
                agentId: $agent->id
            );
        }
    }

    public function saveWidgetConfig($data)
    {
        if (auth()->check() && $this->widgetConfig) {
            $this->widgetConfig->update([
                'volume' => $data['volume'] ?? $this->widgetConfig->volume,
                'ai_agent_id' => isset($data['agentId']) ? (empty($data['agentId']) ? null : $data['agentId']) : $this->widgetConfig->ai_agent_id,
                'allow_voice_interruption' => isset($data['allowVoiceInterruption']) ? $data['allowVoiceInterruption'] : $this->widgetConfig->allow_voice_interruption,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.shop.ai.ai-widget', [
            'tasks' => \App\Models\Ai\AiWorkspaceTask::with('agent')
                        ->where('status', '!=', 'archived')
                        ->whereIn('status', ['processing', 'pending', 'paused', 'awaiting_approval']) // Also show awaiting_approval so users can accept plans from widget
                        ->latest('created_at')
                        ->take(5) // Just the latest 5 in the widget to save space
                        ->get(),
            'availableAgents' => \App\Models\Ai\AiAgent::where('is_active', true)->get(),
        ]);
    }
}

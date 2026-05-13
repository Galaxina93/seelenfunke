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

    #[On('generate-neural-structure')]
    public function generateNeuralStructure($file_path)
    {
        $filePathStr = is_array($file_path) ? ($file_path['file_path'] ?? $file_path[0] ?? '') : $file_path;
        
        $node = \App\Models\System\SystemNeuralNode::where('file_path', $filePathStr)->first();
        
        // Fallback: Wenn die Datei existiert, aber nicht in der DB ist (weil Command nicht lief)
        if (!$node && \Illuminate\Support\Facades\File::exists(base_path($filePathStr))) {
            $methods = [];
            if (str_ends_with($filePathStr, '.php') && !str_ends_with($filePathStr, '.blade.php')) {
                $content = file_get_contents(base_path($filePathStr));
                preg_match_all('/(?:public|protected|private)\s+(?:static\s+)?function\s+([a-zA-Z0-9_]+)\s*\(/', $content, $mMatches);
                if (!empty($mMatches[1])) {
                    $methods = $mMatches[1];
                }
            }

            $dependencies = [];
            $jsonPath = storage_path('app/public/system-brain-map.json');
            if (\Illuminate\Support\Facades\File::exists($jsonPath)) {
                $graph = json_decode(file_get_contents($jsonPath), true);
                if (isset($graph['links'])) {
                    foreach ($graph['links'] as $link) {
                        $source = is_array($link['source']) ? ($link['source']['id'] ?? '') : $link['source'];
                        $target = is_array($link['target']) ? ($link['target']['id'] ?? '') : $link['target'];
                        
                        if ($source === $filePathStr && !empty($target)) {
                            $dependencies[] = basename($target);
                        } elseif ($target === $filePathStr && !empty($source)) {
                            $dependencies[] = basename($source);
                        }
                    }
                    $dependencies = array_values(array_unique($dependencies));
                    sort($dependencies);
                }
            }

            $node = new \App\Models\System\SystemNeuralNode([
                'file_path' => $filePathStr,
                'name' => basename($filePathStr),
                'group_id' => 1,
                'content_hash' => md5_file(base_path($filePathStr)),
                'dependencies' => $dependencies,
                'methods' => $methods,
            ]);
        }
        
        if ($node) {
            try {
                $indexer = new \App\Livewire\Backend\System\SystemNeuralAnalysisIndex();
                $indexer->createMarkdown($node);
                
                $safeName = str_replace(['/', '\\'], '_', $node->file_path);
                $fullPath = storage_path("app/public/agenten/workspace/md/Struktur_" . $safeName . ".md");

                $this->dispatch('ai-speech-feedback', text: "Struktur generiert. Download startet.");
                $this->dispatch('neural-structure-success', path: "Download gestartet");
                
                $url = asset("storage/agenten/workspace/md/Struktur_" . $safeName . ".md");
                $this->dispatch('trigger-download', url: $url, filename: 'Struktur_' . basename($node->file_path) . '.md');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Fehler beim Generieren der Struktur: " . $e->getMessage());
                $this->dispatch('ai-speech-feedback', text: "Fehler beim Erstellen der Struktur.");
                $this->dispatch('neural-structure-error', message: "Fehler beim Erstellen.");
            }
        } else {
            \Illuminate\Support\Facades\Log::info("Knoten nicht gefunden. file_path war: " . json_encode($filePathStr));
            $this->dispatch('ai-speech-feedback', text: "Knoten nicht in der Datenbank gefunden.");
            $this->dispatch('neural-structure-error', message: "Nicht gefunden");
        }
    }

    #[On('generate-system-brain-map')]
    public function generateSystemBrainMap()
    {
        \Illuminate\Support\Facades\Artisan::call('system:brain:generate');
        $this->dispatch('system-brain-map-generated');
        $this->dispatch('ai-speech-feedback', text: "Ich habe die neuronale Strukturkarte erfolgreich generiert.");
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

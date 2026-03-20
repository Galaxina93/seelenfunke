<?php

namespace App\Livewire\Global\Ai;

use App\Models\Ai\AiAgent;
use Livewire\Component;

class AiAgentManager extends Component
{
    public $pingResults = [];

    public function render()
    {
        $agents = AiAgent::orderByRaw("CASE WHEN name = 'Funkira' THEN 0 ELSE 1 END")->orderBy('name')->get();

        return view('livewire.global.ai.ai-agent-manager', [
            'agents' => $agents
        ])->layout('components.layouts.backend_layout', ['guard' => 'admin']);
    }

    public function createAgent()
    {
        return redirect()->route('admin.ai-agents.editor', ['id' => 'new']);
    }

    public function editAgent($id)
    {
        return redirect()->route('admin.ai-agents.editor', ['id' => $id]);
    }

    public function syncAll()
    {
        $agents = AiAgent::all();
        foreach ($agents as $agent) {
            $this->pingTest($agent->id);
        }
    }

    public function pingTest($id)
    {
        $agent = AiAgent::find($id);
        if (!$agent) return;

        // Ping LLM
        $llmStatus = 'Fehler';
        try {
            $start = microtime(true);
            $llmUrl = env('OPENAI_API_BASE', 'https://api.openai.com/v1');
            $response = \Illuminate\Support\Facades\Http::timeout(3)->withToken(env('OPENAI_API_KEY'))->get(rtrim($llmUrl, '/') . '/models');
            
            // If it returns 200, 401, or 404, the server is reachable
            if ($response->successful() || $response->status() === 401 || $response->status() === 404) {
                $llmStatus = round((microtime(true) - $start) * 1000) . 'ms';
            }
        } catch (\Exception $e) {
            $llmStatus = 'Offline';
        }

        // Ping TTS
        $ttsStatus = 'Inaktiv';
        if ($agent->tts_provider && $agent->tts_provider !== 'none') {
            try {
                $start = microtime(true);
                $ttsUrl = $agent->tts_api_url ?: 'http://127.0.0.1:8020';
                $response = \Illuminate\Support\Facades\Http::timeout(3)->get(rtrim($ttsUrl, '/') . '/languages');
                if ($response->successful() || $response->status() === 404) {
                    // Even a 404 means the server is reachable and instantly replied
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
}

<?php

namespace App\Livewire\Global\Ai;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

class ExternalAgentManager extends Component
{
    public $llm_model = '';
    public $pingRan = false;

    private $toniUrl = 'http://192.168.188.32:8000';

    public function mount()
    {
        // Removed fetchStatus() to prevent the 5-second blocking payload on page load!
    }

    public function fetchStatus()
    {
        $this->pingRan = true;
        try {
            $response = Http::timeout(3)->withToken(env('TONI_AI_API_KEY'))->get($this->toniUrl . '/api/toni/config');
            if ($response->successful()) {
                $data = $response->json();
                $this->llm_model = $data['llm_model'] ?? '';
                $this->connectionError = null;
            } else {
                $this->connectionError = 'Toni antwortet nicht korrekt (' . $response->status() . ').';
            }
        } catch (\Exception $e) {
            $this->connectionError = 'Toni ist offline oder nicht erreichbar.';
        }
    }

    public function editExternalAgent($id)
    {
        return redirect()->route('admin.external-agents.editor', ['id' => $id]);
    }

    public function render()
    {
        return view('livewire.global.ai.external-agent-manager');
    }
}

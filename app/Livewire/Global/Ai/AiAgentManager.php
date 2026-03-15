<?php

namespace App\Livewire\Global\Ai;

use App\Models\Ai\AiAgent;
use Livewire\Component;

class AiAgentManager extends Component
{
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
}

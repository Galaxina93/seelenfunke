<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;

use App\Jobs\ProcessAiWorkspaceTask;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiWorkspaceTask;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class AiWorkspace extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';
    
    public string $newTaskPrompt = '';

    public function getListeners()
    {
        return [
            "echo:workspace,TaskUpdated" => '$refresh',
        ];
    }
    
    public function createTask()
    {
        if(empty(trim($this->newTaskPrompt))) return;
        
        $task = AiWorkspaceTask::create([
            'prompt' => $this->newTaskPrompt,
            'status' => 'pending',
        ]);
        
        \App\Events\TaskUpdated::dispatch($task);
        
        $this->newTaskPrompt = '';
    }
    
    public function assignAgent($taskId, $agentId)
    {
        $task = AiWorkspaceTask::find($taskId);
        if ($task && $task->status === 'pending') {
            $task->update([
                'assigned_agent_id' => $agentId,
                'status' => 'assigned'
            ]);
            
            \App\Events\TaskUpdated::dispatch($task);
            ProcessAiWorkspaceTask::dispatch($task);
        }
    }

    public function render()
    {
        return view('livewire.shop.ai.ai-workspace', [
            'tasks' => AiWorkspaceTask::with('agent')->latest('created_at')->get(),
            'agents' => AiAgent::where('is_active', true)->get()
        ]);
    }
}

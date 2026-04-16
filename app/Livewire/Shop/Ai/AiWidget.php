<?php

namespace App\Livewire\Shop\Ai;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\System\SystemLog;

class AiWidget extends Component
{
    public string $themingDepartment = 'Agenten';
    public $agentId = null;
    
    public function mount($agentId = null)
    {
        $this->agentId = $agentId;
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

    public function render()
    {
        return view('livewire.shop.ai.ai-widget');
    }
}

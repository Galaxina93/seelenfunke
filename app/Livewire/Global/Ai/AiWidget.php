<?php

namespace App\Livewire\Global\Ai;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Global\GlobalLog;

class AiWidget extends Component
{
    #[On('log-widget-error')]
    public function logWidgetError($message)
    {
        if (class_exists(GlobalLog::class)) {
            GlobalLog::create([
                'type' => 'Funkira Widget',
                'action_id' => 'widget:error',
                'message' => $message ?? 'Unbekannter Fehler',
                'status' => 'error'
            ]);
        }
    }

    public function render()
    {
        $logs = GlobalLog::with('agent')->latest()->take(10)->get();
        return view('livewire.global.ai.ai-widget', ['logs' => $logs]);
    }
}

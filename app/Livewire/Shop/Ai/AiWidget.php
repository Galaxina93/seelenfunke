<?php

namespace App\Livewire\Shop\Ai;

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

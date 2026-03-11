<?php

namespace App\Livewire\Global\Funkira;

use Livewire\Component;

class FunkiraWidget extends Component
{
    // Toggle state for the UI
    public $showFunkiView = false;
    
    // Tracks where the user is currently located (Route Name or URI)
    public $currentContext = 'unknown';

    public function mount()
    {
        // Get the current route name or URI and provide it to the AI for Context.
        $this->currentContext = \Illuminate\Support\Facades\Route::currentRouteName() ?? request()->path();
    }

    public function render()
    {
        return view('livewire.global.funkira.funkira-widget');
    }
}

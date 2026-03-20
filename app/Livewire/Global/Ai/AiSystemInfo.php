<?php

namespace App\Livewire\Global\Ai;

use Livewire\Component;

class AiSystemInfo extends Component
{
    public function render()
    {
        $laravelVersion = app()->version();
        $phpVersion = PHP_VERSION;

        return view('livewire.global.ai.ai-system-info', [
            'laravelVersion' => $laravelVersion,
            'phpVersion' => $phpVersion,
        ]);
    }
}

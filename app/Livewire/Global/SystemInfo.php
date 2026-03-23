<?php

namespace App\Livewire\Global;

use Livewire\Component;

class SystemInfo extends Component
{
    public function render()
    {
        $laravelVersion = app()->version();
        $phpVersion = PHP_VERSION;

        return view('livewire.global.system-info', [
            'laravelVersion' => $laravelVersion,
            'phpVersion' => $phpVersion,
        ]);
    }
}

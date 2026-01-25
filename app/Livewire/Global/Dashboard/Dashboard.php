<?php

namespace App\Livewire\Global\Dashboard;

use Livewire\Component;

class Dashboard extends Component
{
    public $widgets;

    public function render()
    {
        return view('livewire.dashboard.dashboard');
    }

}

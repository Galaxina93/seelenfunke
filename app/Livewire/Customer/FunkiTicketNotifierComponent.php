<?php

namespace App\Livewire\Customer;

use Livewire\Component;

class FunkiTicketNotifierComponent extends Component
{
    // Die komplette Listener Logik passiert nun kugelsicher direkt im Frontend (Blade)!
    public function render()
    {
        return view('livewire.customer.funki-ticket-notifier-component');
    }
}

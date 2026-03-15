<?php

namespace App\Livewire\Global\Ai;

use App\Models\Ai\AiAgent;
use Livewire\Component;

class AiChat extends Component
{
    public $isOpen = false;
    public $input = '';
    public $messages = [];

    protected $listeners = ['toggleAiChat' => 'toggle'];

    public function toggle()
    {
        $this->isOpen = !$this->isOpen;
        if ($this->isOpen && empty($this->messages)) {
            // Hole zum Start den CEO (Funkira) oder falle auf System zurück
            $ceo = AiAgent::where('name', 'Funkira')->first();

            $this->messages[] = [
                'role' => 'assistant',
                'name' => $ceo ? $ceo->name : 'System',
                'content' => '> MAS Online. Initialisiere gesicherte Komm.-Kanäle. Wie kann ich helfen?',
                'color' => $ceo ? $ceo->color : 'emerald-500',
                'icon' => $ceo ? $ceo->icon : 'bi-stars',
            ];
        }
    }

    public function sendMessage()
    {
        if (trim($this->input) === '') return;

        $this->messages[] = [
            'role' => 'user',
            'name' => auth()->check() ? auth()->user()->first_name : 'User',
            'content' => $this->input,
            'color' => 'gray-400',
            'icon' => 'bi-person',
        ];

        $temp = $this->input;
        $this->input = '';

        // Placeholder für die spätere richtige Backend-Logik durch den AiAgentService
        $ceo = AiAgent::where('name', 'Funkira')->first();
        $this->messages[] = [
            'role' => 'assistant',
            'name' => $ceo ? $ceo->name : 'System',
            'content' => 'Verarbeite Kommandozeile: ' . $temp,
            'color' => $ceo ? $ceo->color : 'emerald-500',
            'icon' => $ceo ? $ceo->icon : 'bi-stars',
        ];
    }

    public function render()
    {
        return view('livewire.global.ai.ai-chat');
    }
}

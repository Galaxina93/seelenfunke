<?php

namespace App\Livewire\Global\Funkira;

use Livewire\Component;
use Livewire\Attributes\On;

/**
 * Der intelligente Router für das Generative UI von Funkira.
 * Empfängt abstrakte Daten-Kategorien von der KI und entscheidet über PHP (Headless),
 * welches View/Master Modal gerendert wird.
 */
class AiDataVisualization extends Component
{
    public bool $isOpen = false;
    public string $category = '';
    public array $data = [];

    #[On('open-ai-visualization')]
    public function handleAiVisualization(array $payload)
    {
        $this->category = $payload['category'] ?? 'general';
        $this->data = $payload['data'] ?? [];
        $this->isOpen = true;
    }

    public function close()
    {
        $this->isOpen = false;
        $this->category = '';
        $this->data = [];
    }

    public function render()
    {
        return view('livewire.global.funkira.ai-data-visualization');
    }
}

<?php

namespace App\Livewire\Global\Widgets;

use App\Models\FunkiLog;
use App\Services\AiSupportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On; // Neu hinzugefügt
use Livewire\Component;

class FunkiChat extends Component
{
    /* --- UI STATUS --- */
    public bool $isOpen = false;
    public string $activeMode = 'chat'; // chat, logs

    /* --- CHAT STATE --- */
    public string $input = '';
    public array $messages = [];
    public bool $isTyping = false;

    /**
     * Hört auf das Event vom Action-Dock
     */
    #[On('toggle-chat')]
    public function toggleChat(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function mount(): void
    {
        // Initial-Nachricht
        if (auth()->check()) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Hallo ' . auth()->user()->first_name . '! 👋 Ich bin Funki. Wie kann ich heute helfen?'
            ];
        } else {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Hallo! 👋 Ich bin Funki. Wie kann ich dir heute helfen?'
            ];
        }
    }

    #[Computed]
    public function history()
    {
        return FunkiLog::latest()->take(20)->get();
    }

    public function setMode(string $mode): void
    {
        if ($mode === 'logs' && !auth()->guard('admin')->check()) return;
        $this->activeMode = $mode;
    }

    public function sendMessage(AiSupportService $aiService): void
    {
        if (trim($this->input) === '') return;

        $userMessage = $this->input;
        $this->messages[] = ['role' => 'user', 'content' => $userMessage];
        $this->input = '';
        $this->isTyping = true;

        // KI-Anfrage
        $response = $aiService->askFunki($this->messages, $userMessage);

        $this->messages[] = ['role' => 'assistant', 'content' => $response];
        $this->isTyping = false;
    }

    public function render()
    {
        return view('livewire.global.widgets.funki-chat');
    }
}

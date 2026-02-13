<?php

namespace App\Livewire\Global\Widgets;

use App\Services\AiSupportService;
use Livewire\Component;

class FunkiChat extends Component
{
    public bool $isOpen = false;
    public string $input = '';
    public array $messages = [];
    public bool $isTyping = false;

    // Erste Nachricht von Funki beim Laden
    public function mount()
    {
        // ...
        if(auth()->check()) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Hallo ' . auth()->user()->first_name . '! 👋 Ich bin Funki. Soll ich mal nach deinen offenen Bestellungen schauen?'
            ];
        } else {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Hallo! 👋 Ich bin Funki. Wenn du dich einloggst, kann ich dir Infos zu deinen Bestellungen geben.'
            ];
        }
    }

    public function toggleChat()
    {
        $this->isOpen = !$this->isOpen;
    }

    public function sendMessage(AiSupportService $aiService)
    {
        // Validierung
        if (trim($this->input) === '') return;

        // 1. User Nachricht hinzufügen
        $userMessage = $this->input;
        $this->messages[] = ['role' => 'user', 'content' => $userMessage];
        $this->input = ''; // Input leeren
        $this->isTyping = true; // Ladeanimation starten

        // 2. Antwort asynchron holen (damit UI reagiert)
        // In Livewire können wir das direkt im nächsten Request verarbeiten oder hier blockierend machen.
        // Für simple Chats ist blockierend ok, aber wir nutzen einen Trick für UX.

        $response = $aiService->askFunki($this->messages, $userMessage);

        $this->messages[] = ['role' => 'assistant', 'content' => $response];
        $this->isTyping = false;
    }

    public function render()
    {
        return view('livewire.global.widgets.funki-chat');
    }
}

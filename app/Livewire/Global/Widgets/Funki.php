<?php

namespace App\Livewire\Global\Widgets;

use App\Models\FunkiLog;
use App\Services\AiSupportService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Funki extends Component
{
    /* --- UI STATUS --- */
    public bool $isOpen = false;
    public string $activeMode = 'chat'; // chat, automations

    /* --- CHAT STATE --- */
    public string $input = '';
    public array $messages = [];
    public bool $isTyping = false;

    /**
     * Initialisierung
     */
    public function mount(): void
    {
        if (auth()->check()) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Hallo ' . auth()->user()->first_name . '! 👋 Ich bin Funki. Wie kann ich helfen?'
            ];
        }
    }

    /**
     * Holt die neuesten Aktivitäten aus dem FunkiLog
     */
    #[Computed]
    public function history()
    {
        return FunkiLog::latest()->take(15)->get();
    }

    public function toggleChat(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function setMode(string $mode): void
    {
        if ($mode !== 'chat' && !auth()->guard('admin')->check()) return;
        $this->activeMode = $mode;
    }

    public function sendMessage(AiSupportService $aiService): void
    {
        if (trim($this->input) === '') return;

        $userMessage = $this->input;
        $this->messages[] = ['role' => 'user', 'content' => $userMessage];
        $this->input = '';
        $this->isTyping = true;

        $response = $aiService->askFunki($this->messages, $userMessage);

        $this->messages[] = ['role' => 'assistant', 'content' => $response];
        $this->isTyping = false;
    }

    public function render()
    {
        $autoTasks = [
            [
                'id' => 'newsletter:send',
                'name' => 'Newsletter-Marketing',
                'description' => 'Ich analysiere den Kalender und versende autonome Kampagnen.',
                'schedule' => 'Alle 15 Min',
                'status' => 'active',
                'icon' => 'bi-envelope-paper-heart',
                'last_run' => FunkiLog::where('action_id', 'newsletter:send')->where('status', 'success')->latest()->first()?->started_at?->diffForHumans() ?? 'Wartet...'
            ],
            [
                'id' => 'coupons:generate',
                'name' => 'Gutschein-Agent',
                'description' => 'Autonome Rabattcodes basierend auf Kunden-Interaktionen.',
                'schedule' => 'Täglich',
                'status' => 'coming_soon',
                'icon' => 'bi-ticket-perforated',
                'last_run' => 'In Planung'
            ],
            [
                'id' => 'blog:ai-writer',
                'name' => 'KI-Redaktion',
                'description' => 'SEO-Beiträge über Achtsamkeit und Kristalle verfassen.',
                'schedule' => 'Wöchentlich',
                'status' => 'coming_soon',
                'icon' => 'bi-journal-richtext',
                'last_run' => 'In Planung'
            ]
        ];

        return view('livewire.global.widgets.funki', [
            'autoTasks' => $autoTasks
        ]);
    }
}

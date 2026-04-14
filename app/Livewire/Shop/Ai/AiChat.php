<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;

use Livewire\Attributes\Layout;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class AiChat extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';
    public $input = '';
    public $messages = [];
    public $activeAgentIds = [];
    public $typingAgents = []; // Array of agent IDs currently typing

    public function mount()
    {
        // Lade Chat-Historie aus der Datenbank
        $history = AiChatMemory::where('session_id', session()->getId())
                               ->orderBy('created_at', 'asc')
                               ->get();

        if ($history->isNotEmpty()) {
            foreach ($history as $mem) {
                // Ignore internal 'tool' role messages in UI rendering
                if ($mem->role === 'tool') continue;

                $ctx = $mem->context_data ?? [];
                $this->messages[] = [
                    'role' => $mem->role,
                    'name' => $ctx['name'] ?? ucfirst($mem->role),
                    'content' => $mem->content,
                    'color' => $ctx['color'] ?? ($mem->role === 'user' ? 'gray-400' : 'emerald-500'),
                    'icon' => $ctx['icon'] ?? ($mem->role === 'user' ? 'user' : 'sparkles'),
                    'profile_picture' => $ctx['profile_picture'] ?? null,
                ];
            }
            // Aktive Agenten aus dem Verlauf rekonstruieren? (Optional, aktuell überspringen)

            // Wähle standardmäßig Funkira aus, wenn die Seite geladen wird
            $ceo = AiAgent::where('name', 'Funkira')->first();
            if ($ceo && !in_array($ceo->id, $this->activeAgentIds)) {
                $this->activeAgentIds[] = $ceo->id;
            }
        } else {
            // Hole zum Start den CEO (Funkira) oder falle auf System zurück
            $ceo = AiAgent::where('name', 'Funkira')->first();
            if ($ceo) {
                $this->activeAgentIds[] = $ceo->id;
                $this->saveMessageToDb('assistant', '> Gesicherter Chat aktiviert... Wie kann ich helfen?', [
                    'name' => $ceo->name,
                    'color' => $ceo->color,
                    'icon' => $ceo->icon,
                    'profile_picture' => $ceo->profile_picture,
                ]);
            } else {
                $this->saveMessageToDb('assistant', '> Gesicherter Chat aktiviert... Bitte Agenten aktivieren.', [
                    'name' => 'System',
                    'color' => 'emerald-500',
                    'icon' => 'sparkles',
                    'profile_picture' => null,
                ]);
            }

            // Lade die nun initial gespeicherten Nachrichten in die UI
            $this->messages = [];
            foreach (AiChatMemory::where('session_id', session()->getId())->get() as $mem) {
                if ($mem->role === 'tool') continue;
                $ctx = $mem->context_data ?? [];
                $this->messages[] = [
                    'role' => $mem->role,
                    'name' => $ctx['name'] ?? ucfirst($mem->role),
                    'content' => $mem->content,
                    'color' => $ctx['color'] ?? ($mem->role === 'user' ? 'gray-400' : 'emerald-500'),
                    'icon' => $ctx['icon'] ?? ($mem->role === 'user' ? 'user' : 'sparkles'),
                    'profile_picture' => $ctx['profile_picture'] ?? null,
                ];
            }
        }
    }

    public function getAgentsProperty()
    {
        return AiAgent::where('is_active', true)->orderByRaw("CASE WHEN name = 'Funkira' THEN 0 ELSE 1 END")->orderBy('name')->get();
    }

    public function toggleAgent($agentId)
    {
        if (in_array($agentId, $this->activeAgentIds)) {
            $this->activeAgentIds = array_values(array_diff($this->activeAgentIds, [$agentId]));
        } else {
            $this->activeAgentIds[] = $agentId;
        }
    }

    private function saveMessageToDb($role, $content, $contextData)
    {
        AiChatMemory::create([
            'session_id' => session()->getId(),
            'role' => $role,
            'content' => $content,
            'context_data' => $contextData,
        ]);
    }

    public function sendMessage()
    {
        if (trim($this->input) === '') return;

        $userCtx = [
            'name' => auth()->check() ? auth()->user()->first_name : 'User',
            'color' => 'gray-400',
            'icon' => 'user',
            'profile_picture' => auth()->check() && auth()->user()->profile ? auth()->user()->profile->photo_path : null,
        ];

        // UI Update
        $this->messages[] = [
            'role' => 'user',
            'name' => $userCtx['name'],
            'content' => $this->input,
            'color' => $userCtx['color'],
            'icon' => $userCtx['icon'],
            'profile_picture' => $userCtx['profile_picture'],
        ];

        // DB Save
        $this->saveMessageToDb('user', $this->input, $userCtx);

        $this->input = '';

        if (empty($this->activeAgentIds)) {
            $errCtx = [
                'name' => 'System',
                'color' => 'red-500',
                'icon' => 'exclamation-triangle',
                'profile_picture' => null,
            ];
            $errStr = 'FEHLER: Kein Agent für Verarbeitung ausgewählt. Bitte wähle mindestens einen Agenten im oberen Panel aus.';
            $this->messages[] = array_merge(['role' => 'assistant', 'content' => $errStr], $errCtx);
            $this->saveMessageToDb('assistant', $errStr, $errCtx);
            return;
        }

        // Aktiviere paralleles Tipping für alle gewählten Agenten
        $this->typingAgents = array_merge($this->typingAgents, $this->activeAgentIds);

        // Ping Frontend to dispatch parallel background processing
        $this->dispatch('start-ai-inference', agentIds: $this->activeAgentIds);
    }

    #[On('process-agent')]
    public function processAgent($agentId)
    {
        // Falls dieser Event mehrfach feuert, checken, ob er noch laden muss
        if (!in_array($agentId, $this->typingAgents)) return;

        $agent = AiAgent::find($agentId);
        if (!$agent) {
             $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
             return;
        }

        // Kompletten DB Verlauf: Lade nur die letzten 20 Nachrichten für ultra-schnelle API-Antworten
        $fullDbHistory = AiChatMemory::where('session_id', session()->getId())
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->reverse();

        $apiHistory = [];
        foreach ($fullDbHistory as $mem) {
            // Ignoriere alte Tool-Calls für den API Kontext. Das spart massiv Token und verhindert Schema-Fehler.
            if ($mem->role === 'tool') continue;

            $apiHistory[] = [
                'role' => $mem->role,
                'content' => $mem->content
            ];
        }

        // --- MULTI-AGENT ROUTING INJECTION ---
        $multiAgentRule = '';
        if (count($this->activeAgentIds) > 1) {
            $isFunkira = $agent->name === 'Funkira';
            $funkiraRule = $isFunkira 
                ? "Du BIST Funkira (CEO). Du bist die Leiterin. Wenn der User fachliche Anfragen stellt (z.B. Bestellungen, Buchhaltung, Produkte, Server), für die du KEINE eigenen Werkzeuge hast, VERWEISE NICHT auf Kollegen und sag nichts, sondern antworte AUSSCHLIESSLICH mit exakt '[SKIP]'. Deine Kollegen (die anderen Agenten) bearbeiten das dann! Du antwortest NUR auf CEO-Smalltalk, Begrüßungen, allgemeine System-Informationen oder wenn keine andere Fachabteilung zuständig ist."
                : "Du bist in einem Multi-Agent Chat. Es hören auch andere Agenten (z.B. Sales, Support, System) und Funkira (die CEO) zu. WICHTIGE REGEL: Wenn die Anfrage des Users NICHT exakt in deinen fachlichen Aufgabenbereich / zu deinen API-Werkzeugen passt, antworte ZWINGEND und NUR mit '[SKIP]'. Mache keine Ausnahmen! Du bearbeitest NUR Anfragen, für die du der absolute Spezialist bist. Wenn ein Kollege besser passt, hülle dich in Schweigen ('[SKIP]'). Bedenke: Wenn du eine Antwort aus dem Chatverlauf ablesen müsstest, anstatt ein Werkzeug zu nutzen, bist du sehr wahrscheinlich der FALSCHE Agent! Antworte dann mit '[SKIP]'!";

            $multiAgentRule = "[MULTI-AGENT KOORDINATIONS-PROTOKOLL]\n" . $funkiraRule;
        }

        try {
            $apiService = \App\Services\AI\AiAgentFactory::make($agent);
            if ($multiAgentRule) {
                $apiService->setDynamicSystemPrompt($multiAgentRule);
            }
            

            $response = $apiService->ask($apiHistory);
            $replyText = $response['response'] ?? 'Ich konnte keine Antwort generieren.';

            // Überprüfe auf Skipped Routing
            if (str_contains(strtoupper($replyText), '[SKIP]')) {
                // Agent ignoriert die Nachricht still (keine Kosten im Frontend, kein DB Eintrag)
                $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
                return;
            }

            // Tracking is now automatically handled centrally in AiAgentFactory

            $ctx = [
                'name' => $agent->name,
                'color' => $agent->color,
                'icon' => $agent->icon,
                'profile_picture' => $agent->profile_picture,
            ];

            $this->saveMessageToDb('assistant', $replyText, $ctx);

            $this->messages[] = array_merge(['role' => 'assistant', 'content' => $replyText], $ctx);

        } catch (\Exception $e) {
            $errCtx = [
                'name' => 'System',
                'color' => 'red-500',
                'icon' => 'exclamation-triangle',
                'profile_picture' => null,
            ];
            $errStr = 'API Fehler [' . $agent->name . ']: ' . $e->getMessage();
            $this->messages[] = array_merge(['role' => 'assistant', 'content' => $errStr], $errCtx);
            $this->saveMessageToDb('assistant', $errStr, $errCtx);
        }

        // Remove from typing array
        $this->typingAgents = array_diff($this->typingAgents, [$agentId]);
    }

    public function clearChat()
    {
        AiChatMemory::where('session_id', session()->getId())->delete();
        $this->messages = [];
        $this->activeAgentIds = [];
        $this->typingAgents = [];
        $this->mount();
    }

    public function render()
    {
        return view('livewire.shop.ai.ai-chat', [
            'agents' => $this->agents
        ]);
    }
}

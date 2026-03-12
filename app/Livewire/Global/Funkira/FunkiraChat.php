<?php

namespace App\Livewire\Global\Funkira;

use App\Models\Funki\FunkiLog;
use App\Models\User as UserHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class FunkiraChat extends Component
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
    public function toggleChat(): void
    {
        $this->isOpen = !$this->isOpen;
        session()->put('funkira_is_open', $this->isOpen);
    }

    /**
     * Schließt den Chat explizit und öffnet das Zentrum
     */
    public function openZentrum(): void
    {
        // Teile dem Frontend (Alpine) mit, dass das Zentrum öffnet
        // -> WICHTIG: Orb Mic deaktiviert sich daraufhin automatisch (`@funkira-center-opened` Listener im Blade)
        $this->dispatch('funkira-center-opened');

        // Das 3D Widget reagiert jetzt auf das 'open-funkira' Event, ohne die Seite neu zu laden
        $this->dispatch('open-funkira');
    }

    public function mount(): void
    {
        $this->isOpen = session()->get('funkira_is_open', false);
        $guard = (new UserHelper)->getGuard();
        $user = $guard ? Auth::guard($guard)->user() : null;

        // Versuche Chat-Verlauf aus Session zu laden
        $savedMessages = session()->get('funkira_chat_history', []);

        if (!empty($savedMessages)) {
            $this->messages = $savedMessages;
        } else {
            // Initial-Nachricht angepasst an Funkira Persona
            if (auth()->check()) {
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => 'Hallo ' . auth()->user()->first_name . '! 👋 Ich bin Funkira. Bereit, effizient Probleme zu lösen und Ziele zu erreichen?'
                ];
            } else {
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => 'Hallo! 👋 Ich bin Funkira. Lass uns auf Ergebnisse fokussieren.'
                ];
            }
            session()->put('funkira_chat_history', $this->messages);
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

    public function sendMessage(): void
    {
        if (trim($this->input) === '') return;

        $userMessage = $this->input;
        $this->messages[] = ['role' => 'user', 'content' => $userMessage];
        $this->input = '';
        $this->isTyping = true;

        // Log user message into Live Log
        if (class_exists(FunkiLog::class)) {
            FunkiLog::create([
                'action_id' => 'chat_user_' . uniqid(),
                'title' => auth()->check() ? auth()->user()->first_name : 'User',
                'message' => $userMessage,
                'type' => 'chat_user',
                'status' => 'success',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        \Illuminate\Support\Facades\Cache::put('ai_live_state', [
            'active_node' => 'bolt',
            'action_text' => 'Livewire erhält Transkript: ' . \Illuminate\Support\Str::limit($userMessage, 15),
            'pulse_color' => 'indigo'
        ], 60);

        try {
            $agent = new \App\Services\AI\MittwaldAgent();

            // Provide exact URL Context to the AI
            $currentUrl = request()->headers->get('referer') ?? url()->current();
            $pageName = $this->translateUrlToPageName($currentUrl);

            $payload = $this->messages;
            $payload[] = [
                'role' => 'system',
                'content' => "SYSTEM-INFO (Verdeckt): Der User befindet sich momentan im System-Bereich: '" . $pageName . "'. Nutze ausschließlich diesen Namen für die Orientierung und sage mir nicht den Pfad."
            ];

            $result = $agent->ask($payload);

            // The MittwaldAgent already appends the assistant response to the history array
            $this->messages = $result['history'];

            // Extrahiere die letzte Assistant-Antwort, um sie ins Live Log zu pushen
            $lastResponse = end($this->messages);
            if ($lastResponse && $lastResponse['role'] === 'assistant' && !empty($lastResponse['content'])) {
                if (class_exists(FunkiLog::class)) {
                    FunkiLog::create([
                        'action_id' => 'chat_ai_' . uniqid(),
                        'title' => 'Funkira',
                        'message' => $lastResponse['content'],
                        'type' => 'chat_ai',
                        'status' => 'success',
                        'started_at' => now(),
                        'finished_at' => now(),
                    ]);
                }
            }

            // Execute the side-effect actions (Navigation, Opening Modules) triggered by internal API Tools
            if (isset($result['events']) && is_array($result['events'])) {
                foreach ($result['events'] as $evt) {
                    if (isset($evt['type']) && $evt['type'] === 'navigate' && isset($evt['url'])) {
                        $this->dispatch('funkira-navigate', url: $evt['url']);
                    }
                    if (isset($evt['type']) && $evt['type'] === 'dispatch' && isset($evt['name'])) {
                         // Special case for 'open-funkira': We must also stop the Orb Mic
                        if ($evt['name'] === 'open-funkira') {
                            $this->dispatch('funkira-center-opened');
                        }
                        $this->dispatch($evt['name']);
                    }
                }
            }

            session()->put('funkira_chat_history', $this->messages);

            // Audio-Ausgabe triggern (Event an AlpineJS)
            if (!empty($result['response'])) {
                \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                    'active_node' => 'globe-alt',
                    'action_text' => 'Ausgabe via Web Speech API...',
                    'pulse_color' => 'emerald'
                ], 60);
                
                $this->dispatch('funkira-spoke', text: $result['response']);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Chat Error: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());

            $errorDetail = $e->getMessage();
            $file = basename($e->getFile());
            $line = $e->getLine();

            $this->messages[] = [
                'role' => 'assistant',
                'content' => "⚠️ **SYSTEM WARNUNG & FEHLERANALYSE** ⚠️\n\nMein neurales Netzwerk hat eine kritische Ausnahmebedingung abgefangen.\n\n[FEHLER-DETAILS]\nFile: {$file} (Zeile: {$line})\nMessage: {$errorDetail}\n\n[GEGENMASSNAHME]\nIch kann diesen Codeblock nicht selbst umschreiben. Bitte kopiere diese genaue Fehlermeldung und übergib sie meinem Entwickler **Gemini**, damit er den Bug sofort in der Architektur fixen kann, Herrin Alina. Wenn das erledigt ist, bin ich sofort wieder zu 100% einsatzbereit."
            ];

            // Speichern in der Session, damit auch der Fehler sichtbar bleibt
            session()->put('funkira_chat_history', $this->messages);
        }

        $this->isTyping = false;

        $this->dispatch('message-sent');
    }

    public function render()
    {
        return view('livewire.global.funkira.funkira-chat');
    }

    private function translateUrlToPageName(string $url): string
    {
        $map = [
            '/admin/dashboard' => 'Dashboard / Startseite',
            '/admin/funki' => 'Funkira 3D Zentrum',
            '/admin/funki-routine' => 'Morgenroutine',
            '/admin/funki-todos' => 'Todo-Listenverwaltung',
            '/admin/funki-kalender' => 'Firmenkalender / Termine',
            '/admin/company-map' => 'Firmenstruktur & Partner',
            '/admin/tickets' => 'Kundensupport (Tickets)',
            '/admin/knowledge_base' => 'Wiki / Wissensdatenbank',
            '/admin/user-management' => 'Benutzerverwaltung (Mitarbeiter)',
            '/admin/products' => 'Produkte / Shop-Artikel',
            '/admin/product-templates' => 'Produkt-Templates',
            '/admin/reviews' => 'Produkt-Bewertungen',
            '/admin/invoices' => 'Rechnungen',
            '/admin/credit-management' => 'Gutschriftenverwaltung',
            '/admin/orders' => 'Bestellungen',
            '/admin/quote-requests' => 'Angebotsanfragen (Quotes)',
            '/admin/financial-evaluation' => 'Finanzübersicht & Auswertung',
            '/admin/financial-liquidity-planning' => 'Liquiditätsplanung',
            '/admin/financial-banks' => 'Bankkonten & Liquidität',
            '/admin/financial-fix-costs' => 'Fixkosten',
            '/admin/financial-variable-costs' => 'Variable Kosten / Sonderausgaben',
            '/admin/financial-tax' => 'Steuer Export & Tresor',
            '/admin/configuration' => 'System-Einstellungen',
            '/admin/blog' => 'Blog-Beiträge',
            '/admin/voucher' => 'Gutscheine / Rabattcodes',
            '/admin/newsletter' => 'Newsletter-Verwaltung',
        ];

        // Find match in map
        foreach ($map as $path => $name) {
            if (str_contains($url, $path)) {
                return $name;
            }
        }

        return 'Unbekannte Seite';
    }
}

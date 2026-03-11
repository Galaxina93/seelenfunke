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

            // Extract [NAVIGATE] tag to trigger a safe singular browser redirect event
            $redirectUrl = null;
            $eventName = null;
            $lastMessageIndex = count($this->messages) - 1;
            
            if (isset($this->messages[$lastMessageIndex]['content'])) {
                if (preg_match('/\[NAVIGATE\](.*?)\[\/NAVIGATE\]/', $this->messages[$lastMessageIndex]['content'], $matches)) {
                    $redirectUrl = trim($matches[1]);
                }
                if (preg_match('/\[EVENT\](.*?)\[\/EVENT\]/', $this->messages[$lastMessageIndex]['content'], $matches)) {
                    $eventName = trim($matches[1]);
                }
            }

            // Save to session, but strip [NAVIGATE], [EVENT] and [COMPONENT] tags to prevent infinite redirect loops on reload
            // Wir überschreiben jetzt AUCH $this->messages selbst, damit beim Livewire Re-Render der Tag weg ist!
            $this->messages = array_map(function($msg) {
                if (isset($msg['content'])) {
                    $msg['content'] = preg_replace('/\[NAVIGATE\](.*?)\[\/NAVIGATE\]/is', "\n*(Sprung zu: $1)*", $msg['content']);
                    $msg['content'] = preg_replace('/\[EVENT\](.*?)\[\/EVENT\]/is', "", $msg['content']);
                    // KEEP [COMPONENT] in the history so Blade can render it!
                    // Do NOT strip it here, Blade needs it to render the component UI.
                }
                return $msg;
            }, $this->messages);

            session()->put('funkira_chat_history', $this->messages);

            // Execute the redirect via Livewire Event instead of raw HTML script injection
            if ($redirectUrl) {
                $this->dispatch('funkira-navigate', url: $redirectUrl);
            }
            if ($eventName) {
                // Special case for 'open-funkira': We must also stop the Orb Mic
                if ($eventName === 'open-funkira') {
                    $this->dispatch('funkira-center-opened');
                }
                $this->dispatch($eventName);
            }

            // Audio-Ausgabe triggern (Event an AlpineJS)
            if (!empty($result['response'])) {
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

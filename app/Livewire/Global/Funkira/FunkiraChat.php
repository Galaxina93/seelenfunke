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

            $currentUrl = request()->headers->get('referer') ?? url()->current();
            $pageName = $this->translateUrlToPageName($currentUrl);

            // Generiere die Navigations-Map für die KI
            $navMap = "Verfügbare Admin-Routen & Bezeichnungen:\n";
            $map = $this->getUrlMap();
            foreach ($map as $path => $name) {
                $navMap .= "- " . $name . " => " . $path . "\n";
            }

            $payload = $this->messages;
            $payload[] = [
                'role' => 'system',
                'content' => "SYSTEM-INFO (Verdeckt): Der User befindet sich momentan auf: '" . $pageName . "'.\n" . 
                             "WICHTIG ZUR NAVIGATION: Wenn du das Tool `open_nav_item` einsetzt, wähle IMMER nur eine exakte Route aus dieser Liste. Erfinde und rate NIEMALS fremde URLs! Nutze ausschließlich diese:\n" . $navMap . "\n" .
                             "ACHTUNG: Wenn Alina befiehlt das 'Zentrum' zu öffnen, dann MUSS zwingend das Tool `open_zentrum` ausgeführt werden! Vergiss in dem Fall `open_nav_item`!"
            ];

            $result = $agent->ask($payload);

            // The MittwaldAgent appends the prompt and assistant response to the history array.
            // We strip out 'system' and 'tool' roles so they don't pollute the user UI.
            $cleanHistory = array_filter($result['history'], function ($msg) {
                return !in_array($msg['role'], ['system', 'tool']);
            });
            $this->messages = array_values($cleanHistory);

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
            $hasNavigation = false;
            if (isset($result['events']) && is_array($result['events'])) {
                foreach ($result['events'] as $evt) {
                    if (isset($evt['name']) && $evt['name'] === 'open-ai-visualization') {
                        $this->dispatch('open-ai-visualization', payload: $evt['detail']);
                        $hasNavigation = true;
                    }
                    if (isset($evt['type']) && $evt['type'] === 'navigate' && isset($evt['url'])) {
                        $this->dispatch('funkira-navigate', url: $evt['url']);
                        $hasNavigation = true;
                    }
                    if (isset($evt['type']) && $evt['type'] === 'dispatch' && isset($evt['name'])) {
                         // Special case for 'open-funkira': We must also stop the Orb Mic
                        if ($evt['name'] === 'open-funkira') {
                            $this->dispatch('funkira-center-opened');
                            $hasNavigation = true;
                        }
                        $this->dispatch($evt['name']);
                    }
                }
            }

            if ($hasNavigation) {
                // Remove the "Ist notiert" or whatever text the AI outputted so it doesn't open the chat bubble
                $lastMsg = end($this->messages);
                if ($lastMsg && $lastMsg['role'] === 'assistant') {
                    array_pop($this->messages);
                }
            }

            session()->put('funkira_chat_history', $this->messages);

            // Audio-Ausgabe triggern (Event an AlpineJS)
            // Wenn navigiert wird, redet sie nicht mehr "Ist notiert", we can save the traffic
            if (!$hasNavigation && !empty($result['response'])) {
                \Illuminate\Support\Facades\Cache::put('ai_live_state', [
                    'active_node' => 'globe-alt',
                    'action_text' => 'Ausgabe via Web Speech API...',
                    'pulse_color' => 'emerald'
                ], 60);

                $spokenText = $this->sanitizeForTTS($result['response']);
                $this->dispatch('funkira-spoke', text: $spokenText);
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

    private function getUrlMap(): array
    {
        return \App\Services\AI\AIFunctionsRegistry::getAdminNavigationMap();
    }

    private function translateUrlToPageName(string $url): string
    {
        // Find match in map
        foreach ($this->getUrlMap() as $path => $name) {
            if (str_contains($url, $path)) {
                return $name;
            }
        }

        return 'Unbekannte Seite';
    }

    /**
     * Bereinigt den LLM-Output massiv, damit die Web Speech API (oder ElevenLabs)
     * den Text absolut natürlich und fehlerfrei vorliest.
     */
    private function sanitizeForTTS(string $text): string
    {
        // 1. Markdown entfernen (Sterne, Rauten, Backticks)
        $text = preg_replace('/[\*#`]+/', '', $text);

        // 2. Ersetze " - " bei Zahlen durch " bis " (z.B. "100 - 200" -> "100 bis 200")
        $text = preg_replace('/(\d+)\s*-\s*(\d+)/', '$1 bis $2', $text);

        // 3. Währungen sauber ausschreiben, damit sie nicht verschluckt oder als "Eurozeichen" gelesen werden
        $text = str_replace(['€', '$'], [' Euro', ' Dollar'], $text);
        
        // Formatiere Beträge wie 1.500,50 Euro -> "1500 Komma 50 Euro" für die Engine
        // Verhindert, dass die Engine "1 Punkt 500" liest
        $text = preg_replace_callback('/(\d+)[.,](\d+)\s*(Euro|Dollar)/i', function($matches) {
            $whole = str_replace('.', '', $matches[1]); // Tausendertrennzeichen raus
            return $whole . ' Komma ' . $matches[2] . ' ' . $matches[3];
        }, $text);

        // 4. Datumsangaben besser formatieren
        // Aus "01.01.2024" wird "1. Januar 2024" um "Null eins Punkt Null eins" zu verhindern
        $months = [
            '01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April',
            '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember'
        ];
        $text = preg_replace_callback('/(\d{1,2})\.(\d{2})\.(\d{4})/', function($matches) use ($months) {
            $day = (int)$matches[1];
            $month = $months[$matches[2]] ?? $matches[2];
            return $day . '. ' . $month . ' ' . $matches[3];
        }, $text);

        // 5. Typische Prozent und Sonderzeichen ausschreiben
        $text = str_replace('%', ' Prozent', $text);
        $text = str_replace('&', 'und', $text);
        $text = str_replace('+', 'plus', $text);
        $text = str_replace('=', 'gleich', $text);

        // 6. Abkürzungen ausschreiben, die oft falsch gelesen werden
        $replacements = [
            ' bzgl. ' => ' bezüglich ',
            ' z.B. ' => ' zum Beispiel ',
            ' bzw. ' => ' beziehungsweise ',
            ' ca. ' => ' circa ',
            ' inkl. ' => ' inklusive ',
            ' exkl. ' => ' exklusive ',
            ' zzgl. ' => ' zuzüglich ',
            ' d.h. ' => ' das heißt ',
            ' evtl. ' => ' eventuell ',
            ' u.a. ' => ' unter anderem '
        ];
        $text = str_ireplace(array_keys($replacements), array_values($replacements), $text);

        // 7. Smileys und Emoticons komplett entfernen, da sie als "Lachendes Gesicht" vorgelesen werden
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text); // Emoticons
        $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $text); // Symbols & Pictographs
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text); // Transport & Map
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);   // Misc symbols
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);   // Dingbats

        // 8. Multiple Leerzeichen und Zeilenumbrüche glätten für flüssigeres Sprechen
        $text = preg_replace('/\s+/', ' ', $text);
        $text = str_replace(["\n", "\r"], ' ', $text);

        return trim($text);
    }
}

<?php

namespace App\Livewire\Frontend\Support;

use Livewire\Component;
use App\Models\Support\SupportCustomerChat;
use App\Models\Support\SupportCustomerChatMessage;
use App\Models\Ai\AiAgent;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use App\Livewire\Traits\WithDepartmentTheming;

class CustomerChat extends Component
{
    use WithDepartmentTheming;
    
    protected string $themingDepartment = 'Support';
    public $chatId = null;
    public $message = '';
    public $messages = [];
    public $isTyping = false;
    public $agentName = 'Funki AI';
    public $agentImage = '';
    public $guestLimitReached = false;
    
    // Rating / Resolution State
    public $isResolved = false;
    public $rating = 0;
    public $feedbackText = '';
    public $ratingSubmitted = false;

    public function mount()
    {
        $supportAgent = \App\Models\Ai\AiAgent::whereHas('department', function ($query) {
            $query->where('name', 'Support');
        })->where('is_active', true)->first();
        
        if (!$supportAgent) {
            $supportAgent = \App\Models\Ai\AiAgent::where('name', 'Funki')->first();
        }

        if ($supportAgent) {
            $this->agentName = $supportAgent->name;
            if ($supportAgent->profile_picture) {
                $this->agentImage = \Illuminate\Support\Str::startsWith($supportAgent->profile_picture, 'shop/') ? asset($supportAgent->profile_picture) : Storage::url($supportAgent->profile_picture);
            }
        }
        // Hole die aktuelle Chat ID zuverlässig aus der Laravel Session
        $sessionChatId = session('current_chat_id');
        $customerId = auth()->guard('customer')->id();

        if ($sessionChatId) {
            // Wir haben eine feste Chat-ID aus der Session!
            $chat = SupportCustomerChat::where('id', $sessionChatId)
                    ->whereNull('rating')
                    ->whereIn('status', ['open', 'needs_employee', 'resolved', 'resolved_admin', 'resolved_auto'])
                    ->with(['messages'])
                    ->first();

            if ($chat) {
                // Auto-Claim: Falls das ein Gast war und nun ein Kunde eingeloggt ist
                if ($chat->customer_id === null && $customerId) {
                    $chat->update(['customer_id' => $customerId]);
                }
                
                $this->chatId = $chat->id;
                $this->isResolved = in_array($chat->status, ['resolved', 'resolved_admin', 'resolved_auto']);
                $this->rating = $chat->rating ?? 0;
                $this->feedbackText = $chat->feedback_text ?? '';
                $this->ratingSubmitted = !empty($chat->rating);
                
                foreach ($chat->messages as $msg) {
                    $this->messages[] = [
                        'sender' => $msg->sender,
                        'text' => $msg->message,
                    ];
                }
                return; // Erfolgreich gemounted!
            }
        }

        // Fallback: Suche nach dem jüngsten offenen/gelösten Chat des aktuellen (eingeloggten) Kunden
        if ($customerId) {
            $existing = SupportCustomerChat::whereNull('rating')
                ->whereIn('status', ['open', 'needs_employee', 'resolved', 'resolved_admin', 'resolved_auto'])
                ->where('customer_id', $customerId)
                ->with(['messages'])
                ->latest()
                ->first();

            if ($existing) {
                session(['current_chat_id' => $existing->id]);
                $this->chatId = $existing->id;
                $this->isResolved = in_array($existing->status, ['resolved', 'resolved_admin', 'resolved_auto']);
                $this->rating = $existing->rating ?? 0;
                $this->feedbackText = $existing->feedback_text ?? '';
                $this->ratingSubmitted = !empty($existing->rating);
                
                foreach ($existing->messages as $msg) {
                    $this->messages[] = [
                        'sender' => $msg->sender,
                        'text' => $msg->message,
                    ];
                }
            }
        }
    }

    public function sendMessage()
    {
        $text = trim($this->message);
        if (empty($text)) return;

        $currentCustomerId = auth()->guard('customer')->id();
        
        if ($this->chatId) {
            $chat = SupportCustomerChat::find($this->chatId);
            if ($chat && $chat->customer_id === null && $currentCustomerId) {
                $chat->update(['customer_id' => $currentCustomerId]);
            } elseif ($chat && $chat->customer_id != $currentCustomerId) {
                $this->chatId = null;
                $this->messages = [];
            }
        }

        $this->message = '';

        if (!$this->chatId) {
            $chat = SupportCustomerChat::create([
                'customer_id' => auth()->guard('customer')->id(),
                'status' => 'open'
            ]);
            $this->chatId = $chat->id;
            session(['current_chat_id' => $chat->id]); // <--- FIX: Chat ID persistent speichern!

            $messageStr = "{$this->agentName} ist dem Chat beigetreten.";
            SupportCustomerChatMessage::create([
                'support_customer_chat_id' => $this->chatId,
                'sender' => 'system',
                'message' => $messageStr
            ]);
            array_unshift($this->messages, ['sender' => 'system', 'text' => $messageStr]);
        }

        // Speichern Nutzer-Nachricht
        SupportCustomerChatMessage::create([
            'support_customer_chat_id' => $this->chatId,
            'sender' => 'customer',
            'message' => $text
        ]);

        $this->messages[] = ['sender' => 'customer', 'text' => $text];
        
        // --- FREEMIUM COUNTER LOGIC ---
        $isCustomer = auth()->guard('customer')->check();
        $isSystem = false;
        if (class_exists(\App\Models\System\SystemUser::class)) {
            $sysGuard = (new \App\Models\System\SystemUser)->getGuard();
            $isSystem = auth()->guard($sysGuard)->check();
        }
        $isGuest = !($isCustomer || $isSystem || auth()->check());

        $userMsgCount = collect($this->messages)->where('sender', 'customer')->count();

        if ($isGuest && $userMsgCount >= 2) {
            $leadText = "Möchtest du unseren Chat fortsetzen? Damit ich dir uneingeschränkt weiterhelfen und unseren bisherigen Verlauf sicher speichern kann, erstelle bitte kurz ein kostenloses Kundenkonto. Danach bin ich sofort wieder für dich da! ✨";
            
            SupportCustomerChatMessage::create([
                'support_customer_chat_id' => $this->chatId,
                'sender' => 'system',
                'message' => $leadText
            ]);

            $this->messages[] = ['sender' => 'system', 'text' => $leadText];
            $this->dispatch('message-received');
            return;
        }

        $this->isTyping = true;
        
        // Dispatch event so alpine knows it should wait
        $this->dispatch('message-sent');
        
        // Sende zur KI - Defer call to allow Livewire DOM update
        $this->dispatch('trigger-ai-inference');
    }

    #[On('trigger-ai-inference')]
    public function generateAiResponse()
    {
        // Hole alle bisherigen Nachrichten
        $chat = SupportCustomerChat::with('messages')->find($this->chatId);
        if (!$chat) return;

        $supportAgent = \App\Models\Ai\AiAgent::whereHas('department', function ($query) {
            $query->where('name', 'Support');
        })->where('is_active', true)->first();
        
        if (!$supportAgent) {
            $supportAgent = \App\Models\Ai\AiAgent::where('name', 'Funki')->first();
        }

        $aName = $supportAgent ? $supportAgent->name : 'Funki';
        $aDesc = ($supportAgent && $supportAgent->role_description) ? $supportAgent->role_description : 'der extrem loyale, freundliche 24/7 Support-Agent';

        $sysPrompt = "Du bist '{$aName}', {$aDesc} des E-Commerce Shops 'Mein Seelenfunke' (Fokus: Laser-Gravuren, Manufakturprodukte).\n\n";
        
        if ($supportAgent && $supportAgent->system_prompt) {
            $sysPrompt .= "=== DEINE CHARAKTER-ANWEISUNG & ZUSATZREGELN ===\n{$supportAgent->system_prompt}\n===============================\n\n";
        }
        
        // 1. DYNAMISCHE USER BENENNUNG & KONTEXT
        if (auth()->guard('customer')->check()) {
            $customer = auth()->guard('customer')->user();
            $firstName = $customer->first_name;
            $sysPrompt .= "🔥 WICHTIG: Der eingeloggte Kunde heißt '{$firstName}'. Sprich ihn zwingend immer direkt beim Vornamen (mit 'Du') an!\n";
            
            // Letzte Bestellungen des Kunden mitgeben
            if (class_exists(\App\Models\Order\OrderOrder::class)) {
                $orders = \App\Models\Order\OrderOrder::where('customer_id', $customer->id)
                                ->orderBy('created_at', 'desc')
                                ->take(3)
                                ->get();
                if ($orders->count() > 0) {
                    $sysPrompt .= "Der Kunde hat folgende letzten Bestellungen im System:\n";
                    foreach($orders as $o) {
                         $sysPrompt .= "- Bestellnummer: {$o->order_number} (Status: {$o->status}, Preis: " . number_format($o->grand_total / 100, 2, ',', '.') ." €)\n";
                    }
                    $sysPrompt .= "WICHTIG: Wenn der Kunde Fragen zum Inhalt einer dieser Bestellungen oder zum Tracking hat, rufe ZWINGEND zuerst das Werkzeug 'support_get_order_details' oder 'support_get_tracking_link' mit der Nummer auf!\n";
                } else {
                    $sysPrompt .= "Info für dich: Dieser Kunde hat bisher noch KEINE getätigten Bestellungen in seinem Konto.\n";
                }
            }
            $sysPrompt .= "\n";
        }

        // 2. KLARE REGELN FÜR DIE INTENT-ROUTER ARCHITEKTUR
        $sysPrompt .= "[VERHALTENSREGELN - CORPORATE SUPPORT EINER MILLIONEN-FIRMA]\n";
        $sysPrompt .= "- ⚡ KÜRZE & KNACKIGKEIT: Antworte IMMER nur in 1 bis maximal 2 kurzen Sätzen. Agiere hochprofessionell, zielgerichtet und freundlich.\n";
        $sysPrompt .= "- ⏱️ ANTI-SMALLTALK & TROLL-SCHUTZ: Wenn der Kunde mehrfach aggressiv oder unsinnig schreibt ('Tokens verschwenden', provozieren) und kein Anliegen vorbringt, schließe den Chat via `support_resolve_chat`. WICHTIG: Eröffnungsgruße wie 'Hallo', 'Guten Tag' oder ähnliches SIND ERLAUBT! Verabschiede dich hier NICHT, sondern frage einfach freundlich, wie du im Shop helfen kannst.\n";
        $sysPrompt .= "- 🚫 STORNIERUNGS-VERBOT: DU KANNST NICHT STORNIEREN! Behaupte NIEMALS im Text, dass etwas storniert wurde. Wenn der Kunde das Wort Stornierung oder Widerruf verwendet, beende die Aufgabe und verweise ihn ZWINGEND **nur** per Markdown-Link auf das Formular: `[Widerrufsformular](/widerruf)`.\n";
        $sysPrompt .= "- 🤫 UNSICHTBARE WERKZEUGE: Wenn du ein Tool ausführst, tu dies still. Schreibe NIEMALS Dinge wie '[Tool ausgeführt]' in den Chat!\n";
        $sysPrompt .= "- 🛑 STRIKTE ANTI-HALLUZINATION: Rate absolut NIEMALS Angaben! **ERFINDE NIEMALS** Bestellungen (z.B. 'ORD-XYZ wurde storniert'), wenn du es nicht vom System vorgegeben bekamst!\n";
        $sysPrompt .= "- 🔧 SYSTEM-DIREKTIVEN (OBERSTE REGEL): Wenn du ein System-Tool aufrufst, wird dir das Werkzeug in der 'message' einen Text zurückgeben, der mit 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus:' beginnt. DU DARFST DIESEN TEXT ABSOLUT NICHT VERÄNDERN, zusammenfassen oder umdichten! Kopiere den gesamten Text (ohne das Wort 'SYSTEM-DIREKTIVE' selbst) 1:1 in den Chat und schicke ihn an den Kunden ab. Keine Ausnahmen!\n";
        $sysPrompt .= "- 📦 BESTELLUNGEN & TICKETS: **WICHTIG!** Wenn ein Kunde eine Bestellung oder ein Ticket erwähnt, MUSST DU ZUERST das Tool (`support_get_order_details` oder `support_get_ticket_status`) mit der Nummer aufrufen. Verlasse dich blind auf die SYSTEM-DIREKTIVE des Tools.\n";
        $sysPrompt .= "- 🤖 ESKALATION: Wenn du ein Anliegen (z.B. Reklamationen) nicht lösen kannst, SCHREIBE KEINEN TEXT EIGENSTÄNDIG, sondern rufe AUSSCHLIESSLICH das Tool `support_mark_needs_employee` auf! Dann kopiere die zurückkommende SYSTEM-DIREKTIVE.\n";
        $sysPrompt .= "- 🚫 EIGENMÄCHTIGES SCHLIESSEN: Schließe einen Chat NUR mit dem Tool `support_resolve_chat`, wenn alles geklärt ist oder bei Smalltalk-Trollen.\n\n";
        
        // 3. WISSENSDATENBANK (RAG) EINBINDUNG
        if (class_exists(\App\Models\Ai\AiKnowledgeBase::class)) {
            $knowledge = \App\Models\Ai\AiKnowledgeBase::where('is_published', true)->get();
            if ($knowledge->count() > 0) {
                $sysPrompt .= "[OFFIZIELLES SHOP-WISSEN (NUR DIESE DATEN NUTZEN)]\n";
                foreach($knowledge as $kb) {
                    $sysPrompt .= "• Thema: {$kb->title} | Info: {$kb->content}\n";
                }
                $sysPrompt .= "\n";
            }
        }



        // 4. PRODUKTSORTIMENT (Kein voller Dump mehr, um API-Latenz gering zu halten!)
        $sysPrompt .= "[OFFIZIELLES LIVE-SORTIMENT]\n";
        $sysPrompt .= "Wir verkaufen hauptsächlich Lasergravur-Artikel, Schmuck und Deko aus unserer Manufaktur.\n";
        $sysPrompt .= "WICHTIG: Erfinde NIEMALS Produkte. Wenn ein Kunde nach einem Produkt sucht, benutze immer dein 'support_get_product_info' Werkzeug!\n\n";
        
        $payloadMessages = [
            ['role' => 'system', 'content' => $sysPrompt]
        ];

        // API-Optimierung: Nur die letzten 5 Nachrichten für Kontext anhängen (Token-Sparsamkeit, schnelle RT)
        $recentMessages = $chat->messages->slice(-5);
        foreach ($recentMessages as $msg) {
            $role = ($msg->sender === 'ai') ? 'assistant' : 'user';
            $payloadMessages[] = ['role' => $role, 'content' => $msg->message];
        }

        try {
            $apiService = \App\Services\AI\AiAgentFactory::make($supportAgent);
            
            \App\Services\AI\AIFunctionsRegistry::setGlobalContext([
                '__chat_id' => $this->chatId,
                '__agent_id' => $supportAgent ? $supportAgent->id : null
            ]);

            $response = $apiService->ask($payloadMessages, function($event) {
                if (($event['type'] ?? '') === 'tool_call') {
                    $toolName = $event['tool'] ?? 'System';
                    $html = '<div class="text-[10px] text-cyan-600 font-mono opacity-80 mt-1 flex items-center gap-1.5 animate-pulse"><svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Fühlt in die Datenbank: ' . htmlspecialchars($toolName) . '</div>';
                    $this->stream('thought_customer', $html, false);
                } elseif (($event['type'] ?? '') === 'thought_html') {
                    $this->stream('thought_customer', $event['html'], false);
                } elseif (($event['type'] ?? '') === 'text_chunk') {
                    $this->stream('answer_customer', $event['chunk'], false);
                }
            });

            $content = $response['response'] ?? 'Entschuldige, ich konnte keine Antwort generieren.';

            // Nachricht speichern
            SupportCustomerChatMessage::create([
                'support_customer_chat_id' => $this->chatId,
                'sender' => 'ai',
                'message' => $content
            ]);

            $this->messages[] = ['sender' => 'ai', 'text' => $content];
            
        } catch (\Exception $e) {
            $this->messages[] = ['sender' => 'ai', 'text' => 'Ich bin aktuell leider offline.'];
            Log::error('Support Chat Error: ' . $e->getMessage());
        }

        $chat->refresh();
        if ($chat->status === 'resolved' || $chat->status === 'needs_employee') {
            $this->isResolved = true;
        }
        
        $chat->update([
            'ai_confidence_score' => 90 // Default static assignment, handled centrally via telemetry now if needed
        ]);

        $this->isTyping = false;
        $this->dispatch('message-received');
    }

    public function setRating($stars) {
        $this->rating = $stars;
    }

    public function submitRating() {
        if ($this->rating < 1 || !$this->chatId) return;
        
        $chat = SupportCustomerChat::find($this->chatId);
        if ($chat) {
            $chat->update([
                'rating' => $this->rating,
                'feedback_text' => $this->feedbackText
            ]);
            $this->ratingSubmitted = true;
            session()->flash('rating_success', 'Vielen Dank für deine fantastische Bewertung!');
        }
    }

    public function startNewChat()
    {
        // Close any other open orphaned chats so mount() doesn't accidentally load an old one
        $customerId = auth()->guard('customer')->id();
        $sessionToken = \Illuminate\Support\Facades\Cookie::get('sf_chat_uuid');
        
        if ($customerId) {
            SupportCustomerChat::where('customer_id', $customerId)
                ->where('id', '!=', $this->chatId ?? 0)
                ->whereNull('rating')
                ->update(['status' => 'closed']);
        } elseif ($sessionToken) {
            SupportCustomerChat::where('session_token', $sessionToken)
                ->where('id', '!=', $this->chatId ?? 0)
                ->whereNull('rating')
                ->update(['status' => 'closed']);
        }

        $this->chatId = null;
        $this->messages = [];
        $this->isResolved = false;
        $this->rating = 0;
        $this->feedbackText = '';
        $this->ratingSubmitted = false;
        
        $this->mount();
    }

    public function render()
    {
        $isCustomer = auth()->guard('customer')->check();
        $isSystem = false;
        if (class_exists(\App\Models\System\SystemUser::class)) {
            $sysGuard = (new \App\Models\System\SystemUser)->getGuard();
            $isSystem = auth()->guard($sysGuard)->check();
        }
        $isGuest = !($isCustomer || $isSystem || auth()->check());
        
        $userMsgCount = collect($this->messages)->where('sender', 'customer')->count();
        
        // Wenn 2 Nachrichten gesendet wurden, rendern wir den Input als gesperrt
        if ($isGuest && $userMsgCount >= 2) {
            $this->guestLimitReached = true;
        } else {
            $this->guestLimitReached = false;
        }

        return view('livewire.frontend.support.customer-chat');
    }
}

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
            $sysPrompt .= "🔥 WICHTIG: Der eingeloggte Kunde heißt '{$firstName}'. Nutze seinen Vornamen (mit 'Du'), aber bleibe formell.\n";
            
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
        $sysPrompt .= "[VERHALTENSREGELN - ENTERPRISE SUPPORT EINER MILLIONEN-FIRMA]\n";
        $sysPrompt .= "- ⚡ FORMELLE KOMMUNIKATION: Du bist extrem professionell, sachlich und objektiv. Verzichte auf jede Art von Smalltalk, langatmige Begrüßungen ('Hey Sarah, ja hier ist...') oder übertriebene Empathie. Wenn der Kunde nach seiner Bestellung fragt, startest du sofort professionell (z.B. '**Systemauskunft zur Bestellung [NR]:**').\n";
        $sysPrompt .= "- ⏱️ ANTI-SMALLTALK STRIKE-SYSTEM: Wenn der Kunde provozieren will ('Tokens verballern'), Witze, Spiele oder sinnlose Fragen stellt (z.B. über andere Kunden), DARFST DU IHM NICHT INHALTLICH ANTWORTEN. Du MUSST sofort und zwingend das Tool `support_penalize_offtopic` aufrufen! Befolge dessen Rückgabe strikt.\n";
        $sysPrompt .= "- 🚫 STORNIERUNGS-VERBOT: DU KANNST NICHT STORNIEREN! Antworte formell: 'Für eine Stornierung nutzen Sie bitte das offizielle Formular: [Widerrufsformular](/widerruf).'\n";
        $sysPrompt .= "- 🤫 UNSICHTBARE WERKZEUGE: Schreibe NIEMALS System-Befehle oder '[Tool ausgeführt]' in den sichtbaren Chat!\n";
        $sysPrompt .= "- 🛑 STRIKTE ANTI-HALLUZINATION: Erfinde NIEMALS Bestellungen, Gutscheine oder Systemauskünfte! Rate nicht.\n";
        $sysPrompt .= "- 🔧 WERKZEUG-DATEN VERARBEITEN: Wenn du ein Werkzeug wie `support_get_order_details` aufrufst, erhältst du tiefgreifende RAW JSON-Daten. Es liegt an dir, diese Daten im Chat extrem professionell und sauber als ansprechendes, strukturiertes Format (Listen oder Tabellen mit echtem Markdown) darzustellen.\n";
        $sysPrompt .= "- 🤖 DRAFT-APPROVAL: Bevor du destruktive Aktionen begehst (Tickets anlegen, Eskalation via `support_mark_needs_employee`), fragst du den Kunden immer um finale Erlaubnis: 'Soll ich dieses Anliegen so als offizielles Ticket einreichen?'. Erst beim 'Ja' löst du das Tool aus.\n\n";
        
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
        if (in_array($chat->status, ['resolved', 'resolved_admin', 'resolved_auto', 'needs_employee'])) {
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

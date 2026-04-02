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

class CustomerChat extends Component
{
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
                    ->whereIn('status', ['open', 'needs_employee', 'resolved'])
                    ->with(['messages'])
                    ->first();

            if ($chat) {
                // Auto-Claim: Falls das ein Gast war und nun ein Kunde eingeloggt ist
                if ($chat->customer_id === null && $customerId) {
                    $chat->update(['customer_id' => $customerId]);
                }
                
                $this->chatId = $chat->id;
                $this->isResolved = $chat->status === 'resolved';
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
                ->whereIn('status', ['open', 'needs_employee', 'resolved'])
                ->where('customer_id', $customerId)
                ->with(['messages'])
                ->latest()
                ->first();

            if ($existing) {
                session(['current_chat_id' => $existing->id]);
                $this->chatId = $existing->id;
                $this->isResolved = $existing->status === 'resolved';
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
        $sysPrompt .= "- 🤫 UNSICHTBARE WERKZEUGE: Wenn du ein Tool ausführst, tu dies still. Schreibe NIEMALS Dinge wie '[Tool ausgeführt]' in den Chat!\n";
        $sysPrompt .= "- 🛑 STRIKTE ANTI-HALLUZINATION: Rate absolut NIEMALS Angaben! **ERFINDE NIEMALS** Bestellungen, Preise, Ticket-IDs oder Paket-Inhalte!\n";
        $sysPrompt .= "- 🔧 SYSTEM-DIREKTIVEN (OBERSTE REGEL): Wenn du ein System-Tool aufrufst, wird dir das Werkzeug in der 'message' einen Text zurückgeben, der mit 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus:' beginnt. DU DARFST DIESEN TEXT ABSOLUT NICHT VERÄNDERN, zusammenfassen oder umdichten! Kopiere den gesamten Text (ohne das Wort 'SYSTEM-DIREKTIVE' selbst) 1:1 in den Chat und schicke ihn an den Kunden ab. Keine Ausnahmen!\n";
        $sysPrompt .= "- 📦 BESTELLUNGEN & TICKETS: **WICHTIG!** Wenn ein Kunde eine Bestellung oder ein Ticket erwähnt, MUSST DU ZUERST das Tool (`support_get_order_details` oder `support_get_ticket_status`) mit der Nummer aufrufen. Verlasse dich blind auf die SYSTEM-DIREKTIVE des Tools.\n";
        $sysPrompt .= "- 🤖 ESKALATION: Wenn du ein Anliegen (z.B. Reklamationen) nicht lösen kannst, SCHREIBE KEINEN TEXT EIGENSTÄNDIG, sondern rufe AUSSCHLIESSLICH das Tool `support_mark_needs_employee` auf! Dann kopiere die zurückkommende SYSTEM-DIREKTIVE.\n";
        $sysPrompt .= "- 🚫 EIGENMÄCHTIGES SCHLIESSEN: Schließe einen Chat NUR mit dem Tool `support_resolve_chat`, wenn alles geklärt ist.\n\n";
        
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



        // 4. DYNAMISCHES PRODUKTSORTIMENT (LIVE AUS DER DATENBANK)
        if (class_exists(\App\Models\Product\Product::class)) {
            $activeProducts = \App\Models\Product\Product::where('status', 'active')->pluck('name')->toArray();
            if (!empty($activeProducts)) {
                $sysPrompt .= "[OFFIZIELLES LIVE-SORTIMENT]\n";
                $sysPrompt .= "Wir verkaufen aktuell exakt und AUSSCHLIESSLICH diese Stamm-Produkte:\n";
                $sysPrompt .= "- " . implode("\n- ", $activeProducts) . "\n";
                $sysPrompt .= "Erfinde NIEMALS andere Produkte (wie Wallets, Gitarren, Schmuck etc.)!\n\n";
            }
        }
        
        $payloadMessages = [
            ['role' => 'system', 'content' => $sysPrompt]
        ];

        foreach ($chat->messages as $msg) {
            $role = ($msg->sender === 'ai') ? 'assistant' : 'user';
            $payloadMessages[] = ['role' => $role, 'content' => $msg->message];
        }

        // Funki's / Agent's Werkzeuge einbinden (Support Schema)
        $aiToolsConfig = AIFunctionsRegistry::getAiSupportFuncsSchema();

        $payload = [
            'model' => $supportAgent->model ?? 'meta-llama/Meta-Llama-3-70B-Instruct',
            'temperature' => 0.5,
            'messages' => $payloadMessages,
        ];

        if (!empty($aiToolsConfig)) {
            $payload['tools'] = array_map(function($schema) {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $schema['name'],
                        'description' => $schema['description'],
                        'parameters' => $schema['parameters'],
                    ]
                ];
            }, $aiToolsConfig);
            $payload['tool_choice'] = 'auto';
        }

        try {
            $toolConfidence = 90;
            $startTime = microtime(true);
            $response = Http::withToken(config('services.mittwald.key'))
                ->connectTimeout(30)
                ->timeout(120)
                ->asJson()
                ->post(rtrim(config('services.mittwald.url'), '/') . '/chat/completions', $payload);
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $messageData = $data['choices'][0]['message'] ?? [];

                if (isset($data['usage']) && $supportAgent) {
                    \Illuminate\Support\Facades\DB::table('ai_metrics')->insert([
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'ai_agent_id' => $supportAgent->id,
                        'type' => 'inference',
                        'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                        'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                        'total_time_ms' => $responseTimeMs,
                        'is_success' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Funktion aufrufen?
                if (isset($messageData['tool_calls'])) {
                    $toolCalls = $messageData['tool_calls'];
                    $toolResults = [];
                    // Führe alle Tools aus (wir hängen 'chat_id' an die Args, falls Funktionen das brauchen)
                    foreach ($toolCalls as $toolCall) {
                        $funcName = $toolCall['function']['name'];
                        $argsString = $toolCall['function']['arguments'];
                        $args = json_decode($argsString, true) ?? [];
                        $args['__chat_id'] = $this->chatId; // Versteckte Injection für Status Updates

                        if ($funcName === 'support_resolve_chat') $toolConfidence = 100;
                        if ($funcName === 'support_mark_needs_employee') $toolConfidence = 40;

                        try {
                            if ($supportAgent) {
                                \App\Models\Ai\AiToolUsage::create([
                                    'ai_agent_id' => $supportAgent->id,
                                    'tool_name' => $funcName,
                                    'used_at' => now(),
                                    'context' => $args,
                                    'is_error' => false,
                                    'error_message' => null,
                                ]);
                            }
                        } catch (\Exception $ex) {
                            Log::error("Telemetry Insert Failed: " . $ex->getMessage());
                        }

                        try {
                            $res = AIFunctionsRegistry::execute($funcName, $args);
                            if (is_array($res) && isset($res['message'])) {
                                $toolResults[] = $res['message'];
                            } elseif (is_string($res)) {
                                $toolResults[] = $res;
                            }
                        } catch (\Exception $e) {
                            Log::error("Funki Tool Call failed: " . $e->getMessage());
                            $toolResults[] = "Interner Fehler beim Ausführen des Werkzeugs: " . $e->getMessage();
                        }
                    }

                    unset($payload['tools']); // Keine Endlos-Loops
                    unset($payload['tool_choice']); // OpenAI 400 Prevent: tool_choice is invalid without tools
                    
                    $sysText = "System-Meldung: Du hast soeben ein oder mehrere Werkzeuge ausgeführt. ";
                    if (!empty($toolResults)) {
                        $sysText .= "Hier ist die strikte und echte System-Antwort der durchgeführten Tools (Datenbank):\n\n" . implode("\n\n", $toolResults) . "\n\n";
                        $sysText .= "Regel: Benutze EXAKT die Ticketnummern (z.B. TCK-XXX) oder Status-Meldungen aus diesem Tool-Ergebnis. Erfinde auf keinen Fall eigene Nummern oder Namen von Mitarbeitern, wenn sie oben nicht stehen!";
                    } else {
                        $sysText .= "Antworte dem Kunden nun darauf aufbauend kurz, menschlich und abschließend. Falls ein Mitarbeiter verständigt wurde, verabschiede dich höflich.";
                    }

                    $payload['messages'][] = [
                        'role' => 'user',
                        'content' => $sysText
                    ];
                    
                    $startT2 = microtime(true);
                    $secondCall = Http::withToken(config('services.mittwald.key'))
                        ->connectTimeout(30)
                        ->timeout(120)
                        ->asJson()
                        ->post(rtrim(config('services.mittwald.url'), '/') . '/chat/completions', $payload);
                    $responseTimeMs += (int) ((microtime(true) - $startT2) * 1000);
                        
                    if ($secondCall->successful()) {
                        $data2 = $secondCall->json();
                        $content = $data2['choices'][0]['message']['content'] ?? 'Ich habe mich darum gekümmert!';
                        
                        if (isset($data2['usage']) && $supportAgent) {
                            \Illuminate\Support\Facades\DB::table('ai_metrics')->insert([
                                'id' => \Illuminate\Support\Str::uuid()->toString(),
                                'ai_agent_id' => $supportAgent->id,
                                'type' => 'inference',
                                'input_tokens' => $data2['usage']['prompt_tokens'] ?? 0,
                                'output_tokens' => $data2['usage']['completion_tokens'] ?? 0,
                                'total_time_ms' => (int) ((microtime(true) - $startT2) * 1000),
                                'is_success' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    } else {
                        Log::error("Funki 2nd Pass API Error: " . $secondCall->body());
                        $content = "Entschuldige, beim Ausführen meines Werkzeugs trat ein kurzer Hänger auf.";
                    }
                } else {
                    $content = $messageData['content'] ?? 'Ich habe dich verstanden!';
                }

                // Nachricht speichern
                SupportCustomerChatMessage::create([
                    'support_customer_chat_id' => $this->chatId,
                    'sender' => 'ai',
                    'message' => $content
                ]);

                $this->messages[] = ['sender' => 'ai', 'text' => $content];
            } else {
                Log::error('Mittwald API Rejection: ' . $response->body());
                $this->messages[] = ['sender' => 'ai', 'text' => 'Ups, meine Server-Brain-Verbindung wackelt gerade etwas. Bitte versuche es in ein paar Minuten nochmal!'];
            }
        } catch (\Exception $e) {
            $this->messages[] = ['sender' => 'ai', 'text' => 'Ich bin aktuell leider offline.'];
            Log::error('Support Chat Error: ' . $e->getMessage());
        }

        $chat->refresh();
        if ($chat->status === 'resolved' || $chat->status === 'needs_employee') {
            $this->isResolved = true;
        }
        
        $chat->update([
            'avg_response_time_ms' => $responseTimeMs ?? null,
            'ai_confidence_score' => $toolConfidence ?? 90,
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

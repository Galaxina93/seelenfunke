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

class AiFrontendSupportChat extends Component
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
    public $hasAcceptedPrivacy = false;
    public $privacyChecked = false;
    
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

        $this->hasAcceptedPrivacy = session()->get('ai_chat_privacy_accepted', false);

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

        // Fix: Ensure chat is not resolved if the last message was the registration prompt
        if (!empty($this->messages)) {
            $lastMessage = end($this->messages);
            if ($lastMessage['sender'] === 'system' && str_contains($lastMessage['text'], 'Möchtest du unseren Chat fortsetzen')) {
                $this->isResolved = false;
                if ($this->chatId) {
                    SupportCustomerChat::where('id', $this->chatId)->where('status', '!=', 'open')->update(['status' => 'open']);
                }
            }
        }
    }

    public function acceptPrivacy()
    {
        $this->hasAcceptedPrivacy = true;
        session()->put('ai_chat_privacy_accepted', true);
    }

    public function sendMessage()
    {
        if (!$this->hasAcceptedPrivacy) return;

        $text = trim($this->message);
        if (empty($text)) return;

        $currentCustomerId = auth()->guard('customer')->id();
        
        if ($this->chatId) {
            $chat = SupportCustomerChat::find($this->chatId);
            if ($chat && empty($chat->customer_id) && $currentCustomerId) {
                $chat->update(['customer_id' => $currentCustomerId]);
            } elseif ($chat && !empty($chat->customer_id) && $currentCustomerId && (string)$chat->customer_id !== (string)$currentCustomerId) {
                // Nur zur Sicherheit nochmal das Auto-Claiming probieren, falls es ein Migrations-Problem gab
                // Normalerweise greift hier Livewire-Session-Schutz, also leeren wir es nicht aggressiv.
                \Illuminate\Support\Facades\Log::warning("SupportChat ID mismatch: Chat belongs to {$chat->customer_id}, but user is {$currentCustomerId}");
            }
        }

        $this->message = '';

        if (!$this->chatId) {
            $chat = SupportCustomerChat::create([
                'customer_id' => auth()->guard('customer')->id(),
                'session_token' => request()->cookie('sf_chat_uuid'),
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

        if ($isGuest && $userMsgCount >= 3) {
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

        if (!$supportAgent) {
            $supportAgent = new \App\Models\Ai\AiAgent([
                'name' => 'Funki',
                'system_prompt' => 'Du bist Funki, ein hilfreicher Support-Assistent.'
            ]);
        }

        $sysPrompt = \App\Services\AI\AiPromptService::getRichPrompt($supportAgent);

        // --- AUSDAUER & CHAT-LIMIT MECHANIK (Weg B) ---
        $customerMsgs = $chat->messages->where('sender', 'customer');
        $customerMsgCount = $customerMsgs->count();
        $severitySum = $customerMsgs->sum('severity');
        $chatWeight = ($customerMsgCount * 10) + $severitySum;
        
        if ($chatWeight >= 100) {
            $customerId = auth()->guard('customer')->id() ?? $chat->customer_id;

            if ($customerId) {
                $abortMsg = "Ich merke, dass wir hier gerade an unsere technischen Grenzen stoßen. Um dir bestmöglich und schnellstmöglich zu helfen, habe ich unser Gespräch nun als offizielles Ticket markiert und an meine menschlichen Kollegen weitergeleitet. Sie werden sich den Verlauf in Kürze ansehen und sich bei dir melden!";
            } else {
                $abortMsg = "Ich merke, dass wir hier gerade an unsere technischen Grenzen stoßen. Da du als Gast unterwegs bist, kann ich leider kein automatisches Ticket für dich eröffnen. Bitte melde dich an oder kontaktiere den Support über das offizielle Kontaktformular.";
            }
            
            SupportCustomerChatMessage::create([
                'support_customer_chat_id' => $this->chatId,
                'sender' => 'system',
                'message' => $abortMsg
            ]);
            $this->messages[] = ['sender' => 'system', 'text' => $abortMsg];
            
            $chat->update([
                'status' => 'needs_employee',
                'top_topic' => 'Automatischer Abbruch (Chat-Limit)',
                'ai_summary' => 'Das System hat diesen Chat automatisch eskaliert, da das Ausdauer-Limit (100 Punkte) durch eine zu hohe Anzahl von Nachrichten oder negativen Severity-Werten erreicht wurde. Eine manuelle Überprüfung durch das Team ist notwendig.'
            ]);

            if ($customerId) {
                $ticket = \App\Models\Support\SupportTicket::create([
                    'ticket_number' => 'TCK-' . strtoupper(\Illuminate\Support\Str::random(8)),
                    'customer_id'   => $customerId,
                    'subject'       => 'Automatischer Abbruch (Chat-Limit)',
                    'category'      => 'allgemein',
                    'status'        => 'open',
                    'priority'      => 'normal',
                ]);

                $historyStr = "";
                foreach($chat->messages as $m) {
                    $sender = $m->sender === 'customer' ? 'Kunde' : 'KI';
                    if ($m->sender === 'system') $sender = 'System';
                    $historyStr .= "[{$sender}] {$m->message}\n\n";
                }

                \App\Models\Support\SupportTicketMessage::create([
                    'support_ticket_id' => $ticket->id,
                    'sender_type'       => 'system',
                    'message'           => "Das System hat diesen Chat automatisch eskaliert, da das Ausdauer-Limit (100 Punkte) erreicht wurde.\n\n--- CHAT VERLAUF ---\n" . $historyStr
                ]);

                $chat->update(['support_ticket_id' => $ticket->id]);
            }

            $this->isResolved = true;
            $this->isTyping = false;
            $this->dispatch('message-received');
            return; // Keine API-Kosten mehr verursachen
        }
        
        if ($chatWeight >= 40) {
            // Weicher Druck aufbauen
            $sysPrompt .= "[SYSTEM WARNUNG - AUSDAUER-LIMIT FAST ERREICHT]\n";
            $sysPrompt .= "Der Kunde hat bereits extrem viele Fragen gestellt (Auslastung: {$chatWeight}/100 Punkten). Dein oberstes Ziel ist es nun, dieses Gespräch extrem höflich, charmant und zielführend zum Abschluss zu bringen. Stelle KEINE Gegenfragen mehr, die den Chat unnötig verlängern könnten. Fasse dich kurz und weise charmant darauf hin, dass du das Anliegen bei Bedarf gerne als Ticket für einen menschlichen Mitarbeiter öffnest.\n\n";
        }

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
            
            // Dispatch frontend events generated by tools (like system_visualize_data)
            if (isset($response['events']) && is_array($response['events'])) {
                foreach ($response['events'] as $event) {
                    if (isset($event['name'])) {
                        $this->dispatch($event['name'], $event['detail'] ?? []);
                    }
                }
            }
            
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
        
        // Wenn 3 Nachrichten gesendet wurden, rendern wir den Input als gesperrt
        if ($isGuest && $userMsgCount >= 3) {
            $this->guestLimitReached = true;
        } else {
            $this->guestLimitReached = false;
        }

        return view('livewire.frontend.support.ai-frontend-support-chat');
    }
}

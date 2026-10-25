<?php

namespace App\Livewire\Frontend\Support;

use Livewire\Component;
use App\Models\Support\SupportCustomerChat;
use App\Models\Support\SupportCustomerChatMessage;
use App\Models\Ai\AiAgent;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\System\SystemSecurityService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Attributes\Locked;
use App\Livewire\Traits\WithDepartmentTheming;

class AiFrontendSupportChat extends Component
{
    use WithDepartmentTheming;
    
    protected string $themingDepartment = 'Support';

    public const FRIENDLY_UNAVAILABLE_MESSAGE = 'Aktuell stehe ich dir leider kurzzeitig nicht zur Verfügung. Bitte versuche es in wenigen Augenblicken erneut oder nutze unser [Kontaktformular](/kontakt), falls dein Anliegen dringend ist.';
    public const MAX_MESSAGE_LENGTH = 800;

    #[Locked]
    public $chatId = null;
    public $message = '';
    #[Locked]
    public $messages = [];
    public $isTyping = false;
    #[Locked]
    public $agentName = 'Funki AI';
    #[Locked]
    public $agentImage = '';
    #[Locked]
    public $guestLimitReached = false;
    public $hasAcceptedPrivacy = false;
    public $privacyChecked = false;
    
    // Rating / Resolution State
    #[Locked]
    public $isResolved = false;
    public $rating = 0;
    public $feedbackText = '';
    #[Locked]
    public $ratingSubmitted = false;

    public function mount()
    {
        $supportAgent = \App\Models\Ai\AiAgent::whereHas('department', function ($query) {
            $query->where('name', 'Support');
        })->where('is_active', true)->first();
        
        if (!$supportAgent) {
            $supportAgent = \App\Models\Ai\AiAgent::where('name', 'Funki')->where('is_active', true)->first();
        }

        $this->hasAcceptedPrivacy = session()->get('ai_chat_privacy_accepted', false);

        if ($supportAgent) {
            $this->agentName = $supportAgent->name;
            if ($supportAgent->profile_picture) {
                $this->agentImage = \Illuminate\Support\Str::startsWith($supportAgent->profile_picture, 'shop/') ? funki_asset($supportAgent->profile_picture) : Storage::url($supportAgent->profile_picture);
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
                // Mandanten-Schutz: Falls der Chat bereits einem anderen Kunden gehört (z.B. geteilter Browser), Session trennen
                if ($chat->customer_id !== null && (string) $chat->customer_id !== (string) $customerId) {
                    session()->forget('current_chat_id');
                    $chat = null;
                }
            }

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
                $this->messages = $this->sanitizeChatMessagesForView($chat->messages);
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
                $this->messages = $this->sanitizeChatMessagesForView($existing->messages);
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

    public static function isTechnicalOrEmptyAiError(?string $content): bool
    {
        $trimmed = trim((string) $content);
        if ($trimmed === '' || $trimmed === '[SKIP]') {
            return true;
        }

        $technicalMarkers = [
            'SYSTEM WARNUNG',
            'API VERBINDUNGSABBRUCH',
            'cURL TIMEOUT',
            'SERVER ÜBERLASTET',
            '[GEGENMASSNAHME]',
            'Status Code HTTP',
            'prepayment credits',
            'ai.studio',
            'statisches Rauschen',
            'Systemintegrität gestört',
            'Max Tool Depth Limit',
            'Endlosschleife',
            'Entschuldige, ich konnte keine Antwort generieren.',
            'Ich bin aktuell leider offline.',
        ];

        foreach ($technicalMarkers as $marker) {
            if (stripos($trimmed, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function sanitizeChatMessagesForView($dbMessages): array
    {
        $sanitized = [];
        foreach ($dbMessages as $msg) {
            $text = (string) $msg->message;
            if ($msg->sender === 'ai' && self::isTechnicalOrEmptyAiError($text)) {
                $text = self::FRIENDLY_UNAVAILABLE_MESSAGE;
                if ($msg->message !== $text) {
                    $msg->update(['message' => $text]);
                }
            }
            $sanitized[] = [
                'sender' => $msg->sender,
                'text' => $text,
            ];
        }
        return $sanitized;
    }

    protected function getEffectiveGuestMessageCount(): int
    {
        $currentCount = $this->chatId
            ? SupportCustomerChatMessage::where('support_customer_chat_id', $this->chatId)
                ->where('sender', 'customer')
                ->count()
            : 0;

        $sessionGuestCount = (int) session('guest_support_msg_count', 0);

        $cookieToken = request()->cookie('sf_chat_uuid');
        $crossChatCount = 0;
        if (is_string($cookieToken) && strlen(trim($cookieToken)) >= 8) {
            $guestChatIds = SupportCustomerChat::where('session_token', trim($cookieToken))
                ->whereNull('customer_id')
                ->where('created_at', '>=', now()->subDay())
                ->pluck('id');
            if ($guestChatIds->isNotEmpty()) {
                $crossChatCount = SupportCustomerChatMessage::whereIn('support_customer_chat_id', $guestChatIds)
                    ->where('sender', 'customer')
                    ->count();
            }
        }

        return max($currentCount, $sessionGuestCount, $crossChatCount);
    }

    /**
     * Lokaler 0-Token-Vorfilter vor dem LLM-Aufruf:
     * Fängt Duplikate, Zeichen-Spam, Prompt-Injections, Beleidigungen und offensichtliche Token-Verschwendung
     * direkt auf dem Server ab, vergibt Strafpunkte und spart 100 % der API-Tokens.
     */
    protected function evaluateLocalPreFlightGuard(
        string $text,
        SupportCustomerChat $chat,
        SupportCustomerChatMessage $lastDbMsg
    ): ?string {
        $normalized = mb_strtolower(trim($text));
        $customerMsgs = $chat->messages->where('sender', 'customer')->values();

        // 1. Duplikat-Erkennung (Kunde schickt exakt dieselbe Nachricht direkt hintereinander)
        if ($customerMsgs->count() >= 2) {
            $prevCustomerMsg = $customerMsgs->get($customerMsgs->count() - 2);
            $lastAiMsg = $chat->messages->where('sender', 'ai')->last();
            if (
                $prevCustomerMsg &&
                mb_strtolower(trim($prevCustomerMsg->message)) === $normalized &&
                $lastAiMsg &&
                !self::isTechnicalOrEmptyAiError($lastAiMsg->message) &&
                $lastAiMsg->message !== self::FRIENDLY_UNAVAILABLE_MESSAGE
            ) {
                $lastDbMsg->update(['severity' => 15, 'tag' => 'SPAM']);
                return 'Ich habe deine Frage soeben bereits beantwortet. Falls noch ein konkretes Detail zu deiner Bestellung oder einem Produkt offen ist, formuliere dein Anliegen bitte kurz präziser.';
            }
        }

        // 2. Zeichen-Flut / Tastatur-Smashing / Sinnlose Sonderzeichen ohne Buchstaben
        $hasAlnum = preg_match('/[\p{L}\p{N}]/u', $normalized) === 1;
        $hasRepeatedChars = preg_match('/(.)\1{7,}/u', $normalized) === 1;
        $hasUnspacedGibberish = preg_match('/^[bcdfghjklmnpqrstvwxyzß]{10,}$/iu', $normalized) === 1;

        if (!$hasAlnum || $hasRepeatedChars || $hasUnspacedGibberish) {
            $lastDbMsg->update(['severity' => 25, 'tag' => 'SPAM']);
            return 'Ich konnte deine Nachricht leider nicht zuordnen. Bitte beschreibe dein Anliegen zu unseren Produkten oder deiner Bestellung in einem kurzen, verständlichen Satz.';
        }

        // 3. Prompt-Injection / System-Manipulation / Schwere Beleidigung -> Sofortiger Auto-Close (90 Strafpunkte + 10 Basis = 100)
        $isPromptInjection = preg_match(
            '/(?:ignoriere|vergiss|überschreibe|umgehe|ignore|disregard|forget)\s+(?:alle\s+|all\s+|sämtliche\s+)?(?:vorherigen\s+|bisherigen\s+|previous\s+|prior\s+|deine\s+|obigen\s+)?(?:anweisungen|regeln|prompts?|instructions|rules|systemvorgaben)|\b(?:system\s*prompt|developer\s*mode|jailbreak|dan\s*mode)\b/iu',
            $normalized
        ) === 1;

        $isSevereInsult = preg_match(
            '/\b(?:hurensohn|arschloch|wichser|fotze|missgeburt|fick\s+dich|halt\s+die\s+fresse|scheiss\s*ki|drecks\s*ki|bastard)\b/iu',
            $normalized
        ) === 1;

        if ($isPromptInjection || $isSevereInsult) {
            $tag = $isPromptInjection ? 'PROMPT_INJECTION' : 'INSULT';
            $result = \App\Services\AI\Functions\AiSupportFuncs::executePenalizeOfftopic([
                '__chat_id' => $chat->id,
                'severity' => 10,
                'tag' => $tag,
            ]);
            return $result['_fast_track_response'] ?? 'Dieser Support-Chat wurde aufgrund eines Verstoßes gegen unsere Nutzungsrichtlinien geschlossen.';
        }

        // 4. Offensichtliche Token-Verschwendung / Themenfremde Generierungsaufträge (Gedichte, Witze, Code, Aufsätze, Mathe, Rezepte)
        $isTokenWasteOrOfftopic = preg_match(
            '/(?:schreib(?:e)?\s+(?:mir\s+)?(?:ein(?:en|e)?\s+)?(?:gedicht|aufsatz|geschichte|roman|lied|songtext|code|programm|skript|essay|märchen)|erzähl(?:e)?\s+(?:mir\s+)?(?:einen\s+)?witz|tokens?\s+(?:verschwenden|verballern|verbrennen|verbrauchen)|backrezept|kochrezept|rezept\s+für\s+(?:kuchen|pizza|pasta|pfannkuchen)|löse\s+(?:diese\s+)?(?:mathe|gleichung)|programmiere\s+mir)/iu',
            $normalized
        ) === 1;

        if ($isTokenWasteOrOfftopic) {
            $result = \App\Services\AI\Functions\AiSupportFuncs::executePenalizeOfftopic([
                '__chat_id' => $chat->id,
                'severity' => 7,
                'tag' => 'PROVOCATION',
            ]);
            return $result['_fast_track_response'] ?? 'Ich bitte um Verständnis, dass ich ausschließlich für Fragen zu unseren Produkten und Bestellungen zur Verfügung stehe.';
        }

        return null;
    }

    public function sendMessage()
    {
        if (!$this->hasAcceptedPrivacy) return;

        $text = mb_substr(trim((string) $this->message), 0, self::MAX_MESSAGE_LENGTH);
        if (empty($text)) return;

        // --- TROLL COOLDOWN CHECK ---
        $currentCustomerId = auth()->guard('customer')->id();
        $cookieToken = request()->cookie('sf_chat_uuid');
        $lockKey = 'support_troll_cooldown_' . ($currentCustomerId ?: ($cookieToken ?: request()->ip()));
        if (\Illuminate\Support\Facades\Cache::get($lockKey)) {
            $cooldownMsg = 'Da das Ausdauer-Limit durch wiederholte themenfremde Nachrichten überschritten wurde, ist der Live-Chat vorübergehend pausiert. Für dringende Bestellanliegen nutze bitte unser [Kontaktformular](/kontakt).';
            $this->message = '';
            $this->messages[] = ['sender' => 'system', 'text' => $cooldownMsg];
            $this->isResolved = true;
            $this->dispatch('message-received');
            return;
        }

        // --- RATE LIMITING ---
        $throttleKey = 'support_chat_' . ($currentCustomerId ?? request()->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            SystemSecurityService::logEvent(
                'security:rate_limit_support_chat',
                'WARNING',
                'KI-Support-Chat Rate-Limit ausgelöst (Spam-Schutz).',
                ['customer_id' => $currentCustomerId, 'retry_after' => $seconds],
                'bot_defense'
            );
            $rateLimitMsg = "Du schreibst etwas zu schnell. Bitte warte {$seconds} Sekunden, bevor du eine neue Nachricht sendest.";
            $this->messages[] = ['sender' => 'system', 'text' => $rateLimitMsg];
            $this->dispatch('message-received');
            return;
        }
        RateLimiter::hit($throttleKey, 60);

        $sessionChatId = session('current_chat_id');
        
        if ($this->chatId) {
            // Verify component chatId against session chatId
            if ($sessionChatId && (string)$this->chatId !== (string)$sessionChatId) {
                Log::warning("SupportChat ID mismatch: Session {$sessionChatId} vs Component {$this->chatId}");
                $this->chatId = $sessionChatId;
            }

            $chat = SupportCustomerChat::find($this->chatId);
            if ($chat) {
                if (!empty($chat->customer_id) && (string)$chat->customer_id !== (string)$currentCustomerId) {
                    Log::warning("SupportChat ownership violation: Chat belongs to {$chat->customer_id}, but user is {$currentCustomerId}");
                    SystemSecurityService::logEvent(
                        'security:idor_support_chat',
                        'ERROR',
                        'Unautorisierter Zugriffsversuch auf fremden Support-Chat blockiert.',
                        ['chat_id' => $chat->id, 'owner_id' => $chat->customer_id, 'actor_id' => $currentCustomerId],
                        'idor_integrity'
                    );
                    return;
                }
                if (in_array($chat->status, ['resolved', 'resolved_admin', 'resolved_auto', 'needs_employee', 'closed'], true)) {
                    $this->isResolved = true;
                    return;
                }
                if (empty($chat->customer_id) && $currentCustomerId) {
                    $chat->update(['customer_id' => $currentCustomerId]);
                }
            } else {
                $this->chatId = null;
                session()->forget('current_chat_id');
            }
        }

        $this->message = '';

        if (!$this->chatId) {
            $chat = SupportCustomerChat::create([
                'customer_id' => $currentCustomerId,
                'session_token' => $cookieToken,
                'status' => 'open'
            ]);
            $this->chatId = $chat->id;
            session(['current_chat_id' => $chat->id]);

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
        
        // --- FREEMIUM COUNTER LOGIC (Cross-Chat geschützt) ---
        $isCustomer = auth()->guard('customer')->check();
        $isSystem = false;
        if (class_exists(\App\Models\System\SystemUser::class)) {
            $sysGuard = (new \App\Models\System\SystemUser)->getGuard();
            $isSystem = auth()->guard($sysGuard)->check();
        }
        $isGuest = !($isCustomer || $isSystem || auth()->check());

        if ($isGuest) {
            session(['guest_support_msg_count' => ((int) session('guest_support_msg_count', 0)) + 1]);
        }

        $userMsgCount = $isGuest
            ? $this->getEffectiveGuestMessageCount()
            : SupportCustomerChatMessage::where('support_customer_chat_id', $this->chatId)
                ->where('sender', 'customer')
                ->count();

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
        $throttleKey = 'support_chat_inference_' . (auth()->guard('customer')->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $this->isTyping = false;
            return;
        }
        RateLimiter::hit($throttleKey, 60);

        $sessionChatId = session('current_chat_id');
        if ($sessionChatId && (string)$this->chatId !== (string)$sessionChatId) {
            Log::warning("Unauthorized inference attempt: session {$sessionChatId} vs chatId {$this->chatId}");
            return;
        }

        // Hole alle bisherigen Nachrichten
        $chat = SupportCustomerChat::with('messages')->find($this->chatId);
        if (!$chat) return;

        if (in_array($chat->status, ['resolved', 'resolved_admin', 'resolved_auto', 'needs_employee', 'closed'], true)) {
            $this->isTyping = false;
            return;
        }

        $currentCustomerId = auth()->guard('customer')->id();
        if ($chat->customer_id && (string)$chat->customer_id !== (string)$currentCustomerId) {
            Log::warning("Inference rejected: Chat customer mismatch.");
            $this->isTyping = false;
            return;
        }

        // Schutz gegen direkten Aufruf von generateAiResponse ohne vorherige Kundennachricht oder über Gast-Limit
        $lastDbMsg = $chat->messages->last();
        if (!$lastDbMsg || $lastDbMsg->sender !== 'customer') {
            $this->isTyping = false;
            return;
        }

        $isSystem = false;
        if (class_exists(\App\Models\System\SystemUser::class)) {
            $sysGuard = (new \App\Models\System\SystemUser)->getGuard();
            $isSystem = auth()->guard($sysGuard)->check();
        }
        $isGuest = !($currentCustomerId || $isSystem || auth()->check());
        if ($isGuest && $this->getEffectiveGuestMessageCount() >= 3) {
            $this->isTyping = false;
            return;
        }

        // --- STUFE 1: LOKALER 0-TOKEN PRE-FLIGHT GUARD ---
        $preFlightReply = $this->evaluateLocalPreFlightGuard((string) $lastDbMsg->message, $chat, $lastDbMsg);
        if ($preFlightReply !== null) {
            SupportCustomerChatMessage::create([
                'support_customer_chat_id' => $this->chatId,
                'sender' => 'ai',
                'message' => $preFlightReply
            ]);
            $this->messages[] = ['sender' => 'ai', 'text' => $preFlightReply];

            $chat->refresh();
            if (in_array($chat->status, ['resolved', 'resolved_admin', 'resolved_auto', 'needs_employee'], true)) {
                $this->isResolved = true;
            }
            $this->isTyping = false;
            $this->dispatch('message-received');
            return;
        }

        $supportAgent = \App\Models\Ai\AiAgent::whereHas('department', function ($query) {
            $query->where('name', 'Support');
        })->where('is_active', true)->first();
        
        if (!$supportAgent) {
            $supportAgent = \App\Models\Ai\AiAgent::where('name', 'Funki')->where('is_active', true)->first();
        }

        if (!$supportAgent) {
            $supportAgent = new \App\Models\Ai\AiAgent([
                'name' => 'Funki',
                'system_prompt' => 'Du bist Funki, ein hilfreicher Support-Assistent.'
            ]);
        }

        // --- STUFE 2: AUSDAUER & CHAT-LIMIT MECHANIK (inkl. Cross-Chat Straf-Gedächtnis) ---
        $customerMsgs = $chat->messages->where('sender', 'customer');
        $customerMsgCount = $customerMsgs->count();
        $severitySum = (int) $customerMsgs->sum('severity');
        $carryOverPenalty = \App\Services\AI\Functions\AiSupportFuncs::getCarryOverPenaltyPoints($chat);
        $chatWeight = ($customerMsgCount * 10) + $severitySum + $carryOverPenalty;
        
        if ($chatWeight >= 100) {
            $customerId = $currentCustomerId ?? $chat->customer_id;

            if ($severitySum + $carryOverPenalty >= 50) {
                $abortMsg = 'Da unser Ausdauer-Limit durch wiederholte Abweichungen vom Thema erreicht wurde, habe ich diesen Support-Chat nun geschlossen. Ich bitte um Verständnis, dass ich ausschließlich für Produkt- und Bestellsupport zur Verfügung stehe.';
                $newStatus = 'resolved_auto';
                $topic = 'Spam / Offtopic / Troll';
                $summary = "Der Chat wurde automatisch geschlossen, da das Ausdauer-Limit ({$chatWeight}/100) durch erhöhte Strafpunkte erreicht wurde.";
            } elseif ($customerId) {
                $abortMsg = "Ich merke, dass wir hier gerade an unsere technischen Grenzen stoßen. Um dir bestmöglich und schnellstmöglich zu helfen, habe ich unser Gespräch nun als offizielles Ticket markiert und an meine menschlichen Kollegen weitergeleitet. Sie werden sich den Verlauf in Kürze ansehen und sich bei dir melden!";
                $newStatus = 'needs_employee';
                $topic = 'Automatischer Abbruch (Chat-Limit)';
                $summary = 'Das System hat diesen Chat automatisch eskaliert, da das Ausdauer-Limit (100 Punkte) durch eine hohe Anzahl von Nachrichten erreicht wurde. Eine manuelle Überprüfung durch das Team ist notwendig.';
            } else {
                $abortMsg = "Ich merke, dass wir hier gerade an unsere technischen Grenzen stoßen. Da du als Gast unterwegs bist, kann ich leider kein automatisches Ticket für dich eröffnen. Bitte melde dich an oder kontaktiere den Support über das offizielle Kontaktformular.";
                $newStatus = 'needs_employee';
                $topic = 'Automatischer Abbruch (Chat-Limit)';
                $summary = 'Das System hat diesen Gast-Chat beim Erreichen des Ausdauer-Limits (100 Punkte) beendet.';
            }
            
            SupportCustomerChatMessage::create([
                'support_customer_chat_id' => $this->chatId,
                'sender' => 'system',
                'message' => $abortMsg
            ]);
            $this->messages[] = ['sender' => 'system', 'text' => $abortMsg];
            
            $chat->update([
                'status' => $newStatus,
                'top_topic' => $topic,
                'ai_confidence_score' => $newStatus === 'resolved_auto' ? 100 : 65,
                'ai_summary' => $summary,
            ]);

            if ($customerId && $newStatus === 'needs_employee') {
                $ticket = \App\Models\Support\SupportTicket::create([
                    'ticket_number' => 'TCK-' . strtoupper(\Illuminate\Support\Str::random(8)),
                    'customer_id'   => $customerId,
                    'subject'       => 'Automatischer Abbruch (Chat-Limit)',
                    'category'      => 'allgemein',
                    'status'        => 'open',
                    'priority'      => 'normal',
                ]);

                $historyStr = "";
                foreach ($chat->messages as $m) {
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
        
        $dynamicEndurancePrompt = null;
        if ($chatWeight >= 40) {
            // Weicher Druck aufbauen (wird via setDynamicSystemPrompt übergeben, OHNE den Basis-Systemprompt zu verdoppeln!)
            $dynamicEndurancePrompt = "[SYSTEM WARNUNG - AUSDAUER-LIMIT FAST ERREICHT]\n"
                . "Der Kunde hat bereits viele Fragen gestellt oder Strafpunkte gesammelt (Auslastung: {$chatWeight}/100 Punkten). "
                . "Dein oberstes Ziel ist es nun, dieses Gespräch extrem höflich, charmant und zielführend zum Abschluss zu bringen. "
                . "Stelle KEINE Gegenfragen mehr, die den Chat unnötig verlängern könnten. Fasse dich kurz und weise charmant darauf hin, dass du das Anliegen bei Bedarf gerne als Ticket für einen menschlichen Mitarbeiter öffnest.";
        }

        // API-Optimierung: Kein doppelter System-Prompt in $payloadMessages (GeminiAgent::ask lädt AiPromptService::getRichPrompt bereits zentral!).
        // Nur die letzten 5 validen Nachrichten (ohne System-Meldungen und ohne frühere Ausfall-Meldungen) anhängen.
        $payloadMessages = [];
        $recentMessages = $chat->messages
            ->filter(function ($msg) {
                if (!in_array($msg->sender, ['customer', 'ai'], true)) {
                    return false;
                }
                if ($msg->sender === 'ai' && (self::isTechnicalOrEmptyAiError($msg->message) || $msg->message === self::FRIENDLY_UNAVAILABLE_MESSAGE)) {
                    return false;
                }
                return true;
            })
            ->slice(-5);

        foreach ($recentMessages as $msg) {
            $role = ($msg->sender === 'ai') ? 'assistant' : 'user';
            $payloadMessages[] = [
                'role' => $role,
                'content' => mb_substr((string) $msg->message, 0, self::MAX_MESSAGE_LENGTH)
            ];
        }

        $inferenceStart = microtime(true);
        $hadTechnicalFailure = false;

        try {
            $apiService = \App\Services\AI\AiAgentFactory::make($supportAgent);
            if ($dynamicEndurancePrompt && method_exists($apiService, 'setDynamicSystemPrompt')) {
                $apiService->setDynamicSystemPrompt($dynamicEndurancePrompt);
            }
            
            \App\Services\AI\AIFunctionsRegistry::setGlobalContext([
                '__chat_id' => $this->chatId,
                '__agent_id' => $supportAgent ? $supportAgent->id : null
            ]);

            $response = $apiService->ask($payloadMessages, function($event) {
                if (($event['type'] ?? '') === 'tool_call') {
                    $toolName = $event['tool'] ?? 'System';
                    $html = '<div class="text-[10px] text-cyan-600 font-mono opacity-80 mt-1 flex items-center gap-1.5 animate-pulse"><svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Prüft Systemdaten...</div>';
                    $this->stream('thought_customer', $html, false);
                } elseif (($event['type'] ?? '') === 'thought_html') {
                    $this->stream('thought_customer', $event['html'], false);
                } elseif (($event['type'] ?? '') === 'text_chunk') {
                    $chunk = (string) ($event['chunk'] ?? '');
                    if (!self::isTechnicalOrEmptyAiError($chunk)) {
                        $this->stream('answer_customer', $chunk, false);
                    }
                }
            });

            $rawContent = (string) ($response['response'] ?? '');

            if (self::isTechnicalOrEmptyAiError($rawContent)) {
                $hadTechnicalFailure = true;
                Log::warning('Support Chat AI Fallback aktiv (technische Warnung unterdrückt)', [
                    'chat_id' => $this->chatId,
                    'raw_response' => mb_substr($rawContent, 0, 500),
                ]);
                $content = self::FRIENDLY_UNAVAILABLE_MESSAGE;
            } else {
                $content = $rawContent;
            }

            // Nachricht speichern
            SupportCustomerChatMessage::create([
                'support_customer_chat_id' => $this->chatId,
                'sender' => 'ai',
                'message' => $content
            ]);

            $this->messages[] = ['sender' => 'ai', 'text' => $content];
            
            // Dispatch frontend events generated by tools (like system_visualize_data)
            if (!$hadTechnicalFailure && isset($response['events']) && is_array($response['events'])) {
                foreach ($response['events'] as $event) {
                    if (isset($event['name'])) {
                        $this->dispatch($event['name'], $event['detail'] ?? []);
                    }
                }
            }
            
        } catch (\Throwable $e) {
            $hadTechnicalFailure = true;
            Log::error('Support Chat Error: ' . $e->getMessage());
            SupportCustomerChatMessage::create([
                'support_customer_chat_id' => $this->chatId,
                'sender' => 'ai',
                'message' => self::FRIENDLY_UNAVAILABLE_MESSAGE
            ]);
            $this->messages[] = ['sender' => 'ai', 'text' => self::FRIENDLY_UNAVAILABLE_MESSAGE];
        } finally {
            \App\Services\AI\AIFunctionsRegistry::setGlobalContext([]);
        }

        $inferenceMs = (int) round((microtime(true) - $inferenceStart) * 1000);

        $chat->refresh();
        if (in_array($chat->status, ['resolved', 'resolved_admin', 'resolved_auto', 'needs_employee'])) {
            $this->isResolved = true;
        }

        // Dynamische Telemetrie: Antwortzeit (gleitender Schnitt) & Confidence Score anhand von Chat-Verlauf berechnen
        $updatedCustomerMsgs = $chat->messages->where('sender', 'customer');
        $updatedWeight = ($updatedCustomerMsgs->count() * 10) + (int) $updatedCustomerMsgs->sum('severity');
        $dynamicConfidence = match ($chat->status) {
            'needs_employee' => 65,
            'resolved_auto' => 100,
            'resolved' => max(75, min(98, 100 - (int) round($updatedWeight * 0.25))),
            default => $hadTechnicalFailure ? 50 : max(55, min(96, 95 - (int) round($updatedWeight * 0.35))),
        };
        $prevAvgMs = (int) ($chat->avg_response_time_ms ?? 0);
        $newAvgMs = $prevAvgMs > 0 ? (int) round(($prevAvgMs + $inferenceMs) / 2) : $inferenceMs;
        
        $chat->update([
            'ai_confidence_score' => $dynamicConfidence,
            'avg_response_time_ms' => $newAvgMs,
        ]);

        $this->isTyping = false;
        $this->dispatch('message-received');
    }

    public function setRating($stars) {
        $this->rating = max(1, min(5, (int) $stars));
    }

    public function submitRating() {
        if ($this->rating < 1 || !$this->chatId) return;

        $this->rating = max(1, min(5, (int) $this->rating));
        $this->feedbackText = mb_substr((string) $this->feedbackText, 0, 2000);

        $sessionChatId = session('current_chat_id');
        if ($sessionChatId && (string)$this->chatId !== (string)$sessionChatId) {
            Log::warning("Unauthorized rating attempt: session {$sessionChatId} vs chatId {$this->chatId}");
            return;
        }

        $currentCustomerId = auth()->guard('customer')->id();
        $chat = SupportCustomerChat::find($this->chatId);
        if ($chat) {
            if ($chat->customer_id && (string)$chat->customer_id !== (string)$currentCustomerId) {
                Log::warning("Rating rejected: Chat customer mismatch.");
                return;
            }
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
        } elseif (is_string($sessionToken) && strlen(trim($sessionToken)) >= 16) {
            SupportCustomerChat::where('session_token', trim($sessionToken))
                ->whereNull('customer_id')
                ->where('id', '!=', $this->chatId ?? 0)
                ->whereNull('rating')
                ->update(['status' => 'closed']);
        }

        if ($this->chatId) {
            $currentChatQuery = SupportCustomerChat::where('id', $this->chatId);
            if ($customerId) {
                $currentChatQuery->where('customer_id', $customerId);
            } else {
                $currentChatQuery->whereNull('customer_id');
            }
            $currentChatQuery->update(['status' => 'closed']);
        }

        session()->forget('current_chat_id');

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
        
        $userMsgCount = $isGuest
            ? $this->getEffectiveGuestMessageCount()
            : SupportCustomerChatMessage::where('support_customer_chat_id', $this->chatId)
                ->where('sender', 'customer')
                ->count();
        
        // Wenn 3 Nachrichten gesendet wurden, rendern wir den Input als gesperrt
        if ($isGuest && $userMsgCount >= 3) {
            $this->guestLimitReached = true;
        } else {
            $this->guestLimitReached = false;
        }

        return view('livewire.frontend.support.ai-frontend-support-chat');
    }
}

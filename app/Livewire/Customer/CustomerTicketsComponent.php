<?php

namespace App\Livewire\Customer;

use App\Events\TicketMessageSent;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Services\Gamification\GamificationService;
use App\Services\System\SystemSecurityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On; // NEU HINZUGEFÜGT

#[Layout('components.layouts.customer_layout')]
class CustomerTicketsComponent extends Component
{
    use WithFileUploads;

    public $viewMode = 'list';
    public $activeTicketId = null;

    // GANZ WICHTIG FÜR WEBSOCKETS BEI UUIDS: MUSS STRING SEIN!
    #[Locked]
    public string $customerId = '';

    public $emailNotifications = true;
    public $newSubject = '';
    public $newCategory = 'support';
    public $newOrderId = '';
    public $newMessage = '';
    public $attachments = [];
    public $newAttachments = [];
    public $uploadError = '';
    public $chatMessage = '';
    public $chatAttachments = [];
    public $chatNewAttachments = [];

    // Rating State
    public $rating = 0;
    public $feedbackText = '';
    public $ratingSubmitted = false;

    // Feature: Customer closing ticket
    public $closeReason = '';

    public function mount()
    {
        if (!Auth::guard('customer')->check()) {
            return redirect()->route('login');
        }

        // Als String sichern für Livewire
        $this->customerId = (string) Auth::guard('customer')->id();

        $profile = CustomerGamification::firstOrCreate(
            ['customer_id' => $this->customerId],
            ['ticket_emails_enabled' => true]
        );
        $this->emailNotifications = (bool) $profile->ticket_emails_enabled;
    }

    private function enforceCustomerId(): string
    {
        $authId = (string) Auth::guard('customer')->id();
        if ($this->customerId !== '' && $this->customerId !== $authId) {
            SystemSecurityService::logEvent(
                'security:idor_customer_ticket',
                'ERROR',
                'Manipulation der Customer-ID im Ticket-System verhindert (IDOR-Schutz).',
                ['attempted_customer_id' => $this->customerId, 'authenticated_customer_id' => $authId],
                'idor_integrity'
            );
        }
        $this->customerId = $authId;
        return $authId;
    }

    public function setRating($stars) {
        $this->rating = max(1, min(5, (int) $stars));
    }

    public function submitRating() {
        $this->enforceCustomerId();
        if ($this->rating < 1 || !$this->activeTicketId) return;

        $this->rating = max(1, min(5, (int) $this->rating));
        $this->feedbackText = mb_substr((string) $this->feedbackText, 0, 2000);

        $ticket = SupportTicket::where('id', $this->activeTicketId)->where('customer_id', $this->customerId)->first();
        if ($ticket && $ticket->status === 'closed') {
            $ticket->update([
                'rating' => $this->rating,
                'feedback_text' => $this->feedbackText
            ]);
            $this->ratingSubmitted = true;
            session()->flash('rating_success', 'Vielen Dank für deine fantastische Bewertung!');
        }
    }

    // DIE MAGISCHE ECHTZEIT-FUNKTION!
    public function receiveMessage($event)
    {
        # \Illuminate\Support\Facades\Log::info('WS Triggered: Customer receiveMessage', ['payload' => $event, 'activeTicketId' => $this->activeTicketId]);

        if (isset($event['message']['support_ticket_id']) && (string) $this->activeTicketId === (string) $event['message']['support_ticket_id']) {
            $this->markAsRead($this->activeTicketId);
            $this->dispatch('ticket-message-received');

            // NEU: Punkt ausschalten, falls Chat offen ist
            $this->dispatch('clear-ticket-badge');
        } else {
            // NEU: Punkt anschalten, falls User im Listen-Modus ist
            $this->dispatch('ticket-badge-update', hasUnread: true);
        }
        $this->dispatch('$refresh');
    }

    public function updatedEmailNotifications()
    {
        $this->enforceCustomerId();
        $profile = CustomerGamification::firstOrCreate(
            ['customer_id' => $this->customerId]
        );
        $profile->update(['ticket_emails_enabled' => (bool) $this->emailNotifications]);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Benachrichtigungs-Einstellung gespeichert.']);
    }

    public function updatedNewAttachments() { $this->processUploads('attachments', 'newAttachments'); }
    public function updatedChatNewAttachments() { $this->processUploads('chatAttachments', 'chatNewAttachments'); }

    private function processUploads($targetArray, $newArray)
    {
        $this->uploadError = '';
        $totalSize = 0;
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'txt', 'docx'];

        foreach ($this->$targetArray as $file) { $totalSize += $file->getSize(); }
        foreach ($this->$newArray as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowedExtensions, true)) {
                $this->uploadError = 'Ungültiges Dateiformat (.' . $ext . '). Erlaubt sind: ' . implode(', ', $allowedExtensions) . '.';
                break;
            }

            $totalSize += $file->getSize();
            if ($totalSize <= 5 * 1024 * 1024) {
                $this->$targetArray[] = $file;
            } else {
                $this->uploadError = 'Achtung: Upload-Limit erreicht (max. 5 MB).';
                break;
            }
        }
        $this->reset($newArray);
    }

    public function removeAttachment($index, $targetArray = 'attachments') { array_splice($this->$targetArray, $index, 1); $this->uploadError = ''; }

    public function setMode($mode, $ticketId = null)
    {
        $this->enforceCustomerId();
        $this->viewMode = $mode;
        $this->activeTicketId = $ticketId;
        $this->reset(['attachments', 'newAttachments', 'chatAttachments', 'chatNewAttachments', 'chatMessage', 'uploadError']);
        $this->rating = 0;
        $this->feedbackText = '';
        $this->ratingSubmitted = false;

        if ($mode === 'chat' && $ticketId) {
            // Mandantenprüfung: Ticket muss dem eingeloggten Kunden gehören
            $ticket = SupportTicket::where('customer_id', $this->customerId)->find($ticketId);
            if (!$ticket) {
                SystemSecurityService::logEvent(
                    'security:idor_customer_ticket_view',
                    'WARNING',
                    'Versuchter Zugriff auf fremdes oder nicht existentes Support-Ticket blockiert.',
                    ['customer_id' => $this->customerId, 'ticket_id' => $ticketId],
                    'idor_integrity'
                );
                $this->activeTicketId = null;
                $this->viewMode = 'list';
                return;
            }

            $this->markAsRead($ticket->id);
            $this->dispatch('ticket-message-received');

            // NEU: Punkt ausschalten
            $this->dispatch('clear-ticket-badge');

            // Lade Rating falls das Ticket geschlossen ist
            if ($ticket->rating !== null) {
                $this->rating = $ticket->rating;
                $this->feedbackText = $ticket->feedback_text;
                $this->ratingSubmitted = true;
            }
        }
    }

    public function createTicket(GamificationService $gameService)
    {
        $this->enforceCustomerId();
        $throttleKey = 'create_ticket_' . $this->customerId;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            SystemSecurityService::logEvent(
                'security:rate_limit_ticket_create',
                'WARNING',
                'Support-Ticket Rate-Limit für Kunden ausgelöst.',
                ['customer_id' => $this->customerId, 'retry_after' => $seconds],
                'bot_defense'
            );
            $this->addError('newSubject', "Zu viele Ticket-Erstellungen. Bitte warte {$seconds} Sekunden.");
            return;
        }

        $this->validate([
            'newSubject' => 'required|min:5|max:100',
            'newCategory' => 'required',
            'newMessage' => 'required|min:10',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf,txt,docx|max:5120',
        ], [
            'newSubject.required' => 'Bitte gib einen Betreff für deine Anfrage ein.',
            'newSubject.min' => 'Der Betreff ist zu kurz (mindestens 5 Zeichen).',
            'newSubject.max' => 'Der Betreff ist zu lang (maximal 100 Zeichen).',
            'newCategory.required' => 'Bitte wähle eine Kategorie aus.',
            'newMessage.required' => 'Bitte beschreibe dein Anliegen in einer Nachricht.',
            'newMessage.min' => 'Deine Nachricht ist zu kurz (mindestens 10 Zeichen).'
        ]);

        RateLimiter::hit($throttleKey, 3600);

        // Sicherheitscheck: Zugehörige Bestellung muss dem Kunden gehören
        $validatedOrderId = null;
        if (!empty($this->newOrderId)) {
            $orderBelongs = OrderOrder::where('id', $this->newOrderId)
                ->where('customer_id', $this->customerId)
                ->exists();
            if ($orderBelongs) {
                $validatedOrderId = $this->newOrderId;
            }
        }

        $ticket = SupportTicket::create([
            'ticket_number' => 'MSF-' . date('y') . '-' . strtoupper(Str::random(5)),
            'customer_id' => $this->customerId,
            'order_id' => $validatedOrderId,
            'subject' => $this->newSubject,
            'category' => $this->newCategory,
            'status' => 'open',
            'priority' => $this->newCategory === 'bug' ? 'high' : 'normal',
        ]);

        $attachmentPaths = [];
        foreach ($this->attachments as $photo) { $attachmentPaths[] = $photo->store('support/tickets/attachments', 'public'); }

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'message' => $this->newMessage,
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_read_by_customer' => true,
        ]);

        // Hole den Nutzer, um E-Mail und Namen zu nutzen
        $customerUser = Auth::guard('customer')->user();
        $firstName = $customerUser ? $customerUser->first_name : 'liebe Seele';

        $welcomeMessage = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'message' => "Hallo {$firstName},\n\nvielen Dank für deine Nachricht. Dein Ticket mit der Nummer {$ticket->ticket_number} ist sicher bei uns eingegangen.\n\nWir melden uns in Kürze. Du erhältst eine Benachrichtigung, sobald es Neuigkeiten gibt.\n\nEine magische Zeit wünscht dir\nDein Seelenfunke Team",
            'is_read_by_customer' => false,
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($customerUser->email)
                ->send(new \App\Mail\SupportTicketCreatedMailToCustomer($customerUser, $ticket));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Mail Error: ' . $e->getMessage());
        }

        try {
            broadcast(new TicketMessageSent($message, $ticket->id, $this->customerId));
            // Optional: Broadcast welcome message to self just so the chat updates instantly on the client side
            broadcast(new TicketMessageSent($welcomeMessage, $ticket->id, $this->customerId));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Websocket Offline: ' . $e->getMessage());
        }

        if ($this->newCategory === 'bug') {
            $alreadyRewardedToday = SupportTicket::where('customer_id', $this->customerId)
                ->where('category', 'bug')
                ->where('reward_claimed', true)
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();

            if (!$alreadyRewardedToday) {
                $gameService->addFunken(Auth::guard('customer')->user(), 5, 'bug_report');
                $ticket->update(['reward_claimed' => true]);
                $this->dispatch('notify', ['type' => 'success', 'message' => 'SupportTicket eröffnet! +5 ✨ für deine Fehlermeldung!']);
                $this->dispatch('sparks-awarded');
            } else {
                $this->dispatch('notify', ['type' => 'success', 'message' => 'Dein SupportTicket wurde erfolgreich eröffnet.']);
            }
        } else {
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Dein SupportTicket wurde erfolgreich eröffnet.']);
        }

        $this->reset(['newSubject', 'newCategory', 'newOrderId', 'newMessage', 'attachments', 'newAttachments', 'uploadError']);
        $this->setMode('list');
    }

    public function sendReply()
    {
        $this->enforceCustomerId();
        $this->validate([
            'chatMessage' => 'required|min:1',
            'chatAttachments.*' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf,txt,docx|max:5120',
        ], [
            'chatMessage.required' => 'Bitte schreibe eine Nachricht.',
            'chatMessage.min' => 'Die Nachricht darf nicht leer sein.'
        ]);

        $ticket = SupportTicket::where('customer_id', $this->customerId)->where('id', $this->activeTicketId)->firstOrFail();

        $attachmentPaths = [];
        foreach ($this->chatAttachments as $photo) { $attachmentPaths[] = $photo->store('support/tickets/attachments', 'public'); }

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'message' => $this->chatMessage,
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_read_by_customer' => true,
        ]);

        $ticket->update(['status' => 'open']);

        try {
            broadcast(new TicketMessageSent($message, $ticket->id, $this->customerId));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Websocket Offline: ' . $e->getMessage());
        }

        $this->reset(['chatMessage', 'chatAttachments', 'chatNewAttachments', 'uploadError']);
        $this->dispatch('ticket-message-received');
    }

    public function closeTicket()
    {
        $this->enforceCustomerId();
        $this->validate([
            'closeReason' => 'required|min:5'
        ], [
            'closeReason.required' => 'Gibt bitte einen Grund für die Schließung an.',
            'closeReason.min' => 'Der Grund sollte mindestens 5 Zeichen enthalten.'
        ]);

        $ticket = SupportTicket::where('customer_id', $this->customerId)->where('id', $this->activeTicketId)->firstOrFail();
        $ticket->update([
            'status' => 'closed',
            'close_reason' => $this->closeReason
        ]);

        // Dispatch event to close Alpine modal
        $this->dispatch('close-ticket-modal-hide');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Dein Ticket wurde erfolgreich geschlossen.']);

        $this->closeReason = '';
    }

    private function markAsRead($ticketId)
    {
        $this->enforceCustomerId();
        $ticket = SupportTicket::where('customer_id', $this->customerId)->find($ticketId);
        if ($ticket) {
            SupportTicketMessage::where('support_ticket_id', $ticket->id)
                ->where('sender_type', '!=', 'customer')
                ->update(['is_read_by_customer' => true]);
        }
    }

    public function render()
    {
        $this->enforceCustomerId();
        $tickets = SupportTicket::where('customer_id', $this->customerId)->with('order')->orderBy('updated_at', 'desc')->get();
        $orders = OrderOrder::where('customer_id', $this->customerId)->with('items')->orderBy('created_at', 'desc')->get();
        $activeTicket = $this->viewMode === 'chat' && $this->activeTicketId ? SupportTicket::with('messages', 'order')->where('customer_id', $this->customerId)->where('id', $this->activeTicketId)->first() : null;

        return view('livewire.customer.customer-tickets-component', ['tickets' => $tickets, 'orders' => $orders, 'activeTicket' => $activeTicket]);
    }
}

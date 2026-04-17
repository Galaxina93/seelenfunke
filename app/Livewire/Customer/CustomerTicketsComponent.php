<?php

namespace App\Livewire\Customer;

use App\Events\TicketMessageSent;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On; // NEU HINZUGEFÜGT

#[Layout('components.layouts.customer_layout')]
class CustomerTicketsComponent extends Component
{
    use WithFileUploads;

    public $viewMode = 'list';
    public $activeTicketId = null;

    // GANZ WICHTIG FÜR WEBSOCKETS BEI UUIDS: MUSS STRING SEIN!
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

        $profile = CustomerGamification::where('customer_id', $this->customerId)->first();
        if ($profile) {
            $this->emailNotifications = $profile->ticket_emails_enabled;
        }
    }

    public function setRating($stars) {
        $this->rating = $stars;
    }

    public function submitRating() {
        if ($this->rating < 1 || !$this->activeTicketId) return;

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
        $profile = CustomerGamification::where('customer_id', $this->customerId)->first();
        if ($profile) {
            $profile->update(['ticket_emails_enabled' => $this->emailNotifications]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Benachrichtigungs-Einstellung gespeichert.']);
        }
    }

    public function updatedNewAttachments() { $this->processUploads('attachments', 'newAttachments'); }
    public function updatedChatNewAttachments() { $this->processUploads('chatAttachments', 'chatNewAttachments'); }

    private function processUploads($targetArray, $newArray)
    {
        $this->uploadError = '';
        $totalSize = 0;
        foreach ($this->$targetArray as $file) { $totalSize += $file->getSize(); }
        foreach ($this->$newArray as $file) {
            $totalSize += $file->getSize();
            if ($totalSize <= 5 * 1024 * 1024) { $this->$targetArray[] = $file; }
            else { $this->uploadError = 'Achtung: Upload-Limit erreicht.'; break; }
        }
        $this->reset($newArray);
    }

    public function removeAttachment($index, $targetArray = 'attachments') { array_splice($this->$targetArray, $index, 1); $this->uploadError = ''; }

    public function setMode($mode, $ticketId = null)
    {
        $this->viewMode = $mode;
        $this->activeTicketId = $ticketId;
        $this->reset(['attachments', 'newAttachments', 'chatAttachments', 'chatNewAttachments', 'chatMessage', 'uploadError']);
        $this->rating = 0;
        $this->feedbackText = '';
        $this->ratingSubmitted = false;

        if ($mode === 'chat' && $ticketId) {
            $this->markAsRead($ticketId);
            $this->dispatch('ticket-message-received');

            // NEU: Punkt ausschalten
            $this->dispatch('clear-ticket-badge');

            // Lade Rating falls das Ticket geschlossen ist
            $ticket = SupportTicket::find($ticketId);
            if ($ticket && $ticket->rating !== null) {
                $this->rating = $ticket->rating;
                $this->feedbackText = $ticket->feedback_text;
                $this->ratingSubmitted = true;
            }
        }
    }

    public function createTicket(GamificationService $gameService)
    {
        $this->validate([
            'newSubject' => 'required|min:5|max:100',
            'newCategory' => 'required',
            'newMessage' => 'required|min:10'
        ], [
            'newSubject.required' => 'Bitte gib einen Betreff für deine Anfrage ein.',
            'newSubject.min' => 'Der Betreff ist zu kurz (mindestens 5 Zeichen).',
            'newSubject.max' => 'Der Betreff ist zu lang (maximal 100 Zeichen).',
            'newCategory.required' => 'Bitte wähle eine Kategorie aus.',
            'newMessage.required' => 'Bitte beschreibe dein Anliegen in einer Nachricht.',
            'newMessage.min' => 'Deine Nachricht ist zu kurz (mindestens 10 Zeichen).'
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => 'MSF-' . date('y') . '-' . strtoupper(Str::random(5)),
            'customer_id' => $this->customerId,
            'order_id' => $this->newOrderId ?: null,
            'subject' => $this->newSubject,
            'category' => $this->newCategory,
            'status' => 'open',
            'priority' => $this->newCategory === 'bug' ? 'high' : 'normal',
        ]);

        $attachmentPaths = [];
        foreach ($this->attachments as $photo) { $attachmentPaths[] = $photo->store('tickets/attachments', 'public'); }

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
            'message' => "Hallo {$firstName},\n\nvielen Dank für deine Nachricht. Dein Ticket mit der Nummer {$ticket->ticket_number} ist sicher bei uns eingegangen.\n\nEiner unserer Support-Magier wird sich in Kürze deines Anliegens annehmen und dir so schnell wie möglich antworten. Du erhältst eine Benachrichtigung, sobald es Neuigkeiten gibt.\n\nEine magische Zeit wünscht dir\nDein Seelenfunke Team",
            'is_read_by_customer' => false,
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($customerUser->email)
                ->queue(new \App\Mail\SupportTicketCreatedMailToCustomer($customerUser, $ticket));
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
            $gameService->addFunken(Auth::guard('customer')->user(), 5, 'bug_report');
            $this->dispatch('notify', ['type' => 'success', 'message' => 'SupportTicket eröffnet! +5 ✨ für deine Fehlermeldung!']);
            $this->dispatch('sparks-awarded');
            $ticket->update(['reward_claimed' => true]);
        } else {
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Dein SupportTicket wurde erfolgreich eröffnet.']);
        }

        $this->reset(['newSubject', 'newCategory', 'newOrderId', 'newMessage', 'attachments', 'newAttachments', 'uploadError']);
        $this->setMode('list');
    }

    public function sendReply()
    {
        $this->validate([
            'chatMessage' => 'required|min:1'
        ], [
            'chatMessage.required' => 'Bitte schreibe eine Nachricht.',
            'chatMessage.min' => 'Die Nachricht darf nicht leer sein.'
        ]);

        $ticket = SupportTicket::where('customer_id', $this->customerId)->where('id', $this->activeTicketId)->firstOrFail();

        $attachmentPaths = [];
        foreach ($this->chatAttachments as $photo) { $attachmentPaths[] = $photo->store('tickets/attachments', 'public'); }

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
        SupportTicketMessage::where('support_ticket_id', $ticketId)->where('sender_type', '!=', 'customer')->update(['is_read_by_customer' => true]);
    }

    public function render()
    {
        $tickets = SupportTicket::where('customer_id', $this->customerId)->with('order')->orderBy('updated_at', 'desc')->get();
        $orders = OrderOrder::where('customer_id', $this->customerId)->orderBy('created_at', 'desc')->get();
        $activeTicket = $this->viewMode === 'chat' && $this->activeTicketId ? SupportTicket::with('messages', 'order')->where('customer_id', $this->customerId)->where('id', $this->activeTicketId)->first() : null;

        return view('livewire.customer.customer-tickets-component', ['tickets' => $tickets, 'orders' => $orders, 'activeTicket' => $activeTicket]);
    }
}

<?php

namespace App\Livewire\Customer;

use App\Events\FunkiTicketMessageSent;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\Order;
use App\Models\FunkiTicket;
use App\Models\FunkiTicketMessage;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On; // NEU HINZUGEFÜGT

#[Layout('components.layouts.customer_layout')]
class FunkiTicketsComponent extends Component
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

    // DIE MAGISCHE ECHTZEIT-FUNKTION!
    #[On('echo-private:customer.{customerId},.TicketMessageSent')]
    public function receiveMessage($event)
    {
        if (isset($event['message']['funki_ticket_id']) && $this->activeTicketId === $event['message']['funki_ticket_id']) {
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

        if ($mode === 'chat' && $ticketId) {
            $this->markAsRead($ticketId);
            $this->dispatch('ticket-message-received');

            // NEU: Punkt ausschalten
            $this->dispatch('clear-ticket-badge');
        }
    }

    public function createTicket(GamificationService $gameService)
    {
        $this->validate(['newSubject' => 'required|min:5|max:100', 'newCategory' => 'required', 'newMessage' => 'required|min:10']);

        $ticket = FunkiTicket::create([
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

        $message = FunkiTicketMessage::create([
            'funki_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'message' => $this->newMessage,
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_read_by_customer' => true,
        ]);

        broadcast(new FunkiTicketMessageSent($message, $ticket->id, $this->customerId));

        if ($this->newCategory === 'bug') {
            $gameService->addFunken(Auth::guard('customer')->user(), 5, 'bug_report');
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Ticket eröffnet! +5 ✨ für deine Fehlermeldung!']);
            $this->dispatch('sparks-awarded');
            $ticket->update(['reward_claimed' => true]);
        } else {
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Dein Ticket wurde erfolgreich eröffnet.']);
        }

        $this->reset(['newSubject', 'newCategory', 'newOrderId', 'newMessage', 'attachments', 'newAttachments', 'uploadError']);
        $this->setMode('list');
    }

    public function sendReply()
    {
        $this->validate(['chatMessage' => 'required|min:1']);

        $ticket = FunkiTicket::where('customer_id', $this->customerId)->where('id', $this->activeTicketId)->firstOrFail();

        $attachmentPaths = [];
        foreach ($this->chatAttachments as $photo) { $attachmentPaths[] = $photo->store('tickets/attachments', 'public'); }

        $message = FunkiTicketMessage::create([
            'funki_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'message' => $this->chatMessage,
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_read_by_customer' => true,
        ]);

        $ticket->update(['status' => 'open']);

        broadcast(new FunkiTicketMessageSent($message, $ticket->id, $this->customerId));

        $this->reset(['chatMessage', 'chatAttachments', 'chatNewAttachments', 'uploadError']);
        $this->dispatch('ticket-message-received');
    }

    public function closeTicket()
    {
        $ticket = FunkiTicket::where('customer_id', $this->customerId)->where('id', $this->activeTicketId)->firstOrFail();
        $ticket->update(['status' => 'closed']);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Das Ticket wurde geschlossen.']);
    }

    private function markAsRead($ticketId)
    {
        FunkiTicketMessage::where('funki_ticket_id', $ticketId)->where('sender_type', '!=', 'customer')->update(['is_read_by_customer' => true]);
    }

    public function render()
    {
        $tickets = FunkiTicket::where('customer_id', $this->customerId)->with('order')->orderBy('updated_at', 'desc')->get();
        $orders = Order::where('customer_id', $this->customerId)->orderBy('created_at', 'desc')->get();
        $activeTicket = $this->viewMode === 'chat' && $this->activeTicketId ? FunkiTicket::with('messages', 'order')->where('customer_id', $this->customerId)->where('id', $this->activeTicketId)->first() : null;

        return view('livewire.customer.funki-tickets-component', ['tickets' => $tickets, 'orders' => $orders, 'activeTicket' => $activeTicket]);
    }
}

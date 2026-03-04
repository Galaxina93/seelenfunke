<?php

namespace App\Livewire\Admin;

use App\Events\FunkiTicketMessageSent;
use App\Models\Customer\CustomerGamification;
use App\Models\FunkiTicket;
use App\Models\FunkiTicketMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\On; // NEU HINZUGEFÜGT

class FunkiTicketSystemComponent extends Component
{
    use WithPagination, WithFileUploads;

    public $filterStatus = 'open';
    public $search = '';

    public $activeTicketId = null;
    public $replyMessage = '';
    public $replyAttachments = [];

    // DIE MAGISCHE ECHTZEIT-FUNKTION!
    #[On('echo-private:admin.tickets,.TicketMessageSent')]
    public function receiveMessage($event)
    {
        if (isset($event['message']['funki_ticket_id'])) {
            $ticketId = $event['message']['funki_ticket_id'];

            if ($this->activeTicketId === $ticketId) {
                $this->filterStatus = 'open';
                $this->markAsReadAdmin($ticketId);
                $this->dispatch('ticket-message-received');
            }
        }
        $this->dispatch('$refresh');
    }

    public function selectTicket($id)
    {
        $this->activeTicketId = $id;
        $this->reset(['replyMessage', 'replyAttachments']);
        $this->markAsReadAdmin($id);
        $this->dispatch('ticket-message-received');
    }

    private function markAsReadAdmin($id)
    {
        FunkiTicketMessage::where('funki_ticket_id', $id)
            ->where('sender_type', 'customer')
            ->update(['is_read_by_admin' => true]);
    }

    public function sendReply()
    {
        $this->validate(['replyMessage' => 'required|min:1']);

        $ticket = FunkiTicket::with('customer')->findOrFail($this->activeTicketId);

        $attachmentPaths = [];
        foreach ($this->replyAttachments as $photo) {
            $attachmentPaths[] = $photo->store('tickets/attachments', 'public');
        }

        $message = FunkiTicketMessage::create([
            'funki_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'message' => $this->replyMessage,
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_read_by_admin' => true,
            'is_read_by_customer' => false,
        ]);

        $ticket->update(['status' => 'answered']);
        $this->filterStatus = 'answered';

        broadcast(new FunkiTicketMessageSent($message, $ticket->id, $ticket->customer_id));

        $isOnline = false;
        $lastCustomerMsg = FunkiTicketMessage::where('funki_ticket_id', $ticket->id)->where('sender_type', 'customer')->latest('id')->first();

        if ($lastCustomerMsg && (time() - strtotime($lastCustomerMsg->created_at)) < 600) {
            $isOnline = true;
        }
        if (!$isOnline) {
            $profile = \App\Models\Customer\CustomerProfile::where('customer_id', $ticket->customer_id)->first();
            if ($profile && $profile->last_seen && (time() - strtotime($profile->last_seen)) < 300) {
                $isOnline = true;
            }
        }
        if (!$isOnline && Cache::has('customer-online-' . $ticket->customer_id)) {
            $isOnline = true;
        }

        if (!$isOnline && $ticket->customer) {
            $gamification = CustomerGamification::where('customer_id', $ticket->customer_id)->first();
            if (!$gamification || $gamification->ticket_emails_enabled) {
                Mail::to($ticket->customer->email)->send(new \App\Mail\TicketUpdateMailToCustomer($ticket->customer, $ticket));
            }
        }

        $this->reset(['replyMessage', 'replyAttachments']);
        $this->dispatch('ticket-message-received');
    }

    public function closeTicket()
    {
        $ticket = FunkiTicket::findOrFail($this->activeTicketId);
        $ticket->update(['status' => 'closed']);
        $this->filterStatus = 'closed';
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Ticket geschlossen.']);
    }

    public function removeAttachment($index) { array_splice($this->replyAttachments, $index, 1); }

    public function render()
    {
        $query = FunkiTicket::with('customer', 'messages', 'order')->where('status', $this->filterStatus);
        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('ticket_number', 'like', '%' . $this->search . '%')->orWhere('subject', 'like', '%' . $this->search . '%')
                    ->orWhereHas('customer', function($subQ) {
                        $subQ->where('email', 'like', '%' . $this->search . '%')->orWhere('first_name', 'like', '%' . $this->search . '%')->orWhere('last_name', 'like', '%' . $this->search . '%');
                    });
            });
        }

        $tickets = $query->orderBy('updated_at', 'desc')->paginate(15);
        $activeTicket = $this->activeTicketId ? FunkiTicket::with('messages', 'customer', 'order')->find($this->activeTicketId) : null;

        return view('backend.admin.livewire.funki-ticket-system-component', ['tickets' => $tickets, 'activeTicket' => $activeTicket]);
    }
}

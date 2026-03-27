<?php

namespace App\Livewire\Shop\System;

use Livewire\Attributes\Layout;

use App\Events\TicketMessageSent;
use App\Models\Customer\CustomerGamification;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\On;

#[Layout('components.layouts.backend_layout')]
class SystemTickets extends Component
{
    use WithPagination, WithFileUploads, \App\Livewire\Traits\WithDepartmentTheming;

    protected string $themingDepartment = 'System';

    public $filterStatus = 'open';
    public $search = '';
    public $activeTicketId = null;
    public $replyMessage = '';
    public $replyAttachments = [];

    #[On('echo-private:admin.tickets,.TicketMessageSent')]
    public function receiveMessage($event)
    {
        if (isset($event['message']['support_ticket_id'])) {
            $ticketId = $event['message']['support_ticket_id'];
            if ($this->activeTicketId === $ticketId) {
                $this->filterStatus = 'open';
                $this->markAsReadAdmin($ticketId);
                $this->dispatch('ticket-message-received');
                $this->dispatch('clear-admin-ticket-badge'); // Entfernt den roten Punkt sofort
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
        $this->dispatch('clear-admin-ticket-badge'); // Entfernt den roten Punkt sofort beim Öffnen
    }

    private function markAsReadAdmin($id)
    {
        SupportTicketMessage::where('support_ticket_id', $id)
            ->where('sender_type', 'customer')
            ->update(['is_read_by_admin' => true]);
    }

    public function sendReply()
    {
        $this->validate(['replyMessage' => 'required|min:1']);

        $ticket = SupportTicket::with('customer')->findOrFail($this->activeTicketId);

        $attachmentPaths = [];
        foreach ($this->replyAttachments as $photo) {
            $attachmentPaths[] = $photo->store('tickets/attachments', 'public');
        }

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'message' => $this->replyMessage,
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_read_by_admin' => true,
            'is_read_by_customer' => false,
        ]);

        $ticket->update(['status' => 'answered']);
        $this->filterStatus = 'answered';

        broadcast(new TicketMessageSent($message, $ticket->id, $ticket->customer_id));

        $isOnline = false;
        $lastCustomerMsg = SupportTicketMessage::where('support_ticket_id', $ticket->id)
            ->where('sender_type', 'customer')
            ->latest('id')
            ->first();

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
                Mail::to($ticket->customer->email)->send(new \App\Mail\SupportTicketUpdateMailToCustomer($ticket->customer, $ticket));
            }
        }

        $this->reset(['replyMessage', 'replyAttachments']);
        $this->dispatch('ticket-message-received');
    }

    public function closeTicket()
    {
        $ticket = SupportTicket::findOrFail($this->activeTicketId);
        $ticket->update(['status' => 'closed']);
        $this->filterStatus = 'closed';
        $this->dispatch('notify', ['type' => 'success', 'message' => 'SupportTicket geschlossen.']);
    }

    public function removeAttachment($index)
    {
        array_splice($this->replyAttachments, $index, 1);
    }

    public function render()
    {
        $query = SupportTicket::with('customer', 'messages', 'order')
            ->where('status', $this->filterStatus);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('ticket_number', 'like', '%' . $this->search . '%')
                    ->orWhere('subject', 'like', '%' . $this->search . '%')
                    ->orWhereHas('customer', function ($subQ) {
                        $subQ->where('email', 'like', '%' . $this->search . '%')
                            ->orWhere('first_name', 'like', '%' . $this->search . '%')
                            ->orWhere('last_name', 'like', '%' . $this->search . '%');
                    });
            });
        }

        $tickets = $query->orderBy('updated_at', 'desc')->paginate(15);
        $activeSupportTicket = $this->activeTicketId ? SupportTicket::with('messages', 'customer', 'order')->find($this->activeTicketId) : null;

        return view('livewire.shop.system.system-tickets', [
            'tickets' => $tickets,
            'activeTicket' => $activeSupportTicket
        ]);
    }
}

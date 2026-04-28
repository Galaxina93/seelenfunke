<?php

namespace App\Livewire\Shop\Support;

use Livewire\Attributes\Layout;

use App\Events\TicketMessageSent;
use App\Models\Customer\CustomerGamification;
use App\Models\Support\SupportTicket as SupportTicketModel;
use App\Models\Support\SupportTicketMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\On;

#[Layout('components.layouts.backend_layout')]
class SupportTicket extends Component
{
    use WithPagination, WithFileUploads, \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'Support';

    public $viewMode = 'split'; // 'split' or 'table'
    public $filterStatus = 'open';
    public $search = '';
    public $activeTicketId = null;
    public $replyMessage = '';
    public $replyAttachments = [];

    // KPIs
    public $kpiAvgRating = 0;
    public $kpiOpenCount = 0;
    public $kpiAvgResolutionHrs = 0;

    public function mount()
    {
        $this->calculateKPIs();
    }

    public function calculateKPIs()
    {
        $this->kpiOpenCount = SupportTicketModel::where('status', 'open')->count();
        $this->kpiAvgRating = round((float) SupportTicketModel::whereNotNull('rating')->avg('rating'), 1);

        $closedTickets = SupportTicketModel::where('status', 'closed')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $totalHours = 0;
        foreach($closedTickets as $ticket) {
            $totalHours += $ticket->created_at->diffInHours($ticket->updated_at);
        }

        $this->kpiAvgResolutionHrs = $closedTickets->count() > 0 ? round($totalHours / $closedTickets->count(), 1) : 0;
    }
    #[On('echo-private:admin.tickets,.TicketMessageSent')]
    public function receiveMessage($event)
    {
        # \Illuminate\Support\Facades\Log::info('WS Triggered: Admin receiveMessage', ['payload' => $event, 'activeTicketId' => $this->activeTicketId]);

        if (isset($event['message']['support_ticket_id'])) {
            $ticketId = $event['message']['support_ticket_id'];
            if ((string) $this->activeTicketId === (string) $ticketId) {
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

        $ticket = SupportTicketModel::with('customer')->findOrFail($this->activeTicketId);

        $attachmentPaths = [];
        foreach ($this->replyAttachments as $photo) {
            $attachmentPaths[] = $photo->store('support/tickets/attachments', 'public');
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

        try {
            // Versuchen das Event über Websockets zu schießen
            broadcast(new TicketMessageSent($message, $ticket->id, $ticket->customer_id));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Websocket Server Error (Reverb/Pusher offline?): ' . $e->getMessage());
        }

        // Präzise Prüfung: Ist der Nutzer aktuell wirklich online? (1-Minuten-Zeitfenster durch UserLastActivity Middleware)
        $isOnline = \Illuminate\Support\Facades\Cache::has('is_online' . $ticket->customer_id);

        if (!$isOnline && $ticket->customer) {
            $gamification = \App\Models\Customer\CustomerGamification::where('customer_id', $ticket->customer_id)->first();
            if (!$gamification || $gamification->ticket_emails_enabled) {
                // Sende die E-Mail als Hintergrund-Job, das blockiert den Admin nicht
                \Illuminate\Support\Facades\Mail::to($ticket->customer->email)->queue(new \App\Mail\SupportTicketUpdateMailToCustomer($ticket->customer, $ticket));
            }
        }

        $this->reset(['replyMessage', 'replyAttachments']);
        $this->dispatch('ticket-message-received');
    }

    public function closeTicket()
    {
        $ticket = SupportTicketModel::findOrFail($this->activeTicketId);
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
        $query = SupportTicketModel::with('customer', 'messages', 'order');

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

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
        $activeSupportTicket = $this->activeTicketId ? SupportTicketModel::with('messages', 'customer', 'order')->find($this->activeTicketId) : null;

        return view('livewire.shop.support.support-ticket', [
            'tickets' => $tickets,
            'activeTicket' => $activeSupportTicket
        ]);
    }
}

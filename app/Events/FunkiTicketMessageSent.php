<?php

namespace App\Events;

use App\Models\FunkiTicketMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FunkiTicketMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $ticketId;
    public $customerId;

    public function __construct(FunkiTicketMessage $message, $ticketId, $customerId)
    {
        $this->message = $message->load(['ticket', 'ticket.customer']);
        $this->ticketId = $ticketId;
        $this->customerId = $customerId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.tickets'),
            new PrivateChannel('customer.' . $this->customerId),
        ];
    }

    // HIER IST DER FIX: Ein sauberer Name ohne "\" oder Ordnerpfade!
    public function broadcastAs(): string
    {
        return 'TicketMessageSent';
    }
}

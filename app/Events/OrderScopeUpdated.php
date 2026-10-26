<?php

namespace App\Events;

use App\Models\Order\OrderOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderScopeUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $orderId;
    public string $orderNumber;
    public bool $isNewOrder;

    public function __construct(?OrderOrder $order = null, bool $isNewOrder = false)
    {
        $this->orderId = (string) ($order?->id ?? '');
        $this->orderNumber = (string) ($order?->order_number ?? '');
        $this->isNewOrder = $isNewOrder;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('shop'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderScopeUpdated';
    }
}

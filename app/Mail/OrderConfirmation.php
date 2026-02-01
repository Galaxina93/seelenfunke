<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Deine Bestellung bei Mein Seelenfunke (#' . $this->order->order_number . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.order_confirmation',
            with: [
                'order' => $this->order,
                // Wir fügen das formatierte Array hinzu, damit das Template darauf zugreifen kann
                'data'  => $this->order->toFormattedArray(),
            ],
        );
    }
}

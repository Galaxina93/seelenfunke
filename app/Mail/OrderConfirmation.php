<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
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
        // WICHTIG: Hier der Pfad zu deiner neuen Datei
        return new Content(
            view: 'global.mails.confirmation',
        );
    }
}

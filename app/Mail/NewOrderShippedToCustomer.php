<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrderShippedToCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Deine Bestellung #' . $this->data['quote_number'] . ' wurde versendet! 🚚',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.new_order_shipped_to_customer',
        );
    }
}

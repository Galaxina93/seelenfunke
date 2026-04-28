<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RevocationProcessedMail extends Mailable
{
    use Queueable, SerializesModels;

    public \App\Models\Order\OrderRevocation $revocation;

    /**
     * Create a new message instance.
     */
    public function __construct(\App\Models\Order\OrderRevocation $revocation)
    {
        $this->revocation = $revocation;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Widerruf erfolgreich bearbeitet — Mein Seelenfunke',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'global.mails.revocation_processed_mail_to_customer',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

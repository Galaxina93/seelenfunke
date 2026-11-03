<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RevocationRejectedMail extends Mailable
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
        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));

        return new Envelope(
            subject: 'Information zu Ihrem Widerruf — ' . $companyName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'global.mails.revocation_rejected_mail_to_customer',
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

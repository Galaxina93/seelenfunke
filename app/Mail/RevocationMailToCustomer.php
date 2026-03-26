<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RevocationMailToCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $revocationData;

    /**
     * Create a new message instance.
     */
    public function __construct(array $revocationData)
    {
        $this->revocationData = $revocationData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Eingangsbestätigung: Ihr Widerruf vom ' . $this->revocationData['timestamp'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'global.mails.revocation_mail_to_customer',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        if (!empty($this->revocationData['attachments'])) {
            foreach ($this->revocationData['attachments'] as $path) {
                $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromStorageDisk('private', $path);
            }
        }
        return $attachments;
    }
}

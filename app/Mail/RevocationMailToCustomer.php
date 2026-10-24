<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RevocationMailToCustomer extends Mailable
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
        $timestamp = str_replace(["\r", "\n"], '', (string) ($this->revocationData['timestamp'] ?? ''));

        return new Envelope(
            subject: 'Eingangsbestätigung: Ihr Widerruf vom ' . $timestamp,
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
        if (!empty($this->revocationData['attachments']) && is_array($this->revocationData['attachments'])) {
            foreach ($this->revocationData['attachments'] as $path) {
                if (!is_string($path) || $path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\')) {
                    continue;
                }
                $normalized = ltrim($path, '/');
                if (!str_starts_with($normalized, 'revocations/')) {
                    continue;
                }
                $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromStorageDisk('local', $normalized);
            }
        }
        return $attachments;
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class CrmOutgoingMailToCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectLine;
    public $bodyHtml;
    public $signatureHtml;
    public $fromEmail;
    public $fromName;
    public $attachmentFiles;

    /**
     * Create a new message instance.
     */
    public function __construct($subjectLine, $bodyHtml, $signatureHtml, $fromEmail, $fromName, $attachmentFiles = [])
    {
        $this->subjectLine = $subjectLine;
        $this->bodyHtml = $bodyHtml;
        $this->signatureHtml = $signatureHtml;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->attachmentFiles = $attachmentFiles;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'global.mails.crm_outgoing_mail_to_customer',
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
        foreach ($this->attachmentFiles as $file) {
            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromStorage($file['path'])
                ->as($file['filename'])
                ->withMime($file['mime']);
        }
        return $attachments;
    }
}

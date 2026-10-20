<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPhotoIssueMailToCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Wichtige Rückfrage zu deinem Foto für Bestellung #' . ($this->data['order_number'] ?? '') . ' 📸✨',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.order_photo_issue_mail_to_customer',
        );
    }
}

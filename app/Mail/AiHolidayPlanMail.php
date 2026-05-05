<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiHolidayPlanMail extends Mailable
{
    use Queueable, SerializesModels;

    public $messageSubject;
    public $messageContent;
    public $agentName;
    public $attachmentPaths;

    /**
     * Create a new message instance.
     */
    public function __construct(string $messageSubject, string $messageContent, string $agentName = 'Mapi - Leiter Globale Planung', array $attachmentPaths = [])
    {
        $this->messageSubject = $messageSubject;
        $this->messageContent = $messageContent;
        $this->agentName = $agentName;
        $this->attachmentPaths = $attachmentPaths;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->messageSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'global.mails.ai-holiday-plan',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $atts = [];
        foreach ($this->attachmentPaths as $path) {
            $atts[] = \Illuminate\Mail\Mailables\Attachment::fromPath($path);
        }
        return $atts;
    }
}

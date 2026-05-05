<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiMapSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $messageSubject;
    public $messageContent;
    public $agentName;
    public $attachmentPaths;

    public function __construct(string $messageSubject, string $messageContent, string $agentName = 'System', array $attachmentPaths = [])
    {
        $this->messageSubject = $messageSubject;
        $this->messageContent = $messageContent;
        $this->agentName = $agentName;
        $this->attachmentPaths = $attachmentPaths;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->messageSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.ai-map-summary',
        );
    }

    public function attachments(): array
    {
        $atts = [];
        foreach ($this->attachmentPaths as $path) {
            $atts[] = \Illuminate\Mail\Mailables\Attachment::fromPath($path);
        }
        return $atts;
    }
}

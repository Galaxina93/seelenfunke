<?php

namespace App\Services\AI\Mails;

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
    public $design;

    public function __construct(string $messageSubject, string $messageContent, string $agentName = 'System', array $attachmentPaths = [], string $design = 'seelenfunke')
    {
        $this->messageSubject = $messageSubject;
        $this->messageContent = $messageContent;
        $this->agentName = $agentName;
        $this->attachmentPaths = $attachmentPaths;
        $this->design = $design;
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
            view: $this->design === 'generic' ? 'global.mails.ai.ai-map-summary-generic' : 'global.mails.ai.ai-map-summary',
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

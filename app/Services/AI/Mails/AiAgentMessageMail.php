<?php

namespace App\Services\AI\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiAgentMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $messageSubject;
    public $messageContent;
    public $agentName;
    public $attachmentPaths;
    public $design;

    /**
     * Create a new message instance.
     */
    public function __construct(string $messageSubject, string $messageContent, string $agentName = 'System-Agent', array $attachmentPaths = [], string $design = 'seelenfunke')
    {
        $this->messageSubject = $messageSubject;
        $this->messageContent = $messageContent;
        $this->agentName = $agentName;
        $this->attachmentPaths = $attachmentPaths;
        $this->design = $design;
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
            view: $this->design === 'generic' ? 'global.mails.ai.ai-agent-message-generic' : 'global.mails.ai.ai-agent-message',
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

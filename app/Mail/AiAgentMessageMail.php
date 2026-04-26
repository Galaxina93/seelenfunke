<?php

namespace App\Mail;

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

    /**
     * Create a new message instance.
     */
    public function __construct(string $messageSubject, string $messageContent, string $agentName = 'System-Agent')
    {
        $this->messageSubject = $messageSubject;
        $this->messageContent = $messageContent;
        $this->agentName = $agentName;
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
            view: 'global.mails.ai-agent-message',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

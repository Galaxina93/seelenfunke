<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class OrderMailToCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $data;
    protected ?string $pdfPath;
    public ?string $xmlPath; //

    /**
     * Create a new message instance.
     * Wir machen xmlPath optional (= null), damit alter Code (z.B. QuoteRequests) nicht kaputt geht.
     */
    public function __construct(array $data, ?string $pdfPath = null, ?string $xmlPath = null)
    {
        $this->data = $data;
        $this->pdfPath = $pdfPath;
        $this->xmlPath = $xmlPath;
    }

    public function envelope(): Envelope
    {
        $orderNum = $this->data['quote_number'] ?? 'Unbekannt';
        $owner_name = shop_setting('owner_name', 'Mein Seelenfunke');

        return new Envelope(
            subject: '📦 Deine Bestellung bei ' .  $owner_name . ' (#' . $orderNum . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.new_order_mail_to_customer',
            with: ['data' => $this->data],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        // Die Rechnung als PDF anhängen
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $attachments[] = Attachment::fromPath($this->pdfPath)
                ->as('Rechnung-' . ($this->data['quote_number'] ?? 'Bestellung') . '.pdf')
                ->withMime('application/pdf');
        }

        // 2. [NEU] XML anhängen (falls vorhanden)
        if ($this->xmlPath && file_exists($this->xmlPath)) {
            // Dateiname extrahieren (z.B. RE-2026-1001.xml)
            $filename = basename($this->xmlPath);

            $attachments[] = Attachment::fromPath($this->xmlPath)
                ->as($filename)
                ->withMime('application/xml');
        }

        return $attachments;
    }
}

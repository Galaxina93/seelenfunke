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
    public ?string $xmlPath;
    public array $snapshotPaths;

    /**
     * Create a new message instance.
     * Wir machen xmlPath optional (= null), damit alter Code (z.B. QuoteRequests) nicht kaputt geht.
     */
    public function __construct(array $data, ?string $pdfPath = null, ?string $xmlPath = null, array $snapshotPaths = [])
    {
        $this->data = $data;
        $this->pdfPath = $pdfPath;
        $this->xmlPath = $xmlPath;
        $this->snapshotPaths = $snapshotPaths;
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

        // 2. XML anhängen (falls vorhanden)
        if ($this->xmlPath && file_exists($this->xmlPath)) {
            // Dateiname extrahieren (z.B. RE-2026-1001.xml)
            $filename = basename($this->xmlPath);

            $attachments[] = Attachment::fromPath($this->xmlPath)
                ->as($filename)
                ->withMime('application/xml');
        }

        // 3. Snapshots anhängen
        if (!empty($this->snapshotPaths)) {
            foreach ($this->snapshotPaths as $index => $snapshotPath) {
                if (file_exists($snapshotPath)) {
                    $attachments[] = Attachment::fromPath($snapshotPath)
                        ->as('Bestell-Sicherung-' . ($index + 1) . '.jpg')
                        ->withMime('image/jpeg');
                }
            }
        }

        return $attachments;
    }
}

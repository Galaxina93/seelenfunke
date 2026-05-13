<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class NewOrderMailToCustomer extends Mailable implements ShouldQueue
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
        $owner_name = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));

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

        // 2. XML anhängen (falls vorhanden - wird nur bei gewerblichen Bestellungen erzeugt)
        if ($this->xmlPath && file_exists($this->xmlPath)) {
            $filename = basename($this->xmlPath);
            $attachments[] = Attachment::fromPath($this->xmlPath)
                ->as($filename)
                ->withMime('application/xml');
        }

        // 3. Snapshots anhängen
        if (!empty($this->snapshotPaths)) {
            $isAssoc = array_keys($this->snapshotPaths) !== range(0, count($this->snapshotPaths) - 1);
            
            foreach ($this->snapshotPaths as $key => $snapshotPath) {
                if (file_exists($snapshotPath)) {
                    $filename = $isAssoc ? $key : (($key === 0) ? 'Vorderseite-Sicherung.jpg' : 'Rückseite-Sicherung.jpg');
                    $attachments[] = Attachment::fromPath($snapshotPath)
                        ->as($filename)
                        ->withMime('image/jpeg');
                }
            }
        }

        return $attachments;
    }
}

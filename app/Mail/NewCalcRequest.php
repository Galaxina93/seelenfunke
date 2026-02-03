<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewCalcRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $data;
    protected ?string $pdfPath;

    public function __construct(array $data, ?string $pdfPath = null)
    {
        $this->data = $data;
        $this->pdfPath = $pdfPath;
    }

    public function envelope(): Envelope
    {
        $vorname = $this->data['contact']['vorname'] ?? '';
        $nachname = $this->data['contact']['nachname'] ?? 'Unbekannt';
        $email = $this->data['contact']['email'] ?? 'keine-email';
        $firma = $this->data['contact']['firma'] ?? null;

        $prefix = ($this->data['express'] ?? false) ? '[EXPRESS] ' : '';
        $betreffName = $firma ? "$firma ($nachname)" : "$vorname $nachname";

        return new Envelope(
            from: new Address('kontakt@mein-seelenfunke.de', 'Seelenfunke Website'),
            replyTo: [new Address($email, "$vorname $nachname")],
            subject: $prefix . 'Neue Anfrage: ' . $betreffName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.new_calc_mail_to_admin',
            with: ['data' => $this->data],
        );
    }

    /**
     * Hier werden PDF UND Bilder angehängt
     */
    public function attachments(): array
    {
        $attachments = [];

        // 1. Das Haupt-PDF (Angebot oder Rechnung) anhängen
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($this->pdfPath);
        }

        /*// 2. NEU: Logos der einzelnen Artikel automatisch mitsenden
        // Da wir jetzt das zentrale $data['items'] nutzen:
        if (isset($this->data['items'])) {
            foreach ($this->data['items'] as $item) {
                if (!empty($item['config']['logo_storage_path'])) {
                    $logoPath = storage_path('app/public/' . $item['config']['logo_storage_path']);

                    if (file_exists($logoPath)) {
                        $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($logoPath)
                            ->as('Logo_' . \Illuminate\Support\Str::slug($item['name']) . '.' . pathinfo($logoPath, PATHINFO_EXTENSION));
                    }
                }
            }
        }*/

        return $attachments;
    }
}

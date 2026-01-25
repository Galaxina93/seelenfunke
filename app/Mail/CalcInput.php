<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CalcInput extends Mailable
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
            view: 'global.mails.calculation',
            with: ['data' => $this->data],
        );
    }

    /**
     * Hier werden PDF UND Bilder angehängt
     */
    public function attachments(): array
    {
        $attachments = [];

        // 1. PDF anhängen (Standard)
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $name = $this->data['contact']['nachname'] ?? 'anfrage';
            $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '', Str::slug($name));

            $attachments[] = Attachment::fromPath($this->pdfPath)
                ->as("Kalkulation-{$cleanName}.pdf")
                ->withMime('application/pdf');
        }

        // 2. Logos/Bilder anhängen (ANGESPASST FÜR PRIVATE STORAGE)
        if (!empty($this->data['items'])) {
            foreach ($this->data['items'] as $index => $item) {
                if (!empty($item['config']['logo_storage_path'])) {

                    // NEU: Wir greifen auf den 'local' Storage Pfad zu
                    // storage_path('app/...') greift auf storage/app/... zu
                    $fullPath = storage_path('app/' . $item['config']['logo_storage_path']);

                    if (file_exists($fullPath)) {
                        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
                        $fileName = sprintf('Pos%d_Logo_%s.%s',
                            $index + 1,
                            Str::slug($item['name']),
                            $extension
                        );

                        $attachments[] = Attachment::fromPath($fullPath)->as($fileName);
                    }
                }
            }
        }

        return $attachments;
    }
}

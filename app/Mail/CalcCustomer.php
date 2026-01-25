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

class CalcCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    protected string $pdfPath;

    /**
     * @param array $data - Die übermittelten Formulardaten
     * @param string $pdfPath - Der absolute Pfad zur generierten PDF
     */
    public function __construct(array $data, string $pdfPath)
    {
        $this->data = $data;
        $this->pdfPath = $pdfPath;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
        // Absender ist Mein Seelenfunke
            from: new Address('kontakt@mein-seelenfunke.de', 'Mein Seelenfunke'),
            replyTo: [new Address('kontakt@mein-seelenfunke.de', 'Mein Seelenfunke')],
            subject: 'Ihr persönliches Angebot von Mein Seelenfunke',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.calculation_customer',
            with: ['data' => $this->data],
        );
    }

    public function attachments(): array
    {
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            // Zugriff auf ['contact']['nachname'] statt ['nachname']
            $name = $this->data['contact']['nachname'] ?? 'Angebot';
            $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '', Str::slug($name));

            return [
                Attachment::fromPath($this->pdfPath)
                    ->as("Angebot-MeinSeelenfunke-{$cleanName}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CalcMailToAdmin extends Mailable implements ShouldQueue
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

        $owner_mail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
        $owner_name = shop_setting('owner_name', 'Mein Seelenfunke');

        return new Envelope(
            from: new Address($owner_mail, $owner_name . ' Website'),
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

        return $attachments;
    }
}

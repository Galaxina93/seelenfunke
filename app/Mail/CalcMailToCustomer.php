<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CalcMailToCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $data;
    protected string $pdfPath;

    public function __construct(array $data, string $pdfPath)
    {
        $this->data = $data;
        $this->pdfPath = $pdfPath;
    }

    public function envelope(): Envelope
    {

        $owner_mail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
        $owner_name = shop_setting('owner_name', 'Mein Seelenfunke');

        return new Envelope(
        // Absender ist Mein Seelenfunke
            from: new Address($owner_mail, $owner_name),
            replyTo: [new Address($owner_mail, $owner_name)],
            subject: '🧾 Ihr persönliches Angebot von ' . $owner_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.new_calc_mail_to_customer',
            with: ['data' => $this->data],
        );
    }

    public function attachments(): array
    {
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            // Wir holen den Nachnamen aus unserem neuen zentralen Array
            $lastName = $this->data['contact']['nachname'] ?? 'Kunde';

            // Dateiname säubern (z.B. "Angebot-MeinSeelenfunke-Mustermann.pdf")
            $cleanName = \Illuminate\Support\Str::slug($lastName);

            return [
                \Illuminate\Mail\Mailables\Attachment::fromPath($this->pdfPath)
                    ->as("Angebot-MeinSeelenfunke-{$cleanName}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}

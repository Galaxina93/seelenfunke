<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class NewCalcMailToAdmin extends Mailable
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
        $vorname = str_replace(["\r", "\n"], '', (string) ($this->data['contact']['vorname'] ?? ''));
        $nachname = str_replace(["\r", "\n"], '', (string) ($this->data['contact']['nachname'] ?? 'Unbekannt'));
        $email = trim(str_replace(["\r", "\n"], '', (string) ($this->data['contact']['email'] ?? '')));
        $firma = !empty($this->data['contact']['firma']) ? str_replace(["\r", "\n"], '', (string) $this->data['contact']['firma']) : null;

        $prefix = ($this->data['express'] ?? false) ? '[EXPRESS] ' : '';
        $betreffName = $firma ? "$firma ($nachname)" : trim("$vorname $nachname");

        $owner_mail = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
        $owner_name = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
        $replyTo = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? [new Address($email, trim("$vorname $nachname"))]
            : [];

        return new Envelope(
            from: new Address($owner_mail, $owner_name . ' Website'),
            replyTo: $replyTo,
            subject: '🧾 ' . $prefix . 'Neue Anfrage: ' . $betreffName,
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
        $lastName = $this->data['contact']['nachname'] ?? 'Kunde';
        $firm = $this->data['contact']['firma'] ?? '';
        $namePart = $firm ?: $lastName;
        $cleanName = \Illuminate\Support\Str::slug($namePart) ?: 'Kunde';

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => \Barryvdh\DomPDF\Facade\Pdf::loadView('global.mails.calculation_pdf_template', ['data' => $this->data, 'isPdf' => true])->setOptions(['isRemoteEnabled' => false])->output(), "Angebot_{$cleanName}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}

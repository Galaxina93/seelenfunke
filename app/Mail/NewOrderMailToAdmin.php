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

class NewOrderMailToAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;
    protected ?string $pdfPath;
    public ?string $xmlPath; //
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
        $vorname = str_replace(["\r", "\n"], '', (string) ($this->data['contact']['vorname'] ?? ''));
        $nachname = str_replace(["\r", "\n"], '', (string) ($this->data['contact']['nachname'] ?? 'Unbekannt'));
        $email = trim(str_replace(["\r", "\n"], '', (string) ($this->data['contact']['email'] ?? '')));
        $firma = !empty($this->data['contact']['firma']) ? str_replace(["\r", "\n"], '', (string) $this->data['contact']['firma']) : null;
        $orderNum = str_replace(["\r", "\n"], '', (string) ($this->data['quote_number'] ?? 'Unbekannt'));

        $prefix = ($this->data['express'] ?? false) ? '[EXPRESS] ' : '';
        $betreffName = $firma ? "$firma ($nachname)" : trim("$vorname $nachname");
        $replyTo = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? [new Address($email, trim("$vorname $nachname"))]
            : [];

        return new Envelope(
            from: new Address(shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')), shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'))),
            replyTo: $replyTo,
            // Klarer Betreff für bezahlte Bestellungen
            subject: $prefix . '💰 [NEUE BESTELLUNG] #' . $orderNum . ': ' . $betreffName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.new_order_mail_to_admin',
            with: ['data' => $this->data],
        );
    }

    protected function resolveSafeAttachmentPath(?string $path): ?string
    {
        if (!is_string($path) || $path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }
        if (!file_exists($path) || is_dir($path)) {
            return null;
        }
        $real = realpath($path);
        if ($real === false) {
            return null;
        }
        $allowedRoots = array_values(array_filter([
            realpath(storage_path()),
            realpath(public_path()),
        ]));
        foreach ($allowedRoots as $root) {
            if ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }
        return null;
    }

    public function attachments(): array
    {
        $attachments = [];

        // Die Rechnung als PDF anhängen
        if ($safePdf = $this->resolveSafeAttachmentPath($this->pdfPath)) {
            $attachments[] = Attachment::fromPath($safePdf)
                ->as('Rechnung-' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($this->data['quote_number'] ?? 'Bestellung')) . '.pdf')
                ->withMime('application/pdf');
        }

        // 2. XML anhängen (falls vorhanden)
        if ($safeXml = $this->resolveSafeAttachmentPath($this->xmlPath)) {
            // Dateiname extrahieren (z.B. RE-2026-1001.xml)
            $filename = basename($safeXml);

            $attachments[] = Attachment::fromPath($safeXml)
                ->as($filename)
                ->withMime('application/xml');
        }

        // 3. Snapshots anhängen
        if (!empty($this->snapshotPaths)) {
            $isAssoc = array_keys($this->snapshotPaths) !== range(0, count($this->snapshotPaths) - 1);
            
            foreach ($this->snapshotPaths as $key => $snapshotPath) {
                if ($safeSnap = $this->resolveSafeAttachmentPath(is_string($snapshotPath) ? $snapshotPath : null)) {
                    $filename = $isAssoc ? basename((string) $key) : (($key === 0) ? 'Vorderseite-Sicherung.jpg' : 'Rückseite-Sicherung.jpg');
                    $attachments[] = Attachment::fromPath($safeSnap)
                        ->as($filename)
                        ->withMime('image/jpeg');
                }
            }
        }

        return $attachments;
    }
}

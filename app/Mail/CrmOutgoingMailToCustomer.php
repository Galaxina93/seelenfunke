<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CrmOutgoingMailToCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectLine;
    public $bodyHtml;
    public $signatureHtml;
    public $fromEmail;
    public $fromName;
    public $attachmentFiles;

    /**
     * Create a new message instance.
     */
    public function __construct($subjectLine, $bodyHtml, $signatureHtml, $fromEmail, $fromName, $attachmentFiles = [])
    {
        $this->subjectLine = (string) ($subjectLine ?? '');
        $this->bodyHtml = (string) ($bodyHtml ?? '');
        $this->signatureHtml = $signatureHtml;
        $this->fromEmail = (string) $fromEmail;
        $this->fromName = (string) ($fromName ?: $fromEmail);
        $this->attachmentFiles = is_array($attachmentFiles) ? $attachmentFiles : [];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'global.mails.crm_outgoing_mail_to_customer',
        );
    }

    /**
     * Resolve an attachment storage path safely to a verified readable absolute path on disk.
     * Never returns null/missing streams to Symfony Mime (prevents TextPart null crashes).
     */
    public static function resolveVerifiedAttachmentDiskPath(string $rawPath, ?int $expectedSize = null, ?string $filename = null): ?string
    {
        $path = ltrim($rawPath, '/');
        if (
            $path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || !Str::startsWith($path, ['temp/mail-attachments/', 'leitung/crm/mail-attachments/', 'mail-attachments/', 'crm/', 'leitung/crm/'])
        ) {
            return null;
        }

        foreach (['local', 'public'] as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($path)) {
                    $abs = $disk->path($path);
                    if (is_file($abs) && is_readable($abs) && filesize($abs) > 0) {
                        return $abs;
                    }
                }
            } catch (\Throwable) {
                // ignore and continue fallback
            }
        }

        // Self-healing lookup in permanent mail-attachments if temp file was moved/cleaned
        if ($filename !== null && trim($filename) !== '') {
            $safeBase = basename($filename);
            $crmRoot = storage_path('app/leitung/crm/mail-attachments');
            if (is_dir($crmRoot)) {
                try {
                    $it = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($crmRoot, \FilesystemIterator::SKIP_DOTS)
                    );
                    foreach ($it as $fileInfo) {
                        if (!$fileInfo->isFile()) {
                            continue;
                        }
                        $candName = $fileInfo->getFilename();
                        $sizeMatches = ($expectedSize === null || $expectedSize <= 0 || $fileInfo->getSize() === $expectedSize);
                        if ($sizeMatches && ($candName === $safeBase || str_ends_with($candName, '_' . $safeBase))) {
                            return $fileInfo->getPathname();
                        }
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        return null;
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->attachmentFiles as $file) {
            $path = ltrim((string) ($file['path'] ?? ''), '/');
            $filename = basename((string) ($file['filename'] ?? 'attachment'));
            $expectedSize = isset($file['size']) ? (int) $file['size'] : null;

            $absPath = self::resolveVerifiedAttachmentDiskPath($path, $expectedSize, $filename);
            if ($absPath === null) {
                throw new \RuntimeException("MISSING_ATTACHMENT:{$filename}");
            }

            $attachments[] = Attachment::fromPath($absPath)
                ->as($filename)
                ->withMime((string) ($file['mime'] ?? 'application/octet-stream'));
        }
        return $attachments;
    }
}

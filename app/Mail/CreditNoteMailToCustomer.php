<?php

namespace App\Mail;

use App\Models\Accounting\AccountingInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CreditNoteMailToCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public AccountingInvoice $invoice;

    /**
     * Create a new message instance.
     */
    public function __construct(AccountingInvoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Gutschrift / Storno zu ' . $this->invoice->invoice_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'global.mails.credit_note_mail_to_customer',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if ($this->invoice->has_archived_pdf) {
            return [
                Attachment::fromStorageDisk('local', $this->invoice->pdf_storage_path)
                    ->as($this->invoice->invoice_number . '.pdf')
                    ->withMime('application/pdf'),
            ];
        }

        return [
            Attachment::fromData(function () {
                $service = app(\App\Services\InvoiceService::class);
                return $service->generatePdf($this->invoice)->output();
            }, $this->invoice->invoice_number . '.pdf')
            ->withMime('application/pdf'),
        ];
    }
}

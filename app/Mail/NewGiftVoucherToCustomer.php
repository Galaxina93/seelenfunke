<?php

namespace App\Mail;

use App\Models\Marketing\MarketingGiftVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewGiftVoucherToCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public MarketingGiftVoucher $voucher;

    /**
     * Create a new message instance.
     */
    public function __construct(MarketingGiftVoucher $voucher)
    {
        $this->voucher = $voucher;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $senderName = shop_setting('company_name', 'Mein Seelenfunke');
        return new Envelope(
            subject: '✨ Dein Geschenkgutschein von ' . $senderName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'global.mails.new_gift_voucher_to_customer',
            with: [
                'voucher' => $this->voucher,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        // Generate PDF on the fly and attach it
        $pdf = Pdf::loadView('pdf.marketing-gift-voucher', [
            'voucher' => $this->voucher,
        ]);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'Geschenkgutschein-' . $this->voucher->code . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

<?php

namespace App\Mail;

use App\Models\Marketing\MarketingGiftVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewGiftVoucherToCustomer extends Mailable
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
        $senderName = str_replace(["\r", "\n"], '', (string) shop_setting('company_name', 'Mein Seelenfunke'));
        return new Envelope(
            subject: '✨ Dein Geschenkgutschein von ' . $senderName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $downloadUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'customer.gift-voucher.download',
            now()->addDays(7),
            ['voucher' => $this->voucher->id]
        );

        return new Content(
            view: 'global.mails.new_gift_voucher_to_customer',
            with: [
                'voucher' => $this->voucher,
                'downloadUrl' => $downloadUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        // Generate PDF on the fly and attach it (disable remote asset execution to prevent SSRF)
        $pdf = Pdf::loadView('pdf.marketing-gift-voucher', [
            'voucher' => $this->voucher,
        ])->setOptions(['isRemoteEnabled' => false]);

        $safeCode = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $this->voucher->code) ?: 'Gutschein';

        return [
            Attachment::fromData(fn () => $pdf->output(), 'Geschenkgutschein-' . $safeCode . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

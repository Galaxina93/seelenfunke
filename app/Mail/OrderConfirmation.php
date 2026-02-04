<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;
    protected ?string $pdfPath;

    /**
     * @param Order $order
     * @param string|null $pdfPath - Pfad zur generierten Rechnung für den Kunden
     */
    public function __construct(Order $order, ?string $pdfPath = null)
    {
        $this->order = $order;
        $this->pdfPath = $pdfPath;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Deine Bestellung bei Mein Seelenfunke (#' . $this->order->order_number . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.new_order_mail_to_customer',
            with: [
                'order' => $this->order,
                'data'  => $this->order->toFormattedArray(),
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        // Die Rechnung als PDF für den Kunden anhängen
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $attachments[] = Attachment::fromPath($this->pdfPath)
                ->as('Rechnung-' . $this->order->order_number . '.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}

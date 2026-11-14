<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use App\Models\Cart\Cart;

class AbandonedCartReminder extends Mailable
{
    use Queueable, SerializesModels;

    public Cart $cart;
    public string $cartLink;
    public ?string $unsubscribeUrl = null;
    public Collection $activeItems;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
        
        // Ensure cart is loaded with customer profile and items for the view
        $this->cart->loadMissing(['customer.profile', 'items.product']);

        $this->activeItems = $this->cart->items
            ->filter(fn ($item) => $item->product && $item->product->status === 'active')
            ->values();
        
        // Nahtloser Rückkehr-Link (leitet bei abgelaufener Browser-Session freundlich über den Login direkt in den Warenkorb)
        $this->cartLink = $cart->customer_id
            ? route('cart.reminder.resume', [
                'id' => $cart->id,
                'hash' => $cart->getReminderResumeHash(),
            ])
            : route('cart');

        if ($cart->customer) {
            $this->unsubscribeUrl = route('cart.reminder.unsubscribe', [
                'id' => $cart->customer->id,
                'hash' => $cart->customer->getCartReminderUnsubscribeHash(),
            ]);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Deine Auswahl bei Mein Seelenfunke ist noch für dich gespeichert ✨',
        );
    }

    public function headers(): Headers
    {
        $textHeaders = [];
        if (!empty($this->unsubscribeUrl)) {
            $textHeaders['List-Unsubscribe'] = '<' . $this->unsubscribeUrl . '>';
        }

        return new Headers(
            text: $textHeaders,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.order_shopping_cart_reminder_to_customer',
            with: [
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'activeItems' => $this->activeItems,
            ],
        );
    }
}

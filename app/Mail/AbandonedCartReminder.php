<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Cart\Cart;

class AbandonedCartReminder extends Mailable
{
    use Queueable, SerializesModels;

    public Cart $cart;
    public string $cartLink;
    public ?string $unsubscribeUrl = null;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
        
        // Ensure cart is loaded with customer profile and items for the view
        $this->cart->loadMissing(['customer.profile', 'items.product']);
        
        // Link directly to the cart
        $this->cartLink = route('cart');

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
            subject: 'Du hast da etwas vergessen! 🛒',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.order_shopping_cart_reminder_to_customer',
            with: [
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }
}

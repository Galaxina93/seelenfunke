<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Cart\Cart;

class AbandonedCartReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Cart $cart;
    public string $cartLink;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
        
        // Ensure cart is loaded with customer for the view to use
        $this->cart->loadMissing('customer');
        
        // Link directly to the cart if they are logged in or just to the cart page
        $this->cartLink = config('app.url') . '/cart';
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
        );
    }
}

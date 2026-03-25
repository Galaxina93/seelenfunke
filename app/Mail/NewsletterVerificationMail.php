<?php

namespace App\Mail;

use App\Models\Marketing\Newsletter\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $subscriber;

    public function __construct(NewsletterSubscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    public function build()
    {
        return $this->subject('Bitte bestätige deine Anmeldung – Mein Seelenfunke')
            ->view('global.mails.newsletter.verification');
    }
}

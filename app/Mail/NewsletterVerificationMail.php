<?php

namespace App\Mail;

use App\Models\Marketing\MarketingNewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $subscriber;

    public function __construct(MarketingNewsletterSubscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    public function build()
    {
        return $this->subject('Bitte bestätige deine Anmeldung bei Mein-Seelenfunke.')
            ->view('global.mails.newsletter.verification_mail_to_customer');
    }
}

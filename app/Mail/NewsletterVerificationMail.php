<?php

namespace App\Mail;

use App\Models\Marketing\MarketingNewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subscriber;

    public function __construct(MarketingNewsletterSubscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    public function build()
    {
        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));

        return $this->subject('Bitte bestätige deine Anmeldung bei ' . $companyName . '.')
            ->view('global.mails.newsletter.verification_mail_to_customer');
    }
}

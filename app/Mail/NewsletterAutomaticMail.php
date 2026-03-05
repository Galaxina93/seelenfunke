<?php

namespace App\Mail;

use App\Models\Newsletter\Newsletter;
use App\Models\Newsletter\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class NewsletterAutomaticMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public $template;
    public $subscriber;

    public function __construct(Newsletter $template, NewsletterSubscriber $subscriber)
    {
        $this->template = $template;
        $this->subscriber = $subscriber;
    }

    public function build()
    {
        // Name ermitteln (Datenbank oder Fallback)
        $nameToUse = !empty($this->subscriber->first_name)
            ? $this->subscriber->first_name
            : 'du';

        // Platzhalter ersetzen (Unterstützt alte und neue Tag-Syntax)
        $contentReplaced = str_replace(
            ['{first_name}', '{NAME}', '{year}', '{URL}', '{UNSUBSCRIBE_LINK}'],
            [$nameToUse, $nameToUse, date('Y'), url('/'), url('/newsletter')],
            $this->template->content
        );

        return $this->subject($this->template->subject)
            ->view('global.mails.newsletter.new_newsletter_test_mail_to_admin')
            ->with(['content' => $contentReplaced]);
    }
}

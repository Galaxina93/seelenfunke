<?php

namespace App\Mail;

use App\Models\FunkiNewsletter;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class AutomaticNewsletterMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public $template;
    public $subscriber;

    public function __construct(FunkiNewsletter $template, NewsletterSubscriber $subscriber)
    {
        $this->template = $template;
        $this->subscriber = $subscriber;
    }

    public function build()
    {
        // Name ermitteln (Datenbank oder Fallback)
        $nameToUse = !empty($this->subscriber->first_name)
            ? $this->subscriber->first_name
            : 'du'; // Fallback für "Hallo {first_name}" -> "Hallo du"

        $content = str_replace('{first_name}', $nameToUse, $this->template->content);
        $content = str_replace('{year}', date('Y'), $content);

        return $this->subject($this->template->subject)
            ->view('global.mails.newsletter.default')
            ->with(['content' => $content]);
    }
}

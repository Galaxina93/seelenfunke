<?php

namespace App\Mail;

use App\Models\NewsletterTemplate;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public $template;
    public $subscriber;

    public function __construct(NewsletterTemplate $template, NewsletterSubscriber $subscriber)
    {
        $this->template = $template;
        $this->subscriber = $subscriber;
    }

    public function build()
    {
        // 1. Echten Namen ermitteln oder Fallback setzen
        // Wenn first_name existiert und nicht leer ist -> nimm Namen
        // Sonst -> nimm "Kunde" (damit im Text z.B. "Hallo Kunde" steht statt "Hallo ,")
        $nameToUse = !empty($this->subscriber->first_name)
            ? $this->subscriber->first_name
            : 'Kunde';

        // 2. Platzhalter im Content ersetzen
        $content = str_replace('{first_name}', $nameToUse, $this->template->content);

        return $this->subject($this->template->subject)
            ->view('global.mails.newsletter.default')
            ->with(['content' => $content]);
    }
}

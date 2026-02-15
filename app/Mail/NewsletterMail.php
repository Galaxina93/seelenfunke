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
        // Platzhalter ersetzen
        $content = str_replace('{first_name}', 'Lieber Kunde', $this->template->content);
        // (In einem echten System würdest du hier den echten Namen nehmen, falls vorhanden)

        return $this->subject($this->template->subject)
            ->view('emails.newsletter.default')
            ->with(['content' => $content]);
    }
}

<?php

namespace App\Mail;

use App\Models\Marketing\MarketingNewsletter;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class NewsletterAutomaticMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public $template;
    public $subscriber;
    public $unsubscribeUrl;

    public function __construct(MarketingNewsletter $template, MarketingNewsletterSubscriber $subscriber)
    {
        $this->template = $template;
        $this->subscriber = $subscriber;
        $this->unsubscribeUrl = url('/newsletter?tab=unsubscribe&type=newsletter' . (!empty($subscriber->email) ? '&email=' . urlencode($subscriber->email) : ''));
    }

    public function headers(): \Illuminate\Mail\Mailables\Headers
    {
        $unsubUrl = $this->unsubscribeUrl ?: url('/newsletter?tab=unsubscribe&type=newsletter' . (!empty($this->subscriber->email) ? '&email=' . urlencode($this->subscriber->email) : ''));

        return new \Illuminate\Mail\Mailables\Headers(
            text: [
                'List-Unsubscribe' => '<' . $unsubUrl . '>',
            ],
        );
    }

    public function build()
    {
        if (empty($this->unsubscribeUrl)) {
            $this->unsubscribeUrl = url('/newsletter?tab=unsubscribe&type=newsletter' . (!empty($this->subscriber->email) ? '&email=' . urlencode($this->subscriber->email) : ''));
        }

        // Name ermitteln (Datenbank oder Kundenkonto-Fallback) und gegen HTML-Injection escapen
        $rawFirstName = $this->subscriber->first_name ?? null;
        if (empty($rawFirstName) && !empty($this->subscriber->email)) {
            $rawFirstName = \App\Models\Customer\Customer::where('email', $this->subscriber->email)->value('first_name');
        }
        $nameToUse = !empty($rawFirstName)
            ? e((string) $rawFirstName)
            : 'du';

        $safeTemplateContent = \App\Livewire\Shop\Marketing\MarketingBlog::sanitizeBlogHtml((string) ($this->template->content ?? ''));

        // Platzhalter ersetzen (Unterstützt alte und neue Tag-Syntax)
        $contentReplaced = str_replace(
            ['{first_name}', '{NAME}', '{year}', '{URL}', '{UNSUBSCRIBE_LINK}'],
            [$nameToUse, $nameToUse, date('Y'), url('/'), $this->unsubscribeUrl],
            $safeTemplateContent
        );

        $safeSubject = str_replace(["\r", "\n"], ' ', trim((string) ($this->template->subject ?? 'Newsletter')));

        return $this->subject($safeSubject)
            ->view('global.mails.newsletter.new_newsletter_test_mail_to_admin')
            ->with([
                'content' => $contentReplaced,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ]);
    }
}

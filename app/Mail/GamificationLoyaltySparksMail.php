<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GamificationLoyaltySparksMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $headline;
    public string $firstName;
    public string $introText;
    public string $badgeText;
    public int $awardedSparks;
    public int $newBalance;
    public int $funkiLevel;
    public ?string $nextVoucherHint;
    public string $dashboardUrl;
    public string $unsubscribeUrl;
    public string $mailType;

    public function __construct(array $payload)
    {
        $this->subjectLine = (string) ($payload['subject'] ?? '✨ Ein besonderer Seelenfunke für dich!');
        $this->headline = (string) ($payload['headline'] ?? 'Ein besonderer Seelenfunke für dich! ✨');
        $this->firstName = (string) ($payload['first_name'] ?? 'Seelenfunke-Mitglied');
        $this->introText = (string) ($payload['intro_text'] ?? '');
        $this->badgeText = (string) ($payload['badge_text'] ?? 'TREUE-GESCHENK GUTGESCHRIEBEN');
        $this->awardedSparks = (int) ($payload['awarded_sparks'] ?? 50);
        $this->newBalance = (int) ($payload['new_balance'] ?? 50);
        $this->funkiLevel = (int) ($payload['funki_level'] ?? 1);
        $this->nextVoucherHint = $payload['next_voucher_hint'] ?? null;
        $this->dashboardUrl = (string) ($payload['dashboard_url'] ?? url('/customer/dashboard'));
        $this->mailType = (string) ($payload['mail_type'] ?? 'birthday');
        $this->unsubscribeUrl = (string) ($payload['unsubscribe_url'] ?? url('/newsletter?tab=unsubscribe&type=' . $this->mailType));
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('global.mails.gamification_loyalty_mail_to_customer');
    }
}

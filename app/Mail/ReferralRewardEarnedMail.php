<?php

namespace App\Mail;

use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingReferral;
use App\Models\Order\OrderOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferralRewardEarnedMail extends Mailable
{
    use Queueable, SerializesModels;

    public MarketingGiftVoucher $voucher;
    public MarketingReferral $referral;
    public OrderOrder $order;

    public function __construct(MarketingGiftVoucher $voucher, MarketingReferral $referral, OrderOrder $order)
    {
        $this->voucher = $voucher;
        $this->referral = $referral;
        $this->order = $order;
    }

    public function envelope(): Envelope
    {
        $companyName = shop_setting('company_name', 'Mein Seelenfunke');
        return new Envelope(
            subject: '🎁 Juhu! Ein Freund hat über dich bestellt – Deine 5 € Belohnung von ' . $companyName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.referral_reward_earned',
            with: [
                'voucher' => $this->voucher,
                'referral' => $this->referral,
                'order' => $this->order,
            ],
        );
    }
}

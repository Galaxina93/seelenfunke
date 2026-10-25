<?php

namespace App\Models\Marketing;

use App\Models\Accounting\AccountingSpecialIssue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingInfluencerPayout extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'marketing_influencer_payouts';

    protected $fillable = [
        'influencer_id',
        'credit_note_number',
        'payout_method',
        'net_amount_cents',
        'tax_rate',
        'tax_amount_cents',
        'bonus_amount_cents',
        'gross_amount_cents',
        'gift_voucher_id',
        'accounting_special_issue_id',
        'pdf_path',
        'status',
        'requested_at',
        'paid_at',
        'paid_by_admin_id',
        'recipient_snapshot',
    ];

    protected $casts = [
        'net_amount_cents' => 'integer',
        'tax_rate' => 'integer',
        'tax_amount_cents' => 'integer',
        'bonus_amount_cents' => 'integer',
        'gross_amount_cents' => 'integer',
        'requested_at' => 'datetime',
        'paid_at' => 'datetime',
        'recipient_snapshot' => 'array',
    ];

    public function influencer()
    {
        return $this->belongsTo(MarketingInfluencer::class, 'influencer_id')->withTrashed();
    }

    public function commissions()
    {
        return $this->hasMany(MarketingInfluencerCommission::class, 'payout_id');
    }

    public function giftVoucher()
    {
        return $this->belongsTo(MarketingGiftVoucher::class, 'gift_voucher_id');
    }

    public function accountingSpecialIssue()
    {
        return $this->belongsTo(AccountingSpecialIssue::class, 'accounting_special_issue_id');
    }
}

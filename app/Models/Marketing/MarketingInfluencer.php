<?php

namespace App\Models\Marketing;

use App\Models\Employee\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingInfluencer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'marketing_influencers';

    protected $fillable = [
        'employee_id',
        'invitation_label',
        'handle',
        'slug',
        'primary_channel',
        'profile_url',
        'commission_type',
        'commission_value',
        'follower_discount_percent',
        'cookie_days',
        'default_voucher_id',
        'default_tracking_link_id',
        'tax_status',
        'tax_number',
        'self_employed_confirmed',
        'self_employed_confirmed_at',
        'payout_method',
        'account_holder',
        'iban',
        'bic',
        'paypal_email',
        'invitation_token_hash',
        'invitation_token_encrypted',
        'invitation_code_hash',
        'invitation_code_encrypted',
        'invitation_expires_at',
        'invitation_accepted_at',
        'is_active',
        'is_archived',
        'internal_notes',
    ];

    protected $hidden = [
        'iban',
        'invitation_token_hash',
        'invitation_token_encrypted',
        'invitation_code_hash',
        'invitation_code_encrypted',
        'internal_notes',
    ];

    protected $casts = [
        'commission_value' => 'integer',
        'follower_discount_percent' => 'integer',
        'cookie_days' => 'integer',
        'self_employed_confirmed' => 'boolean',
        'self_employed_confirmed_at' => 'datetime',
        'iban' => 'encrypted',
        'invitation_token_encrypted' => 'encrypted',
        'invitation_code_encrypted' => 'encrypted',
        'invitation_expires_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function defaultVoucher()
    {
        return $this->belongsTo(MarketingVoucher::class, 'default_voucher_id');
    }

    public function defaultTrackingLink()
    {
        return $this->belongsTo(MarketingTrackingLink::class, 'default_tracking_link_id');
    }

    public function trackingLinks()
    {
        return $this->hasMany(MarketingTrackingLink::class, 'influencer_id');
    }

    public function vouchers()
    {
        return $this->hasMany(MarketingVoucher::class, 'influencer_id');
    }

    public function commissions()
    {
        return $this->hasMany(MarketingInfluencerCommission::class, 'influencer_id');
    }

    public function payouts()
    {
        return $this->hasMany(MarketingInfluencerPayout::class, 'influencer_id');
    }

    public function getInvitationUrlAttribute(): ?string
    {
        if ($this->invitation_accepted_at !== null || empty($this->invitation_token_encrypted)) {
            return null;
        }

        return route('influencer.invitation', ['token' => $this->invitation_token_encrypted]);
    }

    public function getVerificationCodeAttribute(): ?string
    {
        if ($this->invitation_accepted_at !== null || empty($this->invitation_code_encrypted)) {
            return null;
        }

        return (string) $this->invitation_code_encrypted;
    }

    public function getIsPendingInvitationAttribute(): bool
    {
        return $this->invitation_accepted_at === null && empty($this->employee_id);
    }

    public function getMaskedIbanAttribute(): ?string
    {
        $raw = preg_replace('/\s+/', '', (string) ($this->iban ?? ''));
        if ($raw === '') {
            return null;
        }
        if (strlen($raw) <= 8) {
            return str_repeat('*', max(0, strlen($raw) - 2)) . substr($raw, -2);
        }

        return substr($raw, 0, 4) . ' •••• •••• •••• ' . substr($raw, -4);
    }

    /**
     * Prüft, ob alle steuerlichen und zahlungstechnischen Pflichtangaben für eine Auszahlung vorliegen.
     */
    public function isReadyForPayout(): bool
    {
        if (!$this->self_employed_confirmed || empty(trim((string) $this->tax_number))) {
            return false;
        }

        $profile = $this->employee?->profile;
        if (!$profile || empty(trim((string) $profile->street)) || empty(trim((string) $profile->postal)) || empty(trim((string) $profile->city))) {
            return false;
        }

        if ($this->payout_method === 'bank_transfer') {
            return !empty(trim((string) $this->account_holder)) && !empty(trim((string) $this->iban));
        }

        if ($this->payout_method === 'paypal') {
            return !empty(trim((string) $this->paypal_email)) && filter_var($this->paypal_email, FILTER_VALIDATE_EMAIL) !== false;
        }

        if ($this->payout_method === 'shop_voucher') {
            return true;
        }

        return false;
    }

    /**
     * Erzeugt den offiziellen EPC-QR-Code-Datensatz (GiroCode Standard) für SEPA-Überweisungen per Banking-App.
     */
    public function buildEpcGiroCodePayload(int $amountCents, string $reference): ?string
    {
        $cleanIban = strtoupper(preg_replace('/\s+/', '', (string) ($this->iban ?? '')));
        $holder = trim((string) ($this->account_holder ?: ($this->employee ? $this->employee->first_name . ' ' . $this->employee->last_name : '')));

        if ($cleanIban === '' || $holder === '' || $amountCents <= 0) {
            return null;
        }

        $cleanBic = strtoupper(preg_replace('/\s+/', '', (string) ($this->bic ?? '')));
        $formattedEur = 'EUR' . number_format($amountCents / 100, 2, '.', '');
        $cleanHolder = mb_substr(preg_replace('/[\r\n]+/', ' ', $holder), 0, 70);
        $cleanRef = mb_substr(preg_replace('/[\r\n]+/', ' ', $reference), 0, 140);

        return implode("\n", [
            'BCD',
            '002',
            '1',
            'SCT',
            $cleanBic,
            $cleanHolder,
            $cleanIban,
            $formattedEur,
            '',
            '',
            $cleanRef,
        ]);
    }
}

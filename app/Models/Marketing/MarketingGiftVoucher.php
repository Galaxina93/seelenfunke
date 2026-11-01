<?php

namespace App\Models\Marketing;


use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketingGiftVoucher extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kauf-Wertgutschein (Geschenkgutschein)',
            description: 'Bezahlte Mehrzweck-Wertgutscheine inklusive Restguthaben-Verwaltung und PDF-Download.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.gift-vouchers', 'shop.checkout'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [ExternalChannel::StripePayment, ExternalChannel::DATEV_TaxExport, ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, SoftDeletes;

    protected $table = 'marketing_gift_vouchers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'code', 'order_item_id', 'initial_value', 'current_balance',
        'recipient_name', 'recipient_email', 'personal_message',
        'delivery_method', 'is_active', 'valid_until'
    ];

    protected $casts = [
        'initial_value' => 'integer',
        'current_balance' => 'integer',
        'is_active' => 'boolean',
        'valid_until' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = $model->id ?? (string) Str::uuid());
    }

    /**
     * Check if voucher is valid and has balance.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->current_balance <= 0) return false;

        $now = now();
        if ($this->valid_until && $now->gt($this->valid_until)) return false;

        return true;
    }

    public function orderItem()
    {
        return $this->belongsTo(\App\Models\Order\OrderOrderItem::class, 'order_item_id');
    }

    public function logs()
    {
        return $this->hasMany(MarketingGiftVoucherLog::class, 'gift_voucher_id');
    }

    /**
     * Generiert einen eindeutigen Gutscheincode mit dem Präfix "SEELENFUNKE".
     */
    public static function generateCode(): string
    {
        do {
            $code = 'SEELENFUNKE-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}

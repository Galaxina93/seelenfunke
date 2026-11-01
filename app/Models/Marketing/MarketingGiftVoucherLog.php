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
use Illuminate\Support\Str;

class MarketingGiftVoucherLog extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Wertgutschein-Buchungsprotokoll',
            description: 'Unveränderliches Protokoll jeder Guthaben-Einlösung und Storno-Rückbuchung eines Geschenkgutscheins.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.gift-vouchers'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::GoBD],
            externalChannels: [ExternalChannel::DATEV_TaxExport],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory;

    protected $table = 'marketing_gift_voucher_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'gift_voucher_id', 'order_id', 'amount', 'remaining_balance'
    ];

    protected $casts = [
        'amount' => 'integer',
        'remaining_balance' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = $model->id ?? (string) Str::uuid());
    }

    public function voucher()
    {
        return $this->belongsTo(MarketingGiftVoucher::class, 'gift_voucher_id');
    }

    public function order()
    {
        return $this->belongsTo(\App\Models\Order\OrderOrder::class, 'order_id');
    }
}

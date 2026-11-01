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
use App\Models\Order\OrderOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingInfluencerCommission extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Influencer-Provisionsbuchung',
            description: 'Aus Bestellungen berechnete, freigegebene oder nach Widerruf stornierte Affiliate-Provisionen.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.influencers', 'partner.dashboard'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::PartnerInfluencer, ActorScope::Admin],
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

use HasFactory, HasUuids;

    protected $table = 'marketing_influencer_commissions';

    protected $fillable = [
        'influencer_id',
        'order_id',
        'tracking_link_id',
        'voucher_code',
        'attribution_source',
        'order_number',
        'order_net_amount_cents',
        'commission_rate_snapshot',
        'commission_amount_cents',
        'status',
        'payout_id',
        'approved_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'order_net_amount_cents' => 'integer',
        'commission_rate_snapshot' => 'integer',
        'commission_amount_cents' => 'integer',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function influencer()
    {
        return $this->belongsTo(MarketingInfluencer::class, 'influencer_id')->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(OrderOrder::class, 'order_id')->withTrashed();
    }

    public function trackingLink()
    {
        return $this->belongsTo(MarketingTrackingLink::class, 'tracking_link_id');
    }

    public function payout()
    {
        return $this->belongsTo(MarketingInfluencerPayout::class, 'payout_id');
    }
}

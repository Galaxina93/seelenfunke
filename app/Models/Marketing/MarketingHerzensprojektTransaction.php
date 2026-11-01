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

class MarketingHerzensprojektTransaction extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Herzensprojekt-Spendenbuchung',
            description: 'Auftragsbezogene Buchung und Storno-Korrektur des Spendenbeitrags im Herzensprojekt-Topf.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.herzensprojekt'],
            lifecycle: LifecycleBehavior::ImmutableAfterFinalized,
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

use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(OrderOrder::class, 'order_id');
    }

    public function action()
    {
        return $this->belongsTo(MarketingHerzensprojektAction::class, 'action_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->amount_cents >= 0 ? '+' : '';
        return $sign . number_format($this->amount_cents / 100, 2, ',', '.') . ' €';
    }
}

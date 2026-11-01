<?php

namespace App\Models\Accounting;


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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingCostItemHistory extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Fixkosten-Änderungshistorie',
            description: 'Lückenlose zeitliche Historie von Preis- und Vertragsanpassungen laufender Betriebskosten.',
            department: SystemDepartment::Accounting,
            navigationNodes: ['admin.accounting.costs'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::GoBD],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $fillable = [
        'accounting_cost_item_id',
        'name',
        'is_business',
        'requires_contract',
        'tax_rate',
        'first_payment_date',
        'last_payment_date',
        'contract_file_path',
        'tags',
        'accounting_group_id',
        'amount',
        'interval_months',
        'description',
        'provider_street',
        'provider_house_number',
        'provider_zip',
        'provider_city',
        'provider_phone',
        'provider_email',
        'provider_website',
        'contract_number',
        'notice_period',
        'contract_end_date',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interval_months' => 'integer',
        'first_payment_date' => 'date',
        'last_payment_date' => 'date',
        'contract_end_date' => 'date',
        'is_business' => 'boolean',
        'requires_contract' => 'boolean',
        'tags' => 'array',
    ];

    public function costItem(): BelongsTo
    {
        return $this->belongsTo(AccountingCostItem::class, 'accounting_cost_item_id');
    }
}

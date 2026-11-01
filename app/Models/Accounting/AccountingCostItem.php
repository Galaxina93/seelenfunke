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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingCostItem extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Fixkosten- & Vertragsposition',
            description: 'Wiederkehrende betriebliche Fixkosten, Lizenzen, Serververträge und Versicherungen der Manufaktur.',
            department: SystemDepartment::Accounting,
            navigationNodes: ['admin.accounting.costs'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::GoBD],
            externalChannels: [ExternalChannel::DATEV_TaxExport],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasUuids, SoftDeletes;

    protected $fillable = [
        'accounting_group_id',
        'name',
        'description',
        'amount',
        'interval_months',
        'is_business',
        'requires_contract',
        'contract_file_path',
        'tags',
        'tax_rate',
        'first_payment_date',
        'last_payment_date',
        // Provider/Contract Data
        'provider_company',
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
    ];

    protected $casts = [
        'first_payment_date' => 'date:Y-m-d',
        'last_payment_date'  => 'date:Y-m-d',
        'contract_end_date'  => 'date:Y-m-d',
        'amount'             => 'decimal:2',
        'is_business'        => 'boolean',
        'requires_contract'  => 'boolean',
        'tags'               => 'array',
        'tax_rate'           => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountingGroup::class, 'accounting_group_id');
    }

    public function transactions()
    {
        return $this->hasMany(AccountingBankTransaction::class, 'accounting_cost_item_id');
    }

    public function histories()
    {
        return $this->hasMany(AccountingCostItemHistory::class, 'accounting_cost_item_id')->orderBy('created_at', 'desc');
    }
}

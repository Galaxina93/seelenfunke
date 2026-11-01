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

class AccountingBankTransaction extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Bankumsatz & Kontobewegung',
            description: 'Importierte Banktransaktionen zur automatischen Zuordnung von Kundenzahlungen, Erstattungen und Betriebsausgaben.',
            department: SystemDepartment::Accounting,
            navigationNodes: ['admin.accounting.bank', 'admin.accounting.transactions'],
            lifecycle: LifecycleBehavior::ImmutableAfterFinalized,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [ExternalChannel::DATEV_TaxExport],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $fillable = [
        'accounting_bank_account_id',
        'finapi_transaction_id',
        'amount',
        'currency',
        'purpose',
        'counterpart_name',
        'counterpart_iban',
        'transaction_date',
        'value_date',
        'type',
        'is_pending',
        'raw_data',
        'accounting_category_id',
        'accounting_cost_item_id',
        'assigned_by_type',
        'assigned_by_name',
        'is_business',
        'tags',
        'file_paths',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'value_date' => 'datetime',
        'is_pending' => 'boolean',
        'raw_data' => 'array',
        'amount' => 'decimal:2',
        'file_paths' => 'array',
        'is_business' => 'boolean',
        'tags' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(\App\Models\Accounting\AccountingBankAccount::class, 'accounting_bank_account_id');
    }

    public function costItem()
    {
        return $this->belongsTo(\App\Models\Accounting\AccountingCostItem::class, 'accounting_cost_item_id');
    }

    public function accountingCategory()
    {
        return $this->belongsTo(\App\Models\Accounting\AccountingCategory::class, 'accounting_category_id');
    }
}

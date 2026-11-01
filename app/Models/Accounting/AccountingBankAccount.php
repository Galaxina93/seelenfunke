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

class AccountingBankAccount extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Geschäfts-Bankkonto',
            description: 'Stammdaten der verknüpften Geschäfts-Bankkonten (IBAN, BIC, FinTS/Bank-Sync) für den Zahlungsabgleich.',
            department: SystemDepartment::Accounting,
            navigationNodes: ['admin.accounting.bank'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [ExternalChannel::DATEV_TaxExport],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

// Diese Zeile erlaubt Laravel, unsere finAPI-Daten in die Datenbank zu schreiben!
    protected $fillable = [
        'admin_id',
        'plaid_item_id',
        'plaid_access_token',
        'plaid_account_id',
        'bank_name',
        'account_name',
        'iban',
        'is_active_for_analysis',
        'is_business',
        'balance',
        'currency',
        'last_synced_at'
    ];

    protected $hidden = [
        'plaid_access_token',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_active_for_analysis' => 'boolean',
        'is_business' => 'boolean',
    ];
}

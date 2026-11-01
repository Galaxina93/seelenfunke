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

class AccountingCategorizationRule extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Buchungs-Kategorisierungsregel',
            description: 'Regelwerk zur automatischen Zuweisung von Bankumsätzen zu Buchhaltungskategorien anhand von Verwendungszweck oder IBAN.',
            department: SystemDepartment::Accounting,
            navigationNodes: ['admin.accounting.rules'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadAndPropose,
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
        'admin_id',
        'search_term',
        'accounting_category_id',
        'accounting_cost_item_id',
        'amount_type',
        'amount_value',
        'priority'
    ];

    /**
     * Checks if a given transaction matches this rule.
     */
    public function matches(AccountingBankTransaction $tx): bool
    {
        // 1. Check amount value if explicitly set
        if ($this->amount_value !== null) {
            $expected = (float) $this->amount_value;
            $actual = (float) $tx->amount;
            // Match exactly or absolute value
            if ($expected !== $actual && $expected !== abs($actual)) {
                return false;
            }
        }

        // 2. Normalize and check Search Term
        if (empty($this->search_term)) {
            return false;
        }

        // Escape regex characters but allow spaces/hyphens
        $name = mb_strtolower(trim($tx->counterpart_name ?? ''));
        $purpose = mb_strtolower(trim($tx->purpose ?? ''));
        $searchTerm = mb_strtolower(trim($this->search_term));
        $combinedContext = $name . ' ' . $purpose;

        // Ensure we don't accidentally match 1-2 letter noise words blindly
        if (strlen($searchTerm) < 3) {
            // Require exact match for very short rules like "O2" (to avoid 'Co2' matching)
            return $name === $searchTerm || 
                   preg_match('/\b' . preg_quote($searchTerm, '/') . '\b/i', $combinedContext);
        }

        // Just perform a substring search for EVERY word in the search term.
        // Because "Gewerbesteuer Q1" gets scrubbed to "Gewerbesteuer", and "Kassenzeichen 123" to "Kassenzeichen",
        // the rule is "gewerbesteuer kassenzeichen". But the raw transaction still has "Q1 2026" sitting in between them!
        $keywords = array_filter(explode(' ', $searchTerm));
        foreach ($keywords as $kw) {
            if (!str_contains($combinedContext, $kw)) {
                return false;
            }
        }
        return true;
    }

    public function category()
    {
        return $this->belongsTo(AccountingCategory::class, 'accounting_category_id');
    }

    public function costItem()
    {
        return $this->belongsTo(AccountingCostItem::class, 'accounting_cost_item_id');
    }
}

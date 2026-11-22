<?php

namespace App\Models\Accounting;

use App\Models\Admin\Admin;
use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingInvestmentPlan extends Model implements HasSystemBlueprint
{
    use HasUuids, InteractsWithSystemBlueprint, SoftDeletes;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kalkulatorischer Reinvestitionsplan & Sparziel',
            description: 'Langfristige Investitionsziele (z. B. Ersatz-/Zweit-Laser), monatliche Rücklagen-Raten, § 7g EStG IAB-Vorschau und AfA-Planung.',
            department: SystemDepartment::Accounting,
            navigationNodes: ['financial-analytics', 'financial-liquidity-planning'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::GoBD],
            externalChannels: [],
            dependsOnSettings: ['accounting_payout_config'],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

    protected $guarded = [];

    protected $casts = [
        'target_amount_gross' => 'decimal:2',
        'tax_rate' => 'integer',
        'saved_initial' => 'decimal:2',
        'monthly_rate' => 'decimal:2',
        'profit_percent' => 'decimal:2',
        'start_date' => 'date:Y-m-d',
        'target_date' => 'date:Y-m-d',
        'afa_years' => 'integer',
        'use_iab' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function completedSpecialIssue(): BelongsTo
    {
        return $this->belongsTo(AccountingSpecialIssue::class, 'completed_special_issue_id');
    }

    public function getTargetAmountNetAttribute(): float
    {
        $gross = abs((float) $this->target_amount_gross);
        $rate = max(0, (int) $this->tax_rate);
        if ($rate <= 0) {
            return round($gross, 2);
        }

        return round($gross / (1 + ($rate / 100)), 2);
    }

    public function getTargetVatRefundAttribute(): float
    {
        $gross = abs((float) $this->target_amount_gross);

        return round(max(0.0, $gross - $this->target_amount_net), 2);
    }
}

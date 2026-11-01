<?php

namespace App\Models\Ai;


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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiHealthProtocol extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Gesundheits- & Vitalprotokoll',
            description: 'Tägliche Vital- und Gesundheitsaufzeichnungen im geschützten persönlichen Bereich (Art. 9 DSGVO).',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.ai.health'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::SpecialCategoryArt9,
            retention: RetentionPolicy::UntilConsentRevoked,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::DSGVO_Art9_Health, ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'ai_agent_id',
        'ai_health_treatment_plan_id',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\Admin\Admin::class, 'user_id');
    }

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function treatmentPlan()
    {
        return $this->belongsTo(AiHealthTreatmentPlan::class, 'ai_health_treatment_plan_id');
    }
}

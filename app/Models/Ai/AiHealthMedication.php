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

class AiHealthMedication extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Gesundheits-Medikation & Supplement-Plan',
            description: 'Geschützte persönliche Medikations- und Vitalstoffdaten im privaten Management-Modul (Art. 9 DSGVO).',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.ai.health'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::SpecialCategoryArt9,
            retention: RetentionPolicy::UntilConsentRevoked,
            aiAccess: AiAccessPolicy::ReadAndPropose,
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
        'name',
        'description',
        'active_ingredients',
        'dosage',
        'frequency',
        'is_long_term',
        'image_path',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\Admin\Admin::class, 'user_id');
    }

    protected $casts = [
        'is_long_term' => 'boolean',
    ];
}

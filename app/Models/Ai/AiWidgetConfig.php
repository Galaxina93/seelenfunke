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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiWidgetConfig extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: '3D-Funki-Widget-Konfiguration',
            description: 'Steuerungsparameter für das interaktive 3D-Assistenten-Widget im Frontend und Backend.',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.ai.widget'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::EU_AI_Act_Art50],
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
        'volume',
        'continuous_mode',
        'require_wake_word',
        'allow_voice_interruption',
    ];

    protected $casts = [
        'volume' => 'integer',
        'continuous_mode' => 'boolean',
        'require_wake_word' => 'boolean',
        'allow_voice_interruption' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function aiAgent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}

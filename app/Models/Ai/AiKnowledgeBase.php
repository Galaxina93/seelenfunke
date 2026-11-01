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
use Illuminate\Database\Eloquent\Model;

class AiKnowledgeBase extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'KI-Wissensdatenbank (RAG-Eintrag)',
            description: 'Fachwissen, Manufaktur-Anleitungen und FAQ-Einträge für kontextgestützte KI-Antworten.',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.ai.knowledge'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::EU_AI_Act_Art50],
            externalChannels: [ExternalChannel::GoogleGemini_AI],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasUuids;
    protected $fillable = [
        'title',
        'slug',
        'ai_knowledge_base_category_id',
        'content',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(AiKnowledgeBaseCategory::class, 'ai_knowledge_base_category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(AiKnowledgeBaseTag::class, 'ai_knowledge_base_ai_knowledge_base_tag');
    }
}

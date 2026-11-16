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
use Illuminate\Support\Facades\Cache;

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

    public const VISIBILITY_ADMIN_EXECUTIVE = 'admin_executive';
    public const VISIBILITY_INTERNAL_STAFF = 'internal_staff';

    protected $fillable = [
        'title',
        'slug',
        'source_path',
        'content_sha256',
        'ai_knowledge_base_category_id',
        'content',
        'is_published',
        'visibility_scope',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    /**
     * Cache bei Änderungen am Wissensbestand automatisch invalidieren.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            static::flushBrainCache();
        });

        static::deleted(function () {
            static::flushBrainCache();
        });
    }

    /**
     * Invalidiert alle RAG- und Wissensdatenbank-Caches.
     */
    public static function flushBrainCache(): void
    {
        Cache::forget('ai_kb_published_support');
        $ver = (int) Cache::get('ai_brain_search_version', 1);
        Cache::forever('ai_brain_search_version', $ver + 1);
    }

    public function isSyncedReport(): bool
    {
        return !empty($this->source_path);
    }

    public function category()
    {
        return $this->belongsTo(AiKnowledgeBaseCategory::class, 'ai_knowledge_base_category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(AiKnowledgeBaseTag::class, 'ai_knowledge_base_ai_knowledge_base_tag');
    }
}

<?php

namespace App\Models\Product;


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

class ProductNicheCrawlerRun extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Nischen-Scanner-Suchlauf',
            description: 'Ausführungsprotokoll der KI-gestützten Recherche nach neuen gravierbaren Rohlingen.',
            department: SystemDepartment::Product,
            navigationNodes: ['admin.products.niche-crawler'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [],
            externalChannels: [ExternalChannel::GoogleGemini_AI],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $fillable = [
        'admin_id',
        'name',
        'keyword',
        'platform',
        'products_data',
        'ai_recommendation',
        'ai_agent_id'
    ];

    protected $casts = [
        'products_data' => 'array',
    ];
}

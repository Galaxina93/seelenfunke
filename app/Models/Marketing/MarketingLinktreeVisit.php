<?php

namespace App\Models\Marketing;


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

class MarketingLinktreeVisit extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Linktree-Seitenaufruf',
            description: 'Anonymisierte Besucherzählung der Social-Media-Bio-Seite.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.linktree'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::PublicVisitor, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $table = 'marketing_linktree_visits';

    protected $fillable = [
        'ip_hash',
        'referrer',
        'device_type',
    ];
}

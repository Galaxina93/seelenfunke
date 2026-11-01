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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingBlogCategory extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Blog- & Ratgeber-Kategorie',
            description: 'Themenkategorien für das Seelenfunke-Geschenke-Magazin (SEO-Content).',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.blog', 'shop.blog'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PublicCatalog,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasUuids;

    protected $table = 'marketing_blog_categories';

    protected $fillable = ['name', 'slug'];

    /**
     * Beziehung zu den Blog-Posts.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(MarketingBlogPost::class, 'blog_category_id');
    }
}

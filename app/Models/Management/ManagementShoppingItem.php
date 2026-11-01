<?php

namespace App\Models\Management;


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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementShoppingItem extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Manufaktur-Einkaufslistenposition',
            description: 'Kurzfristig nachzubestellende Verbrauchsmaterialien, Verpackungen und Betriebsmittel.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.shopping'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin, ActorScope::EmployeeProduction],
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

    protected $table = 'management_shopping_items';
    protected $fillable = [
        'category_id',
        'name',
        'quantity',
        'status',
        'last_purchased_at',
        'purchase_count'
    ];

    protected $casts = [
        'last_purchased_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ManagementShoppingCategory::class, 'category_id');
    }
}

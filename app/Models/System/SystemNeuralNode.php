<?php

namespace App\Models\System;


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

class SystemNeuralNode extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: '3D-Projekt-Gehirn-Knoten (Neural Node)',
            description: 'Indexierter Architektur-Knotenpunkt (Models, Livewire, Services, Views) inklusive Blueprint-Typenschild und Abhängigkeits-Graph.',
            department: SystemDepartment::System,
            navigationNodes: ['admin.system.neural-analysis'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [],
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
        'id',
        'file_path',
        'name',
        'group_id',
        'dependencies',
        'methods',
        'properties',
        'content_hash',
    ];

    protected $casts = [
        'dependencies' => 'array',
        'methods' => 'array',
        'properties' => 'array',
    ];

    public function getBlueprintDataAttribute(): ?array
    {
        $props = is_array($this->properties) ? $this->properties : [];
        return isset($props['blueprint']) && is_array($props['blueprint']) ? $props['blueprint'] : null;
    }
}

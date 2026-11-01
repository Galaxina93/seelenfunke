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
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementTaskList extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Management-Aufgabenliste',
            description: 'Board-Spalte oder Projektliste zur Strukturierung interner Aufgaben.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.tasks'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
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

    protected $table = 'management_task_lists';

    protected $fillable = [
        'name',
        'icon',
        'color',
        'position',
        'is_archived'
    ];

    /**
     * Alle Aufgaben dieser Liste.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ManagementTask::class, 'task_list_id');
    }

    /**
     * Nur die Hauptaufgaben (ohne Eltern-Task).
     */
    public function rootTasks(): HasMany
    {
        return $this->hasMany(ManagementTask::class, 'task_list_id')->whereNull('parent_id');
    }
}

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
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementTask extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Management-Aufgabe (Task)',
            description: 'Operative Aufgabenkarte im internen Kanban- und Projektmanagement.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.tasks'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
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

    protected $table = 'management_tasks';
    protected $fillable = [
        'task_list_id',
        'parent_id',
        'title',
        'is_completed',
        'position',
        'priority',
        'ai_plan',
        'is_archived',
        'relevant_from',
        'file_paths'
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'relevant_from' => 'datetime',
        'file_paths' => 'array',
    ];

    /**
     * Die Liste, zu der diese Aufgabe gehört.
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ManagementTaskList::class, 'task_list_id');
    }

    /**
     * Alias für $this->list()
     */
    public function taskList(): BelongsTo
    {
        return $this->list();
    }

    /**
     * Falls es ein Schritt ist: Die übergeordnete Aufgabe.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ManagementTask::class, 'parent_id');
    }

    /**
     * Die Schritte (Unteraufgaben) dieser Aufgabe.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(ManagementTask::class, 'parent_id')
            ->orderBy('is_completed', 'asc')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Hilfsmethode: Ist dies eine Hauptaufgabe?
     */
    public function isRootTask(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Hilfsmethode: Ist dies ein Unterschritt?
     */
    public function isSubtask(): bool
    {
        return !is_null($this->parent_id);
    }
}

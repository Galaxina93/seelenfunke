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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementDayRoutineLog extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Tagesroutine-Erledigungsprotokoll',
            description: 'Dokumentiert abgehakte Routineschritte pro Kalendertag.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.routines'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
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

    protected $table = 'management_day_routine_logs';

    protected $fillable = [
        'day_routine_id',
        'date',
        'is_completed',
        'is_sick',
        'completed_steps',
        'completed_at',
        'note',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_completed' => 'boolean',
        'is_sick' => 'boolean',
        'completed_steps' => 'array',
        'completed_at' => 'datetime',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(ManagementDayRoutine::class, 'day_routine_id');
    }
}

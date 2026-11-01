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
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementDayRoutine extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Manufaktur-Tagesroutine',
            description: 'Wiederkehrende Checklisten für den strukturierten Start und Abschluss des Produktionstages.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.routines'],
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

    protected $table = 'management_day_routines';

    protected $fillable = [
        'start_time', 'title', 'message', 'icon', 'type', 'duration_minutes', 'days_of_week', 'is_active'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'is_active' => 'boolean'
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ManagementDayRoutineStep::class, 'day_routine_id')->orderBy('position');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ManagementDayRoutineLog::class, 'day_routine_id');
    }
}

<?php

namespace App\Models\Employee;


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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeTimeEntry extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Arbeitszeit- & Schichterfassung',
            description: 'Erfasste Arbeitszeiten, Pausen, Krankheitstage und Urlaubsanträge in der Manufaktur.',
            department: SystemDepartment::Employee,
            navigationNodes: ['admin.employees.time'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin, ActorScope::EmployeeProduction],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::GoBD],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids;

    protected $table = 'employee_time_entries';

    protected $fillable = [
        'contract_id',
        'employee_id',
        'work_date',
        'started_at',
        'ended_at',
        'break_minutes',
        'net_minutes',
        'entry_type',
        'activity_note',
        'status',
        'arbzg_compliant',
    ];

    protected $casts = [
        'work_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'break_minutes' => 'integer',
        'net_minutes' => 'integer',
        'arbzg_compliant' => 'boolean',
    ];

    public function contract()
    {
        return $this->belongsTo(EmployeeContract::class, 'contract_id')->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function getFormattedHoursAttribute(): string
    {
        $hours = intdiv((int) $this->net_minutes, 60);
        $mins = ((int) $this->net_minutes) % 60;

        return sprintf('%d:%02d Std.', $hours, $mins);
    }

    public function getDecimalHoursAttribute(): float
    {
        return round(((int) $this->net_minutes) / 60, 2);
    }
}

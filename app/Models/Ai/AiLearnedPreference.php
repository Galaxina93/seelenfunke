<?php

namespace App\Models\Ai;

use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiLearnedPreference extends Model implements HasSystemBlueprint
{
    use HasFactory;
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Gelernte Abteilungs-Präferenz & Dauerentscheidung',
            description: 'Flacher SQL-Speicher für einmalig mit Alina geklärte Entscheidungen (z. B. Stamm-Lieferant, Gebindegröße, Buchungskategorie), damit Rückfragen nie doppelt gestellt werden.',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.tickets'],
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

    protected $fillable = [
        'ai_department_id',
        'scope_key',
        'subject_label',
        'rule_summary',
        'structured_value',
        'source_ticket_id',
        'is_active',
    ];

    protected $casts = [
        'structured_value' => 'array',
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(AiDepartment::class, 'ai_department_id');
    }
}

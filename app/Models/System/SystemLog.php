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
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'System-, Sicherheits- & DSGVO-Audit-Log',
            description: 'Zentrales Ereignisprotokoll für Sicherheitsvorfälle, Hintergrundjobs und DSGVO-Einwilligungsnachweise.',
            department: SystemDepartment::System,
            navigationNodes: ['admin.system.logs'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $table = 'system_logs';

    protected $fillable = [
        'ai_agent_id',
        'type',
        'action_id',
        'title',
        'message',
        'status',
        'payload',
        'started_at',
        'finished_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Relation zum ausführenden KI-Agenten
     */
    public function agent()
    {
        return $this->belongsTo(\App\Models\Ai\AiAgent::class, 'ai_agent_id');
    }

    /**
     * Helper um einen Log-Eintrag schnell zu starten
     */
    public static function start($actionId, $title, $type = 'automation', $aiAgentId = null)
    {
        return self::create([
            'ai_agent_id' => $aiAgentId,
            'action_id' => $actionId,
            'title' => $title,
            'type' => $type,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Helper um einen laufenden Log zu beenden
     */
    public function finish($status = 'success', $message = null, $payload = null)
    {
        $this->update([
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
            'finished_at' => now(),
        ]);
    }
}

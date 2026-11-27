<?php

namespace App\Models\Ai;

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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBehaviorProfile extends Model implements HasSystemBlueprint
{
    use HasFactory;
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Aggregiertes KI-Verhaltensprofil',
            description: 'Verdichtetes Analyse-Profil je Agent, Kanal und Zeitfenster mit Zufriedenheits-Index, Trend-Delta, Reibungs-Clustern, Regelvorschlägen und Vorher/Nachher-Wirkungsmessung.',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.ai-analytics', 'admin.ai-agents.editor'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::EU_AI_Act_Art50],
            externalChannels: [ExternalChannel::GoogleGemini_AI],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

    protected $table = 'ai_behavior_profiles';

    protected $fillable = [
        'ai_agent_id',
        'channel',
        'window_days',
        'praise_count',
        'criticism_count',
        'correction_count',
        'satisfaction_score',
        'trend_delta',
        'top_strengths',
        'top_friction_points',
        'pending_rule_proposals',
        'rule_impact_metrics',
        'last_analyzed_at',
    ];

    protected $casts = [
        'window_days' => 'integer',
        'praise_count' => 'integer',
        'criticism_count' => 'integer',
        'correction_count' => 'integer',
        'satisfaction_score' => 'float',
        'trend_delta' => 'float',
        'top_strengths' => 'array',
        'top_friction_points' => 'array',
        'pending_rule_proposals' => 'array',
        'rule_impact_metrics' => 'array',
        'last_analyzed_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}

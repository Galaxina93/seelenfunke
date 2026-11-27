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

class AiBehaviorFeedback extends Model implements HasSystemBlueprint
{
    use HasFactory;
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public const CHANNELS = [
        'web_portal' => 'Web-Portal',
        'live_widget' => 'Live-Widget',
        'android_app' => 'Android-App',
        'android_live_call' => '3D Live-Call',
        'workshop_minipc' => 'Mini-PC',
        'funkenwerk_desktop' => 'Windows-Suite',
    ];

    public const SOURCE_TYPES = [
        'explicit_reaction' => 'Explizite Reaktion',
        'implicit_text_voice' => 'Sprach-/Text-Signal',
        'quality_gate' => 'Qualitäts-Gate',
        'tool_error' => 'Werkzeug-Fehler',
    ];

    public const POLARITIES = [
        'praise' => 'Lob',
        'criticism' => 'Kritik',
        'correction' => 'Korrektur',
    ];

    public const CATEGORIES = [
        'tone_length' => 'Ton & Länge',
        'tool_accuracy' => 'Werkzeug-Treffsicherheit',
        'hallucination_bluff' => 'Faktentreue & Anti-Bluff',
        'voice_pacing' => 'Sprachfluss & Tempo',
        'proactivity' => 'Rückfragen & Proaktivität',
        'domain_competence' => 'Fachkompetenz',
        'navigation_ux' => 'Navigation & UX',
    ];

    public const STATUSES = [
        'new' => 'Neu',
        'reviewed' => 'Geprüft',
        'applied' => 'Als Regel aktiv',
        'dismissed' => 'Verworfen',
    ];

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'KI-Verhaltens-Feedback (Lob & Kritik)',
            description: 'Zentrales Einzel-Signal-Log für explizites und implizites Lob, Kritik, Korrekturen sowie Quality-Gate- und Tool-Signale über alle internen Bedienkanäle.',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.ai-analytics', 'admin.tickets'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
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

    protected $table = 'ai_behavior_feedbacks';

    protected $fillable = [
        'ai_agent_id',
        'ai_department_id',
        'channel',
        'source_type',
        'polarity',
        'category',
        'intensity',
        'session_id',
        'ai_chat_memory_id',
        'executed_tools',
        'latency_ms',
        'environment_tier',
        'user_excerpt',
        'agent_excerpt',
        'reason_note',
        'signal_hash',
        'status',
        'learned_preference_id',
    ];

    protected $casts = [
        'intensity' => 'integer',
        'latency_ms' => 'integer',
        'executed_tools' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(AiDepartment::class, 'ai_department_id');
    }

    public function learnedPreference(): BelongsTo
    {
        return $this->belongsTo(AiLearnedPreference::class, 'learned_preference_id');
    }

    public function chatMemory(): BelongsTo
    {
        return $this->belongsTo(AiChatMemory::class, 'ai_chat_memory_id');
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function polarityLabel(): string
    {
        return self::POLARITIES[$this->polarity] ?? $this->polarity;
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_TYPES[$this->source_type] ?? $this->source_type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

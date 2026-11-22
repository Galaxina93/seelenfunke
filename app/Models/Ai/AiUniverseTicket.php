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

class AiUniverseTicket extends Model implements HasSystemBlueprint
{
    use HasFactory;
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_FOR_ALINA = 'waiting_for_alina';
    public const STATUS_WAITING_FOR_NOTEBOOK = 'waiting_for_notebook';
    public const STATUS_WAITING_FOR_TRESOR = 'waiting_for_tresor';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED = 'archived';

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Zentrales Funkira Universum-Ticket',
            description: 'Zentraler chronologischer Aufgaben-, Entscheidungs-, Einkaufs- und Entwicklungs-Stream über Web, Live-Widget, Android, Funkenwerk und Werkstatt Mini-PC.',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.tickets'],
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

    protected $fillable = [
        'ticket_number',
        'title',
        'status',
        'priority',
        'ticket_type',
        'ai_department_id',
        'assigned_agent_id',
        'assigned_to_antigravity',
        'source_channel',
        'environment_tier',
        'error_fingerprint',
        'occurrence_count',
        'messages_count',
        'compact_context',
        'compacted_at',
        'action_payload',
        'presence_snapshot',
        'last_activity_at',
        'completed_at',
    ];

    protected $casts = [
        'assigned_to_antigravity' => 'boolean',
        'occurrence_count' => 'integer',
        'messages_count' => 'integer',
        'compacted_at' => 'datetime',
        'action_payload' => 'array',
        'presence_snapshot' => 'array',
        'last_activity_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function generateNextTicketNumber(): string
    {
        $latest = static::query()
            ->where('ticket_number', 'like', 'SF-%')
            ->orderByDesc('created_at')
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $nextInt = 1;
        if (is_string($latest) && preg_match('/^SF-(\d+)$/', $latest, $m)) {
            $nextInt = ((int) $m[1]) + 1;
        } else {
            $nextInt = static::query()->count() + 1;
        }

        do {
            $candidate = 'SF-' . str_pad((string) $nextInt, 4, '0', STR_PAD_LEFT);
            $exists = static::query()->where('ticket_number', $candidate)->exists();
            $nextInt++;
        } while ($exists);

        return $candidate;
    }

    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_NEW => 'Neu',
            self::STATUS_IN_PROGRESS => 'In Arbeit',
            self::STATUS_WAITING_FOR_ALINA => 'Wartet auf Alina',
            self::STATUS_WAITING_FOR_NOTEBOOK => 'Wartet auf Notebook',
            self::STATUS_WAITING_FOR_TRESOR => 'Tresor bereit',
            self::STATUS_COMPLETED => 'Erledigt',
            self::STATUS_ARCHIVED => 'Archiviert',
            default => ucfirst($status),
        };
    }

    public static function getChannelLabel(string $channel): string
    {
        return match ($channel) {
            'web_portal' => 'Web-Portal',
            'live_widget' => 'Seiten-Widget',
            'android_app' => 'Android-App',
            'android_live_call' => 'Android Live-Call',
            'funkenwerk_desktop' => 'Funkenwerk PC',
            'workshop_minipc' => 'Werkstatt Mini-PC',
            'system_sentinel' => 'System-Wächter',
            'customer_escalation' => 'Shop-Eskalation',
            default => $channel,
        };
    }

    public function department()
    {
        return $this->belongsTo(AiDepartment::class, 'ai_department_id');
    }

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'assigned_agent_id');
    }

    public function messages()
    {
        return $this->hasMany(AiUniverseTicketMessage::class, 'ticket_id')->orderBy('created_at');
    }
}

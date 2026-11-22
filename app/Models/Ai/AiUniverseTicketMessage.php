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

class AiUniverseTicketMessage extends Model implements HasSystemBlueprint
{
    use HasFactory;
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Universum-Ticket Nachricht & Schrittprotokoll',
            description: 'Chronologische Einzelnachrichten, ausgeführte Werkzeuge, Anhänge und Freigabekarten eines zentralen Universum-Tickets.',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.tickets'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
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
        'ticket_id',
        'sender_role',
        'sender_name',
        'ai_agent_id',
        'channel',
        'content',
        'executed_tools',
        'attachments',
        'action_card',
        'is_compacted',
    ];

    protected $casts = [
        'executed_tools' => 'array',
        'attachments' => 'array',
        'action_card' => 'array',
        'is_compacted' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(AiUniverseTicket::class, 'ticket_id');
    }

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}

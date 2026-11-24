<?php

namespace App\Models\Management;

use App\Models\Admin\Admin;
use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementTaskMessage extends Model implements HasSystemBlueprint
{
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public const TYPE_COMMENT = 'comment';
    public const TYPE_STATUS_CHANGE = 'status_change';
    public const TYPE_ESCALATION_RESOLUTION = 'escalation_resolution';
    public const TYPE_SYSTEM_ESCALATION = 'system_escalation';

    public const EDIT_WINDOW_SECONDS = 300; // 5 Minuten

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Management-Ticket-Nachricht (Task Chat)',
            description: 'Chronologischer Chat-, Status-, Eskalations- und Dateianhangs-Verlauf einer persönlichen Management-Aufgabe mit 5-Minuten-Nachbearbeitung.',
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
            assetColumns: ['attachments'],
        );
    }

    protected $table = 'management_task_messages';

    protected $fillable = [
        'id',
        'management_task_id',
        'admin_id',
        'sender_actor_type',
        'sender_actor_id',
        'sender_name',
        'sender_avatar',
        'message_type',
        'content',
        'attachments',
        'edited_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'edited_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ManagementTask::class, 'management_task_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function isEditable(?Carbon $now = null): bool
    {
        if (!in_array($this->message_type, [self::TYPE_COMMENT, self::TYPE_ESCALATION_RESOLUTION], true)) {
            return false;
        }
        if (!$this->created_at) {
            return false;
        }
        $ref = $now ? $now->copy() : now();
        $elapsed = $this->created_at->diffInSeconds($ref, false);

        return $elapsed <= self::EDIT_WINDOW_SECONDS;
    }

    public function remainingEditSeconds(?Carbon $now = null): int
    {
        if (!$this->isEditable($now) || !$this->created_at) {
            return 0;
        }
        $ref = $now ? $now->copy() : now();
        $elapsed = max(0, (int) $this->created_at->diffInSeconds($ref, false));

        return max(0, self::EDIT_WINDOW_SECONDS - $elapsed);
    }
}

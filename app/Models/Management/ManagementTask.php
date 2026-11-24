<?php

namespace App\Models\Management;

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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class ManagementTask extends Model implements HasSystemBlueprint
{
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public const STATUS_OPEN = 'open';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    // Legacy compatibility constant
    public const STATUS_ESCALATED = 'escalated';

    public const VALID_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_WAITING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
    ];

    public const STALE_HOURS_THRESHOLD = 72;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Management-Aufgabe (Persönliches Ticket)',
            description: 'Persönliche Chef- & Projekt-Aufgabe mit 4-Stufen-Status, Meine-Tickets-Zuordnung, Chat-Chronik, Auto-Eskalation und 2-Wege-Kalender-Zeitblock.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.tasks', 'admin.management.calendar'],
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
            assetColumns: ['file_paths'],
        );
    }

    protected $table = 'management_tasks';

    protected $fillable = [
        'id',
        'task_list_id',
        'parent_id',
        'title',
        'status',
        'is_mine',
        'assignee_type',
        'assignee_id',
        'assignee_name',
        'assignee_avatar',
        'created_by_type',
        'created_by_id',
        'is_completed',
        'position',
        'my_position',
        'priority',
        'ai_plan',
        'is_archived',
        'relevant_from',
        'due_time',
        'duration_minutes',
        'last_activity_at',
        'claimed_at',
        'completed_at',
        'waiting_started_at',
        'waiting_paused_seconds',
        'escalated_at',
        'escalation_reason',
        'file_paths',
        'ep_awarded_flags',
    ];

    protected $casts = [
        'is_mine' => 'boolean',
        'is_completed' => 'boolean',
        'is_archived' => 'boolean',
        'position' => 'integer',
        'my_position' => 'integer',
        'relevant_from' => 'datetime',
        'duration_minutes' => 'integer',
        'last_activity_at' => 'datetime',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
        'waiting_started_at' => 'datetime',
        'waiting_paused_seconds' => 'integer',
        'escalated_at' => 'datetime',
        'file_paths' => 'array',
        'ep_awarded_flags' => 'array',
    ];

    protected $appends = [
        'is_escalated',
        'formatted_due',
        'status_label',
        'assignee_key',
        'assignee_avatar_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $task) {
            if ($task->parent_id !== null) {
                $task->is_mine = false;
                $task->escalated_at = null;
                $task->escalation_reason = null;
            }

            if ($task->is_completed || $task->status === self::STATUS_COMPLETED) {
                $task->is_completed = true;
                $task->status = self::STATUS_COMPLETED;
                $task->escalated_at = null;
                $task->escalation_reason = null;
            } elseif (empty($task->status) || !in_array($task->status, self::VALID_STATUSES, true)) {
                $task->status = self::STATUS_OPEN;
                $task->is_completed = false;
            } else {
                $task->is_completed = false;
            }

            if ($task->is_mine === null) {
                $task->is_mine = false;
            }
            if ($task->due_time !== null) {
                $task->due_time = self::normalizeDueTime($task->due_time);
            }
            if (empty($task->duration_minutes) || (int) $task->duration_minutes <= 0) {
                $task->duration_minutes = 60;
            }
            if (empty($task->last_activity_at)) {
                $task->last_activity_at = now();
            }
        });

        static::saving(function (self $task) {
            if ($task->due_time !== null) {
                $task->due_time = self::normalizeDueTime($task->due_time);
            }

            if ($task->parent_id !== null) {
                $task->is_mine = false;
                $task->escalated_at = null;
                $task->escalation_reason = null;
            }

            if ($task->status === self::STATUS_ESCALATED) {
                $task->status = self::STATUS_OPEN;
                if ($task->parent_id === null) {
                    $task->escalated_at = $task->escalated_at ?: now();
                }
            }

            if (!$task->exists) {
                // New record: saving() fires BEFORE creating() in Eloquent!
                if ($task->is_completed || $task->status === self::STATUS_COMPLETED) {
                    $task->is_completed = true;
                    $task->status = self::STATUS_COMPLETED;
                    $task->escalated_at = null;
                    $task->escalation_reason = null;
                } else {
                    $task->is_completed = false;
                    if (empty($task->status) || !in_array($task->status, self::VALID_STATUSES, true)) {
                        $task->status = self::STATUS_OPEN;
                    }
                }
            } elseif ($task->isDirty('is_completed') && !$task->isDirty('status')) {
                if ($task->is_completed) {
                    $task->status = self::STATUS_COMPLETED;
                    $task->escalated_at = null;
                    $task->escalation_reason = null;
                } elseif ($task->status === self::STATUS_COMPLETED) {
                    $task->status = self::STATUS_OPEN;
                }
                if (!$task->isDirty('last_activity_at')) {
                    $task->last_activity_at = now();
                }
            } elseif ($task->isDirty('status') && !$task->isDirty('is_completed')) {
                if ($task->status === self::STATUS_COMPLETED) {
                    $task->is_completed = true;
                    $task->escalated_at = null;
                    $task->escalation_reason = null;
                } else {
                    $task->is_completed = false;
                }
                if (!$task->isDirty('last_activity_at')) {
                    $task->last_activity_at = now();
                }
            } elseif ($task->isDirty('status') && $task->isDirty('is_completed')) {
                if ($task->is_completed && $task->status !== self::STATUS_COMPLETED) {
                    if (!(bool) $task->getOriginal('is_completed')) {
                        $task->status = self::STATUS_COMPLETED;
                        $task->escalated_at = null;
                        $task->escalation_reason = null;
                    } else {
                        $task->is_completed = false;
                    }
                } elseif (!$task->is_completed && $task->status === self::STATUS_COMPLETED) {
                    if ($task->getOriginal('status') !== self::STATUS_COMPLETED) {
                        $task->is_completed = true;
                        $task->escalated_at = null;
                        $task->escalation_reason = null;
                    } else {
                        $task->status = self::STATUS_OPEN;
                    }
                }
                if (!$task->isDirty('last_activity_at')) {
                    $task->last_activity_at = now();
                }
            } elseif ((bool) $task->is_completed !== ($task->status === self::STATUS_COMPLETED)) {
                // Self-heal pre-existing status <-> is_completed divergence
                $task->status = $task->is_completed ? self::STATUS_COMPLETED : ($task->status ?: self::STATUS_OPEN);
                if ($task->is_completed) {
                    $task->escalated_at = null;
                    $task->escalation_reason = null;
                }
            }
        });

        static::deleting(function (self $task) {
            if ($task->parent_id === null) {
                self::where('parent_id', $task->id)->delete();
            }
            if (Schema::hasTable('management_task_messages')) {
                ManagementTaskMessage::where('management_task_id', $task->id)->delete();
            }
        });
    }

    /**
     * Kanonischer Scope fuer echte Haupt-Tickets (schliesst Checklisten-Teilschritte,
     * archivierte Tickets und archivierte Listen fehlerresistent aus).
     */
    public function scopeRootTickets(Builder $query): Builder
    {
        $query->whereNull('parent_id')
            ->where('is_archived', false);

        if (Schema::hasTable('management_task_lists')) {
            $query->whereDoesntHave('list', fn (Builder $lq) => $lq->where('is_archived', true));
        }

        return $query;
    }

    /**
     * Kanonischer Scope fuer alle aktiven/unerledigten Haupt-Tickets (Single Source of Truth
     * fuer Dashboard, Navigation, Funkira & Aufgaben-Ansicht).
     */
    public function scopeOpenRootTickets(Builder $query): Builder
    {
        return $query->rootTickets()
            ->where('is_completed', false)
            ->where(function (Builder $q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', self::STATUS_COMPLETED);
            });
    }

    public static function countOpenRootTickets(): int
    {
        if (!Schema::hasTable('management_tasks')) {
            return 0;
        }

        return (int) static::query()->openRootTickets()->count();
    }

    public static function countTotalRootTickets(): int
    {
        if (!Schema::hasTable('management_tasks')) {
            return 0;
        }

        return (int) static::query()->rootTickets()->count();
    }

    public static function normalizeStatus(?string $raw): string
    {
        $clean = strtolower(trim((string) $raw));
        return match ($clean) {
            'waiting', 'warten', 'wartend', 'hold', 'on_hold' => self::STATUS_WAITING,
            'in_progress', 'in_arbeit', 'in bearbeitung', 'working', 'active' => self::STATUS_IN_PROGRESS,
            'completed', 'done', 'erledigt', 'closed' => self::STATUS_COMPLETED,
            default => self::STATUS_OPEN,
        };
    }

    public static function statusLabelFor(?string $status): string
    {
        return match (self::normalizeStatus($status)) {
            self::STATUS_WAITING => 'Warten',
            self::STATUS_IN_PROGRESS => 'In Bearbeitung',
            self::STATUS_COMPLETED => 'Erledigt',
            default => 'Offen',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->is_completed) {
            return 'Erledigt';
        }
        return self::statusLabelFor($this->status);
    }

    public function getAssigneeKeyAttribute(): ?string
    {
        if (!empty($this->assignee_type) && !empty($this->assignee_id)) {
            return $this->assignee_type . ':' . $this->assignee_id;
        }

        return null;
    }

    public function getAssigneeAvatarUrlAttribute(): ?string
    {
        return \App\Services\Management\ManagementTaskTicketService::resolveAvatarUrl($this->assignee_avatar);
    }

    public function isAssignedTo(string $actorType, string $actorId): bool
    {
        return (string) $this->assignee_type === $actorType && (string) $this->assignee_id === $actorId;
    }

    public static function normalizeDueTime(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $clean = trim($raw);
        if ($clean === '') {
            return null;
        }
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $clean, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(ManagementTaskList::class, 'task_list_id');
    }

    public function taskList(): BelongsTo
    {
        return $this->list();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ManagementTask::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(ManagementTask::class, 'parent_id')
            ->orderBy('is_completed', 'asc')
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'asc');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ManagementTaskMessage::class, 'management_task_id')
            ->orderBy('created_at', 'asc');
    }

    public function isRootTask(): bool
    {
        return is_null($this->parent_id);
    }

    public function isSubtask(): bool
    {
        return !is_null($this->parent_id);
    }

    public function hasExplicitTime(): bool
    {
        return $this->due_time !== null && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) $this->due_time) === 1;
    }

    public function getCalendarStartDateTime(): ?Carbon
    {
        if (!$this->relevant_from) {
            return null;
        }
        if ($this->hasExplicitTime()) {
            [$h, $m] = array_map('intval', explode(':', (string) $this->due_time));

            return $this->relevant_from->copy()->setTime($h, $m, 0);
        }

        return $this->relevant_from->copy()->startOfDay();
    }

    public function getCalendarEndDateTime(): ?Carbon
    {
        if (!$this->relevant_from) {
            return null;
        }
        if ($this->hasExplicitTime()) {
            $dur = max(15, min(720, (int) ($this->duration_minutes ?: 60)));

            return $this->getCalendarStartDateTime()->copy()->addMinutes($dur);
        }

        return $this->relevant_from->copy()->endOfDay();
    }

    public function getEscalationDeadline(): ?Carbon
    {
        if (!$this->relevant_from) {
            return null;
        }
        if ($this->hasExplicitTime()) {
            return $this->getCalendarStartDateTime();
        }

        return $this->relevant_from->copy()->endOfDay();
    }

    public function formattedDueDisplay(): ?string
    {
        if (!$this->relevant_from) {
            return null;
        }
        $datePart = $this->relevant_from->format('d.m.Y');
        if ($this->hasExplicitTime()) {
            return $datePart . ' · ' . $this->due_time . ' Uhr';
        }

        return $datePart;
    }

    public function getFormattedDueAttribute(): ?string
    {
        return $this->formattedDueDisplay();
    }

    public function evaluateAutoEscalationReason(?Carbon $now = null): ?string
    {
        if ($this->is_completed || $this->status === self::STATUS_COMPLETED || $this->is_archived || $this->isSubtask()) {
            return null;
        }

        $refNow = $now ? $now->copy() : now();

        // 1. Faelligkeitsdatum (+ optionale Uhrzeit) ueberschritten
        $deadline = $this->getEscalationDeadline();
        if ($deadline && $deadline->lt($refNow)) {
            return 'Fälligkeit überschritten (' . ($this->formattedDueDisplay() ?: $deadline->format('d.m.Y')) . ')';
        }

        // 2. Ticket liegt in "Meine Tickets" oder "In Bearbeitung" und hat seit >= 72 Std. kein Update erhalten
        if ($this->is_mine || $this->status === self::STATUS_IN_PROGRESS) {
            $lastAct = $this->last_activity_at ?? $this->updated_at ?? $this->created_at;
            if ($lastAct && $lastAct->lte($refNow->copy()->subHours(self::STALE_HOURS_THRESHOLD))) {
                return 'Seit über 3 Tagen ohne Status-Update in Meine Tickets';
            }
        }

        return null;
    }

    public function getIsEscalatedAttribute(): bool
    {
        if ($this->is_completed || $this->status === self::STATUS_COMPLETED || $this->isSubtask()) {
            return false;
        }

        return $this->escalated_at !== null || $this->evaluateAutoEscalationReason() !== null;
    }
}

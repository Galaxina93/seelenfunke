<?php

namespace App\Livewire\Shop\Management;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Employee\Employee;
use App\Models\Management\ManagementTask as TaskModel;
use App\Models\Management\ManagementTaskList as TaskListModel;
use App\Models\Management\ManagementTaskMessage;
use App\Services\Management\ManagementTaskTicketService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class ManagementTask extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'Leitung';

    #[Locked]
    public bool $embeddedMode = false;

    // Selected List ('all' or UUID of a ManagementTaskList)
    public ?string $activeListId = 'all';

    // Search & Bearbeiter-Filter
    public string $searchQuery = '';
    public string $assigneeFilter = 'all';

    // Collapsible sections inside Ticket-Detail
    public bool $showSubtasksInDetail = false;
    public bool $showInitialPlanInDetail = true;

    // Persisted UI Layout (loaded from SystemCheckConfig)
    public int $leftColWidthPx = 330;
    public int $myTicketsHeightPct = 52;
    public bool $soundMuted = false;

    // Selected Ticket (transforms the right "Offene Tickets" pane into Ticket-Detail & Chat)
    #[Locked]
    public ?string $selectedTaskId = null;
    public string $detailTitle = '';
    public string $detailStatus = TaskModel::STATUS_OPEN;
    public string $detailAssigneeKey = '';
    public string $detailAiPlan = '';
    public ?string $detailRelevantFrom = null;
    public ?string $detailDueTime = null;
    public int $detailDurationMinutes = 60;
    public string $detailPriority = 'niedrig';
    public ?string $detailTaskListId = null;
    public string $newSubtaskTitle = '';

    // Ticket Chat & Multi-Upload
    public string $chatMessage = '';
    public array $chatUploads = [];

    // 5-Minute Chat Entry Editing
    #[Locked]
    public ?string $editingMessageId = null;
    public string $editingMessageContent = '';
    public array $editingMessageKeepPaths = [];
    public array $editingMessageNewUploads = [];

    // Escalation Resolution Form (forced date + chat message)
    public ?string $escalationNewDate = null;
    public ?string $escalationNewTime = null;
    public int $escalationDurationMinutes = 60;
    public string $escalationTargetStatus = TaskModel::STATUS_IN_PROGRESS;
    public string $escalationChatNote = '';
    public ?string $escalationError = null;

    // List creation & editing (Admin only)
    public string $newListName = '';
    public string $newListIcon = 'list-bullet';
    public string $newListColor = '#C5A059';
    public bool $showNewListModal = false;

    #[Locked]
    public ?string $editingListId = null;
    public string $editingListName = '';
    public string $editingListColor = '#C5A059';

    // Ticket creation (inkl. dynamischer Bearbeiter-Auswahl)
    public string $newTaskTitle = '';
    public string $newTaskAssigneeKey = '';
    public string $newTaskPriority = 'niedrig';
    public ?string $newTaskRelevantFrom = null;
    public ?string $newTaskDueTime = null;
    public int $newTaskDurationMinutes = 60;

    // Gamification Operator Profil-Modal
    public bool $profileModalOpen = false;
    public array $profileModalData = [];

    // Legacy / inline editing compatibility
    #[Locked]
    public ?string $editingTaskId = null;
    public string $editingTaskTitle = '';
    public string $editingTaskPriority = 'niedrig';
    public ?string $editingTaskRelevantFrom = null;
    public array $expandedTasks = [];
    public array $newSubtaskTitles = [];
    public array $taskUploads = [];

    protected $listeners = ['refreshComponent' => '$refresh'];

    protected function resolveEmployeeViewer(): ?Employee
    {
        if ($this->embeddedMode && Auth::guard('employee')->check()) {
            $emp = Auth::guard('employee')->user();
            return $emp instanceof Employee ? $emp : null;
        }
        if (Auth::guard('admin')->check() || \App\Services\AI\AiAuthHelper::isAdmin()) {
            return null;
        }
        $emp = Auth::guard('employee')->user();

        return $emp instanceof Employee ? $emp : null;
    }

    protected function resolveViewerActor(): array
    {
        $emp = $this->resolveEmployeeViewer();
        return app(ManagementTaskTicketService::class)->resolveCurrentActor(null, $emp);
    }

    protected function assertViewerAuthorized(): void
    {
        $emp = $this->resolveEmployeeViewer();
        if ($emp === null && (Auth::guard('admin')->check() || \App\Services\AI\AiAuthHelper::isAdmin())) {
            return;
        }

        $emp = $this->resolveEmployeeViewer();
        if (!$emp || !$emp->isInternalEmployee() || !$emp->isAccountActive()) {
            abort(403, 'Zugriff verweigert.');
        }

        $contract = $emp->employmentContract;
        if ($contract && Schema::hasColumn('employee_contracts', 'can_access_tasks') && !$contract->can_access_tasks) {
            abort(403, 'Aufgaben-Bereich ist für diesen Vertrag deaktiviert.');
        }
    }

    protected function assertTaskAccessible(TaskModel $task): void
    {
        $emp = $this->resolveEmployeeViewer();
        if ($emp !== null) {
            $rootTask = $task->parent_id ? (TaskModel::with('list')->find($task->parent_id) ?: $task) : $task;
            if (!app(ManagementTaskTicketService::class)->isTaskVisibleToEmployee($rootTask, $emp)) {
                abort(403, 'Kein Zugriff auf dieses Ticket.');
            }
        }
    }

    public function boot(): void
    {
        if ($this->embeddedMode) {
            $this->assertViewerAuthorized();
        } elseif (Auth::guard('admin')->check() || Auth::guard('employee')->check() || \App\Services\AI\AiAuthHelper::isAdmin()) {
            $this->assertViewerAuthorized();
        }
    }

    public function mount(bool $embeddedMode = false): void
    {
        $this->embeddedMode = $embeddedMode;
        $this->assertViewerAuthorized();
        $this->activeListId = 'all';

        $service = app(ManagementTaskTicketService::class);
        $service->evaluateEscalations();

        $actor = $this->resolveViewerActor();
        $uiCfg = $service->loadUiConfig($actor['id'] ?: null);
        $this->leftColWidthPx = (int) ($uiCfg['left_col_width_px'] ?? 330);
        $this->myTicketsHeightPct = (int) ($uiCfg['my_tickets_height_pct'] ?? 52);
        $this->soundMuted = (bool) ($uiCfg['sound_muted'] ?? false);
    }

    #[Renderless]
    public function saveSplitterLayout(int $leftWidthPx, int $myTicketsHeightPct): void
    {
        $this->leftColWidthPx = max(230, min(620, $leftWidthPx));
        $this->myTicketsHeightPct = max(20, min(82, $myTicketsHeightPct));

        $actor = $this->resolveViewerActor();
        app(ManagementTaskTicketService::class)->saveUiConfig([
            'left_col_width_px' => $this->leftColWidthPx,
            'my_tickets_height_pct' => $this->myTicketsHeightPct,
        ], $actor['id'] ?: null);
    }

    #[Renderless]
    public function saveSoundMuted(bool $muted): void
    {
        $this->soundMuted = $muted;
        $actor = $this->resolveViewerActor();
        app(ManagementTaskTicketService::class)->saveUiConfig([
            'sound_muted' => $muted,
        ], $actor['id'] ?: null);
    }

    #[Renderless]
    public function reorderTickets(array $orderedIds, bool $isMinePool): void
    {
        $emp = $this->resolveEmployeeViewer();
        if ($emp !== null) {
            $service = app(ManagementTaskTicketService::class);
            $orderedIds = array_values(array_filter($orderedIds, function ($id) use ($emp, $service) {
                $t = TaskModel::with('list')->find((string) $id);
                return $t && $service->isTaskVisibleToEmployee($t, $emp);
            }));
        }

        $actor = $this->resolveViewerActor();
        app(ManagementTaskTicketService::class)->reorderTickets(
            $orderedIds,
            $isMinePool,
            (string) ($this->activeListId ?: 'all'),
            $actor['id'] ?: null
        );
    }

    /**
     * Zieht ein Ticket per Drag & Drop in "Meine Tickets" (`$targetIsMine = true`) oder zurueck
     * in "Offene Tickets" (`$targetIsMine = false`), OHNE den Status zu veraendern!
     */
    public function dropTicketIntoPool(string $taskId, bool $targetIsMine, array $orderedIds = []): void
    {
        $task = TaskModel::with('list')->find($taskId);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        $service = app(ManagementTaskTicketService::class);
        $actor = $this->resolveViewerActor();
        $service->moveToMine($task, $targetIsMine, null, $actor);

        if (!empty($orderedIds)) {
            $this->reorderTickets($orderedIds, $targetIsMine);
        }

        $this->dispatch('sf-task-random-sound');
    }

    public function toggleTicketMine(string $taskId): void
    {
        $task = TaskModel::with('list')->find($taskId);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        $service = app(ManagementTaskTicketService::class);
        $actor = $this->resolveViewerActor();
        $currentlyMineForMe = $task->is_mine && (empty($task->assignee_id) || $task->isAssignedTo($actor['type'], $actor['id']));

        $service->moveToMine($task, !$currentlyMineForMe, null, $actor);
        if ($this->selectedTaskId === $task->id) {
            $fresh = $task->fresh();
            $this->detailAssigneeKey = (string) ($fresh->assignee_key ?? '');
        }
        $this->dispatch('sf-task-random-sound');
    }

    public function assignTicketTo(string $taskId, ?string $assigneeKey): void
    {
        $task = TaskModel::with('list')->find($taskId);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        $actor = $this->resolveViewerActor();
        $updated = app(ManagementTaskTicketService::class)->assignTicket($task, $assigneeKey, $actor);
        if ($this->selectedTaskId === $updated->id) {
            $this->detailAssigneeKey = (string) ($updated->assignee_key ?? '');
        }
        $this->dispatch('sf-task-random-sound');
    }

    #[Renderless]
    public function inlineUpdateTitle(string $taskId, string $newTitle): void
    {
        $clean = trim($newTitle);
        if ($clean === '') {
            return;
        }
        $task = TaskModel::with('list')->find($taskId);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        if ($task->title !== $clean) {
            $task->update([
                'title' => mb_substr($clean, 0, 255),
                'last_activity_at' => now(),
            ]);
            if ($this->selectedTaskId === $task->id) {
                $this->detailTitle = $task->title;
            }
        }
    }

    public function inlineUpdateStatus(string $taskId, string $newStatus): void
    {
        $this->moveTicketToStatus($taskId, $newStatus);
    }

    // --- OPERATOR PROFIL-MODAL (EP, RANG, TITEL & BACKGROUNDS) ---

    public function openOperatorProfileModal(?string $actorType = null, ?string $actorId = null, ?string $fallbackName = null): void
    {
        $service = app(ManagementTaskTicketService::class);
        $viewer = $this->resolveViewerActor();

        $type = ($actorType && in_array($actorType, ['admin', 'employee'], true)) ? $actorType : $viewer['type'];
        $id = ($actorId && trim($actorId) !== '') ? trim($actorId) : $viewer['id'];

        $this->profileModalData = $service->getOperatorProfileModalPayload($type, $id, $viewer, $fallbackName);
        $this->profileModalOpen = true;
        $this->dispatch('sf-task-random-sound');
    }

    public function closeOperatorProfileModal(): void
    {
        $this->profileModalOpen = false;
        $this->dispatch('sf-task-random-sound');
    }

    public function equipOperatorTitle(string $titleKey): void
    {
        $service = app(ManagementTaskTicketService::class);
        $viewer = $this->resolveViewerActor();
        $service->equipOperatorCosmetic($viewer['type'], $viewer['id'], $titleKey, null);
        $this->profileModalData = $service->getOperatorProfileModalPayload($viewer['type'], $viewer['id'], $viewer);
        $this->dispatch('sf-task-random-sound');
        $this->dispatch('operator-profile-updated');
    }

    public function equipOperatorBackground(string $backgroundKey): void
    {
        $service = app(ManagementTaskTicketService::class);
        $viewer = $this->resolveViewerActor();
        $service->equipOperatorCosmetic($viewer['type'], $viewer['id'], null, $backgroundKey);
        $this->profileModalData = $service->getOperatorProfileModalPayload($viewer['type'], $viewer['id'], $viewer);
        $this->dispatch('sf-task-random-sound');
        $this->dispatch('operator-profile-updated');
    }

    // --- NAVIGATION & DETAIL VIEW ---

    public function selectList(string $id): void
    {
        $this->activeListId = $id ?: 'all';
        $this->selectedTaskId = null;
        $this->escalationError = null;
        $this->cancelEditMessage();
        $this->cancelEditTask();
        $this->dispatch('task-list-selected');
        $this->dispatch('sf-task-random-sound');
    }

    public function selectTask(?string $id): void
    {
        $this->escalationError = null;
        $this->cancelEditMessage();
        if (!$id) {
            $this->closeDetailPanel();
            return;
        }
        $task = TaskModel::with('list')->find($id);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        $this->selectedTaskId = $task->id;
        $this->loadSelectedTaskFields();
        $this->dispatch('task-ticket-opened');
        $this->dispatch('sf-task-random-sound');
    }

    public function closeDetailPanel(): void
    {
        $this->selectedTaskId = null;
        $this->chatMessage = '';
        $this->chatUploads = [];
        $this->escalationError = null;
        $this->cancelEditMessage();
        $this->dispatch('task-ticket-closed');
        $this->dispatch('sf-task-random-sound');
    }

    protected function loadSelectedTaskFields(): void
    {
        if (!$this->selectedTaskId) {
            return;
        }
        $task = TaskModel::find($this->selectedTaskId);
        if (!$task) {
            $this->selectedTaskId = null;
            return;
        }

        $this->detailTitle = (string) $task->title;
        $this->detailStatus = $task->is_completed ? TaskModel::STATUS_COMPLETED : TaskModel::normalizeStatus($task->status);
        $this->detailAssigneeKey = (string) ($task->assignee_key ?? '');
        $this->detailAiPlan = (string) ($task->ai_plan ?? '');
        $this->showInitialPlanInDetail = trim($this->detailAiPlan) !== '';
        $this->detailRelevantFrom = $task->relevant_from ? $task->relevant_from->format('Y-m-d') : null;
        $this->detailDueTime = $task->due_time;
        $this->detailDurationMinutes = (int) ($task->duration_minutes ?: 60);
        $this->detailPriority = (string) ($task->priority ?? 'niedrig');
        $this->detailTaskListId = $task->task_list_id;

        $this->escalationNewDate = now()->addDay()->format('Y-m-d');
        $this->escalationNewTime = $task->due_time;
        $this->escalationDurationMinutes = (int) ($task->duration_minutes ?: 60);
        $this->escalationTargetStatus = $task->status ?: TaskModel::STATUS_IN_PROGRESS;
        $this->escalationChatNote = '';
    }

    public function updatedDetailStatus(string $value): void
    {
        if ($this->selectedTaskId) {
            $this->moveTicketToStatus($this->selectedTaskId, $value);
        }
    }

    public function updatedDetailAssigneeKey(string $value): void
    {
        if ($this->selectedTaskId) {
            $this->assignTicketTo($this->selectedTaskId, $value);
        }
    }

    public function saveDetailPanel(): void
    {
        if (!$this->selectedTaskId) {
            return;
        }
        $task = TaskModel::with('list')->find($this->selectedTaskId);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        $title = trim($this->detailTitle);
        if ($title === '') {
            return;
        }

        $newRelevant = !empty($this->detailRelevantFrom) ? Carbon::parse($this->detailRelevantFrom)->startOfDay() : null;
        $newDueTime = $newRelevant ? TaskModel::normalizeDueTime($this->detailDueTime) : null;

        if ($task->is_escalated) {
            $oldDateStr = $task->relevant_from ? $task->relevant_from->format('Y-m-d') : null;
            $newDateStr = $newRelevant ? $newRelevant->format('Y-m-d') : null;
            if ($oldDateStr !== $newDateStr || $task->due_time !== $newDueTime) {
                $this->escalationError = 'Eskaliertes Ticket: Bitte nutze das Auflösungsfeld unten (neues Datum + kurzer Status im Chat).';
                $this->detailRelevantFrom = $oldDateStr;
                $this->detailDueTime = $task->due_time;
                $this->dispatch('sf-task-random-sound');
                return;
            }
        }

        $targetListId = $task->task_list_id;
        if ($this->detailTaskListId && $this->resolveEmployeeViewer() === null) {
            $targetListId = $this->detailTaskListId;
        }

        $task->update([
            'title' => $title,
            'ai_plan' => trim($this->detailAiPlan) !== '' ? trim($this->detailAiPlan) : null,
            'relevant_from' => $newRelevant,
            'due_time' => $newDueTime,
            'duration_minutes' => max(15, min(720, (int) $this->detailDurationMinutes)),
            'priority' => in_array($this->detailPriority, ['niedrig', 'mittel', 'hoch'], true) ? $this->detailPriority : 'niedrig',
            'task_list_id' => $targetListId,
            'last_activity_at' => now(),
        ]);

        $this->dispatch('sf-task-random-sound');
    }

    public function clearSchedule(): void
    {
        if (!$this->selectedTaskId) {
            return;
        }
        $task = TaskModel::with('list')->find($this->selectedTaskId);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        if ($task->is_escalated) {
            $this->escalationError = 'Eskaliertes Ticket kann nicht ohne neues Zieldatum und Status-Eintrag im Chat zurückgesetzt werden.';
            $this->dispatch('sf-task-random-sound');
            return;
        }
        $this->detailRelevantFrom = null;
        $this->detailDueTime = null;
        $task->update([
            'relevant_from' => null,
            'due_time' => null,
            'last_activity_at' => now(),
        ]);
        $this->dispatch('sf-task-random-sound');
    }

    public function updatedDetailTitle(): void { $this->saveDetailPanel(); }
    public function updatedDetailAiPlan(): void { $this->saveDetailPanel(); }
    public function updatedDetailRelevantFrom(): void { $this->saveDetailPanel(); }
    public function updatedDetailDueTime(): void { $this->saveDetailPanel(); }
    public function updatedDetailDurationMinutes(): void { $this->saveDetailPanel(); }
    public function updatedDetailPriority(): void { $this->saveDetailPanel(); }
    public function updatedDetailTaskListId(): void { $this->saveDetailPanel(); }

    // --- TICKET STATUS, CHAT, 5-MIN EDIT & ESCALATION ---

    public function moveTicketToStatus(string $taskId, string $targetStatus): void
    {
        $task = TaskModel::with('list')->find($taskId);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        $service = app(ManagementTaskTicketService::class);
        $actor = $this->resolveViewerActor();
        try {
            $updated = $service->changeStatus($task, $targetStatus, null, true, $actor);
            $this->escalationError = null;
            if ($this->selectedTaskId === $updated->id) {
                $this->detailStatus = $updated->status;
                $this->detailAssigneeKey = (string) ($updated->assignee_key ?? '');
            }
            $this->dispatch('sf-task-random-sound');
            $this->dispatch('operator-profile-updated');
        } catch (InvalidArgumentException $e) {
            $this->selectedTaskId = $task->id;
            $this->loadSelectedTaskFields();
            $this->escalationError = $e->getMessage();
            $this->dispatch('task-ticket-opened');
            $this->dispatch('sf-task-random-sound');
        }
    }

    public function sendChatMessage(): void
    {
        if (!$this->selectedTaskId) {
            return;
        }
        $task = TaskModel::with('list')->find($this->selectedTaskId);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        if (!empty($this->chatUploads)) {
            $this->validate([
                'chatUploads.*' => 'file|max:25600|mimes:pdf,jpg,jpeg,png,webp,gif,txt,md,csv,doc,docx,xls,xlsx,zip,mp4',
            ]);
        }

        $service = app(ManagementTaskTicketService::class);
        $actor = $this->resolveViewerActor();
        try {
            $service->postMessage(
                task: $task,
                content: $this->chatMessage,
                files: $this->chatUploads,
                actorOverride: $actor
            );
            $this->chatMessage = '';
            $this->chatUploads = [];
            $this->escalationError = null;
            $this->dispatch('sf-task-random-sound');
            $this->dispatch('task-chat-updated');
            $this->dispatch('operator-profile-updated');
        } catch (InvalidArgumentException $e) {
            $this->escalationError = $e->getMessage();
        }
    }

    public function removePendingChatUpload(int $index): void
    {
        if (isset($this->chatUploads[$index])) {
            unset($this->chatUploads[$index]);
            $this->chatUploads = array_values($this->chatUploads);
        }
    }

    public function startEditMessage(string $messageId): void
    {
        if (!$this->selectedTaskId) {
            return;
        }
        $msg = ManagementTaskMessage::where('management_task_id', $this->selectedTaskId)->find($messageId);
        if (!$msg || !$msg->isEditable()) {
            $this->escalationError = 'Dieser Chateintrag ist älter als 5 Minuten und kann nicht mehr angepasst werden.';
            return;
        }

        $emp = $this->resolveEmployeeViewer();
        if ($emp !== null && ($msg->sender_actor_type !== 'employee' || (string) $msg->sender_actor_id !== (string) $emp->id)) {
            $this->escalationError = 'Du kannst nur deine eigenen Chateinträge anpassen.';
            return;
        }

        $this->editingMessageId = $msg->id;
        $this->editingMessageContent = (string) ($msg->content ?? '');
        $this->editingMessageKeepPaths = collect($msg->attachments ?? [])
            ->pluck('path')
            ->filter()
            ->values()
            ->all();
        $this->editingMessageNewUploads = [];
        $this->escalationError = null;
        $this->dispatch('sf-task-random-sound');
    }

    public function removeAttachmentFromEditingMessage(string $path): void
    {
        $this->editingMessageKeepPaths = array_values(array_filter(
            $this->editingMessageKeepPaths,
            fn ($p) => $p !== $path
        ));
    }

    public function cancelEditMessage(): void
    {
        $this->editingMessageId = null;
        $this->editingMessageContent = '';
        $this->editingMessageKeepPaths = [];
        $this->editingMessageNewUploads = [];
    }

    public function saveEditedMessage(): void
    {
        if (!$this->selectedTaskId || !$this->editingMessageId) {
            return;
        }
        $msg = ManagementTaskMessage::where('management_task_id', $this->selectedTaskId)->find($this->editingMessageId);
        if (!$msg) {
            $this->cancelEditMessage();
            return;
        }

        $emp = $this->resolveEmployeeViewer();
        if ($emp !== null && ($msg->sender_actor_type !== 'employee' || (string) $msg->sender_actor_id !== (string) $emp->id)) {
            $this->cancelEditMessage();
            return;
        }

        if (!empty($this->editingMessageNewUploads)) {
            $this->validate([
                'editingMessageNewUploads.*' => 'file|max:25600|mimes:pdf,jpg,jpeg,png,webp,gif,txt,md,csv,doc,docx,xls,xlsx,zip,mp4',
            ]);
        }

        try {
            app(ManagementTaskTicketService::class)->editMessage(
                $msg,
                $this->editingMessageContent,
                $this->editingMessageNewUploads,
                $this->editingMessageKeepPaths
            );
            $this->cancelEditMessage();
            $this->escalationError = null;
            $this->dispatch('sf-task-random-sound');
        } catch (InvalidArgumentException $e) {
            $this->escalationError = $e->getMessage();
        }
    }

    public function resolveTicketEscalation(): void
    {
        if (!$this->selectedTaskId) {
            return;
        }
        $task = TaskModel::with('list')->find($this->selectedTaskId);
        if (!$task) {
            return;
        }
        $this->assertTaskAccessible($task);

        if (!empty($this->chatUploads)) {
            $this->validate([
                'chatUploads.*' => 'file|max:25600|mimes:pdf,jpg,jpeg,png,webp,gif,txt,md,csv,doc,docx,xls,xlsx,zip,mp4',
            ]);
        }

        $service = app(ManagementTaskTicketService::class);
        $actor = $this->resolveViewerActor();
        try {
            $updated = $service->resolveEscalation(
                task: $task,
                newDueDate: (string) ($this->escalationNewDate ?? ''),
                newDueTime: $this->escalationNewTime,
                chatStatusText: $this->escalationChatNote,
                targetStatus: $this->escalationTargetStatus,
                durationMinutes: $this->escalationDurationMinutes,
                files: $this->chatUploads,
                actorOverride: $actor
            );

            $this->chatUploads = [];
            $this->escalationChatNote = '';
            $this->escalationError = null;
            $this->detailStatus = $updated->status;
            $this->detailAssigneeKey = (string) ($updated->assignee_key ?? '');
            $this->detailRelevantFrom = $updated->relevant_from ? $updated->relevant_from->format('Y-m-d') : null;
            $this->detailDueTime = $updated->due_time;
            $this->detailDurationMinutes = (int) ($updated->duration_minutes ?: 60);

            $this->dispatch('sf-task-random-sound');
            $this->dispatch('task-chat-updated');
            $this->dispatch('operator-profile-updated');
        } catch (InvalidArgumentException $e) {
            $this->escalationError = $e->getMessage();
            $this->dispatch('sf-task-random-sound');
        }
    }

    // --- LIST MANAGEMENT (Admin only) ---

    public function createList(): void
    {
        if ($this->resolveEmployeeViewer() !== null) {
            return;
        }

        $this->validate([
            'newListName' => 'required|min:2|max:50',
        ]);

        $maxPos = (int) TaskListModel::max('position');

        $list = TaskListModel::create([
            'name' => trim($this->newListName),
            'icon' => $this->newListIcon ?: 'list-bullet',
            'color' => $this->newListColor ?: '#C5A059',
            'position' => $maxPos + 1,
            'is_team_visible' => false,
        ]);

        $this->newListName = '';
        $this->newListIcon = 'list-bullet';
        $this->newListColor = '#C5A059';
        $this->showNewListModal = false;
        $this->activeListId = $list->id;
        $this->dispatch('sf-task-random-sound');
    }

    public function toggleListTeamVisible(string $id): void
    {
        if ($this->resolveEmployeeViewer() !== null) {
            return;
        }
        $list = TaskListModel::find($id);
        if ($list && Schema::hasColumn('management_task_lists', 'is_team_visible')) {
            $list->update([
                'is_team_visible' => !$list->is_team_visible,
            ]);
            $this->dispatch('sf-task-random-sound');
        }
    }

    public function startEditList(string $id, string $currentName, ?string $currentColor = null): void
    {
        if ($this->resolveEmployeeViewer() !== null) {
            return;
        }
        $this->editingListId = $id;
        $this->editingListName = $currentName;
        $this->editingListColor = $currentColor ?: '#C5A059';
    }

    public function updateList(): void
    {
        if ($this->resolveEmployeeViewer() !== null) {
            return;
        }
        if (!$this->editingListId || empty(trim($this->editingListName))) {
            $this->cancelEditList();
            return;
        }

        $list = TaskListModel::find($this->editingListId);
        if ($list) {
            $list->update([
                'name' => trim($this->editingListName),
                'color' => $this->editingListColor ?: $list->color,
            ]);
        }

        $this->cancelEditList();
        $this->dispatch('sf-task-random-sound');
    }

    public function cancelEditList(): void
    {
        $this->editingListId = null;
        $this->editingListName = '';
    }

    public function deleteList(string $id): void
    {
        if ($this->resolveEmployeeViewer() !== null) {
            return;
        }
        $list = TaskListModel::find($id);
        if ($list) {
            $service = app(ManagementTaskTicketService::class);
            $tasks = TaskModel::with('messages')->where('task_list_id', $list->id)->get();
            foreach ($tasks as $t) {
                $service->purgeTaskAttachments($t);
                $t->delete();
            }
            $list->delete();

            if ($this->activeListId === $id) {
                $first = TaskListModel::orderBy('position')->first();
                $this->activeListId = $first ? $first->id : 'all';
            }
            $this->dispatch('sf-task-random-sound');
        }
    }

    // --- TASK / TICKET CREATION & SUBTASKS ---

    public function addTask(): void
    {
        if (!$this->activeListId) {
            $this->activeListId = 'all';
        }

        $this->validate([
            'newTaskTitle' => 'required|min:1|max:255',
            'newTaskPriority' => 'required|in:niedrig,mittel,hoch',
            'newTaskRelevantFrom' => 'nullable|date',
            'newTaskDueTime' => 'nullable|string|max:8',
        ]);

        $service = app(ManagementTaskTicketService::class);
        $actor = $this->resolveViewerActor();
        $emp = $this->resolveEmployeeViewer();

        if ($emp !== null) {
            $teamList = null;
            if ($this->activeListId !== 'all') {
                $cand = TaskListModel::where('id', $this->activeListId)->where('is_team_visible', true)->first();
                if ($cand) {
                    $teamList = $cand;
                }
            }
            if (!$teamList) {
                $teamList = TaskListModel::where('is_archived', false)->where('is_team_visible', true)->orderBy('position', 'asc')->first();
            }
            if (!$teamList) {
                $teamList = TaskListModel::firstOrCreate(
                    ['name' => 'Team & Werkstatt'],
                    ['icon' => 'users', 'color' => '#C5A059', 'position' => 99, 'is_team_visible' => true]
                );
            }
            $targetListId = $teamList->id;
        } elseif ($this->activeListId === 'all') {
            $targetList = TaskListModel::where('name', 'Aufgaben')->first()
                ?? TaskListModel::orderBy('position', 'asc')->first();
            if (!$targetList) {
                $targetList = TaskListModel::create([
                    'name' => 'Aufgaben',
                    'icon' => 'home',
                    'color' => '#C5A059',
                    'position' => 1,
                ]);
            }
            $targetListId = $targetList->id;
        } else {
            $targetListId = $this->activeListId;
        }

        $relevantFrom = null;
        $dueTime = TaskModel::normalizeDueTime($this->newTaskDueTime);
        if (!empty($this->newTaskRelevantFrom)) {
            $relevantFrom = Carbon::parse($this->newTaskRelevantFrom)->startOfDay();
        } elseif ($dueTime !== null) {
            $relevantFrom = Carbon::today();
        }

        $resolvedAssignee = $this->newTaskAssigneeKey !== ''
            ? $service->resolveActorByKey($this->newTaskAssigneeKey)
            : ($emp !== null ? $actor : null);

        $maxPos = (int) TaskModel::where('task_list_id', $targetListId)->whereNull('parent_id')->max('position');

        $payload = [
            'task_list_id' => $targetListId,
            'parent_id' => null,
            'title' => trim($this->newTaskTitle),
            'status' => TaskModel::STATUS_OPEN,
            'is_mine' => false,
            'priority' => $this->newTaskPriority,
            'relevant_from' => $relevantFrom,
            'due_time' => $dueTime,
            'duration_minutes' => max(15, min(720, (int) $this->newTaskDurationMinutes)),
            'is_completed' => false,
            'position' => $maxPos + 1,
            'my_position' => 0,
            'last_activity_at' => now(),
            'file_paths' => [],
        ];

        if (Schema::hasColumn('management_tasks', 'assignee_id')) {
            $payload['assignee_type'] = $resolvedAssignee['type'] ?? null;
            $payload['assignee_id'] = $resolvedAssignee['id'] ?? null;
            $payload['assignee_name'] = $resolvedAssignee['name'] ?? null;
            $payload['assignee_avatar'] = $resolvedAssignee['avatar'] ?? null;
            $payload['created_by_type'] = $actor['type'];
            $payload['created_by_id'] = $actor['id'];
        }

        TaskModel::create($payload);

        $this->newTaskTitle = '';
        $this->newTaskAssigneeKey = '';
        $this->newTaskPriority = 'niedrig';
        $this->newTaskRelevantFrom = null;
        $this->newTaskDueTime = null;
        $this->newTaskDurationMinutes = 60;
        $this->dispatch('sf-task-random-sound');
    }

    public function addSubtask(string $parentId): void
    {
        $title = $this->newSubtaskTitles[$parentId] ?? $this->newSubtaskTitle ?? '';
        if (empty(trim($title))) {
            return;
        }

        $parent = TaskModel::with('list')->find($parentId);
        if (!$parent) {
            return;
        }
        $this->assertTaskAccessible($parent);

        $maxPos = (int) TaskModel::where('parent_id', $parentId)->max('position');

        TaskModel::create([
            'task_list_id' => $parent->task_list_id,
            'parent_id' => $parent->id,
            'title' => trim($title),
            'status' => TaskModel::STATUS_OPEN,
            'priority' => 'niedrig',
            'is_completed' => false,
            'position' => $maxPos + 1,
            'file_paths' => [],
        ]);

        $parent->update(['last_activity_at' => now()]);

        $this->newSubtaskTitles[$parentId] = '';
        $this->newSubtaskTitle = '';

        if (!in_array($parentId, $this->expandedTasks, true)) {
            $this->expandedTasks[] = $parentId;
        }
        $this->dispatch('sf-task-random-sound');
    }

    public function toggleExpand(string $taskId): void
    {
        if (in_array($taskId, $this->expandedTasks, true)) {
            $this->expandedTasks = array_diff($this->expandedTasks, [$taskId]);
        } else {
            $this->expandedTasks[] = $taskId;
        }
    }

    public function toggleTask(string $id): void
    {
        $task = TaskModel::with('list')->find($id);
        if ($task) {
            $this->assertTaskAccessible($task);
            $newState = !$task->is_completed;
            if ($task->parent_id === null) {
                app(ManagementTaskTicketService::class)->changeStatus(
                    $task,
                    $newState ? TaskModel::STATUS_COMPLETED : TaskModel::STATUS_OPEN,
                    null,
                    false
                );
            } else {
                $task->update([
                    'is_completed' => $newState,
                    'status' => $newState ? TaskModel::STATUS_COMPLETED : TaskModel::STATUS_OPEN,
                ]);
                TaskModel::where('id', $task->parent_id)->update(['last_activity_at' => now()]);
            }
            $this->dispatch('sf-task-random-sound');
            $this->dispatch('operator-profile-updated');
        }
    }

    public function toggleImportant(string $id): void
    {
        $task = TaskModel::with('list')->find($id);
        if ($task) {
            $this->assertTaskAccessible($task);
            $newPrio = $task->priority === 'hoch' ? 'niedrig' : 'hoch';
            $task->update(['priority' => $newPrio]);
            if ($this->selectedTaskId === $id) {
                $this->detailPriority = $newPrio;
            }
            $this->dispatch('sf-task-random-sound');
        }
    }

    public function startEditTask(string $id): void
    {
        $task = TaskModel::with('list')->find($id);
        if ($task) {
            $this->assertTaskAccessible($task);
            $this->editingTaskId = $task->id;
            $this->editingTaskTitle = $task->title;
            $this->editingTaskPriority = $task->priority ?: 'niedrig';
            $this->editingTaskRelevantFrom = $task->relevant_from ? $task->relevant_from->format('Y-m-d') : null;
        }
    }

    public function updateTask(): void
    {
        if (!$this->editingTaskId) {
            return;
        }
        $task = TaskModel::with('list')->find($this->editingTaskId);
        if ($task && trim($this->editingTaskTitle) !== '') {
            $this->assertTaskAccessible($task);
            $task->update([
                'title' => trim($this->editingTaskTitle),
                'priority' => in_array($this->editingTaskPriority, ['niedrig', 'mittel', 'hoch'], true) ? $this->editingTaskPriority : 'niedrig',
                'relevant_from' => !empty($this->editingTaskRelevantFrom) ? Carbon::parse($this->editingTaskRelevantFrom)->startOfDay() : null,
                'last_activity_at' => now(),
            ]);
        }
        $this->cancelEditTask();
    }

    public function cancelEditTask(): void
    {
        $this->editingTaskId = null;
        $this->editingTaskTitle = '';
        $this->editingTaskPriority = 'niedrig';
        $this->editingTaskRelevantFrom = null;
    }

    public function deleteTask(string $id): void
    {
        $task = TaskModel::with(['messages', 'list'])->find($id);
        if ($task) {
            $this->assertTaskAccessible($task);
            $emp = $this->resolveEmployeeViewer();
            if ($emp !== null && $task->parent_id === null && ($task->created_by_type !== 'employee' || (string) $task->created_by_id !== (string) $emp->id)) {
                $this->escalationError = 'Nur selbst angelegte Tickets können vom Mitarbeiter gelöscht werden.';
                return;
            }

            $service = app(ManagementTaskTicketService::class);
            $subs = TaskModel::with('messages')->where('parent_id', $task->id)->get();
            foreach ($subs as $sub) {
                $service->purgeTaskAttachments($sub);
                $sub->delete();
            }
            $service->purgeTaskAttachments($task);
            $task->delete();

            if ($this->selectedTaskId === $id) {
                $this->selectedTaskId = null;
            }
            $this->dispatch('sf-task-random-sound');
        }
    }

    public function downloadFile(string $taskId, int $index)
    {
        $task = TaskModel::with('list')->find($taskId);
        if ($task && isset($task->file_paths[$index])) {
            $this->assertTaskAccessible($task);
            $pathStr = (string) $task->file_paths[$index];
            if ($pathStr !== '' && !str_contains($pathStr, '..') && str_starts_with(ltrim($pathStr, '/'), 'leitung/tasks/') && Storage::disk('local')->exists($pathStr)) {
                return Storage::disk('local')->download($pathStr);
            }
        }
        session()->flash('error', 'Datei nicht gefunden.');
    }

    public function render()
    {
        $ticketService = app(ManagementTaskTicketService::class);
        $ticketService->evaluateEscalations();

        $empViewer = $this->resolveEmployeeViewer();
        $isEmployeeViewer = ($empViewer !== null);
        $currentActor = $ticketService->resolveCurrentActor(null, $empViewer);
        $currentAdminAvatarUrl = ManagementTaskTicketService::resolveAvatarUrl($currentActor['avatar']);
        $assignableActors = $ticketService->getAssignableActors();

        $ownProfile = Schema::hasTable('management_task_operator_profiles')
            ? $ticketService->getOrCreateOperatorProfile(
                $currentActor['type'],
                $currentActor['id'],
                $currentActor['name'],
                $currentActor['avatar'],
                $currentActor['role_label']
            )->toModalPayload(true)
            : null;

        $hasAssigneeCols = Schema::hasColumn('management_tasks', 'assignee_id');
        $hasTeamCol = Schema::hasColumn('management_task_lists', 'is_team_visible');

        $listsQuery = TaskListModel::withCount([
            'tasks as open_tasks_count' => function ($query) {
                $query->where('is_completed', false)->whereNull('parent_id')->where('is_archived', false);
            },
            'tasks as total_tasks_count' => function ($query) {
                $query->whereNull('parent_id')->where('is_archived', false);
            },
        ])->where('is_archived', false);

        if ($isEmployeeViewer && $hasTeamCol) {
            $listsQuery->where('is_team_visible', true);
        }

        $lists = $listsQuery
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $teamListIds = $lists->pluck('id')->all();

        if ($isEmployeeViewer) {
            $totalAllCount = TaskModel::whereNull('parent_id')
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->where(function ($q) use ($teamListIds, $empViewer, $hasAssigneeCols) {
                    if (!empty($teamListIds)) {
                        $q->whereIn('task_list_id', $teamListIds);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                    if ($hasAssigneeCols) {
                        $q->orWhere(function ($sq) use ($empViewer) {
                            $sq->where('assignee_type', 'employee')->where('assignee_id', $empViewer->id);
                        });
                    }
                })
                ->count();
        } else {
            $totalAllCount = TaskModel::whereNull('parent_id')
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->count();
        }

        $activeList = null;
        if ($this->activeListId !== 'all') {
            $activeList = $lists->firstWhere('id', $this->activeListId);
            if (!$activeList) {
                $this->activeListId = 'all';
            }
        }

        // 1. "Meine Tickets" (Personenbezogen fuer die aktuell eingeloggte Person!)
        $myTicketsQuery = TaskModel::with(['subtasks', 'list'])
            ->withCount('messages')
            ->whereNull('parent_id')
            ->where('is_archived', false)
            ->where('is_mine', true);

        if ($hasAssigneeCols) {
            if ($isEmployeeViewer) {
                $myTicketsQuery->where('assignee_type', 'employee')
                    ->where('assignee_id', $currentActor['id']);
            } else {
                $myTicketsQuery->where(function ($q) use ($currentActor) {
                    $q->whereNull('assignee_id')
                        ->orWhere(function ($sq) use ($currentActor) {
                            $sq->where('assignee_type', 'admin')
                                ->where('assignee_id', $currentActor['id']);
                        });
                });
            }
        }

        if ($this->searchQuery !== '') {
            $term = '%' . trim($this->searchQuery) . '%';
            $myTicketsQuery->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('ai_plan', 'like', $term)
                    ->orWhere('assignee_name', 'like', $term);
            });
        }

        $myTickets = $myTicketsQuery
            ->orderBy('is_completed', 'asc')
            ->orderByRaw('CASE WHEN my_position > 0 THEN 0 ELSE 1 END ASC')
            ->orderBy('my_position', 'asc')
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $myTicketIds = $myTickets->pluck('id')->flip()->all();

        // 2. "Offene Tickets" Tabelle (Rechts: inkl. an Mitarbeiter delegierte Tickets fuer Admin)
        $poolQuery = TaskModel::with(['subtasks', 'list'])
            ->withCount('messages')
            ->whereNull('parent_id')
            ->where('is_archived', false);

        if ($isEmployeeViewer) {
            $poolQuery->where(function ($q) use ($teamListIds, $currentActor, $hasAssigneeCols) {
                if (!empty($teamListIds)) {
                    $q->whereIn('task_list_id', $teamListIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
                if ($hasAssigneeCols) {
                    $q->orWhere(function ($sq) use ($currentActor) {
                        $sq->where('assignee_type', 'employee')->where('assignee_id', $currentActor['id']);
                    });
                }
            });
        }

        if ($activeList) {
            $poolQuery->where('task_list_id', $activeList->id);
        }

        if (!$isEmployeeViewer && $this->assigneeFilter !== 'all' && $hasAssigneeCols) {
            if ($this->assigneeFilter === 'unassigned') {
                $poolQuery->whereNull('assignee_id');
            } elseif (str_contains($this->assigneeFilter, ':')) {
                [$fType, $fId] = explode(':', $this->assigneeFilter, 2);
                $poolQuery->where('assignee_type', $fType)->where('assignee_id', $fId);
            }
        }

        if ($this->searchQuery !== '') {
            $term = '%' . trim($this->searchQuery) . '%';
            $poolQuery->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('ai_plan', 'like', $term)
                    ->orWhere('assignee_name', 'like', $term);
            });
        }

        $allScopedTasks = $poolQuery
            ->orderBy('is_completed', 'asc')
            ->orderByRaw('CASE WHEN position > 0 THEN 0 ELSE 1 END ASC')
            ->orderBy('position', 'asc')
            ->orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
            ->orderBy('created_at', 'desc')
            ->get();

        $openTasks = $allScopedTasks->where('is_completed', false)->values();

        // Alle Tickets, die NICHT in den eigenen "Meine Tickets" liegen, erscheinen in der rechten Tabelle
        // (Dadurch sieht Alina in der Tabelle auch alle Tickets, die ein Mitarbeiter aktuell "In Bearbeitung" hat!)
        $openPoolTickets = $allScopedTasks->reject(fn ($t) => isset($myTicketIds[$t->id]))->values();

        $selectedTask = null;
        if ($this->selectedTaskId) {
            $cand = TaskModel::with(['subtasks', 'list', 'messages'])->find($this->selectedTaskId);
            if ($cand && (!$isEmployeeViewer || $ticketService->isTaskVisibleToEmployee($cand, $empViewer))) {
                $selectedTask = $cand;
            } else {
                $this->selectedTaskId = null;
            }
        }

        return view('livewire.shop.management.management-task', [
            'lists' => $lists,
            'totalAllCount' => $totalAllCount,
            'activeList' => $activeList,
            'openTasks' => $openTasks,
            'myTickets' => $myTickets,
            'openPoolTickets' => $openPoolTickets,
            'selectedTask' => $selectedTask,
            'currentActor' => $currentActor,
            'isEmployeeViewer' => $isEmployeeViewer,
            'assignableActors' => $assignableActors,
            'ownProfile' => $ownProfile,
            'attachmentRouteName' => $isEmployeeViewer ? 'employee.tasks.attachment' : 'admin.tasks.attachment',
            'currentAdminName' => $currentActor['name'],
            'currentAdminAvatarUrl' => $currentAdminAvatarUrl,
        ]);
    }
}

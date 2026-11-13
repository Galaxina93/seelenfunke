<?php

namespace App\Livewire\Shop\Management;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Management\ManagementTask as TaskModel;
use App\Models\Management\ManagementTaskList as TaskListModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class ManagementTask extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'Leitung';

    // Smart Lists ('my_day', 'important', 'planned', 'all') or UUID of a ManagementTaskList
    public ?string $activeListId = null;

    // Search
    public string $searchQuery = '';

    // Collapsible "Erledigt" section (Microsoft To Do style)
    public bool $showCompletedSection = true;

    // Right-side Detail Inspector Panel (Microsoft To Do style)
    #[Locked]
    public ?string $selectedTaskId = null;
    public string $detailTitle = '';
    public string $detailAiPlan = '';
    public ?string $detailRelevantFrom = null;
    public string $detailPriority = 'niedrig';
    public ?string $detailTaskListId = null;
    public string $newSubtaskTitle = '';

    // List creation & editing
    public string $newListName = '';
    public string $newListIcon = 'list-bullet';
    public string $newListColor = '#818CF8';
    public bool $showNewListModal = false;

    #[Locked]
    public ?string $editingListId = null;
    public string $editingListName = '';
    public string $editingListColor = '#818CF8';

    // Task creation
    public string $newTaskTitle = '';
    public string $newTaskPriority = 'niedrig';
    public ?string $newTaskRelevantFrom = null;

    // Inline editing (fallback)
    #[Locked]
    public ?string $editingTaskId = null;
    public string $editingTaskTitle = '';
    public string $editingTaskPriority = 'niedrig';
    public ?string $editingTaskRelevantFrom = null;

    // Subtasks inline
    public array $expandedTasks = [];
    public array $newSubtaskTitles = [];

    // File uploads per task
    public array $taskUploads = [];

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }

        $this->activeListId = 'all';
    }

    public function selectList(string $id): void
    {
        $this->activeListId = $id;
        $this->cancelEditTask();
    }

    public function selectTask(?string $id): void
    {
        if ($this->selectedTaskId === $id) {
            $this->selectedTaskId = null;
            return;
        }
        $this->selectedTaskId = $id;
        $this->loadSelectedTaskFields();
    }

    public function closeDetailPanel(): void
    {
        $this->selectedTaskId = null;
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
        $this->detailAiPlan = (string) ($task->ai_plan ?? '');
        $this->detailRelevantFrom = $task->relevant_from ? $task->relevant_from->format('Y-m-d') : null;
        $this->detailPriority = (string) ($task->priority ?? 'niedrig');
        $this->detailTaskListId = $task->task_list_id;
    }

    public function saveDetailPanel(): void
    {
        if (!$this->selectedTaskId) {
            return;
        }
        $task = TaskModel::find($this->selectedTaskId);
        if (!$task) {
            return;
        }
        $title = trim($this->detailTitle);
        if ($title === '') {
            return;
        }
        $task->update([
            'title' => $title,
            'ai_plan' => trim($this->detailAiPlan) !== '' ? trim($this->detailAiPlan) : null,
            'relevant_from' => !empty($this->detailRelevantFrom) ? \Carbon\Carbon::parse($this->detailRelevantFrom)->startOfDay() : null,
            'priority' => in_array($this->detailPriority, ['niedrig', 'mittel', 'hoch'], true) ? $this->detailPriority : 'niedrig',
            'task_list_id' => $this->detailTaskListId ?: $task->task_list_id,
        ]);
    }

    public function updatedDetailTitle(): void
    {
        $this->saveDetailPanel();
    }

    public function updatedDetailAiPlan(): void
    {
        $this->saveDetailPanel();
    }

    public function updatedDetailRelevantFrom(): void
    {
        $this->saveDetailPanel();
    }

    public function updatedDetailPriority(): void
    {
        $this->saveDetailPanel();
    }

    public function updatedDetailTaskListId(): void
    {
        $this->saveDetailPanel();
    }

    public function toggleCompletedSection(): void
    {
        $this->showCompletedSection = !$this->showCompletedSection;
    }

    // --- LIST MANAGEMENT ---

    public function createList(): void
    {
        $this->validate([
            'newListName' => 'required|min:2|max:50',
        ]);

        $maxPos = (int) TaskListModel::max('position');

        $list = TaskListModel::create([
            'name' => trim($this->newListName),
            'icon' => $this->newListIcon ?: 'list-bullet',
            'color' => $this->newListColor ?: '#818CF8',
            'position' => $maxPos + 1,
        ]);

        $this->newListName = '';
        $this->newListIcon = 'list-bullet';
        $this->newListColor = '#818CF8';
        $this->showNewListModal = false;
        $this->activeListId = $list->id;
    }

    public function startEditList(string $id, string $currentName, ?string $currentColor = null): void
    {
        $this->editingListId = $id;
        $this->editingListName = $currentName;
        $this->editingListColor = $currentColor ?: '#818CF8';
    }

    public function updateList(): void
    {
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
    }

    public function cancelEditList(): void
    {
        $this->editingListId = null;
        $this->editingListName = '';
    }

    public function deleteList(string $id): void
    {
        $list = TaskListModel::find($id);
        if ($list) {
            $tasks = TaskModel::where('task_list_id', $list->id)->get();
            foreach ($tasks as $t) {
                $this->deleteTaskFiles($t);
                $t->delete();
            }
            $list->delete();

            if ($this->activeListId === $id) {
                $first = TaskListModel::orderBy('position')->first();
                $this->activeListId = $first ? $first->id : 'all';
            }
        }
    }

    // --- TASK MANAGEMENT ---

    public function addTask(): void
    {
        if (!$this->activeListId) {
            return;
        }

        $this->validate([
            'newTaskTitle' => 'required|min:1|max:255',
            'newTaskPriority' => 'required|in:niedrig,mittel,hoch',
            'newTaskRelevantFrom' => 'nullable|date',
        ]);

        // Resolve target list if user is inside a Smart List
        if (in_array($this->activeListId, ['my_day', 'important', 'planned', 'all'], true)) {
            $targetList = TaskListModel::where('name', 'Aufgaben')->first()
                ?? TaskListModel::orderBy('position', 'asc')->first();
            if (!$targetList) {
                $targetList = TaskListModel::create([
                    'name' => 'Aufgaben',
                    'icon' => 'home',
                    'color' => '#818CF8',
                    'position' => 1,
                ]);
            }
            $targetListId = $targetList->id;
        } else {
            $targetListId = $this->activeListId;
        }

        $priority = $this->newTaskPriority;
        if ($this->activeListId === 'important' && $priority === 'niedrig') {
            $priority = 'hoch';
        }

        $relevantFrom = null;
        if (!empty($this->newTaskRelevantFrom)) {
            $relevantFrom = \Carbon\Carbon::parse($this->newTaskRelevantFrom)->startOfDay();
        } elseif (in_array($this->activeListId, ['my_day', 'planned'], true)) {
            $relevantFrom = \Carbon\Carbon::today();
        }

        $maxPos = (int) TaskModel::where('task_list_id', $targetListId)->whereNull('parent_id')->max('position');

        TaskModel::create([
            'task_list_id' => $targetListId,
            'parent_id' => null,
            'title' => trim($this->newTaskTitle),
            'priority' => $priority,
            'relevant_from' => $relevantFrom,
            'is_completed' => false,
            'position' => $maxPos + 1,
            'file_paths' => [],
        ]);

        $this->newTaskTitle = '';
        $this->newTaskPriority = 'niedrig';
        $this->newTaskRelevantFrom = null;
    }

    public function addSubtask(string $parentId): void
    {
        $title = $this->newSubtaskTitles[$parentId] ?? $this->newSubtaskTitle ?? '';
        if (empty(trim($title))) {
            return;
        }

        $parent = TaskModel::find($parentId);
        if (!$parent) {
            return;
        }

        $maxPos = (int) TaskModel::where('parent_id', $parentId)->max('position');

        TaskModel::create([
            'task_list_id' => $parent->task_list_id,
            'parent_id' => $parent->id,
            'title' => trim($title),
            'priority' => 'niedrig',
            'is_completed' => false,
            'position' => $maxPos + 1,
            'file_paths' => [],
        ]);

        $this->newSubtaskTitles[$parentId] = '';
        $this->newSubtaskTitle = '';

        if (!in_array($parentId, $this->expandedTasks, true)) {
            $this->expandedTasks[] = $parentId;
        }
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
        $task = TaskModel::find($id);
        if ($task) {
            $newState = !$task->is_completed;
            $task->update(['is_completed' => $newState]);
        }
    }

    public function toggleImportant(string $id): void
    {
        $task = TaskModel::find($id);
        if ($task) {
            $newPrio = $task->priority === 'hoch' ? 'niedrig' : 'hoch';
            $task->update(['priority' => $newPrio]);
            if ($this->selectedTaskId === $id) {
                $this->detailPriority = $newPrio;
            }
        }
    }

    public function toggleMyDay(string $id): void
    {
        $task = TaskModel::find($id);
        if ($task) {
            $isToday = $task->relevant_from && $task->relevant_from->isToday();
            $newDate = $isToday ? null : \Carbon\Carbon::today();
            $task->update(['relevant_from' => $newDate]);
            if ($this->selectedTaskId === $id) {
                $this->detailRelevantFrom = $newDate ? $newDate->format('Y-m-d') : null;
            }
        }
    }

    public function startEditTask(string $id): void
    {
        $task = TaskModel::find($id);
        if ($task) {
            $this->editingTaskId = $id;
            $this->editingTaskTitle = $task->title;
            $this->editingTaskPriority = $task->priority ?? 'niedrig';
            $this->editingTaskRelevantFrom = $task->relevant_from ? $task->relevant_from->format('Y-m-d') : null;
        }
    }

    public function updateTask(): void
    {
        if (!$this->editingTaskId || empty(trim($this->editingTaskTitle))) {
            $this->cancelEditTask();
            return;
        }

        $task = TaskModel::find($this->editingTaskId);
        if ($task) {
            $task->update([
                'title' => trim($this->editingTaskTitle),
                'priority' => $this->editingTaskPriority,
                'relevant_from' => !empty($this->editingTaskRelevantFrom) ? \Carbon\Carbon::parse($this->editingTaskRelevantFrom)->startOfDay() : null,
            ]);
            if ($this->selectedTaskId === $task->id) {
                $this->loadSelectedTaskFields();
            }
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
        $task = TaskModel::find($id);
        if ($task) {
            $subtasks = TaskModel::where('parent_id', $task->id)->get();
            foreach ($subtasks as $sub) {
                $this->deleteTaskFiles($sub);
                $sub->delete();
            }
            $this->deleteTaskFiles($task);
            if ($this->selectedTaskId === $task->id) {
                $this->selectedTaskId = null;
            }
            $task->delete();
        }
    }

    // --- FILE ATTACHMENTS ---

    public function updatedTaskUploads($value, $taskId): void
    {
        $this->validate([
            "taskUploads.{$taskId}.*" => 'file|max:20480|mimes:pdf,jpg,jpeg,png,webp,gif,txt,md,csv,doc,docx,xls,xlsx,zip',
        ]);

        $task = TaskModel::find($taskId);
        if (!$task) {
            return;
        }

        $files = $this->taskUploads[$taskId] ?? [];
        if (!is_array($files)) {
            $files = [$files];
        }

        $storedPaths = [];
        foreach ($files as $file) {
            $path = $file->store('leitung/tasks/attachments', 'local');
            $storedPaths[] = $path;
        }

        $existing = $task->file_paths ?? [];
        $updated = array_merge($existing, $storedPaths);

        $task->update(['file_paths' => $updated]);
        unset($this->taskUploads[$taskId]);
    }

    public function removeFile(string $taskId, string $filePath): void
    {
        $task = TaskModel::find($taskId);
        if (!$task) {
            return;
        }

        $existing = $task->file_paths ?? [];
        if (($key = array_search($filePath, $existing, true)) !== false) {
            unset($existing[$key]);

            $pathStr = (string) $filePath;
            if ($pathStr !== '' && !str_contains($pathStr, '..') && !str_contains($pathStr, "\0") && str_starts_with(ltrim($pathStr, '/'), 'leitung/tasks/') && Storage::disk('local')->exists($pathStr)) {
                Storage::disk('local')->delete($pathStr);
            }

            $task->update(['file_paths' => array_values($existing)]);
        }
    }

    protected function deleteTaskFiles(TaskModel $task): void
    {
        $files = $task->file_paths ?? [];
        foreach ($files as $path) {
            $pathStr = (string) $path;
            if ($pathStr !== '' && !str_contains($pathStr, '..') && !str_contains($pathStr, "\0") && str_starts_with(ltrim($pathStr, '/'), 'leitung/tasks/') && Storage::disk('local')->exists($pathStr)) {
                Storage::disk('local')->delete($pathStr);
            }
        }
    }

    public function downloadFile(string $filePath)
    {
        $pathStr = (string) $filePath;
        if ($pathStr === '' || str_contains($pathStr, '..') || str_contains($pathStr, "\0") || !str_starts_with(ltrim($pathStr, '/'), 'leitung/tasks/')) {
            abort(403, 'Unzulässiger Dateipfad.');
        }

        if (Storage::disk('local')->exists($pathStr)) {
            $fullPath = Storage::disk('local')->path($pathStr);
            if (is_link($fullPath)) {
                abort(403, 'Unzulässiger Dateipfad.');
            }
            return Storage::disk('local')->download($pathStr);
        }

        session()->flash('error', 'Datei nicht gefunden.');
    }

    public function render()
    {
        $lists = TaskListModel::withCount([
            'tasks as open_tasks_count' => function ($query) {
                $query->where('is_completed', false)->whereNull('parent_id')->where('is_archived', false);
            },
            'tasks as completed_tasks_count' => function ($query) {
                $query->where('is_completed', true)->whereNull('parent_id')->where('is_archived', false);
            }
        ])
            ->where('is_archived', false)
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Smart list counters
        $smartCounts = [
            'my_day' => TaskModel::whereNull('parent_id')
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->whereDate('relevant_from', \Carbon\Carbon::today())
                ->count(),
            'important' => TaskModel::whereNull('parent_id')
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->where('priority', 'hoch')
                ->count(),
            'planned' => TaskModel::whereNull('parent_id')
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->whereNotNull('relevant_from')
                ->count(),
            'all' => TaskModel::whereNull('parent_id')
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->count(),
        ];

        $activeList = null;
        $smartMeta = null;

        $query = TaskModel::with(['subtasks', 'list'])
            ->whereNull('parent_id')
            ->where('is_archived', false);

        if ($this->searchQuery !== '') {
            $term = '%' . trim($this->searchQuery) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('ai_plan', 'like', $term);
            });
            $smartMeta = [
                'name' => 'Suche: "' . $this->searchQuery . '"',
                'subtitle' => 'Suchergebnisse über alle Listen',
                'color' => '#818CF8',
                'icon' => 'magnifying-glass',
            ];
        } elseif ($this->activeListId === 'my_day') {
            $query->whereDate('relevant_from', \Carbon\Carbon::today());
            $smartMeta = [
                'name' => 'Mein Tag',
                'subtitle' => now()->locale('de')->isoFormat('dddd, D. MMMM'),
                'color' => '#38BDF8',
                'icon' => 'sun',
            ];
        } elseif ($this->activeListId === 'important') {
            $query->where('priority', 'hoch');
            $smartMeta = [
                'name' => 'Wichtig',
                'subtitle' => 'Mit Stern markierte Fokus-Aufgaben',
                'color' => '#F472B6',
                'icon' => 'star',
            ];
        } elseif ($this->activeListId === 'planned') {
            $query->whereNotNull('relevant_from');
            $smartMeta = [
                'name' => 'Geplant',
                'subtitle' => 'Aufgaben mit Fälligkeitsdatum',
                'color' => '#34D399',
                'icon' => 'calendar',
            ];
        } elseif ($this->activeListId === 'all') {
            $smartMeta = [
                'name' => 'Alle Aufgaben',
                'subtitle' => 'Gesamtübersicht aller Listen',
                'color' => '#818CF8',
                'icon' => 'home',
            ];
        } else {
            $activeList = $lists->firstWhere('id', $this->activeListId) ?? $lists->first();
            if ($activeList) {
                $this->activeListId = $activeList->id;
                $query->where('task_list_id', $activeList->id);
            }
        }

        $allRootTasks = $query
            ->orderBy('is_completed', 'asc')
            ->orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $openTasks = $allRootTasks->where('is_completed', false)->values();
        $completedTasks = $allRootTasks->where('is_completed', true)->values();

        $selectedTask = $this->selectedTaskId
            ? TaskModel::with(['subtasks', 'list'])->find($this->selectedTaskId)
            : null;

        return view('livewire.shop.management.management-task', [
            'lists' => $lists,
            'smartCounts' => $smartCounts,
            'activeList' => $activeList,
            'smartMeta' => $smartMeta,
            'openTasks' => $openTasks,
            'completedTasks' => $completedTasks,
            'selectedTask' => $selectedTask,
        ]);
    }
}

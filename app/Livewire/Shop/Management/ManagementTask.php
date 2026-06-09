<?php

namespace App\Livewire\Shop\Management;

use Livewire\Attributes\Layout;

use App\Models\Management\ManagementTask as TaskModel;
use App\Models\Management\ManagementTaskList;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ManagementTask extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'Leitung';

    public $search = '';
    public $selectedListId = null;
    public $showArchive = false;

    // File Attachments State
    public $taskFilesUpload;
    public ?string $uploadingTaskId = null;

    // Inline Creation State
    public $isAddingList = false;
    public $newList_name = '';
    public $newList_icon = 'bookmark';

    public $newTask_title = '';

    public function mount()
    {
        // One-time auto-migration of old English priorities
        TaskModel::where('priority', 'low')->update(['priority' => 'niedrig']);
        TaskModel::where('priority', 'medium')->update(['priority' => 'mittel']);
        TaskModel::where('priority', 'high')->update(['priority' => 'hoch']);

        // Headless Artisan calls were removed to preserve test isolation.

        // Wähle standardmäßig die erste Liste, falls vorhanden
        $firstList = ManagementTaskList::orderBy('created_at', 'asc')->first();
        if ($firstList) {
            $this->selectedListId = $firstList->id;
        }
    }

    public function createList()
    {
        $this->validate(['newList_name' => 'required|min:2|max:30']);

        $list = ManagementTaskList::create([
            'id' => (string) Str::uuid(),
            'name' => $this->newList_name,
            'icon' => $this->newList_icon,
            'color' => '#C5A059' // Standard Gold
        ]);

        $this->selectedListId = $list->id;
        $this->reset(['newList_name', 'isAddingList']);
        $this->newList_icon = 'bookmark'; // Reset Icon
    }

    public function cancelCreateList()
    {
        $this->isAddingList = false;
        $this->reset('newList_name');
    }

    public function createTask()
    {
        $this->validate(['newTask_title' => 'required|min:2']);

        if (!$this->selectedListId) {
            // Fallback: Falls keine Liste existiert, erstelle eine Standardliste
            $list = ManagementTaskList::create([
                'id' => (string) Str::uuid(),
                'name' => 'Allgemein',
                'icon' => 'inbox'
            ]);
            $this->selectedListId = $list->id;
        }

        TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->selectedListId,
            'title' => $this->newTask_title,
            'priority' => 'niedrig',
        ]);

        $this->reset('newTask_title');
    }

    public function addSubTask($parentId, $title)
    {
        if (empty($title)) return;

        TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->selectedListId,
            'parent_id' => $parentId,
            'title' => $title,
            'priority' => 'niedrig',
        ]);
    }

    public function updateTaskTitle($id, $newTitle)
    {
        if(empty(trim($newTitle))) return;

        $task = TaskModel::find($id);
        if($task) {
            $task->update(['title' => $newTitle]);
        }
    }

    public function updateTaskPriority($id, $priority)
    {
        if(empty($priority)) return;

        $task = TaskModel::find($id);
        if($task && in_array($priority, ['niedrig', 'mittel', 'hoch'])) {
            $task->update(['priority' => $priority]);
        }
    }

    public function updateTaskRelevantFrom($id, $dateString)
    {
        $task = TaskModel::find($id);
        if ($task) {
            $task->update([
                'relevant_from' => empty($dateString) ? null : \Carbon\Carbon::parse($dateString)->startOfDay()
            ]);
        }
    }

    public function toggleComplete($id)
    {
        $task = TaskModel::find($id);
        if($task) {
            $task->update(['is_completed' => !$task->is_completed]);

            if ($task->is_completed) {
                $this->dispatch('task-completed');
            }
        }
    }

    public function promoteToTask($id)
    {
        $task = TaskModel::find($id);
        if($task) {
            $task->update(['parent_id' => null]);
        }
    }

    public function deleteTask($id)
    {
        $task = TaskModel::find($id);
        if ($task) {
            if (!empty($task->file_paths)) {
                foreach ($task->file_paths as $path) {
                    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                    }
                }
            }
            $task->delete();
        }
    }

    public function updatedTaskFilesUpload()
    {
        if ($this->uploadingTaskId && $this->taskFilesUpload) {
            $task = TaskModel::find($this->uploadingTaskId);
            if ($task) {
                $files = is_array($this->taskFilesUpload) ? $this->taskFilesUpload : [$this->taskFilesUpload];
                $existing = $task->file_paths ?? [];
                
                foreach ($files as $file) {
                    $path = $file->store('leitung/tasks/attachments', 'local');
                    $existing[] = $path;
                }
                
                $task->update(['file_paths' => $existing]);
                session()->flash('success', 'Datei hochgeladen.');
            }
            $this->reset(['taskFilesUpload', 'uploadingTaskId']);
        }
    }

    public function deleteTaskFile($taskId, $fileIndex)
    {
        $task = TaskModel::find($taskId);
        if ($task) {
            $files = $task->file_paths ?? [];
            if (isset($files[$fileIndex])) {
                $path = $files[$fileIndex];
                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                }
                unset($files[$fileIndex]);
                $task->update(['file_paths' => array_values($files)]);
                session()->flash('success', 'Datei gelöscht.');
            }
        }
    }

    public function updateTaskOrder($orderedIds)
    {
        foreach ($orderedIds as $index => $id) {
            TaskModel::where('id', $id)->update(['position' => $index]);
        }
    }

    public function toggleArchiveMode()
    {
        $this->showArchive = !$this->showArchive;
        // Optionally reset selected list if needed, or keep it.
    }

    public function toggleArchiveTask($id)
    {
        $task = TaskModel::find($id);
        if ($task) {
            $task->update(['is_archived' => !$task->is_archived]);
        }
    }

    public function toggleArchiveList($id)
    {
        $list = ManagementTaskList::find($id);
        if ($list) {
            $list->update(['is_archived' => !$list->is_archived]);
        }
    }

    public function deleteList($id)
    {
        $list = ManagementTaskList::find($id);
        if($list) {
            $list->delete();
            $first = ManagementTaskList::first();
            $this->selectedListId = $first ? $first->id : null;
        }
    }

    public function updateListOrder($orderedIds)
    {
        foreach ($orderedIds as $index => $id) {
            ManagementTaskList::where('id', $id)->update(['position' => $index]);
        }
    }

    public function render()
    {
        $listsFiltered = ManagementTaskList::withCount(['tasks as open_count' => function($q) {
            $q->where('is_completed', false)->whereNull('parent_id')->where('is_archived', false);
        }])
        ->where(function ($q) {
            if ($this->showArchive) {
                // In Archive view, show lists that are archived OR contain archived tasks
                $q->where('is_archived', true)
                  ->orWhereHas('tasks', function($t) {
                      $t->where('is_archived', true);
                  });
            } else {
                // Standard view: Show unarchived lists
                $q->where('is_archived', false);
            }
        })
        ->orderBy('position', 'asc')
        ->orderBy('created_at', 'asc')
        ->get();

        $tasks = collect();
        if ($this->selectedListId) {
            $tasks = TaskModel::where('task_list_id', $this->selectedListId)
                ->whereNull('parent_id')
                ->where('is_archived', $this->showArchive)
                ->with(['subtasks' => function($q) {
                    $q->orderBy('is_completed', 'asc')
                      ->orderBy('created_at', 'asc');
                }])
                ->when($this->search, fn($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->orderBy('is_completed', 'asc')
                ->orderByRaw("FIELD(priority, 'hoch', 'mittel', 'niedrig')")
                ->orderBy('position', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('livewire.shop.management.management-task', [
            'lists' => $listsFiltered,
            'tasks' => $tasks
        ]);
    }
}

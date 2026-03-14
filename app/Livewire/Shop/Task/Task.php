<?php

namespace App\Livewire\Shop\Task;

use App\Models\Task as TaskModel;
use App\Models\TaskList;
use Illuminate\Support\Str;
use Livewire\Component;

class Task extends Component
{
    public $search = '';
    public $selectedListId = null;

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

        // Auto-run map_id migrations and seeder (for easy update deployment without terminal)
        if (!\Illuminate\Support\Facades\Schema::hasColumn('map_nodes', 'map_id')) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\Seeders\MapSeeder', '--force' => true]);
        }

        // Wähle standardmäßig die erste Liste, falls vorhanden
        $firstList = TaskList::orderBy('created_at', 'asc')->first();
        if ($firstList) {
            $this->selectedListId = $firstList->id;
        }
    }

    public function createList()
    {
        $this->validate(['newList_name' => 'required|min:2|max:30']);

        $list = TaskList::create([
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
            $list = TaskList::create([
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
        TaskModel::destroy($id);
    }

    public function deleteList($id)
    {
        $list = TaskList::find($id);
        if($list) {
            $list->delete();
            $first = TaskList::first();
            $this->selectedListId = $first ? $first->id : null;
        }
    }

    public function render()
    {
        $lists = TaskList::withCount(['tasks as open_count' => function($q) {
            $q->where('is_completed', false)->whereNull('parent_id');
        }])->orderBy('created_at', 'asc')->get();

        $tasks = collect();
        if ($this->selectedListId) {
            $tasks = TaskModel::where('task_list_id', $this->selectedListId)
                ->whereNull('parent_id')
                ->with(['subtasks' => function($q) {
                    $q->orderBy('is_completed', 'asc')->orderBy('created_at', 'asc');
                }])
                ->when($this->search, fn($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->orderBy('is_completed', 'asc')
                ->orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('livewire.shop.task.task', [
            'lists' => $lists,
            'tasks' => $tasks
        ]);
    }
}

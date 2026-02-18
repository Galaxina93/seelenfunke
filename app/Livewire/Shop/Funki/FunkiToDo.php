<?php

namespace App\Livewire\Shop\Funki;

use App\Models\Todo;
use App\Models\TodoList;
use Livewire\Component;
use Illuminate\Support\Str;

class FunkiToDo extends Component
{
    public $search = '';
    public $selectedListId = null;
    public $newList_name = '';
    public $newList_icon = 'bookmark';
    public $newTask_title = '';

    // UI States
    public $isAddingList = false;

    public function mount()
    {
        $firstList = TodoList::first();
        if ($firstList) $this->selectedListId = $firstList->id;
    }

    public function createList()
    {
        $this->validate(['newList_name' => 'required|min:2']);
        $list = TodoList::create([
            'id' => (string) Str::uuid(),
            'name' => $this->newList_name,
            'icon' => $this->newList_icon
        ]);
        $this->selectedListId = $list->id;
        $this->reset(['newList_name', 'isAddingList']);
    }

    public function createTask()
    {
        $this->validate(['newTask_title' => 'required|min:2']);
        if (!$this->selectedListId) return;

        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $this->selectedListId,
            'title' => $this->newTask_title,
        ]);
        $this->reset('newTask_title');
    }

    public function addSubTask($parentId, $title)
    {
        if (empty($title)) return;
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $this->selectedListId,
            'parent_id' => $parentId,
            'title' => $title,
        ]);
    }

    public function toggleComplete($id)
    {
        $todo = Todo::findOrFail($id);
        $todo->update(['is_completed' => !$todo->is_completed]);
    }

    public function promoteToTask($id)
    {
        $todo = Todo::findOrFail($id);
        $todo->update(['parent_id' => null]);
    }

    public function deleteTodo($id)
    {
        Todo::destroy($id);
    }

    public function render()
    {
        $lists = TodoList::withCount(['todos as open_count' => function($q) {
            $q->where('is_completed', false)->whereNull('parent_id');
        }])->get();

        $todos = Todo::where('todo_list_id', $this->selectedListId)
            ->whereNull('parent_id')
            ->with('subtasks')
            ->when($this->search, fn($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->orderBy('is_completed', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.shop.funki.funki-to-do', [
            'lists' => $lists,
            'todos' => $todos
        ]);
    }
}

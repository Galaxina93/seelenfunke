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

    // Inline Creation State
    public $isAddingList = false;
    public $newList_name = '';
    public $newList_icon = 'bookmark';

    public $newTask_title = '';

    public function mount()
    {
        // Wähle standardmäßig die erste Liste, falls vorhanden
        $firstList = TodoList::first();
        if ($firstList) {
            $this->selectedListId = $firstList->id;
        }
    }

    public function createList()
    {
        $this->validate(['newList_name' => 'required|min:2|max:30']);

        $list = TodoList::create([
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
            $list = TodoList::create([
                'id' => (string) Str::uuid(),
                'name' => 'Allgemein',
                'icon' => 'inbox'
            ]);
            $this->selectedListId = $list->id;
        }

        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $this->selectedListId,
            'title' => $this->newTask_title,
            'priority' => 'low', // NEU: Default Prio
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
            'priority' => 'low',
        ]);
    }

    // Methode zum Aktualisieren des Titels (Inline Edit)
    public function updateTodoTitle($id, $newTitle)
    {
        if(empty(trim($newTitle))) return;

        $todo = Todo::find($id);
        if($todo) {
            $todo->update(['title' => $newTitle]);
        }
    }

    // NEU: Methode zum Aktualisieren der Priorität (Inline Edit)
    public function updateTodoPriority($id, $priority)
    {
        if(empty($priority)) return;

        $todo = Todo::find($id);
        if($todo && in_array($priority, ['low', 'medium', 'high'])) {
            $todo->update(['priority' => $priority]);
        }
    }

    public function toggleComplete($id)
    {
        $todo = Todo::find($id);
        if($todo) {
            $todo->update(['is_completed' => !$todo->is_completed]);
        }
    }

    public function promoteToTask($id)
    {
        $todo = Todo::find($id);
        if($todo) {
            $todo->update(['parent_id' => null]);
        }
    }

    public function deleteTodo($id)
    {
        // Löscht auch Subtasks via Cascade in DB (oder hier im Model Event)
        Todo::destroy($id);
    }

    public function deleteList($id)
    {
        $list = TodoList::find($id);
        if($list) {
            $list->delete();
            // Neue Liste selektieren
            $first = TodoList::first();
            $this->selectedListId = $first ? $first->id : null;
        }
    }

    public function render()
    {
        $lists = TodoList::withCount(['todos as open_count' => function($q) {
            $q->where('is_completed', false)->whereNull('parent_id');
        }])->orderBy('created_at', 'asc')->get();

        $todos = collect();
        if ($this->selectedListId) {
            $todos = Todo::where('todo_list_id', $this->selectedListId)
                ->whereNull('parent_id') // Nur Hauptaufgaben
                ->with(['subtasks' => function($q) {
                    $q->orderBy('is_completed', 'asc')->orderBy('created_at', 'asc');
                }])
                ->when($this->search, fn($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->orderBy('is_completed', 'asc')
                ->orderByRaw("FIELD(COALESCE(priority, 'low'), 'high', 'medium', 'low')") // NEU: Prio Sortierung
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('livewire.shop.funki.funki-to-do', [
            'lists' => $lists,
            'todos' => $todos
        ]);
    }
}

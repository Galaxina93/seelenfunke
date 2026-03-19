<?php

namespace Tests\Feature\Livewire\Shop\Task;

use App\Livewire\Shop\Task\Task;
use App\Models\Task as TaskModel;
use App\Models\TaskList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_component_and_auto_selects_first_list()
    {
        $list = TaskList::create(['id' => (string) Str::uuid(), 'name' => 'First List', 'icon' => 'bookmark']);

        Livewire::test(Task::class)
            ->assertSet('selectedListId', $list->id)
            ->assertViewHas('lists');
    }

    #[Test]
    public function it_can_create_a_new_task_list()
    {
        Livewire::test(Task::class)
            // Validation error expecting min 2 chars
            ->set('newList_name', 'A')
            ->call('createList')
            ->assertHasErrors(['newList_name'])
            // Correct creation
            ->set('newList_name', 'My New List')
            ->set('newList_icon', 'star')
            ->call('createList')
            ->assertHasNoErrors()
            ->assertSet('isAddingList', false)
            ->assertSet('newList_name', '');

        $this->assertDatabaseHas('task_lists', [
            'name' => 'My New List',
            'icon' => 'star',
            'color' => '#C5A059'
        ]);

        // The newly created list should automatically be selected
        $list = TaskList::where('name', 'My New List')->first();
        Livewire::test(Task::class)
            ->assertSet('selectedListId', $list->id);
    }

    #[Test]
    public function it_cancels_list_creation()
    {
        Livewire::test(Task::class)
            ->set('isAddingList', true)
            ->set('newList_name', 'Draft Name')
            ->call('cancelCreateList')
            ->assertSet('isAddingList', false)
            ->assertSet('newList_name', '');
    }

    #[Test]
    public function it_creates_a_task_with_fallback_list_if_none_exists()
    {
        Livewire::test(Task::class)
            ->set('newTask_title', 'My First Task')
            ->call('createTask')
            ->assertHasNoErrors();

        // Should have created the fallback list "Allgemein"
        $this->assertDatabaseHas('task_lists', [
            'name' => 'Allgemein',
            'icon' => 'inbox'
        ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'My First Task',
            'priority' => 'niedrig' // Default priority
        ]);
    }

    #[Test]
    public function it_can_add_subtasks_and_promote_them()
    {
        $list = TaskList::create(['id' => (string) Str::uuid(), 'name' => 'General']);
        $parentTask = TaskModel::create(['id' => (string) Str::uuid(), 'task_list_id' => $list->id, 'title' => 'Parent Task']);

        $component = Livewire::test(Task::class)
            ->call('addSubTask', $parentTask->id, 'Child Task');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Child Task',
            'parent_id' => $parentTask->id,
            'task_list_id' => $list->id
        ]);

        $childTask = TaskModel::where('title', 'Child Task')->first();

        // Promote the child to main task
        $component->call('promoteToTask', $childTask->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $childTask->id,
            'title' => 'Child Task',
            'parent_id' => null
        ]);
    }

    #[Test]
    public function it_updates_task_titles_and_priorities()
    {
        $list = TaskList::create(['id' => (string) Str::uuid(), 'name' => 'General']);
        $task = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $list->id,
            'title' => 'Old Title',
            'priority' => 'niedrig'
        ]);

        Livewire::test(Task::class)
            ->call('updateTaskTitle', $task->id, 'New Title')
            ->call('updateTaskPriority', $task->id, 'hoch');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'New Title',
            'priority' => 'hoch'
        ]);
    }

    #[Test]
    public function it_toggles_completion_and_emits_event()
    {
        $list = TaskList::create(['id' => (string) Str::uuid(), 'name' => 'General']);
        $task = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $list->id,
            'title' => 'Test Task',
            'is_completed' => false
        ]);

        Livewire::test(Task::class)
            ->call('toggleComplete', $task->id)
            ->assertDispatched('task-completed');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'is_completed' => true
        ]);
    }

    #[Test]
    public function it_can_delete_tasks_and_lists()
    {
        $list = TaskList::create(['id' => (string) Str::uuid(), 'name' => 'General']);
        $list2 = TaskList::create(['id' => (string) Str::uuid(), 'name' => 'Another List']);
        
        $task = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $list->id,
            'title' => 'Test Task',
        ]);

        Livewire::test(Task::class)
            // Delete Task
            ->call('deleteTask', $task->id)
            ->call('deleteList', $list->id)
            // It should auto select $list2 after deleting $list
            ->assertSet('selectedListId', $list2->id);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
    }

    #[Test]
    public function it_searches_and_sorts_tasks_by_priority()
    {
        $list = TaskList::create(['id' => (string) Str::uuid(), 'name' => 'General']);
        
        TaskModel::create(['id' => (string) Str::uuid(), 'task_list_id' => $list->id, 'title' => 'Apple', 'priority' => 'niedrig']);
        TaskModel::create(['id' => (string) Str::uuid(), 'task_list_id' => $list->id, 'title' => 'Banana', 'priority' => 'hoch']);
        TaskModel::create(['id' => (string) Str::uuid(), 'task_list_id' => $list->id, 'title' => 'Cherry', 'priority' => 'mittel']);

        $component = Livewire::test(Task::class);
        
        // Sorting should be: hoch -> mittel -> niedrig
        // 'tasks' collection is populated in the render() method.
        $tasks = $component->viewData('tasks');
        
        $this->assertCount(3, $tasks);
        $this->assertEquals('Banana', $tasks[0]->title);
        $this->assertEquals('Cherry', $tasks[1]->title);
        $this->assertEquals('Apple', $tasks[2]->title);

        // Search
        $component->set('search', 'Apple');
        $tasks = $component->viewData('tasks');
        $this->assertCount(1, $tasks);
        $this->assertEquals('Apple', $tasks[0]->title);
    }
}

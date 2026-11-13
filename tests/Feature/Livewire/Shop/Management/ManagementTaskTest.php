<?php

namespace Tests\Feature\Livewire\Shop\Management;

use App\Livewire\Shop\Management\ManagementTask;
use App\Models\Management\ManagementTask as TaskModel;
use App\Models\Management\ManagementTaskList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagementTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\Admin\Admin::factory()->create(), 'admin');
    }

    #[Test]
    public function it_renders_the_component_and_auto_selects_first_list()
    {
        $list = ManagementTaskList::create(['id' => (string) Str::uuid(), 'name' => 'First List', 'icon' => 'bookmark']);

        Livewire::test(ManagementTask::class)
            ->assertSet('activeListId', 'all')
            ->call('selectList', $list->id)
            ->assertSet('activeListId', $list->id)
            ->assertViewHas('lists');
    }

    #[Test]
    public function it_can_create_a_new_task_list()
    {
        Livewire::test(ManagementTask::class)
            ->set('showNewListModal', true)
            // Validation error expecting min 2 chars
            ->set('newListName', 'A')
            ->call('createList')
            ->assertHasErrors(['newListName'])
            // Correct creation
            ->set('newListName', 'My New List')
            ->set('newListIcon', 'star')
            ->set('newListColor', '#C5A059')
            ->call('createList')
            ->assertHasNoErrors()
            ->assertSet('showNewListModal', false)
            ->assertSet('newListName', '');

        $this->assertDatabaseHas('management_task_lists', [
            'name' => 'My New List',
            'icon' => 'star',
            'color' => '#C5A059'
        ]);

        // The newly created list should automatically be selected
        $list = ManagementTaskList::where('name', 'My New List')->first();
        $this->assertNotNull($list);
    }

    #[Test]
    public function it_cancels_list_creation()
    {
        $list = ManagementTaskList::create(['id' => (string) Str::uuid(), 'name' => 'Draft List']);

        Livewire::test(ManagementTask::class)
            ->call('startEditList', $list->id, 'Draft Name', '#818CF8')
            ->assertSet('editingListId', $list->id)
            ->call('cancelEditList')
            ->assertSet('editingListId', null)
            ->assertSet('editingListName', '');
    }

    #[Test]
    public function it_creates_a_task_with_fallback_list_if_none_exists()
    {
        Livewire::test(ManagementTask::class)
            ->set('newTaskTitle', 'My First Task')
            ->call('addTask')
            ->assertHasNoErrors();

        // Should have created the fallback list "Aufgaben"
        $this->assertDatabaseHas('management_task_lists', [
            'name' => 'Aufgaben',
            'icon' => 'home'
        ]);

        $this->assertDatabaseHas('management_tasks', [
            'title' => 'My First Task',
            'priority' => 'niedrig' // Default priority
        ]);
    }

    #[Test]
    public function it_can_add_subtasks_and_promote_them()
    {
        $list = ManagementTaskList::create(['id' => (string) Str::uuid(), 'name' => 'General']);
        $parentTask = TaskModel::create(['id' => (string) Str::uuid(), 'task_list_id' => $list->id, 'title' => 'Parent Task']);

        Livewire::test(ManagementTask::class)
            ->set("newSubtaskTitles.{$parentTask->id}", 'Child Task')
            ->call('addSubtask', $parentTask->id);

        $this->assertDatabaseHas('management_tasks', [
            'title' => 'Child Task',
            'parent_id' => $parentTask->id,
            'task_list_id' => $list->id
        ]);
    }

    #[Test]
    public function it_updates_task_titles_and_priorities()
    {
        $list = ManagementTaskList::create(['id' => (string) Str::uuid(), 'name' => 'General']);
        $task = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $list->id,
            'title' => 'Old Title',
            'priority' => 'niedrig'
        ]);

        Livewire::test(ManagementTask::class)
            ->call('startEditTask', $task->id)
            ->set('editingTaskTitle', 'New Title')
            ->set('editingTaskPriority', 'hoch')
            ->call('updateTask');

        $this->assertDatabaseHas('management_tasks', [
            'id' => $task->id,
            'title' => 'New Title',
            'priority' => 'hoch'
        ]);
    }

    #[Test]
    public function it_toggles_completion_and_emits_event()
    {
        $list = ManagementTaskList::create(['id' => (string) Str::uuid(), 'name' => 'General']);
        $task = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $list->id,
            'title' => 'Test Task',
            'is_completed' => false
        ]);

        Livewire::test(ManagementTask::class)
            ->call('toggleTask', $task->id);

        $this->assertDatabaseHas('management_tasks', [
            'id' => $task->id,
            'is_completed' => true
        ]);
    }

    #[Test]
    public function it_can_delete_tasks_and_lists()
    {
        $list = ManagementTaskList::create(['id' => (string) Str::uuid(), 'name' => 'General', 'position' => 1]);
        $list2 = ManagementTaskList::create(['id' => (string) Str::uuid(), 'name' => 'Another List', 'position' => 2]);
        
        $task = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $list->id,
            'title' => 'Test Task',
        ]);

        Livewire::test(ManagementTask::class)
            ->call('selectList', $list->id)
            // Delete Task
            ->call('deleteTask', $task->id)
            ->call('deleteList', $list->id)
            // It should auto select $list2 after deleting $list
            ->assertSet('activeListId', $list2->id);

        $this->assertDatabaseMissing('management_tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('management_task_lists', ['id' => $list->id]);
    }

    #[Test]
    public function it_searches_and_sorts_tasks_by_priority()
    {
        $list = ManagementTaskList::create(['id' => (string) Str::uuid(), 'name' => 'General']);
        
        TaskModel::create(['id' => (string) Str::uuid(), 'task_list_id' => $list->id, 'title' => 'Apple', 'priority' => 'niedrig', 'is_completed' => false]);
        TaskModel::create(['id' => (string) Str::uuid(), 'task_list_id' => $list->id, 'title' => 'Banana', 'priority' => 'hoch', 'is_completed' => false]);
        TaskModel::create(['id' => (string) Str::uuid(), 'task_list_id' => $list->id, 'title' => 'Cherry', 'priority' => 'mittel', 'is_completed' => false]);

        $component = Livewire::test(ManagementTask::class);
        
        // Sorting should be: hoch -> mittel -> niedrig
        $tasks = $component->viewData('openTasks');
        
        $this->assertCount(3, $tasks);
        $this->assertEquals('Banana', $tasks[0]->title);
        $this->assertEquals('Cherry', $tasks[1]->title);
        $this->assertEquals('Apple', $tasks[2]->title);

        // Search
        $component->set('searchQuery', 'Apple');
        $tasks = $component->viewData('openTasks');
        $this->assertCount(1, $tasks);
        $this->assertEquals('Apple', $tasks[0]->title);
    }

    #[Test]
    public function it_can_rename_lists()
    {
        $list = ManagementTaskList::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Old Name', 'icon' => 'bookmark']);

        Livewire::test(ManagementTask::class)
            ->call('startEditList', $list->id, 'Old Name')
            ->set('editingListName', 'New Name')
            ->call('updateList');

        $this->assertDatabaseHas('management_task_lists', [
            'id' => $list->id,
            'name' => 'New Name'
        ]);

        // Empty name leaves list unchanged
        Livewire::test(ManagementTask::class)
            ->call('startEditList', $list->id, 'New Name')
            ->set('editingListName', '   ')
            ->call('updateList');

        $this->assertDatabaseHas('management_task_lists', [
            'id' => $list->id,
            'name' => 'New Name' // remains unchanged
        ]);
    }
}

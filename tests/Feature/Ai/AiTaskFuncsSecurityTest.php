<?php

namespace Tests\Feature\Ai;

use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Services\AI\Functions\AiTaskFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTaskFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_task_list_id_and_create_task()
    {
        $dummy = new class {
            use AiTaskFuncs;
        };

        $list = ManagementTaskList::create([
            'name' => 'Phase 1 - Produkte',
            'color' => '#123456',
            'icon' => 'clipboard-document-list',
        ]);

        // Exact match
        $idExact = $dummy::resolveTaskListId('Phase 1 - Produkte');
        $this->assertEquals($list->id, $idExact);

        // Normalized match
        $idNorm = $dummy::resolveTaskListId('phase_1_produkte');
        $this->assertEquals($list->id, $idNorm);

        // Create task
        $resCreate = $dummy::executeCreateTask([
            'title' => 'Neue Laser-Grafiken importieren',
            'priority' => 'hoch',
            'list_name' => 'phase_1_produkte',
            'subtasks' => ['DXF prüfen', 'SVG konvertieren'],
        ]);

        $this->assertEquals('success', $resCreate['status']);
        $task = ManagementTask::find($resCreate['task_id']);
        $this->assertNotNull($task);
        $this->assertEquals('Neue Laser-Grafiken importieren', $task->title);
        $this->assertEquals('hoch', $task->priority);
        $this->assertEquals($list->id, $task->task_list_id);
        $this->assertCount(2, $task->subtasks);
    }

    public function test_delete_task_prevents_wildcard_mass_deletion()
    {
        $dummy = new class {
            use AiTaskFuncs;
        };

        $list = ManagementTaskList::create([
            'name' => 'Standard Liste',
            'color' => '#123456',
            'icon' => 'clipboard-document-list',
        ]);

        $task1 = ManagementTask::create([
            'title' => 'Wichtige Produktionsaufgabe',
            'priority' => 'hoch',
            'task_list_id' => $list->id,
        ]);

        $task2 = ManagementTask::create([
            'title' => 'Sicherheitsbackup prüfen',
            'priority' => 'mittel',
            'task_list_id' => $list->id,
        ]);

        // Attempt mass delete with single wildcard character or short strings
        $resShort = $dummy::executeDeleteTask([
            'task_titles' => ['%', 'a'],
        ]);

        $this->assertEquals('error', $resShort['status']);
        $this->assertStringContainsString('mind. 3 Zeichen', $resShort['message']);

        // Verify neither task was deleted
        $this->assertDatabaseHas('management_tasks', ['id' => $task1->id]);
        $this->assertDatabaseHas('management_tasks', ['id' => $task2->id]);

        // Targeted delete with valid title >= 3 chars
        $resDelete = $dummy::executeDeleteTask([
            'task_titles' => ['Produktionsaufgabe'],
        ]);

        $this->assertEquals('success', $resDelete['status']);
        $this->assertDatabaseMissing('management_tasks', ['id' => $task1->id]);
        $this->assertDatabaseHas('management_tasks', ['id' => $task2->id]);
    }
}

<?php

namespace Tests\Feature\Services\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Services\AI\Functions\AiTaskFuncs;
use App\Models\Management\ManagementTaskList;
use App\Models\Management\ManagementTask as TaskModel;

class AiTaskFuncsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_schema_is_valid()
    {
        $schema = AiTaskFuncs::getAiTaskFuncsSchema();
        
        $this->assertIsArray($schema);
        $this->assertCount(11, $schema);
        
        $names = array_map(fn($item) => $item['name'], $schema);
        $this->assertContains('task_get_all', $names);
        $this->assertContains('task_read_file', $names);
        $this->assertContains('task_add_subtask', $names);
    }

    public function test_task_get_all_includes_attachments()
    {
        $list = ManagementTaskList::create(['name' => 'General', 'icon' => 'inbox']);
        $task = TaskModel::create([
            'task_list_id' => $list->id,
            'title' => 'Task with files',
            'is_completed' => false,
            'file_paths' => [
                'leitung/tasks/attachments/document.txt',
                'leitung/tasks/attachments/invoice.pdf'
            ]
        ]);

        $response = AiTaskFuncs::executeGetTasks([]);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals(1, $response['open_tasks_count']);
        
        $t = $response['tasks'][0];
        $this->assertTrue($t['has_attachments']);
        $this->assertCount(2, $t['attachments']);
        $this->assertEquals('document.txt', $t['attachments'][0]['filename']);
        $this->assertEquals('leitung/tasks/attachments/document.txt', $t['attachments'][0]['path']);
    }

    public function test_task_read_file_requires_path()
    {
        $response = AiTaskFuncs::executeReadFile([]);
        $this->assertEquals('success', $response['status']);
        $this->assertStringContainsString('Kein Dateipfad angegeben', $response['message']);
    }

    public function test_task_read_file_prevents_unauthorized_paths()
    {
        $response = AiTaskFuncs::executeReadFile(['file_path' => 'public/secrets.txt']);
        $this->assertEquals('success', $response['status']);
        $this->assertStringContainsString('Zugriff verweigert', $response['message']);
    }

    public function test_task_read_file_reads_txt_file()
    {
        $path = 'leitung/tasks/attachments/document.txt';
        Storage::disk('local')->put($path, 'Secret task instructions here.');

        $response = AiTaskFuncs::executeReadFile(['file_path' => $path]);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('document.txt', $response['filename']);
        $this->assertEquals('Secret task instructions here.', $response['content']);
    }

    public function test_task_read_file_fails_on_missing_file()
    {
        $response = AiTaskFuncs::executeReadFile(['file_path' => 'leitung/tasks/attachments/nonexistent.txt']);
        $this->assertEquals('success', $response['status']);
        $this->assertStringContainsString('Datei nicht gefunden', $response['message']);
    }

    public function test_task_read_file_analyzes_image()
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Das Bild zeigt ein weißes Dokument mit schwarzer Schrift. Überschrift: Mietvertrag.']
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $path = 'leitung/tasks/attachments/document.jpg';
        Storage::disk('local')->put($path, 'fake_image_data');

        $response = AiTaskFuncs::executeReadFile(['file_path' => $path]);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('document.jpg', $response['filename']);
        $this->assertEquals('Das Bild zeigt ein weißes Dokument mit schwarzer Schrift. Überschrift: Mietvertrag.', $response['content']);
    }

    public function test_task_create_resolves_list_by_name_and_slug_and_does_not_falsely_duplicate()
    {
        $list1 = ManagementTaskList::create(['name' => 'PHASE 1 - PRODUKTE', 'icon' => 'cube']);
        $list4 = ManagementTaskList::create(['name' => 'PHASE 4 - LIVEGANG', 'icon' => 'rocket']);

        // An existing task with "test" substring in another list (like Webhook in Stripe ... /test/settings)
        TaskModel::create([
            'task_list_id' => $list4->id,
            'title' => 'Webhook anpassen https://stripe.com/test/settings',
            'is_completed' => false,
            'priority' => 'mittel'
        ]);

        // Creating "Test" by passing list_name = "Phase 1 Produkte" (should normalize to PHASE 1 - PRODUKTE)
        $res1 = AiTaskFuncs::executeCreateTask([
            'title' => 'Test',
            'list_name' => 'Phase 1 Produkte'
        ]);

        $this->assertEquals('success', $res1['status']);
        $this->assertEquals('PHASE 1 - PRODUKTE', $res1['list_name']);
        $this->assertEquals($list1->id, $res1['list_id']);
        $this->assertDatabaseHas('management_tasks', [
            'id' => $res1['task_id'],
            'title' => 'Test',
            'task_list_id' => $list1->id
        ]);

        // Creating another task by passing task_list_id = "phase_1_produkte"
        $res2 = AiTaskFuncs::executeCreateTask([
            'title' => 'Neues Produktfoto schießen',
            'task_list_id' => 'phase_1_produkte',
            'priority' => 'hoch'
        ]);

        $this->assertEquals('success', $res2['status']);
        $this->assertEquals($list1->id, $res2['list_id']);
        $this->assertDatabaseHas('management_tasks', [
            'id' => $res2['task_id'],
            'title' => 'Neues Produktfoto schießen',
            'task_list_id' => $list1->id,
            'priority' => 'hoch'
        ]);
    }

    public function test_task_create_with_subtasks_and_add_subtask_tool()
    {
        $list = ManagementTaskList::create(['name' => 'Marketing', 'icon' => 'sparkles']);

        // 1. Create task with initial subtasks
        $res = AiTaskFuncs::executeCreateTask([
            'title' => 'Instagram Kampagne starten',
            'list_name' => 'Marketing',
            'relevant_from' => 'heute',
            'subtasks' => ['Reel filmen', 'Hashtags recherchieren']
        ]);

        $this->assertEquals('success', $res['status']);
        $parentTaskId = $res['task_id'];

        $this->assertDatabaseHas('management_tasks', [
            'parent_id' => $parentTaskId,
            'title' => 'Reel filmen'
        ]);
        $this->assertDatabaseHas('management_tasks', [
            'parent_id' => $parentTaskId,
            'title' => 'Hashtags recherchieren'
        ]);

        // 2. Add an additional subtask via tool
        $subRes = AiTaskFuncs::executeAddSubtask([
            'task_id' => $parentTaskId,
            'title' => 'Story hochladen'
        ]);

        $this->assertEquals('success', $subRes['status']);
        $this->assertDatabaseHas('management_tasks', [
            'parent_id' => $parentTaskId,
            'title' => 'Story hochladen'
        ]);
    }
}


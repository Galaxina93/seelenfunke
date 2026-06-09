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
        // We added task_read_file, so it should have 10 functions now in seelenfunke
        $this->assertCount(10, $schema);
        
        $names = array_map(fn($item) => $item['name'], $schema);
        $this->assertContains('task_get_all', $names);
        $this->assertContains('task_read_file', $names);
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
}

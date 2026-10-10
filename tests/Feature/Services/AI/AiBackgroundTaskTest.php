<?php

namespace Tests\Feature\Services\AI;

use App\Events\TaskUpdated;
use App\Jobs\ExecuteAiBackgroundToolJob;
use App\Jobs\ProcessAiWorkspaceTask;
use App\Livewire\Shop\Ai\AiWidget;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiWorkspaceTask;
use App\Models\System\SystemLog;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\Functions\AiSystemFuncs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class AiBackgroundTaskTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Verify that new background tools and enhanced schemas are present.
     */
    public function test_background_tools_are_registered_in_schema(): void
    {
        $schema = AIFunctionsRegistry::getSchema();
        $this->assertIsArray($schema);

        $functionNames = array_map(fn($item) => $item['function']['name'] ?? '', $schema);
        $this->assertContains('system_dispatch_background_task', $functionNames);
        $this->assertContains('system_get_task_status', $functionNames);
        $this->assertContains('system_manage_logs', $functionNames);

        // Find system_manage_logs and verify new properties
        $manageLogsTool = collect($schema)->first(fn($item) => ($item['function']['name'] ?? '') === 'system_manage_logs');
        $this->assertNotNull($manageLogsTool);
        $properties = $manageLogsTool['function']['parameters']['properties'] ?? [];
        $this->assertArrayHasKey('status', $properties);
        $this->assertArrayHasKey('run_in_background', $properties);
    }

    /**
     * Test dispatching a background task with a specific tool to execute.
     */
    public function test_system_dispatch_background_task_dispatches_tool_job(): void
    {
        Bus::fake([ExecuteAiBackgroundToolJob::class]);
        Event::fake([TaskUpdated::class]);

        $response = AiSystemFuncs::executeDispatchBackgroundTask([
            'task_description' => '164 Logs im Hintergrund auf gelöst setzen',
            'tool_to_execute' => 'system_manage_logs',
            'tool_arguments' => [
                'action' => 'resolve',
                'target_scope' => 'all',
                'status' => 'error',
            ],
        ]);

        $this->assertEquals('queued', $response['status']);
        $this->assertNotEmpty($response['task_id']);
        $this->assertStringContainsString('164 Logs im Hintergrund auf gelöst setzen', $response['message']);

        // Verify task in database
        $task = AiWorkspaceTask::find($response['task_id']);
        $this->assertNotNull($task);
        $this->assertEquals('pending', $task->status);
        $this->assertEquals('system_manage_logs', $task->ui_metadata['tool_to_execute']);

        Bus::assertDispatched(ExecuteAiBackgroundToolJob::class, function ($job) use ($task) {
            return $job->taskId === $task->id && $job->toolName === 'system_manage_logs';
        });

        Event::assertDispatched(TaskUpdated::class);
    }

    /**
     * Test dispatching a general task (without specific tool) dispatches ProcessAiWorkspaceTask.
     */
    public function test_system_dispatch_background_task_dispatches_workspace_job_when_no_tool(): void
    {
        Bus::fake([ProcessAiWorkspaceTask::class]);

        $response = AiSystemFuncs::executeDispatchBackgroundTask([
            'task_description' => 'Recherchiere die beliebtesten Kerzen im Shop',
        ]);

        $this->assertEquals('queued', $response['status']);
        $this->assertNotEmpty($response['task_id']);

        $task = AiWorkspaceTask::find($response['task_id']);
        $this->assertNotNull($task);
        $this->assertNotNull($task->assigned_agent_id, 'assigned_agent_id should never be null on background dispatch');
        $this->assertTrue($task->ui_metadata['auto_approve']);

        Bus::assertDispatched(ProcessAiWorkspaceTask::class, function ($job) use ($task) {
            return $job->task->id === $task->id;
        });
    }

    /**
     * Test querying status of a specific background task.
     */
    public function test_system_get_task_status_returns_specific_task(): void
    {
        $task = AiWorkspaceTask::create([
            'prompt' => '164 Logs bereinigen',
            'status' => 'processing',
            'ui_metadata' => [
                'execution_plan' => [
                    ['id' => 1, 'status' => 'completed'],
                    ['id' => 2, 'status' => 'processing'],
                ]
            ]
        ]);

        $response = AiSystemFuncs::executeGetTaskStatus([
            'task_id' => $task->id
        ]);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals($task->id, $response['task']['id']);
        $this->assertEquals('processing', $response['task']['status']);
        $this->assertEquals(50, $response['task']['progress_percent']);
        $this->assertEquals(1, $response['task']['completed_steps']);
        $this->assertEquals(2, $response['task']['total_steps']);
    }

    /**
     * Test querying recent background tasks when no task_id is specified.
     */
    public function test_system_get_task_status_returns_recent_tasks_list(): void
    {
        AiWorkspaceTask::create([
            'prompt' => 'Task A für Test',
            'status' => 'completed',
            'response_content' => 'Erledigt A'
        ]);
        AiWorkspaceTask::create([
            'prompt' => 'Task B für Test',
            'status' => 'processing'
        ]);

        $response = AiSystemFuncs::executeGetTaskStatus([]);

        $this->assertEquals('success', $response['status']);
        $this->assertGreaterThanOrEqual(2, $response['tasks_count']);
        $this->assertIsArray($response['tasks']);
    }

    /**
     * Test ExecuteAiBackgroundToolJob execution lifecycle.
     */
    public function test_execute_ai_background_tool_job_processes_and_completes_task(): void
    {
        Event::fake([TaskUpdated::class]);

        $task = AiWorkspaceTask::create([
            'prompt' => 'Uhrzeit im Hintergrund abfragen',
            'status' => 'pending',
        ]);

        $job = new ExecuteAiBackgroundToolJob($task->id, 'system_get_current_time', []);
        $job->handle();

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertNotEmpty($task->response_content);
        $this->assertNotNull($task->ui_metadata['duration_seconds'] ?? null);

        Event::assertDispatched(TaskUpdated::class);
    }

    /**
     * Test that system_manage_logs automatically offloads to background queue when target_scope=all.
     */
    public function test_system_manage_logs_offloads_bulk_operation_to_background(): void
    {
        Bus::fake([ExecuteAiBackgroundToolJob::class]);

        $response = AiSystemFuncs::executeManageSystemLogs([
            'action' => 'resolve',
            'target_scope' => 'all',
            'status' => 'error'
        ]);

        $this->assertEquals('queued', $response['status']);
        $this->assertNotEmpty($response['task_id']);
        $this->assertStringContainsString('erfolgreich im Hintergrund gestartet', $response['message']);

        Bus::assertDispatched(ExecuteAiBackgroundToolJob::class, function ($job) use ($response) {
            return $job->taskId === $response['task_id'] && $job->toolName === 'system_manage_logs';
        });
    }

    /**
     * Test that system_manage_logs executes bulk resolution when running in worker thread.
     */
    public function test_system_manage_logs_executes_bulk_resolution_in_background_worker(): void
    {
        // Create sample error logs
        $log1 = SystemLog::create([
            'type' => 'test_error',
            'title' => 'Test Error 1',
            'message' => 'Something failed',
            'status' => 'error',
            'action_id' => 'test:action:1',
            'started_at' => now(),
        ]);
        $log2 = SystemLog::create([
            'type' => 'test_error',
            'title' => 'Test Error 2',
            'message' => 'Something else failed',
            'status' => 'error',
            'action_id' => 'test:action:2',
            'started_at' => now(),
        ]);

        // Execute as if in background worker (__is_background_job = true)
        $response = AiSystemFuncs::executeManageSystemLogs([
            'action' => 'resolve',
            'target_scope' => 'all',
            'search_type' => 'test_error',
            '__is_background_job' => true
        ]);

        $this->assertEquals('success', $response['status']);
        $this->assertStringContainsString('als GELÖST markiert', $response['message']);

        $log1->refresh();
        $log2->refresh();
        $this->assertEquals('success', $log1->status);
        $this->assertEquals('success', $log2->status);
        $this->assertStringStartsWith('[GELÖST] ', $log1->title);
        $this->assertStringStartsWith('[GELÖST] ', $log2->title);
    }

    /**
     * Test that AiWidget reacts to TaskUpdated event and dispatches ai-task-completed.
     */
    public function test_ai_widget_dispatches_event_on_task_completion(): void
    {
        $task = AiWorkspaceTask::create([
            'prompt' => '164 Logs auf gelöst setzen',
            'status' => 'completed',
            'response_content' => 'Erfolgreich! Es wurden 164 System-Logs als GELÖST markiert.'
        ]);

        Livewire::test(AiWidget::class)
            ->call('handleTaskUpdated', [
                'task_id' => $task->id,
                'status' => 'completed',
                'prompt' => $task->prompt,
                'response_content' => $task->response_content,
            ])
            ->assertDispatched('ai-task-completed', function ($eventName, $params) use ($task) {
                return ($params['task']['id'] ?? '') === $task->id
                    && ($params['task']['status'] ?? '') === 'completed'
                    && str_contains($params['task']['response'] ?? '', '164 System-Logs');
            });
    }
}

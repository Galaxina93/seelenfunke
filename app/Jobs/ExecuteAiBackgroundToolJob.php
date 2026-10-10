<?php

namespace App\Jobs;

use App\Events\AiFrontendEvent;
use App\Events\TaskUpdated;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiWorkspaceTask;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteAiBackgroundToolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public string $taskId,
        public string $toolName,
        public array $toolArgs = [],
        public ?string $agentId = null,
        public ?string $sessionId = null
    ) {}

    public function handle(): void
    {
        $task = AiWorkspaceTask::find($this->taskId);
        if (!$task) {
            Log::error("ExecuteAiBackgroundToolJob: Task {$this->taskId} not found.");
            return;
        }

        $task->update(['status' => 'processing']);
        TaskUpdated::dispatch($task);

        if ($this->sessionId) {
            config(['ai.current_session_id' => $this->sessionId]);
        }

        $agent = null;
        if ($this->agentId) {
            $agent = AiAgent::find($this->agentId);
        }
        if (!$agent && $task->assigned_agent_id) {
            $agent = $task->agent;
        }

        $startTime = microtime(true);

        try {
            // Flag so tool knows it's executing in worker thread and should execute directly without re-queuing
            $execArgs = array_merge($this->toolArgs, ['__is_background_job' => true]);

            $result = AIFunctionsRegistry::execute($this->toolName, $execArgs, $agent);

            $duration = round(microtime(true) - $startTime, 2);

            $isError = !empty($result['error']) || (($result['status'] ?? '') === 'error');
            $status = $isError ? 'failed' : 'completed';

            $message = '';
            if (is_array($result)) {
                $message = $result['message'] ?? ($result['result'] ?? ($result['output'] ?? json_encode($result, JSON_UNESCAPED_UNICODE)));
                if (is_array($message)) {
                    $message = json_encode($message, JSON_UNESCAPED_UNICODE);
                }
            } elseif (is_string($result)) {
                $message = $result;
            }

            $metadata = $task->ui_metadata ?? [];
            $metadata['duration_seconds'] = $duration;
            $metadata['raw_result'] = $result;

            $task->update([
                'status' => $status,
                'response_content' => (string) $message,
                'completed_at' => now(),
                'ui_metadata' => $metadata,
            ]);

            TaskUpdated::dispatch($task);

            if (class_exists(AiFrontendEvent::class)) {
                broadcast(new AiFrontendEvent('ai-background-task-completed', [
                    'task_id' => $task->id,
                    'prompt' => $task->prompt,
                    'status' => $status,
                    'response' => (string) $message,
                    'agent_id' => $agent?->id,
                    'agent_name' => $agent?->name ?? 'System',
                ]));
            }

        } catch (\Throwable $e) {
            Log::error("ExecuteAiBackgroundToolJob Exception: " . $e->getMessage(), [
                'task_id' => $this->taskId,
                'tool' => $this->toolName,
                'trace' => $e->getTraceAsString(),
            ]);

            $task->update([
                'status' => 'failed',
                'response_content' => "Fehler bei der Ausführung: " . $e->getMessage(),
                'completed_at' => now(),
            ]);

            TaskUpdated::dispatch($task);
        }
    }
}

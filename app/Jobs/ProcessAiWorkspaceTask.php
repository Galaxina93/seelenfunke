<?php

namespace App\Jobs;

use App\Models\Ai\AiWorkspaceTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAiWorkspaceTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 Minutes max LLM inference
    public $tries = 2; // Allow one retry before permanent fail

    public function __construct(
        public AiWorkspaceTask $task
    ) {}

    public function handle(): void
    {
        Log::info("Processing AI Workspace Task: {$this->task->id}");

        // Mark as processing
        $this->task->update(['status' => 'processing']);
        \App\Events\TaskUpdated::dispatch($this->task);

        $agent = $this->task->agent;
        if (!$agent) {
             Log::error("No agent assigned to Workspace Task: {$this->task->id}");
             $this->task->update(['status' => 'failed', 'response_content' => 'Error: No agent assigned.']);
             \App\Events\TaskUpdated::dispatch($this->task);
             return;
        }

        try {
            $contentArray = [];
            $contentArray[] = ['type' => 'text', 'text' => "SWARM ORCHESTRATOR INSTRUCTION:\nDo your best to fulfill the task below. If you need help from a different expert, use the swarm_delegate_task tool to spawn a sub-task for them.\n\nTASK: " . $this->task->prompt];

            // Server Side Files via KI Map or @Mention
            if (!empty($this->task->ui_metadata['attachments'])) {
                foreach ($this->task->ui_metadata['attachments'] as $fPath) {
                    try {
                        $fileStr = file_get_contents($fPath);
                        $ext = strtolower(pathinfo($fPath, PATHINFO_EXTENSION));

                        if (in_array($ext, ['png','jpg','jpeg','webp','gif'])) {
                            $b64 = base64_encode($fileStr);
                            $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                            $contentArray[] = [
                                'type' => 'image_url',
                                'image_url' => ['url' => "data:{$mime};base64,{$b64}"]
                            ];
                        } else {
                            $contentArray[] = [
                                'type' => 'text',
                                'text' => "\n\n--- [FILE ATTACHMENT: ".basename($fPath)."] ---\n" . $fileStr . "\n--- [END OF FILE] ---\n\n"
                            ];
                        }
                    } catch (\Exception $e) {}
                }
            }

            // Local Uploads via Chat
            if (!empty($this->task->ui_metadata['local_uploads'])) {
                foreach ($this->task->ui_metadata['local_uploads'] as $upl) {
                    try {
                        $fPath = storage_path('app/public/' . $upl['path']);
                        $fileStr = file_get_contents($fPath);
                        $ext = strtolower(pathinfo($fPath, PATHINFO_EXTENSION));

                        if (in_array($ext, ['png','jpg','jpeg','webp','gif'])) {
                            $b64 = base64_encode($fileStr);
                            $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                            $contentArray[] = [
                                'type' => 'image_url',
                                'image_url' => ['url' => "data:{$mime};base64,{$b64}"]
                            ];
                        } else {
                            $contentArray[] = [
                                'type' => 'text',
                                'text' => "\n\n--- [UPLOADED FILE: ".basename($upl['name'])."] ---\n" . $fileStr . "\n--- [END OF FILE] ---\n\n"
                            ];
                        }
                    } catch (\Exception $e) {}
                }
            }

            $messages = [
                [
                    'role' => 'user',
                    'content' => $contentArray
                ]
            ];

            $apiService = \App\Services\AI\AiAgentFactory::make($agent);

            // Verwende ask(), um das Tool-Schema nutzbar zu machen. ask() blockiert nicht die Queue.
            // Ohne Stream Callback laufen die tool-events intern durch und er returnt am ende das gesamt ergebnis
            $responseArray = $apiService->ask($messages);
            $response = $responseArray['response'] ?? 'Keine Textantwort.';

            if (str_contains(strtoupper($response), '[SKIP]')) {
                Log::info("Task {$this->task->id} was aborted by User.");
                $this->task->update([
                    'status' => 'pending',   // Revert to pending
                    'assigned_agent_id' => null, // Free it
                ]);
                return;
            }

            // Complete the task
            $this->task->update([
                'status' => 'completed',
                'response_content' => $response,
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $this->task->update(['status' => 'failed', 'response_content' => 'Systemfehler: ' . $e->getMessage()]);
        }

        \App\Events\TaskUpdated::dispatch($this->task);

        // Log::info("Completed AI Workspace Task: {$this->task->id}");
    }
}

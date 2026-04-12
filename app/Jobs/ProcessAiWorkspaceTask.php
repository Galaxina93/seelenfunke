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

    public function __construct(
        public AiWorkspaceTask $task
    ) {}

    public function handle(): void
    {
        Log::info("Processing AI Workspace Task: {$this->task->id}");
        
        // Mark as processing
        $this->task->update(['status' => 'processing']);
        \App\Events\TaskUpdated::dispatch($this->task);
        
        // TODO: Replace with actual Gemini/LLM AI routing
        sleep(3); 
        
        // Complete the task
        $this->task->update([
            'status' => 'completed',
            'response_content' => "Ich bin dein Agent und das ist meine Antwort auf: " . $this->task->prompt,
            'completed_at' => now(),
        ]);
        \App\Events\TaskUpdated::dispatch($this->task);
        
        Log::info("Completed AI Workspace Task: {$this->task->id}");
    }
}

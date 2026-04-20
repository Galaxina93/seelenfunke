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
            $metadata = $this->task->ui_metadata ?? [];
            $plan = $metadata['execution_plan'] ?? [];
            $history = $metadata['llm_history'] ?? [];
            
            $apiService = \App\Services\AI\AiAgentFactory::make($agent);

            // -------------------------------------------------------------
            // PHASE 1: DRAFTING THE PLAN
            // -------------------------------------------------------------
            if (empty($plan)) {
                $contentArray = [];
                $contentArray[] = ['type' => 'text', 'text' => "SWARM ORCHESTRATOR INSTRUCTION:\nDu bist im PLANUNGSMODUS. Bevor du die eigentliche Aufgabe startest, musst du die Aufgabe zwingend in Einzelschritte zerlegen.\nDeine Ausgabe darf AUSSCHLIESSLICH ein valides JSON-Array sein, nichts anderes! Formatiere es als: [{\"id\": 1, \"description\": \"...\"}, {\"id\": 2, \"description\": \"...\"}]. VERWENDE KEINE WERKZEUGE JETZT!\n\nAUFGABE: " . $this->task->prompt];

                // Append Attachments for Context during planning
                if (!empty($metadata['attachments'])) {
                    foreach ($metadata['attachments'] as $fPath) {
                        try {
                            $fileStr = file_get_contents($fPath);
                            $ext = strtolower(pathinfo($fPath, PATHINFO_EXTENSION));
                            if (in_array($ext, ['png','jpg','jpeg','webp','gif'])) {
                                $b64 = base64_encode($fileStr);
                                $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                                $contentArray[] = ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$b64}"]];
                            } else {
                                $contentArray[] = ['type' => 'text', 'text' => "\n\n--- [FILE ATTACHMENT: ".basename($fPath)."] ---\n" . $fileStr . "\n--- [END OF FILE] ---\n\n"];
                            }
                        } catch (\Exception $e) {}
                    }
                }

                if (!empty($metadata['local_uploads'])) {
                    foreach ($metadata['local_uploads'] as $upl) {
                        try {
                            $fPath = storage_path('app/public/' . $upl['path']);
                            $fileStr = file_get_contents($fPath);
                            $ext = strtolower(pathinfo($fPath, PATHINFO_EXTENSION));
                            if (in_array($ext, ['png','jpg','jpeg','webp','gif'])) {
                                $b64 = base64_encode($fileStr);
                                $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                                $contentArray[] = ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$b64}"]];
                            } else {
                                $contentArray[] = ['type' => 'text', 'text' => "\n\n--- [UPLOADED FILE: ".basename($upl['name'])."] ---\n" . $fileStr . "\n--- [END OF FILE] ---\n\n"];
                            }
                        } catch (\Exception $e) {}
                    }
                }

                $messages = [['role' => 'user', 'content' => $contentArray]];

                // Force LLM to plan without tools using a mocked empty toolset
                $agentClone = clone $agent;
                $agentClone->tools = collect([]);
                $planningApiService = \App\Services\AI\AiAgentFactory::make($agentClone);

                $responseArray = $planningApiService->ask($messages);
                $response = $responseArray['response'] ?? '[]';

                // Robust JSON Cleanup
                $jsonStr = preg_replace('/```json/i', '', $response);
                $jsonStr = preg_replace('/```/i', '', $jsonStr);
                $jsonStr = trim($jsonStr);
                
                $parsedPlan = json_decode($jsonStr, true);
                if (is_array($parsedPlan)) {
                    foreach ($parsedPlan as $idx => $step) {
                        $plan[] = [
                            'id' => $step['id'] ?? ($idx + 1),
                            'description' => $step['description'] ?? 'Unbekannter Schritt',
                            'status' => 'pending',
                            'result' => null
                        ];
                    }
                } else {
                    // Fallback if AI fails JSON parsing
                    $plan[] = [
                        'id' => 1,
                        'description' => 'Führe die Aufgabe aus: ' . substr($this->task->prompt, 0, 100) . '...',
                        'status' => 'pending',
                        'result' => null
                    ];
                }

                $history = $responseArray['history'] ?? $messages; // start history trace
                
                $metadata['execution_plan'] = $plan;
                $metadata['llm_history'] = $history;
                $this->task->update(['ui_metadata' => $metadata]);
                \App\Events\TaskUpdated::dispatch($this->task);

                $settings = \App\Models\Ai\AiUserWorkspaceSetting::first();
                $autoApprove = $settings->auto_approve_execution_plan ?? false;
                if (!$autoApprove) {
                    $this->task->update(['status' => 'awaiting_approval']);
                    \App\Events\TaskUpdated::dispatch($this->task);
                    return; // Pause the queue job
                }
            }

            // -------------------------------------------------------------
            // PHASE 2: EXECUTION LOOP
            // -------------------------------------------------------------
            $allCompleted = true;
            $finalSummaryNeeded = empty($this->task->response_content);

            foreach ($plan as $index => &$step) {
                if ($step['status'] === 'completed' || $step['status'] === 'failed') {
                    continue;
                }

                $allCompleted = false;

                // Mark current step as processing
                $step['status'] = 'processing';
                $metadata['execution_plan'] = $plan;
                $this->task->update(['ui_metadata' => $metadata]);
                \App\Events\TaskUpdated::dispatch($this->task);

                // Ask AI to execute this step exclusively
                $history[] = [
                    'role' => 'user', 
                    'content' => "Führe nun folgenden Schritt aus (und nutze falls nötig deine Werkzeuge dafür): \nSchritt " . $step['id'] . ": " . $step['description'] . "\nGib eine kurze Bestätigung oder das Ergebnis des Schritts zurück, wenn du fertig bist."
                ];

                $stepResponseArray = $apiService->ask($history);
                $stepResult = $stepResponseArray['response'] ?? 'Keine Antwort.';
                
                if (str_contains(strtoupper($stepResult), '[SKIP]')) {
                    $this->task->update(['status' => 'pending', 'assigned_agent_id' => null]);
                    return;
                }

                $history = $stepResponseArray['history'] ?? $history;
                
                $step['status'] = 'completed';
                $step['result'] = $stepResult;
                $metadata['execution_plan'] = $plan;
                $metadata['llm_history'] = $history;
                
                $this->task->update(['ui_metadata' => $metadata]);
                \App\Events\TaskUpdated::dispatch($this->task);
            }

            // -------------------------------------------------------------
            // PHASE 3: FINALIZATION
            // -------------------------------------------------------------
            if ($allCompleted && $finalSummaryNeeded) {
                $history[] = [
                    'role' => 'user',
                    'content' => "Alle Schritte sind abgeschlossen. Bitte schreibe nun eine abschließende Zusammenfassung der erledigten Aufgabe für den User."
                ];
                $finalRespArray = $apiService->ask($history);
                $finalResponse = $finalRespArray['response'] ?? 'Aufgabe erfolgreich abgeschlossen.';

                $this->task->update([
                    'status' => 'completed',
                    'response_content' => $finalResponse,
                    'completed_at' => now(),
                ]);
            } else {
                // Should only be reached if $allCompleted is false and we loop again, but we just completed all in the foreach.
                // Wait, if foreach completes them all, the loop finishes. It should always hit $allCompleted if it doesn't crash.
                if (count(array_filter($plan, fn($s) => $s['status'] === 'pending')) === 0) {
                     $this->task->update([
                         'status' => 'completed',
                         'response_content' => 'Alle definierten Schritte wurden in der Historie abgeschlossen.',
                         'completed_at' => now(),
                     ]);
                }
            }

        } catch (\Exception $e) {
            $this->task->update(['status' => 'failed', 'response_content' => 'Systemfehler beim Ausführen des Plans: ' . $e->getMessage()]);
        }

        \App\Events\TaskUpdated::dispatch($this->task);
    }
}

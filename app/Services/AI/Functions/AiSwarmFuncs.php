<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\AiWorkspaceTask;
use App\Models\Ai\AiAgent;

trait AiSwarmFuncs
{
    /**
     * Schickt ein "Swarm Delegate" Schema an das LLM, um Sub-Tasks im System anzulegen.
     * @return array
     */
    public static function getAiSwarmFuncsSchema(): array
    {
        return [
            [
                'name' => 'swarm_delegate_task',
                'description' => 'Ertellt eine untergeordnete Sub-Task für das aktuelle Projekt und delegiert sie an einen anderen Schwarm-Agenten. Nutze dies, um Arbeit wie Coden oder Prüfen an Fach-Kollaborateure auszulagern.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'agent_id' => [
                            'type' => 'string',
                            'description' => 'Die ID des Ziel-Agenten (z.B. Coder, DB-Expert), an den du die Teilaufgabe übergeben möchtest.',
                        ],
                        'prompt' => [
                            'type' => 'string',
                            'description' => 'Die detaillierte Anweisung oder Fragestellung an diesen Agenten.',
                        ]
                    ],
                    'required' => ['agent_id', 'prompt']
                ],
                'callable' => [self::class, 'executeSwarmDelegateTask']
            ],
            [
                'name' => 'swarm_get_available_agents',
                'description' => 'Holt eine Liste aller im System aktiven Spezial-Agenten samt ID und Rolle, an die du Tasks delegieren kannst.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeSwarmGetAvailableAgents']
            ]
        ];
    }

    /**
     * Erstellt eine Sub-Task und reiht sie in den Swarm ein.
     */
    public static function executeSwarmDelegateTask(array $args): array
    {
        $agentId = $args['agent_id'] ?? null;
        $prompt = $args['prompt'] ?? '';

        if (!$agentId || !$prompt) {
            return ['status' => 'error', 'message' => 'Agent ID oder Prompt fehlerhaft.'];
        }

        $agent = AiAgent::find($agentId);
        if (!$agent) {
             return ['status' => 'error', 'message' => 'Spezial-Agent nicht gefunden.'];
        }

        try {
            $task = AiWorkspaceTask::create([
                'prompt' => $prompt,
                'status' => 'pending', // Pending triggers front-end animation, then job picks it up if assigned
                'assigned_agent_id' => $agentId,
                // In einer echten Umgebung müssten wir die parent_task_id des aufrufenden Agenten injizieren. 
                // Für dieses Proxy-Konzept verknüpfen wir theorethisch im Session-State.
            ]);

            \App\Events\TaskUpdated::dispatch($task);
            \App\Jobs\ProcessAiWorkspaceTask::dispatch($task);

            return [
                'status' => 'success',
                'message' => 'Sub-Task erfolgreich an ' . $agent->name . ' delegiert. Die Swarm-Oberfläche wird es dem Nutzer anzeigen.',
                'task_id' => $task->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Holt eine Liste aller Agenten.
     */
    public static function executeSwarmGetAvailableAgents(array $args): array
    {
        $agents = AiAgent::where('is_active', true)->get(['id', 'name', 'role_description']);
        return [
            'status' => 'success',
            'available_swarm_nodes' => $agents->toArray()
        ];
    }
}

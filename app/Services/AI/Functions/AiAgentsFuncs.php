<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiInteraction;

trait AiAgentsFuncs
{
    public static function getAiAgentsFuncsSchema(): array
    {
        return [
            [
                'name' => 'get_ai_company_structure',
                'description' => 'Ruft das komplette KI-Organigramm des Unternehmens mit allen Abteilungen und den darin zugewiesenen Agenten ab.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeGetAiCompanyStructure']
            ],
            [
                'name' => 'move_agent_to_department',
                'description' => 'Verschiebt einen existierenden KI-Agenten in eine andere Abteilung innerhalb des Organigramms.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'agent_name' => ['type' => 'string', 'description' => 'Der Name des Agenten (z.B. Marketi, Buchi).'],
                        'department_name' => ['type' => 'string', 'description' => 'Der Name der Ziel-Abteilung (z.B. Support, Leitung, Produkte) oder "null" fuer Freie Agenten.']
                    ],
                    'required' => ['agent_name', 'department_name']
                ],
                'callable' => [self::class, 'executeMoveAgentToDepartment']
            ],
            [
                'name' => 'get_agent_roles',
                'description' => 'Ruft die vordefinierten KI-Rollen und Instruktionen ab (Rollenverwaltung).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeGetAgentRoles']
            ],
            [
                'name' => 'analyze_agents_activity',
                'description' => 'Analysiert die Aktivitaet und Performance der KI-Agenten basierend auf Chat Logs, Interaktionen und verbrauchten System-Tokens.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'description' => 'Anzahl der vergangenen Tage für die Analyse.', 'default' => 7]
                    ]
                ],
                'callable' => [self::class, 'executeAnalyzeAgentsActivity']
            ]
        ];
    }

    public static function executeGetAiCompanyStructure(array $args): array
    {
        $departments = AiDepartment::with('agents:id,name,role_description,ai_department_id')->orderBy('order_index')->get();
        $freeAgents = AiAgent::whereNull('ai_department_id')->get(['id', 'name', 'role_description']);
        
        $result = [
            'Organigramm_Abteilungen' => $departments->toArray(), 
            'Stabsstelle_Freie_Agenten' => $freeAgents->toArray()
        ];
        return ['status' => 'success', 'data' => $result];
    }

    public static function executeMoveAgentToDepartment(array $args): array
    {
        $agentName = $args['agent_name'] ?? null;
        $deptName = $args['department_name'] ?? null;

        if (!$agentName) return ['status' => 'error', 'message' => "Fehler: agent_name fehlt."];

        $agent = AiAgent::where('name', 'LIKE', '%' . $agentName . '%')->first();
        if (!$agent) return ['status' => 'error', 'message' => "Fehler: Agent '$agentName' nicht gefunden."];

        if ($deptName && strtolower($deptName) !== 'null' && strtolower($deptName) !== 'freie agenten' && strtolower($deptName) !== 'stabsstelle') {
            $dept = AiDepartment::where('name', 'LIKE', '%' . $deptName . '%')->first();
            if (!$dept) {
                return ['status' => 'error', 'message' => "Fehler: Abteilung '$deptName' nicht gefunden. Prüfe das Organigramm per get_ai_company_structure."];
            }
            
            $agent->update(['ai_department_id' => $dept->id]);
            return ['status' => 'success', 'message' => "Erfolg: Agent {$agent->name} wurde erfolgreich in die Abteilung {$dept->name} verschoben.", 'ui_action' => 'reload_organigram'];
        } else {
            $agent->update(['ai_department_id' => null]);
            return ['status' => 'success', 'message' => "Erfolg: Agent {$agent->name} wurde aus seiner Abteilung entfernt und ist nun ein freier Agent (Stabsstelle).", 'ui_action' => 'reload_organigram'];
        }
    }

    public static function executeGetAgentRoles(array $args): array
    {
        if (class_exists(AiRole::class)) {
            $roles = AiRole::select('id', 'name', 'description')->get();
            return ['status' => 'success', 'data' => ['Rollen' => $roles->toArray()]];
        }
        return ['status' => 'error', 'message' => "AiRole Modell nicht gefunden."];
    }

    public static function executeAnalyzeAgentsActivity(array $args): array
    {
        $days = $args['days'] ?? 7;
        $startDate = now()->subDays($days);
        
        if (class_exists(AiInteraction::class)) {
            $interactions = AiInteraction::where('created_at', '>=', $startDate)
                ->selectRaw('ai_agent_id, count(id) as total_messages, sum(total_tokens) as total_tokens_used')
                ->groupBy('ai_agent_id')
                ->with('agent:id,name')
                ->get();
                
            return ['status' => 'success', 'data' => ['Aktivitaets_Analyse' => $interactions->toArray(), 'Zeitraum_Tage' => $days]];
        }
        return ['status' => 'error', 'message' => "AiInteraction Logs nicht verfuegbar oder System loggt aktuell keine Tokens."];
    }
}

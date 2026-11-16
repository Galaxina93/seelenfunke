<?php

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\FunkiraSkillPoolService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (!Schema::hasTable('ai_roles') || !Schema::hasTable('ai_tools') || !Schema::hasTable('ai_role_tool')) {
            return;
        }

        $this->applyUpgrade();
    }

    public function applyUpgrade(): void
    {
        if (!Schema::hasTable('ai_roles') || !Schema::hasTable('ai_tools') || !Schema::hasTable('ai_role_tool')) {
            return;
        }

        $schemaByName = [];
        if (class_exists(AIFunctionsRegistry::class)) {
            foreach (AIFunctionsRegistry::getSchema() as $toolSchema) {
                $fnName = $toolSchema['function']['name'] ?? null;
                if (is_string($fnName) && $fnName !== '') {
                    $schemaByName[$fnName] = $toolSchema['function']['description'] ?? 'System-Werkzeug';
                }
            }
        }

        $serverStatusIdentifiers = [
            'system_get_server_status',
            'system_get_health',
        ];

        $executiveIdentifiers = array_values(array_unique(array_merge(
            class_exists(FunkiraSkillPoolService::class) ? FunkiraSkillPoolService::getCoreToolIdentifiers() : [],
            $serverStatusIdentifiers,
            [
                'system_get_error_sentinel',
                'master_get_next_best_actions',
                class_exists(FunkiraSkillPoolService::class) ? FunkiraSkillPoolService::EQUIP_TOOL_NAME : 'funkira_equip_skill_pool',
            ]
        )));

        $toolIdMap = [];
        foreach ($executiveIdentifiers as $identifier) {
            $tool = AiTool::updateOrCreate(
                ['identifier' => $identifier],
                [
                    'name' => ucwords(str_replace('_', ' ', $identifier)),
                    'description' => $schemaByName[$identifier] ?? 'System- & Server-Monitoring-Werkzeug',
                ]
            );
            $toolIdMap[$identifier] = $tool->id;
        }

        $serverStatusToolIds = array_values(array_intersect_key($toolIdMap, array_flip($serverStatusIdentifiers)));
        $executiveToolIds = array_values($toolIdMap);

        $teamleiterRole = AiRole::firstOrCreate(
            ['name' => 'Teamleiter'],
            ['description' => 'Zuständig für Systemsteuerung, Aufgabenverteilung, strategische Entscheidungen über alle Agenten hinweg.']
        );

        $executiveRoleIds = [$teamleiterRole->id];

        if (Schema::hasTable('ai_agents') && DB::table('ai_agents')->exists()) {
            $execAgents = AiAgent::whereRaw('LOWER(name) IN (?, ?, ?)', ['falcone', 'falcon', 'funkira'])->get();
            foreach ($execAgents as $execAgent) {
                if (empty($execAgent->ai_role_id)) {
                    $execAgent->update(['ai_role_id' => $teamleiterRole->id]);
                    $executiveRoleIds[] = $teamleiterRole->id;
                } else {
                    $executiveRoleIds[] = $execAgent->ai_role_id;
                }
            }
        }

        $executiveRoleIds = array_values(array_unique(array_filter($executiveRoleIds)));

        foreach (AiRole::whereIn('id', $executiveRoleIds)->get() as $execRole) {
            if (strcasecmp($execRole->name, 'Supporter') === 0) {
                continue;
            }
            $execRole->tools()->syncWithoutDetaching($executiveToolIds);
        }

        $adminLeadershipRoleNames = [
            'teamleiter',
            'systemadmin',
            'agentenmanager',
            'leiter globale planung',
        ];

        foreach (AiRole::all() as $role) {
            $lowerName = mb_strtolower(trim((string) $role->name), 'UTF-8');
            if ($lowerName === 'supporter') {
                continue;
            }

            $isAdminOrLeadershipRole = in_array($lowerName, $adminLeadershipRoleNames, true)
                || str_contains($lowerName, 'leitung')
                || str_contains($lowerName, 'admin')
                || str_contains($lowerName, 'ceo')
                || str_contains($lowerName, 'executive')
                || str_contains($lowerName, 'mitgründer')
                || str_contains($lowerName, 'mitgruender');

            if ($isAdminOrLeadershipRole && $serverStatusToolIds !== []) {
                $role->tools()->syncWithoutDetaching($serverStatusToolIds);
            }
        }

        Cache::forget('ai_agent_capabilities_map');
    }

    public function down(): void
    {
        // Idempotentes Executive-Server-Status-Upgrade bleibt dauerhaft bestehen.
    }
};

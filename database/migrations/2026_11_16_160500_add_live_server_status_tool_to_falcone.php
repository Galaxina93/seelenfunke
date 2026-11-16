<?php

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\FunkiraSkillPoolService;
use Database\Seeders\AiAgentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (!Schema::hasTable('ai_agents') || !DB::table('ai_agents')->exists()) {
            return;
        }

        $this->applyUpgrade();
    }

    public function applyUpgrade(): void
    {
        if (!Schema::hasTable('ai_agents')) {
            return;
        }

        if (Schema::hasTable('ai_roles') && Schema::hasTable('ai_tools') && Schema::hasTable('ai_role_tool') && class_exists(AIFunctionsRegistry::class)) {
            $role = AiRole::where('name', 'Teamleiter')->first();
            if ($role) {
                $coreIdentifiers = FunkiraSkillPoolService::getCoreToolIdentifiers();
                $schemaByName = [];
                foreach (AIFunctionsRegistry::getSchema() as $toolSchema) {
                    $fnName = $toolSchema['function']['name'] ?? null;
                    if (is_string($fnName) && $fnName !== '') {
                        $schemaByName[$fnName] = $toolSchema['function']['description'] ?? 'Keine Beschreibung';
                    }
                }

                $coreToolIds = [];
                foreach ($coreIdentifiers as $identifier) {
                    $tool = AiTool::updateOrCreate(
                        ['identifier' => $identifier],
                        [
                            'name' => ucwords(str_replace('_', ' ', $identifier)),
                            'description' => $schemaByName[$identifier] ?? 'Keine Beschreibung',
                        ]
                    );
                    $coreToolIds[] = $tool->id;
                }

                if ($coreToolIds !== []) {
                    $role->tools()->syncWithoutDetaching($coreToolIds);
                }
            }
        }

        $falcone = AiAgent::where('name', 'Falcone')->first();
        if ($falcone) {
            $falcone->update([
                'role_description' => AiAgentSeeder::buildFalconeRoleDescription(),
                'system_prompt' => AiAgentSeeder::buildFalconeSupremeSystemPrompt(),
            ]);
        }
    }

    public function down(): void
    {
        // Idempotentes Executive-Monitoring-Upgrade bleibt dauerhaft bestehen.
    }
};

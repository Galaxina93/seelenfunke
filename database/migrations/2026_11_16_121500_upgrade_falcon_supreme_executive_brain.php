<?php

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
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

        $departmentId = null;
        if (Schema::hasTable('ai_departments')) {
            $dept = AiDepartment::where('id', '019d0000-0000-0000-0000-000000000000')
                ->orWhere('name', 'Leitung')
                ->first();

            if (!$dept) {
                $dept = AiDepartment::updateOrCreate(
                    ['id' => '019d0000-0000-0000-0000-000000000000'],
                    [
                        'name' => 'Leitung',
                        'description' => 'Unternehmensführung, Vision und zentrale Steuerung',
                        'icon' => 'bolt',
                        'color' => 'primary',
                        'order_index' => 1,
                    ]
                );
            }

            $departmentId = $dept->id;
        }

        $roleId = null;
        if (Schema::hasTable('ai_roles')) {
            $role = AiRole::updateOrCreate(
                ['name' => 'Teamleiter'],
                ['description' => 'Zuständig für Systemsteuerung, Aufgabenverteilung, strategische Entscheidungen über alle Agenten hinweg.']
            );
            $roleId = $role->id;

            if (Schema::hasTable('ai_tools') && Schema::hasTable('ai_role_tool') && class_exists(AIFunctionsRegistry::class)) {
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
                    $role->tools()->sync($coreToolIds);
                }
            }
        }

        AiAgent::updateOrCreate(
            ['name' => 'Falcon'],
            [
                'ai_role_id' => $roleId,
                'ai_department_id' => $departmentId,
                'wake_word' => 'Falcon',
                'role_description' => AiAgentSeeder::buildFalconRoleDescription(),
                'system_prompt' => AiAgentSeeder::buildFalconSupremeSystemPrompt(),
                'model' => 'gemini-3.5-flash',
                'temperature' => 0.3,
                'is_active' => true,
                'is_in_chat' => false,
                'color' => 'amber-500',
                'icon' => 'sparkles',
                'profile_picture' => 'shop/ai/images/falcon_selfie.png',
                'tts_enabled' => true,
                'tts_provider' => 'gemini_native',
                'tts_voice' => 'Orus',
            ]
        );
    }

    public function down(): void
    {
        // Das Supreme-Executive-Profil von Falcon bleibt als aktive Leitstand-Konfiguration erhalten.
    }
};

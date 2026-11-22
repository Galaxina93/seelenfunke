<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_tools') || !Schema::hasTable('ai_roles') || !Schema::hasTable('ai_role_tool')) {
            return;
        }

        $now = now();
        $requiredTools = [
            'ag_send_to_antigravity' => [
                'name' => 'Direktnachricht & Bug-Auftrag an Antigravity senden',
                'description' => 'Sendet genau wie Falcone ein Problem, einen Bug-Bericht oder einen Entwicklungsauftrag direkt in das Antigravity-Postfach.',
            ],
            'ag_check_antigravity_status' => [
                'name' => 'Antigravity Postfach- & Auftragsstatus prüfen',
                'description' => 'Prüft den aktuellen Bearbeitungsstatus, Antworten und offene Rückfragen im Antigravity-Entwickler-Postfach.',
            ],
        ];

        $toolIds = [];
        foreach ($requiredTools as $identifier => $meta) {
            $existing = DB::table('ai_tools')->where('identifier', $identifier)->first();
            if ($existing) {
                $toolIds[] = $existing->id;
            } else {
                $id = (string) Str::uuid();
                DB::table('ai_tools')->insert([
                    'id' => $id,
                    'name' => $meta['name'],
                    'identifier' => $identifier,
                    'description' => $meta['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $toolIds[] = $id;
            }
        }

        $targetRoleIds = DB::table('ai_roles')
            ->where('name', 'Teamleiter')
            ->pluck('id')
            ->all();

        if (Schema::hasTable('ai_agents')) {
            $execRoleIds = DB::table('ai_agents')
                ->whereIn('name', ['Falcone', 'Funkira'])
                ->whereNotNull('ai_role_id')
                ->pluck('ai_role_id')
                ->all();
            $targetRoleIds = array_values(array_unique(array_merge($targetRoleIds, $execRoleIds)));

            // Aktualisiere veralteten E-Mail-Fehlerhinweis in bestehenden Funkira-Prompts auf direkte Antigravity-Meldung
            $funkiraRow = DB::table('ai_agents')->where('name', 'Funkira')->first();
            if ($funkiraRow && is_string($funkiraRow->system_prompt) && str_contains($funkiraRow->system_prompt, 'FEHLERMELDUNG-PFLICHT: Sollte bei deiner Arbeit etwas schief laufen')) {
                $updatedPrompt = preg_replace(
                    '/5\.\s*FEHLERMELDUNG-PFLICHT:.*?(?=\n6\.\s*DEFENSIVE SHIELD)/su',
                    "5. FEHLERMELDUNG & DIREKTLEITUNG ZU ANTIGRAVITY: Sollte bei deiner Arbeit ein technisches Problem, ein Werkzeug-Fehler oder ein Code-/System-Bug auftreten, übermittle die präzise Fehlerdiagnose (Titel, Fehlermeldung, betroffener Bereich) für Administratorin Alina direkt über `ag_send_to_antigravity` in den passenden Kanal des Antigravity-Postfachs (`shop`, `android`, `minipc`, `buchhaltung` oder `leitung`, `execution_mode=\"direct\"`) und informiere Alina kurz und klar darüber. Über `ag_check_antigravity_status` kannst du jederzeit den Status im Antigravity-Leitstand prüfen.",
                    $funkiraRow->system_prompt
                );
                if (is_string($updatedPrompt) && $updatedPrompt !== '') {
                    DB::table('ai_agents')->where('id', $funkiraRow->id)->update([
                        'system_prompt' => $updatedPrompt,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $supporterRoleIds = DB::table('ai_roles')
            ->where('name', 'Supporter')
            ->pluck('id')
            ->all();

        if (!empty($supporterRoleIds) && !empty($toolIds)) {
            DB::table('ai_role_tool')
                ->whereIn('ai_role_id', $supporterRoleIds)
                ->whereIn('ai_tool_id', $toolIds)
                ->delete();
        }

        $targetRoleIds = array_values(array_diff($targetRoleIds, $supporterRoleIds));
        foreach ($targetRoleIds as $roleId) {
            foreach ($toolIds as $toolId) {
                $exists = DB::table('ai_role_tool')
                    ->where('ai_role_id', $roleId)
                    ->where('ai_tool_id', $toolId)
                    ->exists();
                if (!$exists) {
                    DB::table('ai_role_tool')->insert([
                        'ai_role_id' => $roleId,
                        'ai_tool_id' => $toolId,
                    ]);
                }
            }
        }

        try {
            Cache::forget('ai_functions_schema_cache');
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        // Idempotente System-Erweiterung
    }
};

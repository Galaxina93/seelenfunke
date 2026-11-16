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
            'system_search_web' => [
                'name' => 'Websuche (Vertex Grounding & Echtzeit-Kaskade)',
                'description' => 'Durchsucht das Internet in Echtzeit über die 3-stufige Quota-freie Web-Such-Kaskade.',
            ],
            'system_read_web_url' => [
                'name' => 'Webseite auslesen (SSRF-geschützt)',
                'description' => 'Liest den bereinigten Textinhalt einer öffentlichen URL sicher aus.',
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
            ->whereIn('name', ['Teamleiter', 'Systemadmin', 'Leiter Globale Planung'])
            ->pluck('id')
            ->all();

        if (Schema::hasTable('ai_agents')) {
            $execRoleIds = DB::table('ai_agents')
                ->whereIn('name', ['Falcone', 'Funkira'])
                ->whereNotNull('ai_role_id')
                ->pluck('ai_role_id')
                ->all();
            $targetRoleIds = array_values(array_unique(array_merge($targetRoleIds, $execRoleIds)));
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
            Cache::forget('ai_agent_capabilities_map');
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        // Idempotente Berechtigungs-Synchronisation, kein destruktiver Rollback erforderlich.
    }
};

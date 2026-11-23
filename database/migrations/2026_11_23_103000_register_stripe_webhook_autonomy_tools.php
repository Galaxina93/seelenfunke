<?php

use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $path = base_path('database/migrations/2026_11_16_190500_seed_stripe_agent_autonomy_settings_and_tools.php');
        if (is_file($path)) {
            $migration = require $path;
            if (is_object($migration) && method_exists($migration, 'applyUpgrade')) {
                $migration->applyUpgrade();
            }
        }

        if (Schema::hasTable('ai_roles') && Schema::hasTable('ai_tools') && Schema::hasTable('ai_role_tool')) {
            $finRole = AiRole::where('name', 'Finanzmanager')->first();
            if ($finRole && class_exists(AIFunctionsRegistry::class)) {
                $stripeNames = array_column(AIFunctionsRegistry::getAiStripeFuncsSchema(), 'name');
                $stripeIds = AiTool::whereIn('identifier', $stripeNames)->pluck('id')->all();
                if (!empty($stripeIds)) {
                    $finRole->tools()->detach($stripeIds);
                }
            }
        }
    }

    public function down(): void
    {
        // Idempotent tool definitions stay registered.
    }
};

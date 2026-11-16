<?php

use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use App\Models\System\SystemSetting;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->applyUpgrade();
    }

    public function applyUpgrade(): void
    {
        if (Schema::hasTable('shop_settings')) {
            $defaultSettings = [
                'stripe_agent_autonomy_enabled' => 'true',
                'stripe_agent_auto_refund_limit_cents' => '25000',
                'stripe_agent_daily_refund_cap_cents' => '100000',
            ];

            foreach ($defaultSettings as $key => $value) {
                SystemSetting::firstOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }

            Cache::forget('global_shop_settings');
        }

        if (Schema::hasTable('ai_tools') && class_exists(AIFunctionsRegistry::class)) {
            $stripeSchemas = AIFunctionsRegistry::getAiStripeFuncsSchema();
            $stripeToolIds = [];

            foreach ($stripeSchemas as $fn) {
                $identifier = (string) ($fn['name'] ?? '');
                if ($identifier === '') {
                    continue;
                }

                $tool = AiTool::updateOrCreate(
                    ['identifier' => $identifier],
                    [
                        'name' => ucwords(str_replace('_', ' ', $identifier)),
                        'description' => (string) ($fn['description'] ?? 'Autonomes Stripe-Werkzeug'),
                    ]
                );

                $stripeToolIds[] = $tool->id;
            }

            if (!empty($stripeToolIds) && Schema::hasTable('ai_roles') && Schema::hasTable('ai_role_tool')) {
                $executiveRoles = AiRole::whereIn('name', ['Teamleiter'])->get();
                foreach ($executiveRoles as $role) {
                    $role->tools()->syncWithoutDetaching($stripeToolIds);
                }

                // Air-Gap-Garantie: Öffentliche Support-Rolle darf niemals Stripe-Werkzeuge besitzen
                $supporterRole = AiRole::where('name', 'Supporter')->first();
                if ($supporterRole) {
                    $supporterRole->tools()->detach($stripeToolIds);
                }
            }

            Cache::forget('ai_agent_capabilities_map');
        }
    }

    public function down(): void
    {
        // Idempotente Sicherheitseinstellungen und Executive-Tool-Definitionen bleiben bestehen.
    }
};

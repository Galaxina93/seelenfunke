<?php

use Database\Seeders\AiAgentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (Schema::hasTable('ai_departments') && Schema::hasTable('ai_agents') && Schema::hasTable('ai_roles') && Schema::hasTable('ai_tools')) {
            (new AiAgentSeeder())->run();
        }
    }

    public function down(): void
    {
        // Idempotente Systemprompt-Aktualisierung
    }
};

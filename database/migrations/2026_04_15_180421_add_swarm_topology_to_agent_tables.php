<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_agents', 'provider')) {
                $table->string('provider')->default('google')->nullable()->after('system_prompt');
            }
            if (!Schema::hasColumn('ai_agents', 'fallback_provider')) {
                $table->string('fallback_provider')->nullable()->after('provider');
            }
        });

        Schema::table('ai_workspace_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_workspace_tasks', 'parent_task_id')) {
                $table->uuid('parent_task_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('ai_workspace_tasks', 'dependencies')) {
                $table->json('dependencies')->nullable()->after('parent_task_id'); 
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_workspace_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('ai_workspace_tasks', 'parent_task_id')) {
                $table->dropColumn('parent_task_id');
            }
            if (Schema::hasColumn('ai_workspace_tasks', 'dependencies')) {
                $table->dropColumn('dependencies');
            }
        });

        Schema::table('ai_agents', function (Blueprint $table) {
            if (Schema::hasColumn('ai_agents', 'provider')) {
                $table->dropColumn('provider');
            }
            if (Schema::hasColumn('ai_agents', 'fallback_provider')) {
                $table->dropColumn('fallback_provider');
            }
        });
    }
};

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
        if (!Schema::hasTable('ai_departments')) {
            Schema::create('ai_departments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('icon')->nullable()->default('building-office');
                $table->string('color')->nullable()->default('emerald-500');
                $table->integer('order_index')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_roles')) {
            Schema::create('ai_roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('ai_department_id')->nullable()->constrained('ai_departments')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_agents')) {
            Schema::create('ai_agents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('ai_role_id')->nullable()->constrained('ai_roles')->nullOnDelete();
                $table->foreignUuid('ai_department_id')->nullable()->constrained('ai_departments')->nullOnDelete();
                $table->string('name');
                $table->string('wake_word')->nullable();
                $table->text('role_description')->nullable();
                $table->text('system_prompt')->nullable();
                $table->string('model')->nullable()->default('gpt-oss-120b');
                $table->boolean('tts_enabled')->default(false);
                $table->string('tts_provider')->default('toni_xttsv2');
                $table->string('tts_voice')->nullable();
                $table->string('tts_api_url')->nullable();
                $table->decimal('tts_speed', 3, 2)->default(1.0);
                $table->float('temperature')->nullable()->default(0.4);
                $table->boolean('is_active')->default(true);
                $table->string('color')->default('cyan-500');
                $table->string('icon')->default('bi-stars');
                $table->string('profile_picture')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_agent_settings')) {
            Schema::create('ai_agent_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_tools')) {
            Schema::create('ai_tools', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('identifier')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_role_tool')) {
            Schema::create('ai_role_tool', function (Blueprint $table) {
                $table->foreignUuid('ai_role_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('ai_tool_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['ai_role_id', 'ai_tool_id']);
            });
        }

        if (!Schema::hasTable('ai_chat_memories')) {
            Schema::create('ai_chat_memories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('session_id')->nullable()->index();
                $table->string('role');
                $table->longText('content')->nullable();
                $table->json('context_data')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_knowledge_base_categories')) {
            Schema::create('ai_knowledge_base_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_knowledge_base_tags')) {
            Schema::create('ai_knowledge_base_tags', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_knowledge_bases')) {
            Schema::create('ai_knowledge_bases', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('slug')->unique();
                $table->longText('content');
                $table->boolean('is_published')->default(true);
                $table->foreignUuid('ai_knowledge_base_category_id')->nullable()->constrained(table: 'ai_knowledge_base_categories', indexName: 'fk_akb_cat_id')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_knowledge_base_ai_knowledge_base_tag')) {
            Schema::create('ai_knowledge_base_ai_knowledge_base_tag', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('ai_knowledge_base_id')->constrained(table: 'ai_knowledge_bases', indexName: 'fk_akb_pivot_base_id')->cascadeOnDelete();
                $table->foreignUuid('ai_knowledge_base_tag_id')->constrained(table: 'ai_knowledge_base_tags', indexName: 'fk_akb_pivot_tag_id')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_agent_tool_usages')) {
            Schema::create('ai_agent_tool_usages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('ai_agent_id')->nullable()->constrained('ai_agents')->onDelete('cascade');
                $table->string('tool_name');
                $table->timestamp('used_at');
                $table->json('context')->nullable();
                $table->boolean('is_error')->default(false);
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_metrics')) {
            Schema::create('ai_metrics', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('ai_agent_id')->constrained('ai_agents')->onDelete('cascade');
                $table->string('type')->default('inference'); // inference, cognitive_load
                $table->integer('input_tokens')->default(0);
                $table->integer('output_tokens')->default(0);
                $table->integer('total_time_ms')->default(0);
                $table->boolean('is_success')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_health_protocols')) {
            Schema::create('ai_health_protocols', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('ai_agent_id')->index();
                $table->uuid('ai_health_treatment_plan_id')->nullable();
                $table->longText('content');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_health_treatment_plans')) {
            Schema::create('ai_health_treatment_plans', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('ai_agent_id')->index();
                $table->string('title');
                $table->text('diagnosis_summary')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status')->default('active');
                $table->text('result_evaluation')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_health_treatment_items')) {
            Schema::create('ai_health_treatment_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('plan_id')->index();
                $table->foreign('plan_id')->references('id')->on('ai_health_treatment_plans')->onDelete('cascade');
                $table->string('name');
                $table->string('dosage');
                $table->integer('duration_days')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_completed')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_health_medications')) {
            Schema::create('ai_health_medications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('active_ingredients')->nullable();
                $table->string('dosage')->nullable();
                $table->string('frequency')->nullable();
                $table->boolean('is_long_term')->default(false);
                $table->string('image_path')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_tool_usages')) {
            Schema::create('ai_tool_usages', function (Blueprint $table) {
                $table->id();
                $table->uuid('ai_agent_id')->index();
                $table->uuid('support_customer_chat_id')->nullable()->index();
                $table->string('tool_name');
                $table->text('arguments')->nullable();
                $table->timestamps();

                // Verzicht auf strikte Constraints zu UUIDs bei Log-Tabellen für performantere Deletes
            });
        }
        if (!Schema::hasTable('ai_workspace_tasks')) {
            Schema::create('ai_workspace_tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('prompt');
                $table->string('status')->default('pending'); // pending, assigned, processing, completed, failed
                $table->uuid('assigned_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
                $table->longText('response_content')->nullable();
                $table->json('ui_metadata')->nullable(); // Stores x, y, expanded state
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_workspace_tasks');
        Schema::dropIfExists('ai_health_medications');
        Schema::dropIfExists('ai_health_treatment_items');
        Schema::dropIfExists('ai_health_treatment_plans');
        Schema::dropIfExists('ai_health_protocols');
        Schema::dropIfExists('ai_metrics');
        Schema::dropIfExists('ai_agent_tool_usages');
        Schema::dropIfExists('ai_knowledge_base_ai_knowledge_base_tag');
        Schema::dropIfExists('ai_knowledge_bases');
        Schema::dropIfExists('ai_knowledge_base_tags');
        Schema::dropIfExists('ai_knowledge_base_categories');
        Schema::dropIfExists('ai_chat_memories');
        Schema::dropIfExists('ai_role_tool');
        Schema::dropIfExists('ai_tools');
        Schema::dropIfExists('ai_agent_settings');
        Schema::dropIfExists('ai_agents');
        Schema::dropIfExists('ai_roles');
        Schema::dropIfExists('ai_departments');
        Schema::dropIfExists('ai_tool_usages');
    }
};

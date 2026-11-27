<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_behavior_feedbacks')) {
            Schema::create('ai_behavior_feedbacks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('ai_agent_id')->nullable()->index();
                $table->uuid('ai_department_id')->nullable()->index();
                $table->string('channel', 40)->default('web_portal')->index();
                $table->string('source_type', 40)->default('implicit_text_voice')->index();
                $table->string('polarity', 20)->index(); // praise | criticism | correction
                $table->string('category', 40)->default('domain_competence')->index();
                $table->unsignedTinyInteger('intensity')->default(3);
                $table->string('session_id', 191)->nullable()->index();
                $table->uuid('ai_chat_memory_id')->nullable()->index();
                $table->json('executed_tools')->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->string('environment_tier', 20)->default('LOCAL')->index();
                $table->text('user_excerpt')->nullable();
                $table->text('agent_excerpt')->nullable();
                $table->string('reason_note', 255)->nullable();
                $table->string('signal_hash', 64)->nullable()->index();
                $table->string('status', 20)->default('new')->index(); // new | reviewed | applied | dismissed
                $table->uuid('learned_preference_id')->nullable()->index();
                $table->timestamps();

                $table->index(['channel', 'polarity', 'created_at'], 'idx_behavior_fb_channel_pol_time');
                $table->index(['ai_agent_id', 'channel', 'category'], 'idx_behavior_fb_agent_chan_cat');
            });
        }

        if (!Schema::hasTable('ai_behavior_profiles')) {
            Schema::create('ai_behavior_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('ai_agent_id')->nullable()->index();
                $table->string('channel', 40)->default('all')->index();
                $table->unsignedSmallInteger('window_days')->default(30);
                $table->unsignedInteger('praise_count')->default(0);
                $table->unsignedInteger('criticism_count')->default(0);
                $table->unsignedInteger('correction_count')->default(0);
                $table->decimal('satisfaction_score', 5, 2)->default(100.00);
                $table->decimal('trend_delta', 6, 2)->default(0.00);
                $table->json('top_strengths')->nullable();
                $table->json('top_friction_points')->nullable();
                $table->json('pending_rule_proposals')->nullable();
                $table->json('rule_impact_metrics')->nullable();
                $table->timestamp('last_analyzed_at')->nullable();
                $table->timestamps();

                $table->unique(['ai_agent_id', 'channel', 'window_days'], 'uq_behavior_profile_scope');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_behavior_profiles');
        Schema::dropIfExists('ai_behavior_feedbacks');
    }
};

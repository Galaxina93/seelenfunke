<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_universe_tickets')) {
            Schema::create('ai_universe_tickets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ticket_number', 24)->unique();
                $table->string('title', 255);
                $table->string('status', 32)->default('new')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->string('ticket_type', 40)->default('general')->index();
                $table->foreignUuid('ai_department_id')->nullable()->constrained('ai_departments')->nullOnDelete();
                $table->foreignUuid('assigned_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
                $table->boolean('assigned_to_antigravity')->default(false)->index();
                $table->string('source_channel', 40)->default('web_portal')->index();
                $table->string('environment_tier', 20)->default('LOCAL')->index();
                $table->string('error_fingerprint', 64)->nullable()->index();
                $table->unsignedInteger('occurrence_count')->default(1);
                $table->unsignedInteger('messages_count')->default(0);
                $table->longText('compact_context')->nullable();
                $table->timestamp('compacted_at')->nullable();
                $table->json('action_payload')->nullable();
                $table->json('presence_snapshot')->nullable();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_universe_ticket_messages')) {
            Schema::create('ai_universe_ticket_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('ticket_id')->constrained('ai_universe_tickets')->cascadeOnDelete();
                $table->string('sender_role', 24)->index();
                $table->string('sender_name', 80);
                $table->foreignUuid('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
                $table->string('channel', 40)->default('web_portal');
                $table->longText('content');
                $table->json('executed_tools')->nullable();
                $table->json('attachments')->nullable();
                $table->json('action_card')->nullable();
                $table->boolean('is_compacted')->default(false)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_learned_preferences')) {
            Schema::create('ai_learned_preferences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('ai_department_id')->nullable()->constrained('ai_departments')->nullOnDelete();
                $table->string('scope_key', 120)->unique();
                $table->string('subject_label', 190);
                $table->text('rule_summary');
                $table->json('structured_value')->nullable();
                $table->uuid('source_ticket_id')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        // Bereinige historische Falcone/Falcon-Datensätze auf bestehenden Installationen und lege Referenzen auf Funkira um
        if (!app()->runningUnitTests() && Schema::hasTable('ai_agents')) {
            $funkiraId = DB::table('ai_agents')->where('name', 'Funkira')->value('id');
            $legacyIds = DB::table('ai_agents')->whereIn('name', ['Falcone', 'Falcon'])->pluck('id')->all();

            if (!empty($legacyIds)) {
                if ($funkiraId) {
                    if (Schema::hasTable('ai_widget_configs')) {
                        DB::table('ai_widget_configs')->whereIn('ai_agent_id', $legacyIds)->update(['ai_agent_id' => $funkiraId]);
                    }
                    if (Schema::hasTable('ai_workspace_tasks')) {
                        DB::table('ai_workspace_tasks')->whereIn('assigned_agent_id', $legacyIds)->update(['assigned_agent_id' => $funkiraId]);
                    }
                }
                DB::table('ai_agents')->whereIn('id', $legacyIds)->delete();
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learned_preferences');
        Schema::dropIfExists('ai_universe_ticket_messages');
        Schema::dropIfExists('ai_universe_tickets');
    }
};

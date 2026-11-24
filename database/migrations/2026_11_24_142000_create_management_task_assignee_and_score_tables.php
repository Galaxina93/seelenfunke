<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_task_lists')) {
            Schema::table('management_task_lists', function (Blueprint $table) {
                if (!Schema::hasColumn('management_task_lists', 'is_team_visible')) {
                    $table->boolean('is_team_visible')->default(false)->index()->after('position');
                }
            });
        }

        if (Schema::hasTable('management_tasks')) {
            Schema::table('management_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('management_tasks', 'assignee_type')) {
                    $table->string('assignee_type', 24)->nullable()->index()->after('is_mine');
                }
                if (!Schema::hasColumn('management_tasks', 'assignee_id')) {
                    $table->uuid('assignee_id')->nullable()->index()->after('assignee_type');
                }
                if (!Schema::hasColumn('management_tasks', 'assignee_name')) {
                    $table->string('assignee_name', 120)->nullable()->after('assignee_id');
                }
                if (!Schema::hasColumn('management_tasks', 'assignee_avatar')) {
                    $table->string('assignee_avatar', 255)->nullable()->after('assignee_name');
                }
                if (!Schema::hasColumn('management_tasks', 'created_by_type')) {
                    $table->string('created_by_type', 24)->nullable()->after('assignee_avatar');
                }
                if (!Schema::hasColumn('management_tasks', 'created_by_id')) {
                    $table->uuid('created_by_id')->nullable()->after('created_by_type');
                }
                if (!Schema::hasColumn('management_tasks', 'claimed_at')) {
                    $table->timestamp('claimed_at')->nullable()->after('last_activity_at');
                }
                if (!Schema::hasColumn('management_tasks', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('claimed_at');
                }
                if (!Schema::hasColumn('management_tasks', 'waiting_started_at')) {
                    $table->timestamp('waiting_started_at')->nullable()->after('completed_at');
                }
                if (!Schema::hasColumn('management_tasks', 'waiting_paused_seconds')) {
                    $table->unsignedInteger('waiting_paused_seconds')->default(0)->after('waiting_started_at');
                }
                if (!Schema::hasColumn('management_tasks', 'ep_awarded_flags')) {
                    $table->json('ep_awarded_flags')->nullable()->after('file_paths');
                }
            });

            // Bestehende is_mine / in_progress Tickets sauber der Haupt-Administratorin zuordnen
            if (Schema::hasTable('admins')) {
                $primaryAdmin = DB::table('admins')
                    ->where('email', 'kontakt@mein-seelenfunke.de')
                    ->first() ?? DB::table('admins')->first();

                if ($primaryAdmin) {
                    $fullName = trim(($primaryAdmin->first_name ?? '') . ' ' . ($primaryAdmin->last_name ?? '')) ?: 'Alina Steinhauer';
                    DB::table('management_tasks')
                        ->whereNull('assignee_id')
                        ->where(function ($q) {
                            $q->where('is_mine', true)->orWhere('status', 'in_progress');
                        })
                        ->update([
                            'assignee_type' => 'admin',
                            'assignee_id' => $primaryAdmin->id,
                            'assignee_name' => $fullName,
                        ]);
                }
            }
        }

        if (Schema::hasTable('management_task_messages')) {
            Schema::table('management_task_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('management_task_messages', 'sender_actor_type')) {
                    $table->string('sender_actor_type', 24)->nullable()->after('admin_id');
                }
                if (!Schema::hasColumn('management_task_messages', 'sender_actor_id')) {
                    $table->uuid('sender_actor_id')->nullable()->index()->after('sender_actor_type');
                }
            });

            DB::table('management_task_messages')
                ->whereNotNull('admin_id')
                ->whereNull('sender_actor_id')
                ->update([
                    'sender_actor_type' => 'admin',
                    'sender_actor_id' => DB::raw('admin_id'),
                ]);
        }

        if (Schema::hasTable('employee_contracts')) {
            Schema::table('employee_contracts', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_contracts', 'can_access_tasks')) {
                    $table->boolean('can_access_tasks')->default(true)->after('can_access_inventory');
                }
            });
        }

        if (!Schema::hasTable('management_task_operator_profiles')) {
            Schema::create('management_task_operator_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('actor_type', 24);
                $table->uuid('actor_id');
                $table->string('display_name', 120);
                $table->string('role_label', 80)->nullable();
                $table->string('avatar_path', 255)->nullable();
                $table->unsignedInteger('ep_total')->default(0);
                $table->unsignedSmallInteger('efficiency_score')->default(100);
                $table->unsignedInteger('tickets_claimed_count')->default(0);
                $table->unsignedInteger('tickets_completed_count')->default(0);
                $table->unsignedInteger('tickets_on_time_count')->default(0);
                $table->unsignedInteger('escalations_resolved_count')->default(0);
                $table->unsignedInteger('fast_escalations_count')->default(0);
                $table->unsignedInteger('chat_responses_count')->default(0);
                $table->unsignedBigInteger('total_net_resolution_seconds')->default(0);
                $table->unsignedBigInteger('total_first_response_seconds')->default(0);
                $table->string('equipped_title_key', 60)->nullable();
                $table->string('equipped_background_key', 60)->default('obsidian_slate');
                $table->timestamps();

                $table->unique(['actor_type', 'actor_id'], 'uq_mgmt_task_operator_actor');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_task_operator_profiles');
    }
};

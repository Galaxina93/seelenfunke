<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_tasks')) {
            Schema::table('management_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('management_tasks', 'status')) {
                    $table->string('status', 24)->default('open')->index()->after('title');
                }
                if (!Schema::hasColumn('management_tasks', 'is_mine')) {
                    $table->boolean('is_mine')->default(false)->index()->after('status');
                }
                if (!Schema::hasColumn('management_tasks', 'my_position')) {
                    $table->integer('my_position')->default(0)->after('position');
                }
                if (!Schema::hasColumn('management_tasks', 'due_time')) {
                    $table->string('due_time', 5)->nullable()->after('relevant_from');
                }
                if (!Schema::hasColumn('management_tasks', 'duration_minutes')) {
                    $table->unsignedSmallInteger('duration_minutes')->default(60)->after('due_time');
                }
                if (!Schema::hasColumn('management_tasks', 'last_activity_at')) {
                    $table->timestamp('last_activity_at')->nullable()->index()->after('duration_minutes');
                }
                if (!Schema::hasColumn('management_tasks', 'escalated_at')) {
                    $table->timestamp('escalated_at')->nullable()->after('last_activity_at');
                }
                if (!Schema::hasColumn('management_tasks', 'escalation_reason')) {
                    $table->string('escalation_reason', 255)->nullable()->after('escalated_at');
                }
            });

            // Bestehende Aufgaben sauber initialisieren & alte Platzhalter-Daten (01.08.2026) neutralisieren
            DB::table('management_tasks')
                ->where('is_completed', true)
                ->update(['status' => 'completed']);

            DB::table('management_tasks')
                ->where('is_completed', false)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '');
                })
                ->update(['status' => 'open']);

            DB::table('management_tasks')
                ->whereNull('last_activity_at')
                ->update([
                    'last_activity_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
                ]);

            DB::table('management_tasks')
                ->where('is_completed', false)
                ->whereBetween('relevant_from', ['2026-07-31 00:00:00', '2026-08-02 23:59:59'])
                ->update(['relevant_from' => null]);
        }

        if (!Schema::hasTable('management_task_messages')) {
            Schema::create('management_task_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('management_task_id')->constrained('management_tasks')->onDelete('cascade');
                $table->uuid('admin_id')->nullable()->index();
                $table->string('sender_name', 120)->default('Alina Steinhauer');
                $table->string('sender_avatar', 255)->nullable();
                $table->string('message_type', 32)->default('comment')->index();
                $table->text('content')->nullable();
                $table->json('attachments')->nullable();
                $table->timestamp('edited_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_task_messages');
    }
};

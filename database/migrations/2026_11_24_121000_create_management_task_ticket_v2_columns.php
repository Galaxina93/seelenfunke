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
                if (!Schema::hasColumn('management_tasks', 'is_mine')) {
                    $table->boolean('is_mine')->default(false)->index()->after('status');
                }
                if (!Schema::hasColumn('management_tasks', 'my_position')) {
                    $table->integer('my_position')->default(0)->after('position');
                }
            });

            // Bestehende in_progress / escalated Tickets initial in Meine Tickets uebernehmen
            DB::table('management_tasks')
                ->whereIn('status', ['in_progress', 'escalated'])
                ->where('is_completed', false)
                ->update(['is_mine' => true]);

            // Status sauber auf die 4 kanonischen Werte (open, waiting, in_progress, completed) normieren
            DB::table('management_tasks')
                ->where('status', 'escalated')
                ->update(['status' => 'open']);
        }

        if (Schema::hasTable('management_task_messages')) {
            Schema::table('management_task_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('management_task_messages', 'edited_at')) {
                    $table->timestamp('edited_at')->nullable()->after('attachments');
                }
            });
        }
    }

    public function down(): void
    {
        // Idempotent additive columns retained safely
    }
};

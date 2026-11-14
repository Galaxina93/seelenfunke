<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('order_orders', 'workshop_note')) {
                $table->text('workshop_note')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('order_orders', 'workshop_note_updated_at')) {
                $table->timestamp('workshop_note_updated_at')->nullable()->after('workshop_note');
            }
            if (!Schema::hasColumn('order_orders', 'workshop_next_check_at')) {
                $table->timestamp('workshop_next_check_at')->nullable()->after('workshop_note_updated_at');
            }
            if (!Schema::hasColumn('order_orders', 'workshop_checkin_paused')) {
                $table->boolean('workshop_checkin_paused')->default(false)->after('workshop_next_check_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_orders', function (Blueprint $table) {
            $dropCols = array_values(array_filter([
                Schema::hasColumn('order_orders', 'workshop_note') ? 'workshop_note' : null,
                Schema::hasColumn('order_orders', 'workshop_note_updated_at') ? 'workshop_note_updated_at' : null,
                Schema::hasColumn('order_orders', 'workshop_next_check_at') ? 'workshop_next_check_at' : null,
                Schema::hasColumn('order_orders', 'workshop_checkin_paused') ? 'workshop_checkin_paused' : null,
            ]));

            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};

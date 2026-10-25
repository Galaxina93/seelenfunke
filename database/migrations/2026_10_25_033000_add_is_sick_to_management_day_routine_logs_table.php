<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_day_routine_logs') && !Schema::hasColumn('management_day_routine_logs', 'is_sick')) {
            Schema::table('management_day_routine_logs', function (Blueprint $table) {
                $table->boolean('is_sick')->default(false)->after('is_completed');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('management_day_routine_logs') && Schema::hasColumn('management_day_routine_logs', 'is_sick')) {
            Schema::table('management_day_routine_logs', function (Blueprint $table) {
                $table->dropColumn('is_sick');
            });
        }
    }
};

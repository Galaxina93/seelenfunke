<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('management_calendar_events', function (Blueprint $table) {
            if (!Schema::hasColumn('management_calendar_events', 'send_email')) {
                $table->boolean('send_email')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('management_calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('management_calendar_events', 'send_email')) {
                $table->dropColumn('send_email');
            }
        });
    }
};

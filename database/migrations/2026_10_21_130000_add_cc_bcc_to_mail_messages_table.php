<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('mail_messages', 'cc_email')) {
                $table->text('cc_email')->nullable()->after('to_email');
            }
            if (!Schema::hasColumn('mail_messages', 'bcc_email')) {
                $table->text('bcc_email')->nullable()->after('cc_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropColumn(['cc_email', 'bcc_email']);
        });
    }
};

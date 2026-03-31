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
        Schema::table('order_revocations', function (Blueprint $table) {
            $table->timestamp('legal_check_at')->nullable()->after('status');
            $table->timestamp('customer_notified_at')->nullable()->after('legal_check_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_revocations', function (Blueprint $table) {
            $table->dropColumn(['legal_check_at', 'customer_notified_at']);
        });
    }
};

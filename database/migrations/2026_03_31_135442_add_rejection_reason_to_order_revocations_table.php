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
            $table->string('rejection_reason')->nullable()->after('product_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_revocations', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};

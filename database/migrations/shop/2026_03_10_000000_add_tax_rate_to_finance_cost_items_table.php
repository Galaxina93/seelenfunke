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
        Schema::table('finance_cost_items', function (Blueprint $table) {
            $table->integer('tax_rate')->default(0)->after('is_business');
        });

        // Initialize business items with 19%
        \Illuminate\Support\Facades\DB::table('finance_cost_items')
            ->where('is_business', true)
            ->update(['tax_rate' => 19]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_cost_items', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};

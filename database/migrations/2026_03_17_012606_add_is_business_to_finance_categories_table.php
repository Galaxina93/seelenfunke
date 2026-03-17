<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('finance_categories', function (Blueprint $table) {
            $table->boolean('is_business')->default(false)->after('name');
        });

        // Initialize user defaults: Everything is Private (false) except the specified ones
        $businessNames = ['Arbeitsmaterial', 'Wareneinkauf', 'Rohmaterial', 'Verpackungen'];
        DB::table('finance_categories')
            ->whereIn('name', $businessNames)
            ->update(['is_business' => true]);
    }

    public function down(): void
    {
        Schema::table('finance_categories', function (Blueprint $table) {
            $table->dropColumn('is_business');
        });
    }
};

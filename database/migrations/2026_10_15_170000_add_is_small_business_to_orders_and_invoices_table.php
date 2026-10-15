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
        if (Schema::hasTable('order_orders') && !Schema::hasColumn('order_orders', 'is_small_business')) {
            Schema::table('order_orders', function (Blueprint $table) {
                $table->boolean('is_small_business')->nullable()->after('tax_amount');
            });
        }

        if (Schema::hasTable('accounting_invoices') && !Schema::hasColumn('accounting_invoices', 'is_small_business')) {
            Schema::table('accounting_invoices', function (Blueprint $table) {
                $table->boolean('is_small_business')->nullable()->after('tax_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_orders') && Schema::hasColumn('order_orders', 'is_small_business')) {
            Schema::table('order_orders', function (Blueprint $table) {
                $table->dropColumn('is_small_business');
            });
        }

        if (Schema::hasTable('accounting_invoices') && Schema::hasColumn('accounting_invoices', 'is_small_business')) {
            Schema::table('accounting_invoices', function (Blueprint $table) {
                $table->dropColumn('is_small_business');
            });
        }
    }
};

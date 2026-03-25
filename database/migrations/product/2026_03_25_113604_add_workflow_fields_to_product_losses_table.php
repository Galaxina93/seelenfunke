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
        Schema::table('product_losses', function (Blueprint $table) {
            $table->uuid('supplier_id')->nullable()->after('product_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            
            $table->timestamp('reported_to_supplier_at')->nullable()->after('reason');
            $table->timestamp('refund_received_at')->nullable()->after('reported_to_supplier_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_losses', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'reported_to_supplier_at', 'refund_received_at']);
        });
    }
};

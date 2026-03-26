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
        Schema::create('accounting_categorization_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('search_term')->index();
            $table->foreignUuid('accounting_category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
            $table->foreignUuid('accounting_cost_item_id')->nullable()->constrained('accounting_cost_items')->nullOnDelete();
            $table->string('amount_type')->default('variable');
            $table->decimal('amount_value', 10, 2)->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_categorization_rules');
    }
};

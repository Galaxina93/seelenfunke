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
        Schema::create('finance_cost_item_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('finance_cost_item_id')->constrained('finance_cost_items')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->integer('interval_months')->nullable();
            $table->text('description')->nullable();

            $table->string('name')->nullable();
            $table->boolean('is_business')->default(false);
            $table->integer('tax_rate')->nullable();
            $table->date('first_payment_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->string('contract_file_path')->nullable();
            $table->json('tags')->nullable();
            $table->foreignUuid('finance_group_id')->nullable()->constrained('finance_groups')->nullOnDelete();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_cost_item_histories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounting_investment_plans')) {
            Schema::create('accounting_investment_plans', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->string('title');
                $table->decimal('target_amount_gross', 12, 2);
                $table->integer('tax_rate')->default(19);
                $table->decimal('saved_initial', 12, 2)->default(0);
                $table->enum('allocation_mode', ['fixed_monthly', 'profit_percent'])->default('fixed_monthly');
                $table->decimal('monthly_rate', 10, 2)->default(0);
                $table->decimal('profit_percent', 5, 2)->default(0);
                $table->date('start_date');
                $table->date('target_date')->nullable();
                $table->integer('afa_years')->default(5);
                $table->boolean('use_iab')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('priority')->default(1);
                $table->text('note')->nullable();
                $table->foreignUuid('completed_special_issue_id')
                    ->nullable()
                    ->constrained('accounting_special_issues')
                    ->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['admin_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_investment_plans');
    }
};

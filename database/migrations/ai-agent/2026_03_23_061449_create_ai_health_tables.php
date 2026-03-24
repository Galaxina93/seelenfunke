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
        Schema::create('ai_health_protocols', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index(); // the admin/CEO
            $table->uuid('ai_agent_id')->index(); // e.g. Dr. Funki
            $table->uuid('ai_health_treatment_plan_id')->nullable();
            $table->longText('content');
            $table->timestamps();
        });

        Schema::create('ai_health_treatment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('ai_agent_id')->index();
            $table->string('title');
            $table->text('diagnosis_summary')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // active, completed
            $table->text('result_evaluation')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_health_treatment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id')->index(); // references ai_health_treatment_plans
            $table->string('name');
            $table->string('dosage');
            $table->integer('duration_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->foreign('plan_id')
                  ->references('id')->on('ai_health_treatment_plans')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_health_treatment_items');
        Schema::dropIfExists('ai_health_treatment_plans');
        Schema::dropIfExists('ai_health_protocols');
    }
};

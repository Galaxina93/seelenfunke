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
        Schema::create('ai_health_medications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index(); // Reference to the CEO/Admin
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('active_ingredients')->nullable();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->boolean('is_long_term')->default(false);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_health_medications');
    }
};

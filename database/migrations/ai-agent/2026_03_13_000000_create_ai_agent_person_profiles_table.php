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
        Schema::create('person_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('is_favorite')->default(false);
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('nickname')->nullable();
            $table->string('relation_type')->nullable(); // e.g. "Brother", "Mother", "Colleague"
            $table->date('birthday')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->string('avatar_path')->nullable();
            $table->json('links')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // Core AI data fields
            $table->text('system_instructions')->nullable();
            $table->text('ai_learned_facts')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_profiles');
    }
};

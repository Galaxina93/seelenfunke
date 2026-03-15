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
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('wake_word')->nullable();
            $table->text('role_description')->nullable();
            $table->text('system_prompt')->nullable();
            $table->string('model')->nullable()->default('gpt-oss-120b');
            $table->string('tts_provider')->default('elevenlabs');
            $table->string('tts_voice')->nullable();
            $table->string('tts_api_url')->nullable();
            $table->decimal('tts_speed', 3, 2)->default(1.0);

            $table->float('temperature')->nullable()->default(0.4);
            $table->boolean('is_active')->default(true);
            $table->string('color')->default('cyan-500');
            $table->string('icon')->default('bi-stars');
            $table->string('profile_picture')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};

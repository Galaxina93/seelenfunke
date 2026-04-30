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
        Schema::create('support_telephony_calls', function (Blueprint $table) {
            $table->id();
            $table->string('twilio_sid')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->text('objective')->nullable();
            $table->longText('transcript')->nullable(); // JSON Array der AI Chat-Protokolle
            $table->text('summary')->nullable(); // Das finale Fazit (vom LLM generiert)
            $table->json('next_steps')->nullable(); // Extrahierte TODOs
            $table->string('status')->default('completed'); // completed, failed, ongoing
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_telephony_calls');
    }
};

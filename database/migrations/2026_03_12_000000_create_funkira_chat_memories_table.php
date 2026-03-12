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
        Schema::create('funkira_chat_memories', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->nullable()->index(); // ID to group conversations
            $table->string('role'); // user, system, assistant, tool
            $table->longText('content')->nullable(); // message payload
            $table->json('context_data')->nullable(); // additional metadata for tool tracking, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funkira_chat_memories');
    }
};

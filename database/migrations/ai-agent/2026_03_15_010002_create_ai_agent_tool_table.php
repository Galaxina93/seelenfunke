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
        Schema::create('ai_agent_tool', function (Blueprint $table) {
            $table->foreignUuid('ai_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ai_tool_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // A tool should only be attached to an agent once
            $table->primary(['ai_agent_id', 'ai_tool_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agent_tool');
    }
};

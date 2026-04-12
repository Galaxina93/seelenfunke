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
        Schema::create('ai_workspace_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('prompt');
            $table->string('status')->default('pending'); // pending, assigned, processing, completed, failed
            $table->uuid('assigned_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->longText('response_content')->nullable();
            $table->json('ui_metadata')->nullable(); // Stores x, y, expanded state
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_workspace_tasks');
    }
};

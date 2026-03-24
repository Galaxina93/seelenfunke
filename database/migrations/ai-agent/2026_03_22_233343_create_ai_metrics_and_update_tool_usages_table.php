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
        Schema::create('ai_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_agent_id')->constrained('ai_agents')->onDelete('cascade');
            $table->string('type')->default('inference'); // inference, cognitive_load
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_time_ms')->default(0);
            $table->boolean('is_success')->default(true);
            $table->timestamps();
        });

        Schema::table('tool_usages', function (Blueprint $table) {
            $table->foreignUuid('ai_agent_id')->nullable()->constrained('ai_agents')->onDelete('cascade');
            $table->boolean('is_error')->default(false);
            $table->text('error_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tool_usages', function (Blueprint $table) {
            $table->dropForeign(['ai_agent_id']);
            $table->dropColumn(['ai_agent_id', 'is_error', 'error_message']);
        });

        Schema::dropIfExists('ai_metrics');
    }
};

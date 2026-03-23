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
        Schema::create('ai_role_tool', function (Blueprint $table) {
            $table->foreignUuid('ai_role_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ai_tool_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // A tool should only be attached to a role once
            $table->primary(['ai_role_id', 'ai_tool_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_role_tool');
    }
};

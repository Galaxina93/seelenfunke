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
        Schema::create('ai_knowledge_base_ai_knowledge_base_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('ai_knowledge_base_id')->constrained(table: 'ai_knowledge_bases', indexName: 'fk_akb_pivot_base_id')->cascadeOnDelete();
            $table->foreignUuid('ai_knowledge_base_tag_id')->constrained(table: 'ai_knowledge_base_tags', indexName: 'fk_akb_pivot_tag_id')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_base_ai_knowledge_base_tag');
    }
};

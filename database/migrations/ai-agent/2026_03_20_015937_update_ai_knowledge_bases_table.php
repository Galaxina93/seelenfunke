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
        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            if (Schema::hasColumn('ai_knowledge_bases', 'category')) {
                $table->dropColumn('category');
            }
            if (Schema::hasColumn('ai_knowledge_bases', 'tags')) {
                $table->dropColumn('tags');
            }
            $table->foreignUuid('ai_knowledge_base_category_id')->nullable()->constrained(table: 'ai_knowledge_base_categories', indexName: 'fk_akb_cat_id')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            $table->dropForeign(['ai_knowledge_base_category_id']);
            $table->dropColumn('ai_knowledge_base_category_id');
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
        });
    }
};

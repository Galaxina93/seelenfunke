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
            $driver = Schema::getConnection()->getDriverName();

            // 1. FULLTEXT-Index auf (title, content)
            if ($driver !== 'sqlite') {
                $table->fullText(['title', 'content'], 'akb_fulltext_title_content');
            }

            // 2. B-Tree Index auf (is_published, ai_knowledge_base_category_id)
            $table->index(['is_published', 'ai_knowledge_base_category_id'], 'akb_published_cat');

            // 3. B-Tree Index auf (title)
            $table->index('title', 'akb_title');
        });

        Schema::table('ai_workspace_documents', function (Blueprint $table) {
            $driver = Schema::getConnection()->getDriverName();

            // FULLTEXT-Index auf (title, filename, purpose, summary, category)
            if ($driver !== 'sqlite') {
                $table->fullText(['title', 'filename', 'purpose', 'summary', 'category'], 'awd_fulltext_search');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver !== 'sqlite') {
                $table->dropIndex('akb_fulltext_title_content');
            }
            $table->dropIndex('akb_published_cat');
            $table->dropIndex('akb_title');
        });

        Schema::table('ai_workspace_documents', function (Blueprint $table) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver !== 'sqlite') {
                $table->dropIndex('awd_fulltext_search');
            }
        });
    }
};

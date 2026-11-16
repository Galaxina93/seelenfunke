<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_knowledge_bases')) {
            return;
        }

        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_knowledge_bases', 'source_path')) {
                $table->string('source_path', 191)->nullable()->unique('uk_akb_source_path')->after('slug');
            }

            if (!Schema::hasColumn('ai_knowledge_bases', 'content_sha256')) {
                $table->string('content_sha256', 64)->nullable()->index('idx_akb_content_sha256')->after('source_path');
            }

            if (!Schema::hasColumn('ai_knowledge_bases', 'visibility_scope')) {
                $table->string('visibility_scope', 32)->default('admin_executive')->index('idx_akb_visibility_scope')->after('is_published');
            }
        });

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `ai_knowledge_bases` MODIFY `content` LONGTEXT NOT NULL');

            $existingIndex = DB::select("SHOW INDEX FROM `ai_knowledge_bases` WHERE Key_name = 'ft_akb_title_content'");
            if (empty($existingIndex)) {
                DB::statement('ALTER TABLE `ai_knowledge_bases` ADD FULLTEXT INDEX `ft_akb_title_content` (`title`, `content`)');
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ai_knowledge_bases')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $existingIndex = DB::select("SHOW INDEX FROM `ai_knowledge_bases` WHERE Key_name = 'ft_akb_title_content'");
            if (!empty($existingIndex)) {
                DB::statement('ALTER TABLE `ai_knowledge_bases` DROP INDEX `ft_akb_title_content`');
            }
        }

        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            if (Schema::hasColumn('ai_knowledge_bases', 'source_path')) {
                $table->dropUnique('uk_akb_source_path');
                $table->dropColumn('source_path');
            }

            if (Schema::hasColumn('ai_knowledge_bases', 'content_sha256')) {
                $table->dropIndex('idx_akb_content_sha256');
                $table->dropColumn('content_sha256');
            }

            if (Schema::hasColumn('ai_knowledge_bases', 'visibility_scope')) {
                $table->dropIndex('idx_akb_visibility_scope');
                $table->dropColumn('visibility_scope');
            }
        });
    }
};

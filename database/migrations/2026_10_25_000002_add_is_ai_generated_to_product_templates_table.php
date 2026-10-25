<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_templates', 'is_ai_generated')) {
            Schema::table('product_templates', function (Blueprint $table) {
                $table->boolean('is_ai_generated')->default(true)->after('preview_image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_templates', 'is_ai_generated')) {
            Schema::table('product_templates', function (Blueprint $table) {
                $table->dropColumn('is_ai_generated');
            });
        }
    }
};

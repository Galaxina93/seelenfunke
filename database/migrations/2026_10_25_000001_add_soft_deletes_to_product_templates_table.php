<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_templates') && !Schema::hasColumn('product_templates', 'deleted_at')) {
            Schema::table('product_templates', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_templates') && Schema::hasColumn('product_templates', 'deleted_at')) {
            Schema::table('product_templates', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        if (!Schema::hasColumn('customers', 'mmorpg_access_enabled')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('mmorpg_access_enabled')
                    ->default(false)
                    ->index()
                    ->after('needs_password_change');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        if (Schema::hasColumn('customers', 'mmorpg_access_enabled')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex(['mmorpg_access_enabled']);
                $table->dropColumn('mmorpg_access_enabled');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_contacts') && !Schema::hasColumn('management_contacts', 'funkira_call_whitelist')) {
            Schema::table('management_contacts', function (Blueprint $table) {
                $table->boolean('funkira_call_whitelist')
                    ->default(false)
                    ->after('is_favorite')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('management_contacts') && Schema::hasColumn('management_contacts', 'funkira_call_whitelist')) {
            Schema::table('management_contacts', function (Blueprint $table) {
                $table->dropIndex(['funkira_call_whitelist']);
                $table->dropColumn('funkira_call_whitelist');
            });
        }
    }
};

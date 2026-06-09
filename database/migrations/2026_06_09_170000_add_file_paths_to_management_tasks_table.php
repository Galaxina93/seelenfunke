<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('management_tasks', function (Blueprint $table) {
            $table->json('file_paths')->nullable();
        });
    }

    public function down(): void {
        Schema::table('management_tasks', function (Blueprint $table) {
            $table->dropColumn('file_paths');
        });
    }
};

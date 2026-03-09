<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_gamifications', function (Blueprint $table) {
            $table->integer('funkenflug_highscore')->default(0)->after('funken_total_earned');
        });
    }

    public function down(): void
    {
        Schema::table('customer_gamifications', function (Blueprint $table) {
            $table->dropColumn('funkenflug_highscore');
        });
    }
};

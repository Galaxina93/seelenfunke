<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_gamifications', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_gamifications', 'unlocked_skins')) {
                $table->json('unlocked_skins')->nullable()->after('unlocked_coupons');
            }
            if (!Schema::hasColumn('customer_gamifications', 'active_ship_skin')) {
                $table->string('active_ship_skin', 64)->default('default')->after('unlocked_skins');
            }
            if (!Schema::hasColumn('customer_gamifications', 'unlocked_hall_auras')) {
                $table->json('unlocked_hall_auras')->nullable()->after('active_ship_skin');
            }
            if (!Schema::hasColumn('customer_gamifications', 'active_hall_aura')) {
                $table->string('active_hall_aura', 64)->default('default')->after('unlocked_hall_auras');
            }
            if (!Schema::hasColumn('customer_gamifications', 'hero_motto_key')) {
                $table->string('hero_motto_key', 64)->nullable()->after('active_hall_aura');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_gamifications', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('customer_gamifications', 'unlocked_skins') ? 'unlocked_skins' : null,
                Schema::hasColumn('customer_gamifications', 'active_ship_skin') ? 'active_ship_skin' : null,
                Schema::hasColumn('customer_gamifications', 'unlocked_hall_auras') ? 'unlocked_hall_auras' : null,
                Schema::hasColumn('customer_gamifications', 'active_hall_aura') ? 'active_hall_aura' : null,
                Schema::hasColumn('customer_gamifications', 'hero_motto_key') ? 'hero_motto_key' : null,
            ]);
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

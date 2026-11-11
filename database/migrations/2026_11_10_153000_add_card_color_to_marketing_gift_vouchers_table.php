<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_gift_vouchers') && !Schema::hasColumn('marketing_gift_vouchers', 'card_color')) {
            Schema::table('marketing_gift_vouchers', function (Blueprint $table) {
                $table->string('card_color', 20)->default('gold')->after('delivery_method');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketing_gift_vouchers') && Schema::hasColumn('marketing_gift_vouchers', 'card_color')) {
            Schema::table('marketing_gift_vouchers', function (Blueprint $table) {
                $table->dropColumn('card_color');
            });
        }
    }
};

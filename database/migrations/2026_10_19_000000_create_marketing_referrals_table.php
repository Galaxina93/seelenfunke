<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketing_referrals')) {
            Schema::create('marketing_referrals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('referrer_email')->index();
                $table->string('referrer_customer_id')->nullable()->index();
                $table->string('referrer_first_name')->nullable();
                $table->string('referrer_last_name')->nullable();
                $table->string('code', 50)->unique();
                $table->string('voucher_id')->index();
                $table->integer('discount_percent')->default(10);
                $table->integer('reward_cents')->default(500); // 5.00 €
                $table->unsignedInteger('successful_referrals_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Unique Index auf marketing_vouchers.code sicherstellen
        $indexes = collect(\Illuminate\Support\Facades\DB::select("SHOW INDEXES FROM marketing_vouchers"))->pluck('Key_name')->all();
        if (!in_array('marketing_vouchers_code_unique', $indexes)) {
            Schema::table('marketing_vouchers', function (Blueprint $table) {
                $table->unique('code', 'marketing_vouchers_code_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_referrals');

        $indexes = collect(\Illuminate\Support\Facades\DB::select("SHOW INDEXES FROM marketing_vouchers"))->pluck('Key_name')->all();
        if (in_array('marketing_vouchers_code_unique', $indexes)) {
            Schema::table('marketing_vouchers', function (Blueprint $table) {
                $table->dropUnique('marketing_vouchers_code_unique');
            });
        }
    }
};

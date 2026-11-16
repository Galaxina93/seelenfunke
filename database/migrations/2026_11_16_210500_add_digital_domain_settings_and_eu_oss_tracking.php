<?php

use App\Models\System\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->applyUpgrade();
    }

    public function applyUpgrade(): void
    {
        if (Schema::hasTable('order_orders') && !Schema::hasColumn('order_orders', 'digital_waiver_accepted_at')) {
            Schema::table('order_orders', function (Blueprint $table) {
                $table->timestamp('digital_waiver_accepted_at')->nullable()->after('is_small_business');
            });
        }

        if (Schema::hasTable('shop_settings')) {
            SystemSetting::firstOrCreate(
                ['key' => 'digital_domain_is_live'],
                ['value' => '0']
            );

            SystemSetting::firstOrCreate(
                ['key' => 'digital_domain_host'],
                ['value' => 'digital.mein-seelenfunke.de']
            );

            Cache::forget('global_shop_settings');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_orders') && Schema::hasColumn('order_orders', 'digital_waiver_accepted_at')) {
            Schema::table('order_orders', function (Blueprint $table) {
                $table->dropColumn('digital_waiver_accepted_at');
            });
        }
    }
};

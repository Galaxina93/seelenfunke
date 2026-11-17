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

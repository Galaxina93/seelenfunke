<?php

use App\Models\System\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (new SystemSetting())->getTable();

        if (Schema::hasTable($tableName)) {
            SystemSetting::firstOrCreate(
                ['key' => 'showroom_multiplayer_enabled'],
                ['value' => '1']
            );

            Cache::forget('global_shop_settings');
        }
    }

    public function down(): void
    {
        $tableName = (new SystemSetting())->getTable();

        if (Schema::hasTable($tableName)) {
            SystemSetting::where('key', 'showroom_multiplayer_enabled')->delete();
            Cache::forget('global_shop_settings');
        }
    }
};

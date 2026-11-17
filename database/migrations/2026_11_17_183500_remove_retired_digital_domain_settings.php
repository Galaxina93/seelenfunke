<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RETIRED_SETTING_KEYS = [
        'digital_domain_is_live',
        'digital_domain_host',
    ];

    private const RETIRED_BLUEPRINT_SKUS = [
        'NXC-BP-SENTINEL-01',
        'NXC-BP-ORCHESTRA-02',
        'NXC-BP-VAULT-03',
        'NXC-BP-VOICE-04',
        'NXC-BP-SUITE-99',
    ];

    public function up(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')
                ->whereIn('key', self::RETIRED_SETTING_KEYS)
                ->delete();
        }

        if (Schema::hasTable('products')) {
            $hasCollectionLine = Schema::hasColumn('products', 'collection_line');

            DB::table('products')
                ->where('slug', '!=', 'geschenkgutschein')
                ->where('sku', '!=', 'MSF-GUTSCHEIN-001')
                ->where(function ($query) use ($hasCollectionLine) {
                    $query->whereIn('sku', self::RETIRED_BLUEPRINT_SKUS);
                    if ($hasCollectionLine) {
                        $query->orWhere('collection_line', 'digital_blueprint');
                    }
                })
                ->delete();
        }

        try {
            Cache::forget('global_shop_settings');
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        // Stillgelegte Digital-Domain-Einstellungen werden nicht wiederhergestellt.
    }
};

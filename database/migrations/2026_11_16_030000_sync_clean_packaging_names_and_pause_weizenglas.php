<?php

use Database\Seeders\ProductPackagingSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products') || !Schema::hasTable('product_packaging_items')) {
            return;
        }

        DB::table('products')
            ->where('slug', 'weizenglas-personalisiert')
            ->update([
                'status' => 'draft',
                'updated_at' => now(),
            ]);

        if (!app()->runningUnitTests() && DB::table('products')->exists()) {
            (new ProductPackagingSeeder())->run();
        }
    }

    public function down(): void
    {
        // Klare Verpackungsnamen und pausiertes Weizenglas bleiben als Startkonfiguration bestehen.
    }
};

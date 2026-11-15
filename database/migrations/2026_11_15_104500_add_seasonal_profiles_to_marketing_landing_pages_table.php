<?php

use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_landing_pages') && !Schema::hasColumn('marketing_landing_pages', 'seasonal_profiles')) {
            Schema::table('marketing_landing_pages', function (Blueprint $table) {
                $table->json('seasonal_profiles')->nullable()->after('cta_text');
            });
        }

        // Bestehende aktive Produkte direkt mit allen 13 Vorlagen-Profilen synchronisieren (z. B. beim Stage-/Live-Migrate)
        if (Schema::hasTable('products') && Schema::hasTable('marketing_landing_pages') && Schema::hasColumn('marketing_landing_pages', 'seasonal_profiles')) {
            try {
                $products = Product::where('slug', 'not like', 'draft-%')->get();
                foreach ($products as $product) {
                    MarketingLandingPage::syncForProduct($product, false);
                }
            } catch (\Throwable $e) {
                // Fehlertolerant bei leeren oder partiellen Test-Schemata
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketing_landing_pages') && Schema::hasColumn('marketing_landing_pages', 'seasonal_profiles')) {
            Schema::table('marketing_landing_pages', function (Blueprint $table) {
                $table->dropColumn('seasonal_profiles');
            });
        }
    }
};

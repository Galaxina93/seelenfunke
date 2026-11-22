<?php

use App\Models\Product\Product;
use App\Services\Laser\XtoolLaserCockpitService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (new Product())->getTable();
        if (!Schema::hasTable($table)) {
            return;
        }

        Product::withTrashed()->chunkById(50, function ($products): void {
            foreach ($products as $product) {
                $settings = is_array($product->configurator_settings)
                    ? $product->configurator_settings
                    : (is_string($product->configurator_settings) ? (json_decode($product->configurator_settings, true) ?: []) : []);

                // Beim überarbeiteten Seelen-Kristall veraltete xtool_profiles zurücksetzen, damit die frischen K9-Glas-Frostwerte + Invert-Photo-Studio greifen
                if ($product->slug === 'seelen-kristall') {
                    unset($settings['xtool_profiles']);
                }

                $synced = XtoolLaserCockpitService::applySeededMultiLaserProfilesToSettings((string) $product->slug, $settings);

                $product->forceFill(['configurator_settings' => $synced])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Idempotente Profil-Bereinigung
    }
};

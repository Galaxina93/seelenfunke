<?php

use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        foreach (['id-armband-silber-herz', 'id-armband-gold-herz'] as $slug) {
            /** @var Product|null $product */
            $product = Product::withTrashed()->where('slug', $slug)->first();
            if (!$product) {
                continue;
            }

            $settings = is_array($product->configurator_settings) ? $product->configurator_settings : [];
            $settings['engraving_scale'] = 40.23;
            $settings['back_engraving_scale'] = 40.23;
            $settings['back_engraving_pos_z'] = -0.75;
            $product->configurator_settings = $settings;
            $product->saveQuietly();

            if (Schema::hasTable('product_templates')) {
                ProductTemplate::where('product_id', $product->id)->get()->each(function (ProductTemplate $tpl): void {
                    $cfg = is_array($tpl->configuration) ? $tpl->configuration : [];
                    if (!empty($cfg['texts_back']) && is_array($cfg['texts_back'])) {
                        foreach ($cfg['texts_back'] as &$tb) {
                            if (is_array($tb) && isset($tb['x']) && abs((float) $tb['x'] - 57.0) < 1.5) {
                                $tb['x'] = 42.5;
                            }
                        }
                        unset($tb);
                        $tpl->configuration = $cfg;
                        $tpl->saveQuietly();
                    }
                });
            }
        }

        /** @var Product|null $dogTag */
        $dogTag = Product::withTrashed()->where('slug', 'seelen-pfote-hundemarke')->first();
        if ($dogTag && Schema::hasTable('product_templates')) {
            ProductTemplate::where('product_id', $dogTag->id)->get()->each(function (ProductTemplate $tpl): void {
                $cfg = is_array($tpl->configuration) ? $tpl->configuration : [];
                if (!empty($cfg['texts_back']) && is_array($cfg['texts_back'])) {
                    foreach ($cfg['texts_back'] as &$tb) {
                        if (is_array($tb) && isset($tb['x']) && abs((float) $tb['x'] - 45.0) < 1.5) {
                            $tb['x'] = 58.0;
                            $tb['size'] = 1.65;
                        }
                    }
                    unset($tb);
                    $tpl->configuration = $cfg;
                    $tpl->saveQuietly();
                }
            });
        }
    }

    public function down(): void
    {
        // Nicht-destruktive 3D- & Rückseiten-Kalibrierungssynchronisation
    }
};

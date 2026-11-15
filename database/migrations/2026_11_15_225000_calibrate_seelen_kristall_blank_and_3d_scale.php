<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        $product = DB::table('products')->where('slug', 'seelen-kristall')->first();
        if (!$product) {
            return;
        }

        $settings = is_string($product->configurator_settings)
            ? (json_decode($product->configurator_settings, true) ?: [])
            : (is_array($product->configurator_settings) ? $product->configurator_settings : []);

        $settings['xtool_plane_width'] = 161.5;
        $settings['xtool_plane_height'] = 161.5;
        $settings['engraving_scale'] = 79.7;

        $attributes = is_string($product->attributes)
            ? (json_decode($product->attributes, true) ?: [])
            : (is_array($product->attributes) ? $product->attributes : []);

        $attributes["Gr\u{00F6}\u{00DF}e"] = "\u{00D8} 161,5 mm (H\u{00F6}he inkl. Sockel 180 mm, St\u{00E4}rke 40 mm)";

        DB::table('products')
            ->where('id', $product->id)
            ->update([
                'configurator_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Kalibrierung bleibt als reales Rohlingsmass bestehen.
    }
};

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
        $settings['xtool_plate_box_pct'] = [
            'left'   => 14.23,
            'top'    => 9.83,
            'width'  => 71.54,
            'height' => 71.54,
        ];

        DB::table('products')
            ->where('id', $product->id)
            ->update([
                'configurator_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at'            => now(),
            ]);
    }

    public function down(): void
    {
        // Reales Rohlingsmass (exakt 4.0 mm Facettenrand ringsum) bleibt erhalten.
    }
};

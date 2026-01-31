<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------------------------------
        // 0. Steuersätze initialisieren (Falls Tabelle leer)
        // ---------------------------------------------------------
        if (DB::table('tax_rates')->count() === 0) {
            DB::table('tax_rates')->insert([
                [
                    'name' => 'Standard DE',
                    'rate' => 19.00,
                    'country_code' => 'DE',
                    'tax_class' => 'standard',
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Ermäßigt DE',
                    'rate' => 7.00,
                    'country_code' => 'DE',
                    'tax_class' => 'reduced',
                    'is_default' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
            ]);
        }

        // ---------------------------------------------------------
        // 1. Standard-Produkt (Der Bestseller)
        // ---------------------------------------------------------
        $p1 = Product::create([
            // Basisdaten
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'description' => 'Ein handgeschliffener Kristall für besondere Momente. Durch die hochwertige 3D-Innengravur schwebt Ihr Wunschmotiv förmlich im Glas. Ein unvergessliches Geschenk für Hochzeiten, Jahrestage oder als Erinnerungsstück. Lieferumfang: Kristallglas, Geschenkbox, Pflegehinweise.',
            'short_description' => 'Personalisiertes 3D-Glasgeschenk inkl. Geschenkbox.',
            'status' => 'active',

            // Preis & Steuer (NEU: tax_class statt tax_rate)
            'price' => 3990, // 39,90 €
            'compare_at_price' => 4990, // UVP 49,90 €
            'cost_per_item' => 1250, // Einkauf

            // Lager & Identifikation
            'sku' => 'CRYSTAL-001-CLR',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 150,
            'continue_selling_when_out_of_stock' => true,

            // Versand
            'is_physical_product' => true,
            'weight' => 1250, // Gramm
            'height' => 70, // mm
            'width' => 244,  // mm
            'length' => 284, // mm
            'shipping_class' => 'paket_s',

            // Medien & Konfigurator
            'preview_image_path' => 'Testdata/overlay.png',
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'Testdata/seelen-kristall_b.jpg',
                    'is_main' => true,
                    'alt' => 'Seelen Kristall Frontansicht'
                ],
                [
                    'type' => 'video',
                    'path' => 'Testdata/video.mp4',
                    'is_main' => false
                ]
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'area_width' => 80,
                'area_height' => 80,
                'area_top' => 4,
                'area_left' => 10,
            ],

            // JSON Daten
            'attributes' => [
                'Material' => 'Hochwertiges K9 Kristallglas',
                'Größe' => '160*180*40 mm',
                'Farbe' => 'Transparent',
                'Verpackung' => 'Geschenkbox mit Seidenfutter',
                'Druck' => 'UV-Direktdruck (optional)',
                'Technik' => 'Oberflächengravur',
                'Gewicht' => '1250'
            ],

            // WICHTIG: Das JSON Feld bleibt leer, da wir die Tabelle 'product_tier_prices' nutzen
            'tier_pricing' => [],

            // SEO
            'seo_title' => 'Der Seelen Kristall | Personalisierbares Glasgeschenk | Mein-Seelenfunke',
            'seo_description' => 'Verschenken Sie Ewigkeit: Unser Seelen Kristall aus hochwertigem Glas mit individueller Gravur.',
            'completion_step' => 4
        ]);

        // Staffelpreise über die Relation anlegen
        $p1->tierPrices()->createMany([
            ['qty' => 5, 'percent' => 5.00],   // Ab 5 Stk: 5% Rabatt
            ['qty' => 10, 'percent' => 10.00], // Ab 10 Stk: 10% Rabatt
            ['qty' => 25, 'percent' => 15.00], // Ab 25 Stk: 15% Rabatt
        ]);



    }
}

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

            // Preis & Steuer
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
            'preview_image_path' => 'Testdata/seelenkristall/overlay.png',
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'Testdata/seelenkristall/seelen-kristall_b.jpg',
                    'is_main' => true,
                    'alt' => 'Seelen Kristall Frontansicht'
                ],
                [
                    'type' => 'video',
                    'path' => 'Testdata/seelenkristall/video.mp4',
                    'is_main' => false
                ]
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'area_top' => 3,
                'area_left' => 10.5,
                'area_width' => 80,
                'area_height' => 80,
                'area_shape' => 'circle'
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

            'tier_pricing' => [],
            'seo_title' => 'Der Seelen Kristall | Personalisierbares Glasgeschenk | Mein-Seelenfunke',
            'seo_description' => 'Verschenken Sie Ewigkeit: Unser Seelen Kristall aus hochwertigem Glas mit individueller Gravur.',
            'completion_step' => 4
        ]);

        // Staffelpreise für p1
        $p1->tierPrices()->createMany([
            ['qty' => 5, 'percent' => 5.00],
            ['qty' => 10, 'percent' => 10.00],
            ['qty' => 25, 'percent' => 15.00],
        ]);

        // ---------------------------------------------------------
        // 2. Neues Produkt (Der Seelenanhänger)
        // ---------------------------------------------------------
        $p2 = Product::create([
            // Basisdaten
            'name' => 'Der Seelenanhänger',
            'slug' => 'seelen-anhaenger',
            'description' => 'Ein Aluminium Metall Herz das ganz besondere Gefühle auslösen kann. Es ist sehr hochwertig und schwer, wodurch es sich besonders wertig in der Hand anfühlt. Ideal als Handschmeichler oder persönlicher Glücksbringer.',
            'short_description' => 'Hochwertiges, schweres Aluminium-Herz für besondere Momente.',
            'status' => 'active',

            // Preis & Steuer
            'price' => 999, // 9,99 €
            'compare_at_price' => 1499, // Optional: UVP z.B. 14,99 €
            'cost_per_item' => 111, // 1,11 € Einkauf

            // Lager & Identifikation
            'sku' => 'ALU-HEART-001',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 50,
            'continue_selling_when_out_of_stock' => true,

            // Versand
            'is_physical_product' => true,
            'weight' => 85, // Gramm (geschätzt für "schweres" kleines Metall)
            'height' => 5, // mm
            'width' => 50,  // mm
            'length' => 50, // mm
            'shipping_class' => 'paket_s', // oder 'brief'

            // Medien & Konfigurator
            'preview_image_path' => 'Testdata/seelenanhaenger/overlay.png',
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'Testdata/seelenanhaenger/seelen-anhaenger_s.jpg',
                    'is_main' => true,
                    'alt' => 'Seelenanhänger Frontansicht'
                ],
                [
                    'type' => 'video',
                    'path' => 'Testdata/seelenkristall/video.mp4', // Wiederverwendet wie gewünscht
                    'is_main' => false
                ]
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'area_top' => 10,
                'area_left' => 10,
                'area_width' => 80,
                'area_height' => 80,
                'area_shape' => 'heart' // Passend zum Produkt
            ],

            // JSON Daten
            'attributes' => [
                'Material' => 'Aluminium (Massiv)',
                'Form' => 'Herz',
                'Oberfläche' => 'Matt gebürstet',
                'Besonderheit' => 'Extra schwer & hochwertig'
            ],

            'tier_pricing' => [],
            'seo_title' => 'Der Seelenanhänger | Massives Aluminium Herz | Mein-Seelenfunke',
            'seo_description' => 'Ein Herz aus schwerem Aluminium, das Gefühle weckt. Hochwertig verarbeitet für 9,99€.',
            'completion_step' => 4
        ]);

        // Optional: Staffelpreise für p2 (z.B. für Gastgeschenke)
        $p2->tierPrices()->createMany([
            ['qty' => 10, 'percent' => 5.00],
            ['qty' => 20, 'percent' => 10.00],
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $cats = [
            'Glas & Kristall' => 'physical',
            'Geschenksets' => 'physical',
            'Bestseller' => 'physical',
            'Metall & Alu' => 'physical',
            'Schmuck & Anhänger' => 'physical',
            'E-Books & Guides' => 'digital',
            'Beratung' => 'service',
            'Express-Service' => 'service',
        ];

        foreach ($cats as $name => $type) {
            Category::firstOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'type' => $type,
                    'color' => 'bg-gray-100 text-gray-800'
                ]
            );
        }

        if (DB::table('tax_rates')->count() === 0) {
            DB::table('tax_rates')->insert([
                ['name' => 'Standard DE', 'rate' => 19.00, 'country_code' => 'DE', 'tax_class' => 'standard', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Ermäßigt DE', 'rate' => 7.00, 'country_code' => 'DE', 'tax_class' => 'reduced', 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // --- PRODUKT 1: Der Seelen Kristall (Physisch) ---
        $p1 = Product::create([
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'type' => 'physical',
            'description' => 'Ein handgeschliffener Kristall für besondere Momente. Durch die hochwertige 3D-Innengravur schwebt Ihr Wunschmotiv förmlich im Glas.',
            'short_description' => 'Personalisiertes 3D-Glasgeschenk inkl. Geschenkbox.',
            'status' => 'active',
            'price' => 3990,
            'compare_at_price' => null,
            'cost_per_item' => 1250,
            'sku' => 'CRYSTAL-001-CLR',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 150,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 920,
            'width' => 180,
            'height' => 200,
            'length' => 40,
            'shipping_class' => 'paket_s',
            'preview_image_path' => 'testdata/seelenkristall/overlay.png',
            'three_d_model_path' => 'testdata/seelenkristall/t_seelenk.glb',
            'three_d_background_path' => 'testdata/seelenkristall/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'testdata/seelenkristall/seelen-kristall_b.jpg', 'is_main' => true, 'alt' => 'Seelen Kristall Frontansicht']
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'area_shape' => 'circle',
                'area_top' => 10,
                'area_left' => 10,
                'area_width' => 81,
                'area_height' => 80,
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'glass',
                'model_scale' => 100,
                'model_pos_x' => -0.26,
                'model_pos_y' => 1.04,
                'model_pos_z' => 2.37,
                'model_rot_x' => 178,79,
                'model_rot_y' => -7.41,
                'model_rot_z' => -178.27,
                'engraving_scale' => 66.04,
                'engraving_pos_x' => 0.05,
                'engraving_pos_y' => 0.46,
                'engraving_pos_z' => -1.53,
                'engraving_rot_x' => -0.86,
                'engraving_rot_y' => 0.38,
                'engraving_rot_z' => -4.12,
                'custom_points' => [
                    ['x' => 20, 'y' => 20], ['x' => 80, 'y' => 20],
                    ['x' => 80, 'y' => 80], ['x' => 20, 'y' => 80]
                ]
            ],
            'attributes' => [
                'Material' => 'Hochwertiges K9 Kristallglas',
                'Größe' => '180x200x40 mm',
                'Farbe' => 'Transparent'
            ],
            'tier_pricing' => [],
            'seo_title' => 'Der Seelen Kristall',
            'seo_description' => 'Verschenken Sie Ewigkeit.',
            'completion_step' => 4
        ]);

        $catIds1 = Category::whereIn('name', ['Glas & Kristall', 'Geschenksets', 'Bestseller'])->pluck('id');
        $p1->categories()->attach($catIds1);


        // --- PRODUKT 2: Der Seelenanhänger (Physisch) ---
        $p2 = Product::create([
            'name' => 'Der Seelenanhänger',
            'slug' => 'seelen-anhaenger',
            'type' => 'physical',
            'description' => 'Ein Aluminium Metall Herz das ganz besondere Gefühle auslösen kann. Es ist sehr hochwertig und schwer, wodurch es sich besonders wertig in der Hand anfühlt. Ideal als Handschmeichler oder persönlicher Glücksbringer.',
            'short_description' => 'Hochwertiges, schweres Aluminium-Herz für besondere Momente.',
            'status' => 'active',
            'price' => 999,
            'compare_at_price' => 1499,
            'cost_per_item' => 111,
            'sku' => 'ALU-HEART-001',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 50,
            'continue_selling_when_out_of_stock' => true,

            'weight' => 85,
            'height' => 5,
            'width' => 50,
            'length' => 50,
            'shipping_class' => 'paket_s',

            'preview_image_path' => 'testdata/seelenanhaenger/overlay.png',
            'three_d_model_path' => 'testdata/seelenanhaenger/seelenanhaenger_3d_.glb',
            'three_d_background_path' => 'testdata/seelenanhaenger/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'testdata/seelenanhaenger/seelen-anhaenger_s.jpg', 'is_main' => true, 'alt' => 'Seelenanhänger Frontansicht'],
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'xtool_plane_width' => 50.0,
                'xtool_plane_height' => 50.0,

                'area_top' => 10,
                'area_left' => 10,
                'area_width' => 80,
                'area_height' => 80,
                'area_shape' => 'custom',

                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'metal',

                'model_scale' => 100,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => 0, 'model_rot_y' => 90, 'model_rot_z' => 0,

                // VORDERSEITE
                'engraving_scale' => 71.3,
                'engraving_pos_x' => -0.43, 'engraving_pos_y' => -0.22, 'engraving_pos_z' => -4.86,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 90, 'engraving_rot_z' => 0,

                // RÜCKSEITE (Gespiegelt zur Vorderseite)
                'back_engraving_scale' => 71.3,
                'back_engraving_pos_x' => 0.43, 'back_engraving_pos_y' => -0.17, 'back_engraving_pos_z' => -4.88,
                'back_engraving_rot_x' => 0, 'back_engraving_rot_y' => 270, 'back_engraving_rot_z' => 0,

                // DEINE NEUEN, EXAKTEN HERZ-KOORDINATEN
                'custom_points' => [
                    ['x' => 55.8, 'y' => 18.3],
                    ['x' => 65.07, 'y' => 13.51],
                    ['x' => 72.46, 'y' => 13.22],
                    ['x' => 79.57, 'y' => 15.54],
                    ['x' => 84.93, 'y' => 19.46],
                    ['x' => 87.97, 'y' => 25.83],
                    ['x' => 90.14, 'y' => 32.36],
                    ['x' => 90.43, 'y' => 39.75],
                    ['x' => 89.28, 'y' => 45.83],
                    ['x' => 85.36, 'y' => 53.8],
                    ['x' => 80.43, 'y' => 61.49],
                    ['x' => 74.06, 'y' => 69.46],
                    ['x' => 67.25, 'y' => 75.98],
                    ['x' => 60.58, 'y' => 80.91],
                    ['x' => 54.64, 'y' => 85.69],
                    ['x' => 45.36, 'y' => 85.69],
                    ['x' => 39.42, 'y' => 80.91],
                    ['x' => 32.75, 'y' => 75.98],
                    ['x' => 25.94, 'y' => 69.46],
                    ['x' => 19.57, 'y' => 61.49],
                    ['x' => 14.64, 'y' => 53.8],
                    ['x' => 10.72, 'y' => 45.83],
                    ['x' => 9.57, 'y' => 39.75],
                    ['x' => 9.86, 'y' => 32.36],
                    ['x' => 12.03, 'y' => 25.83],
                    ['x' => 15.07, 'y' => 19.46],
                    ['x' => 20.43, 'y' => 15.54],
                    ['x' => 27.54, 'y' => 13.22],
                    ['x' => 34.93, 'y' => 13.51],
                    ['x' => 44.2, 'y' => 18.3],
                    ['x' => 49.42, 'y' => 24.09]
                ]
            ],
            'attributes' => [
                'Material' => 'Aluminium (Massiv)',
                'Form' => 'Herz'
            ],
            'tier_pricing' => [],
            'seo_title' => 'Der Seelenanhänger',
            'completion_step' => 4
        ]);

        $catIds2 = Category::whereIn('name', ['Metall & Alu', 'Schmuck & Anhänger'])->pluck('id');
        $p2->categories()->attach($catIds2);


        // --- PRODUKT 3: Das Seelenbuch (Digital) ---
        $p3 = Product::create([
            'name' => 'Das Seelenbuch',
            'slug' => 'seelenbuch',
            'type' => 'digital',
            'description' => 'Ihr digitaler Begleiter für mehr Achtsamkeit und Inspiration. Dieses E-Book enthält wertvolle Impulse, Gedankenanstöße und praktische Übungen für den Alltag. Einfach herunterladen und sofort loslegen.',
            'short_description' => 'Digitaler Guide für Inspiration & Achtsamkeit (PDF).',
            'status' => 'active',
            'price' => 1990,
            'compare_at_price' => 2990,
            'cost_per_item' => 0,
            'sku' => 'EBOOK-SEELEN-01',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => false,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
            'weight' => null, 'height' => null, 'width' => null, 'length' => null, 'shipping_class' => null,
            'digital_download_path' => 'testdata/seelenbuch/Produktübersicht - Mein Seelenfunke.pdf',
            'digital_filename' => 'Produktübersicht - Mein Seelenfunke.pdf',
            'preview_image_path' => null,
            'media_gallery' => [
                ['type' => 'image', 'path' => 'testdata/seelenbuch/Seelen-Book.png', 'is_main' => true, 'alt' => 'Das Seelenbuch Cover']
            ],
            'configurator_settings' => [
                'allow_text_pos' => false,
                'allow_logo' => false,
            ],
            'attributes' => [
                'Format' => 'PDF (Digital)',
                'Seiten' => 'Produktübersicht',
                'Sprache' => 'Deutsch',
                'Auslieferung' => 'Sofort-Download'
            ],
            'tier_pricing' => [],
            'seo_title' => 'Das Seelenbuch | Digitaler Ratgeber | Mein-Seelenfunke',
            'seo_description' => 'Inspiration und Achtsamkeit für jeden Tag. Jetzt das Seelenbuch als PDF herunterladen.',
            'completion_step' => 4
        ]);

        $catIds3 = Category::whereIn('name', ['E-Books & Guides'])->pluck('id');
        $p3->categories()->attach($catIds3);


        // --- PRODUKT 4: Persönliche Laser-Beratung (Service) ---
        $p4 = Product::create([
            'name' => 'Persönliche Laser-Beratung',
            'slug' => 'laser-beratung',
            'type' => 'service',
            'description' => 'Planen Sie ein Großprojekt oder benötigen Sie Hilfe bei der Erstellung Ihrer Gravurdaten? Buchen Sie eine 30-minütige persönliche Beratung per Video-Call mit unseren Experten.',
            'short_description' => '30 Min. Video-Consulting für Ihr Laser-Projekt.',
            'status' => 'active',
            'price' => 4900,
            'compare_at_price' => null,
            'cost_per_item' => 0,
            'sku' => 'SERVICE-CONSULT-30',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 10,
            'continue_selling_when_out_of_stock' => false,
            'weight' => null, 'height' => null, 'width' => null, 'length' => null, 'shipping_class' => null,
            'digital_download_path' => null, 'digital_filename' => null,
            'preview_image_path' => null,
            'media_gallery' => [
                ['type' => 'image', 'path' => 'testdata/laserberatung/laser-beratung.png', 'is_main' => true, 'alt' => 'Laser Beratungsservice']
            ],
            'configurator_settings' => [
                'allow_text_pos' => false,
                'allow_logo' => false,
            ],
            'attributes' => [
                'Dauer' => '30 Minuten',
                'Ort' => 'Online (Video-Call)',
                'Sprache' => 'Deutsch',
                'Experte' => 'Alina Steinhauer'
            ],
            'tier_pricing' => [],
            'seo_title' => 'Laser-Beratung buchen | Mein-Seelenfunke Experten',
            'seo_description' => 'Professionelle Beratung für Ihre Lasergravur-Projekte. Jetzt Termin sichern.',
            'completion_step' => 4
        ]);

        $catIds4 = Category::whereIn('name', ['Beratung', 'Express-Service'])->pluck('id');
        $p4->categories()->attach($catIds4);


        // --- PRODUKT 5: Personalisiertes Weizenglas (NEU) ---
        $p5 = Product::create([
            'name' => 'Personalisiertes Weizenglas',
            'slug' => 'weizenglas-personalisiert',
            'type' => 'physical',
            'description' => 'Stoßen Sie mit Ihrem ganz persönlichen Weizenglas an! Durch unsere präzise Lasertechnik wird Ihr Wunschtext oder Logo als edle 360° Rundum-Gravur tief ins Glas eingebrannt. Spülmaschinenfest und ein absoluter Hingucker auf jeder Feier.',
            'short_description' => 'Hochwertiges Weizenbierglas (580 ml) mit 360° Rundum-Gravur.',
            'status' => 'active',
            'price' => 2490,
            'compare_at_price' => 2990,
            'cost_per_item' => 550,
            'sku' => 'GLASS-WEIZEN-001',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 200,
            'continue_selling_when_out_of_stock' => true,

            // ECHTE PHYSIKALISCHE MAßE IN MM (aus cm umgerechnet)
            'weight' => 450,
            'width' => 74,      // 7,4 cm
            'height' => 218,    // 21,8 cm
            'length' => 74,     // 7,4 cm
            'shipping_class' => 'paket_m',

            // Dateipfade
            'preview_image_path' => 'testdata/weizenspaß/overlay.png',
            'three_d_model_path' => 'testdata/weizenspaß/beer_glas_3d.glb',
            'three_d_background_path' => null,

            // Einzelbild
            'media_gallery' => [
                ['type' => 'image', 'path' => 'testdata/weizenspaß/beer_glas_main.jpg', 'is_main' => true, 'alt' => 'Weizenglas Frontansicht']
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,

                // LOGISCH BERECHNETE xTool MASCHINEN-PARAMETER (in mm)
                'xtool_d_top' => 74.0,           // Max Breite des Glases
                'xtool_d_bottom' => 50.0,        // Schätzwert für den verjüngten Fuß
                'xtool_height' => 148.0,         // Gesamthöhe (218) - Oben (30) - Unten (40)
                'xtool_offset_top' => 30.0,      // 3 cm Platz als Trinkrand
                'xtool_offset_bottom' => 40.0,   // 4 cm Platz am Boden für Rollen/Klemmen

                // Exakte 2D Arbeitsbereich-Werte aus deinem 3D-Konfigurator-Design
                'area_shape' => 'rect',
                'area_top' => 14.6,
                'area_left' => 0,
                'area_width' => 100,
                'area_height' => 64.7,

                'overlay_type' => 'cylinder',
                'cylinder_radius' => 50,
                'material_type' => 'glass',

                'model_scale' => 100,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => -180, 'model_rot_y' => 0, 'model_rot_z' => -180,

                'engraving_scale' => 100,
                'engraving_pos_x' => 0, 'engraving_pos_y' => 0, 'engraving_pos_z' => 0,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 0, 'engraving_rot_z' => 0,

                'custom_points' => [
                    ['x' => 20, 'y' => 20], ['x' => 80, 'y' => 20],
                    ['x' => 80, 'y' => 80], ['x' => 20, 'y' => 80]
                ]
            ],

            // ANGEPASSTE KUNDEN-ATTRIBUTE (Werden auf der Produktseite im Shop angezeigt)
            'attributes' => [
                'Material' => 'Hochwertiges Kristallglas',
                'Volumen' => '0,5 Liter (max. 580 ml)',
                'Größe' => '7,4 x 21,8 cm',
                'Pflege' => 'Spülmaschinenfest'
            ],
            'tier_pricing' => [],
            'seo_title' => 'Weizenglas mit eigener Gravur | Mein-Seelenfunke',
            'seo_description' => 'Gestalten Sie Ihr eigenes Weizenglas mit Rundum-Gravur online. Perfekt als Geschenk.',
            'completion_step' => 4
        ]);

        $catIds5 = Category::whereIn('name', ['Glas & Kristall', 'Geschenksets'])->pluck('id');
        $p5->categories()->attach($catIds5);
    }
}

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

        $p1 = Product::create([
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'type' => 'physical',
            'description' => 'Ein handgeschliffener Kristall für besondere Momente. Durch die hochwertige 3D-Innengravur schwebt Ihr Wunschmotiv förmlich im Glas.',
            'short_description' => 'Personalisiertes 3D-Glasgeschenk inkl. Geschenkbox.',
            'status' => 'active',
            'price' => 3990,
            'compare_at_price' => 4990,
            'cost_per_item' => 1250,
            'sku' => 'CRYSTAL-001-CLR',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 150,
            'continue_selling_when_out_of_stock' => true,

            // Exakte Produktdaten
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

                // Arbeitsbereich 2D
                'area_shape' => 'circle',
                'area_top' => 10,
                'area_left' => 10,
                'area_width' => 81,
                'area_height' => 80,

                // 3D Modell Einstellungen
                'material_type' => 'glass',
                'model_scale' => 100,
                'model_pos_x' => 0,
                'model_pos_y' => 0,
                'model_pos_z' => 0,
                'model_rot_x' => 49.92,
                'model_rot_y' => 88,79,
                'model_rot_z' => -53.13,

                // 3D Overlay (Gravur Ebene)
                'engraving_scale' => 101,8,
                'engraving_pos_x' => -0,05,
                'engraving_pos_y' => 0,53,
                'engraving_pos_z' => -4,94,
                'engraving_rot_x' => -175,
                'engraving_rot_y' => -88.63,
                'engraving_rot_z' => -175.57,

                'custom_points' => [
                    ['x' => 20, 'y' => 20],
                    ['x' => 80, 'y' => 20],
                    ['x' => 80, 'y' => 80],
                    ['x' => 20, 'y' => 80]
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
    }
}


/* // --- PRODUKT 2: Der Seelenanhänger (Physisch) ---
               $p2 = Product::create([
                   'name' => 'Der Seelenanhänger',
                   'slug' => 'seelen-anhaenger',
                   'type' => 'physical',
                   'description' => 'Ein Aluminium Metall Herz das ganz besondere Gefühle auslösen kann. Es ist sehr hochwertig und schwer, wodurch es sich besonders wertig in der Hand endanfühlt. Ideal als Handschmeichler oder persönlicher Glücksbringer.',
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
                   'media_gallery' => [
                       ['type' => 'image', 'path' => 'testdata/seelenanhaenger/seelen-anhaenger_s.jpg', 'is_main' => true, 'alt' => 'Seelenanhänger Frontansicht'],
                       ['type' => 'video', 'path' => 'testdata/seelenkristall/video.mp4', 'is_main' => false]
                   ],
                   'configurator_settings' => [
                       'allow_text_pos' => true,
                       'allow_logo' => true,
                       'area_top' => 10,
                       'area_left' => 10,
                       'area_width' => 80,
                       'area_height' => 80,
                       'area_shape' => 'heart'
                   ],
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

               // -> Pivot Tabelle füllen (category_product)
               $catIds2 = Category::whereIn('name', ['Metall & Alu', 'Schmuck & Anhänger'])->pluck('id');
               $p2->categories()->attach($catIds2);

               // -> Staffelpreise
               $p2->tierPrices()->createMany([
                   ['qty' => 10, 'percent' => 5.00],
                   ['qty' => 20, 'percent' => 10.00],
               ]);


               // --- PRODUKT 3: Das Seelenbuch (Digital) ---
               $p3 = Product::create([
                   'name' => 'Das Seelenbuch', // NEUER NAME
                   'slug' => 'seelenbuch',
                   'type' => 'digital',
                   'description' => 'Ihr digitaler Begleiter für mehr Achtsamkeit und Inspiration. Dieses E-Book enthält wertvolle Impulse, Gedankenanstöße und praktische Übungen für den Alltag. Einfach herunterladen und sofort loslegen. Kompatibel mit allen gängigen Tablets und Readern.',
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

                   // Versand null
                   'weight' => null, 'height' => null, 'width' => null, 'length' => null, 'shipping_class' => null,

                   // KORRIGIERTE PFADE ("seelenbuch" Ordner)
                   'digital_download_path' => 'testdata/seelenbuch/Produktübersicht - Mein Seelenfunke.pdf',
                   'digital_filename' => 'Produktübersicht - Mein Seelenfunke.pdf',

                   'preview_image_path' => null,
                   'media_gallery' => [
                       [
                           'type' => 'image',
                           'path' => 'testdata/seelenbuch/Seelen-Book.png', // KORRIGIERT
                           'is_main' => true,
                           'alt' => 'Das Seelenbuch Cover'
                       ]
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

               // -> Pivot Tabelle füllen (category_product)
               $catIds3 = Category::whereIn('name', ['E-Books & Guides'])->pluck('id');
               $p3->categories()->attach($catIds3);


               // --- PRODUKT 4: Persönliche Laser-Beratung (Service) ---
               $p4 = Product::create([
                   'name' => 'Persönliche Laser-Beratung',
                   'slug' => 'laser-beratung',
                   'type' => 'service',
                   'description' => 'Planen Sie ein Großprojekt oder benötigen Sie Hilfe bei der Erstellung Ihrer Gravurdaten? Buchen Sie eine 30-minütige persönliche Beratung per Video-Call mit unseren Experten. Wir besprechen Materialien, Machbarkeit und Optimierung Ihrer Dateien für das perfekte Ergebnis. Ideal für Firmenkunden und komplexe Unikate.',
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

                   // Versand null
                   'weight' => null, 'height' => null, 'width' => null, 'length' => null, 'shipping_class' => null,
                   'digital_download_path' => null, 'digital_filename' => null,

                   'preview_image_path' => null,
                   'media_gallery' => [
                       [
                           'type' => 'image',
                           'path' => 'testdata/laserberatung/laser-beratung.png',
                           'is_main' => true,
                           'alt' => 'Laser Beratungsservice'
                       ]
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

               // -> Pivot Tabelle füllen (category_product)
               $catIds4 = Category::whereIn('name', ['Beratung', 'Express-Service'])->pluck('id');
               $p4->categories()->attach($catIds4);*/

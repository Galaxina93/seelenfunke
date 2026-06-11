<?php

namespace Database\Seeders;

use App\Models\Product\ProductCategory;
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

        $sup1 = \App\Models\Product\ProductSupplier::where('name', 'Pujiang Wangzhe Crafts Co., Ltd.')->first();
        $sup2 = \App\Models\Product\ProductSupplier::where('name', 'Gifts Crafts Zone')->first();
        $sup3 = \App\Models\Product\ProductSupplier::where('name', 'Sendez')->first();
        $sup4 = \App\Models\Product\ProductSupplier::where('name', 'Mambocat')->first();

        foreach ($cats as $name => $type) {
            ProductCategory::firstOrCreate(
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
        $p1 = Product::firstOrCreate(['slug' => 'seelen-kristall'], [
            'name' => 'Der Seelen Kristall',
            'type' => 'physical',
            'description' => 'Halten Sie besondere Momente für die Ewigkeit fest – mit einem meisterhaft geschliffenen K9-Kristall. Dank unserer detailverliebten 3D-Gravurmethode schwebt Ihr persönliches Wunschmotiv förmlich im Inneren des massiven, glasklaren Blocks. Der Seelen Kristall brilliert durch seine hohe optische Reinheit, die das Licht in faszinierenden Facetten bricht. Ob als tiefgründiges Geschenk für einen geliebten Menschen oder als bedeutungsvolle Erinnerung für Sie selbst: Er entfaltet in jedem Raum seine beeindruckende Präsenz und wird garantiert zum Blickfang. Jedes Exemplar wird in einer schützenden Premium-Geschenkbox geliefert, optimal vorbereitet für die sofortige Übergabe.',
            'short_description' => 'Hochgradig personalisierbares und exklusiv veredeltes Premium-Kristallglas. Inklusive maßgeschneiderter 3D-Innengravur und edler Geschenkpräsentation.',
            'status' => 'active',
            'price' => 3990,
            'compare_at_price' => null,
            'purchase_price' => 536, // EK: ~268€ (285 USD net) / 50 Stück = ~5.36€ pro Stück
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 15,
            'packaging_cost' => 60,
            'marketing_cost_percent' => 15.00,
            'sku' => 'CRYSTAL-001-CLR',
            'product_supplier_id' => $sup1?->id,
            'reorder_url' => 'https://german.alibaba.com/product-detail/Pujiang-Wangzhe-Manufacturer-Wholesale-Blank-Glass-1600897934261.html?chatToken=TDFKeWNGRlpkWFZTWmtkNFRYRnZjVWxhVTBsU1ozUklWbW96V1M5dmVGQkxla1pYWlZWM1RHTTBSbHB6V0ZKak9YVnNSeXR3ZW5aUVNVTmphWE0yVWxaU1ZGa3haa0p3T0hjclQyaDVjRFZhSzJOVmNtbHFSalYwUzFkM1dGRlBVRVZ1WVhaU1JHaGhjRlptUkRJeWFISk5XRTFuZGpGa2FHVjJaWFJFZVd0dVIyZHFlV0ZzUjBKVFZUbFJNVWd3TVRGUGR5OUdkbEp2TmtSTlowSmpXR3N4WjFCSVYwWmtWa0ZZVjAxdVN6VmxNVWxzV2sxcU1FaDRlVmRoUzJadCZ2ZXJzaW9uPTIuMC4w&encryptTargetLoginId=8pctgRBMALM%2FQHxJOCLxWd%2BlWrcRHecO6wdkfYSa%2Bs4%3D',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 150,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 920,
            'packaging_weight' => 150,
            'width' => 180,
            'height' => 200,
            'length' => 40,
            'shipping_class' => 'paket_s',
            'preview_image_path' => 'produkte/products/seelen-kristall/overlay.png',
            'three_d_model_path' => 'produkte/products/seelen-kristall/t_seelenk.glb',
            'three_d_background_path' => 'produkte/products/seelen-kristall/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/seelen-kristall/seelen-kristall_b.jpg', 'is_main' => true, 'alt' => 'Seelen Kristall Frontansicht']
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
                'model_rot_x' => -102.57,
                'model_rot_y' => 89.49,
                'model_rot_z' => 99.97,
                'engraving_scale' => 66.04,
                'engraving_pos_x' => -0.49,
                'engraving_pos_y' => 0.62,
                'engraving_pos_z' => -4.28,
                'engraving_rot_x' => -108.76,
                'engraving_rot_y' => 88.32,
                'engraving_rot_z' => 110.35,
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
            'seo_title' => 'Der Seelen Kristall | Personalisiertes Premium Glas-Geschenk online gestalten',
            'seo_description' => 'Erwecken Sie Erinnerungen zum Leben. Entdecken Sie den Seelen Kristall aus exklusivem K9-Glas mit präziser 3D-Gravur. Ihr individuelles Geschenk für besondere Menschen.',
            'completion_step' => 4
        ]);

        $catIds1 = ProductCategory::whereIn('name', ['Glas & Kristall', 'Geschenksets', 'Bestseller'])->pluck('id');
        $p1->categories()->sync($catIds1);

        // Staffelpreise als relationale Datensätze
        $p1->tierPrices()->delete();
        $p1->tierPrices()->createMany([
            ['qty' => 5, 'percent' => 5],
            ['qty' => 10, 'percent' => 10],
            ['qty' => 20, 'percent' => 15]
        ]);

        // --- PRODUKT 2: Der Seelenanhänger (Physisch) ---
        $p2 = Product::firstOrCreate(['slug' => 'seelen-anhaenger'], [
            'name' => 'Der Seelenanhänger',
            'type' => 'physical',
            'description' => 'Der Seelenanhänger verkörpert pure Emotion in einer greifbaren Form. Dieses außergewöhnlich schwere und massiv gefertigte Aluminium-Herz besticht durch eine Handschmeichler-Haptik, die beruhigend und überzeugend zugleich wirkt. Durch unsere exklusive Laser-Veredelung können Sie das Herz sowohl auf der Vorder- als auch auf der Rückseite mit feinsten Botschaften, Koordinaten oder Initialen versehen lassen. Ein idealer ständiger Begleiter, sei es am Schlüsselbund oder als stiller Glücksbringer in der Hosentasche.',
            'short_description' => 'Massives, handschmeichelndes Aluminium-Herz mit beidseitiger, hochpräziser Lasergravur. Ein fühlbarer Ausdruck wahrer Zuneigung.',
            'status' => 'active',
            'price' => 1699,
            'compare_at_price' => null,
            'purchase_price' => 136, // EK: 13,67€ / 10 Stück = 1,36€ pro Stück
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 5,
            'packaging_cost' => 14,
            'marketing_cost_percent' => 10.00,
            'sku' => 'ALU-HEART-001',
            'product_supplier_id' => $sup2?->id,
            'reorder_url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=601100677385875&sku_id=17596815295317&_oak_page_source=501&_x_sessn_id=53dsx2mzic&refer_page_name=shopping_cart&refer_page_id=10037_1770854253534_8i2pculylb&refer_page_sn=10037',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 150,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 19,
            'packaging_weight' => 0,
            'height' => 40,
            'width' => 45,
            'length' => 2.3,
            'shipping_class' => 'paket_s',
            'preview_image_path' => 'produkte/products/seelen-anhaenger/overlay.png',
            'three_d_model_path' => 'produkte/products/seelen-anhaenger/seelenanhaenger_3d_.glb',
            'three_d_background_path' => 'produkte/products/seelen-anhaenger/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/seelen-anhaenger/seelen-anhaenger_s.jpg', 'is_main' => true, 'alt' => 'Seelenanhänger Frontansicht'],
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
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
            'seo_title' => 'Der Seelenanhänger | Massives graviertes Alu-Herz als Geschenk',
            'seo_description' => 'Zeigen Sie Zuneigung, die man fühlen kann. Der Seelenanhänger: Ein schweres, massives Metallherz mit individueller Zwei-Seiten-Gravur. Online konfigurieren.',
            'completion_step' => 4
        ]);

        $catIds2 = ProductCategory::whereIn('name', ['Metall & Alu', 'Schmuck & Anhänger'])->pluck('id');
        $p2->categories()->sync($catIds2);

        // --- PRODUKT 3: Der Feierabend-Funke (NEU) ---
        $p3 = Product::firstOrCreate(['slug' => 'weizenglas-personalisiert'], [
            'name' => 'Der Feierabend-Funke',
            'type' => 'physical',
            'description' => 'Krönen Sie den wohlverdienten Feierabend mit einem Glas, das genauso einzigartig ist wie Sie! Der Feierabend-Funke is ein klassisch geformtes, hochwertiges Kristall-Weizenbierglas, das durch eine atemberaubende 360° Rundum-Gravur Ihr persönliches Highlight wird. Mittels feinster Laser-Rotationstechnik brennen wir Ihre Texte, Vereinslogos oder humorvollen Sprüche tief und abriebfest ins Material ein. Das Glas bleibt dabei zu 100% spülmaschinenfest und behält auch nach vielen Einsätzen seine vollkommene Brillanz.',
            'short_description' => 'Premium-Weizenbierglas (0,5l) versehen mit einer beeindruckenden, spülmaschinenfesten 360° Lasergravur. Perfekt als Vereins- oder Männergeschenk.',
            'status' => 'active',
            'price' => 2490,
            'compare_at_price' => 2990,
            'purchase_price' => 333, // EK: 19,99€ / 6 Stück = 3,33€ pro Stück
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 15,
            'packaging_cost' => 60,
            'marketing_cost_percent' => 12.50,
            'sku' => 'GLASS-WEIZEN-001',
            'product_supplier_id' => $sup4?->id,
            'reorder_url' => 'https://www.metro.de/marktplatz/product/70a1602b-8551-48a6-aff4-d763d68f1f33',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 200,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 450,
            'packaging_weight' => null,
            'width' => 74,      // 7,4 cm
            'height' => 218,    // 21,8 cm
            'length' => 74,     // 7,4 cm
            'shipping_class' => 'paket_m',
            'preview_image_path' => 'produkte/products/weizenglas-personalisiert/overlay.png',
            'three_d_model_path' => 'produkte/products/weizenglas-personalisiert/beer_glas_3d.glb',
            'three_d_background_path' => null,
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/weizenglas-personalisiert/beer_glas_main.jpg', 'is_main' => true, 'alt' => 'Weizenglas Frontansicht']
            ],
            'configurator_settings' => [
                'allow_text_pos' => true,
                'allow_logo' => true,
                'xtool_d_top' => 74.0,           // Max Breite des Glases
                'xtool_d_bottom' => 50.0,        // Schätzwert für den verjüngten Fuß
                'xtool_height' => 148.0,         // Gesamthöhe (218) - Oben (30) - Unten (40)
                'xtool_offset_top' => 30.0,      // 3 cm Platz als Trinkrand
                'xtool_offset_bottom' => 40.0,   // 4 cm Platz am Boden für Rollen/Klemmen
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

        $catIds5 = ProductCategory::whereIn('name', ['Glas & Kristall', 'Geschenksets'])->pluck('id');
        $p3->categories()->sync($catIds5);

        // --- PRODUKT 4: KI-Agenten im E-Commerce (Digital) ---
        $p4 = Product::updateOrCreate(['slug' => 'ki-agenten-ecommerce-playbook'], [
            'name' => 'KI-Agenten im E-Commerce: Der Solo-Entwickler-Praxisbericht',
            'type' => 'digital',
            'description' => 'Der ultimative, unzensierte Praxisbericht für Entwickler, Softwarearchitekten und Tech-Entrepreneure. Auf 176 Seiten dokumentieren wir Schritt für Schritt die 4-monatige Entwicklungsreise eines autonomen Multi-Agenten-ERP-Systems. Kein theoretisches KI-Marketing-Geschwätz, sondern pure, produktionsreife Realität für dein eigenes Business.

Deine konkreten Vorteile und Umsetzungsfähigkeiten nach dem Lesen:
• KI-Telefonie-Infrastruktur selbst aufbauen: Verbinde Twilio-Sprachströme in Echtzeit über WebSockets mit der Gemini Live API. Führe vollautomatische Telefonate mit intelligenten Support- und Vertriebs-Agenten, die Aktionen im Backend ausführen.
• Interaktive 3D-Konfiguratoren entwickeln: Erlerne mathematisch präzises zylindrisches Texture-Mapping in Three.js, um 3D-Modelle im WebGL-Canvas live zu manipulieren und nahtlos mit Alpine.js und Livewire 3 zu synchronisieren.
• Native C++ ELSTER ERiC Integration: Binde die native C++ Bibliothek der Finanzbehörden direkt in deine PHP-Anwendung ein – für rechtssichere Umsatzsteuermeldungen ohne teure API-Drittanbieter.
• Agent-zu-Agent-Kommunikationsprotokolle: Implementiere Loop-Detection und automatisiertes Klonen von Agenten-Instanzen für komplexe, parallele Hintergrundprozesse.
• Self-Healing & Backend-Ausfallsicherheit: Integriere Exception Listener, die Stacktraces analysieren, eigenständig Code-Patches generieren und fehlerhafte Transaktionen atomar absichern.

Zusätzlich erhältst du das vollständige ZIP-Archiv mit allen 200 einsatzbereiten Code-Assets (Laravel-Migrations, Models, Node.js-Proxys und C++ Wrapper-Strukturen) zum direkten Kopieren und Einfügen in dein eigenes Projekt. Überspringe Monate voller Trial-and-Error!',
            'is_personalizable' => false,
            'short_description' => 'Digitaler Praxisbericht (176 Seiten PDF) + 200 Code-Assets (ZIP) für Multi-Agenten-Systeme.',
            'status' => 'active',
            'price' => 4900,
            'compare_at_price' => null,
            'sku' => 'PLAYBOOK-KI-AGENTS-01',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => false,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
            'weight' => null, 'height' => null, 'width' => null, 'length' => null, 'shipping_class' => null,
            'digital_download_path' => 'produkte/products-secure/KI_Agenten_Management_Paket.zip',
            'digital_filename' => 'KI_Agenten_Management_Paket.zip',
            'preview_image_path' => 'system/testdata/ki-agenten-playbook/playbook-cover.jpg',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'system/testdata/ki-agenten-playbook/playbook-cover.jpg', 'is_main' => true, 'alt' => 'KI-Agenten im E-Commerce Cover'],
                ['type' => 'video', 'path' => 'system/testdata/ki-agenten-playbook/playbook-promo.mp4', 'is_main' => false, 'alt' => 'KI-Agenten im E-Commerce Promo-Video'],
                ['type' => 'image', 'path' => 'system/testdata/ki-agenten-playbook/playbook-perspective-tablet.jpg', 'is_main' => false, 'alt' => 'KI-Agenten im E-Commerce Tablet-Ansicht'],
                ['type' => 'image', 'path' => 'system/testdata/ki-agenten-playbook/playbook-perspective-devices.jpg', 'is_main' => false, 'alt' => 'KI-Agenten im E-Commerce Multi-Device-Ansicht']
            ],
            'configurator_settings' => [
                'allow_text_pos' => false,
                'allow_logo' => false,
            ],
            'attributes' => [
                'Format' => 'PDF + ZIP (Digital)',
                'Seiten' => '176',
                'Assets' => '200 Code-Dateien',
                'Sprache' => 'Deutsch',
                'Auslieferung' => 'Sofort-Download'
            ],
            'tier_pricing' => [],
            'seo_title' => 'KI-Agenten im E-Commerce | Solo-Entwickler-Praxisbericht',
            'seo_description' => '176 Seiten PDF + 200 Code-Assets. Wie man ein Multi-Agenten-ERP mit Laravel 13, Twilio WebSockets, Three.js und C++ ELSTER aufbaut.',
            'completion_step' => 4
        ]);

        $catIds3 = ProductCategory::whereIn('name', ['E-Books & Guides'])->pluck('id');
        $p4->categories()->sync($catIds3);

        // --- PRODUKT 5: Geschenkgutschein (Digital / Konfigurierbar) ---
        $p5 = Product::updateOrCreate(['slug' => 'geschenkgutschein'], [
            'name' => 'Geschenkgutschein',
            'type' => 'digital',
            'description' => 'Verschenken Sie unvergessliche Momente mit unserem edlen Seelenfunken Geschenkgutschein. Der Gutschein kann wahlweise digital per E-Mail oder hochwertig gedruckt per Post bestellt werden. Sie können den Betrag flexibel wählen und eine persönliche Grußbotschaft hinzufügen. Der perfekte Ausdruck Ihrer Wertschätzung.',
            'is_personalizable' => true,
            'short_description' => 'Der flexible Seelenfunken Geschenkgutschein als E-Mail oder edle Post-Klappkarte mit Wachssiegel.',
            'status' => 'active',
            'price' => 0, // Wird dynamisch durch den Konfigurator/Warenkorb bestimmt
            'compare_at_price' => null,
            'sku' => 'GIFT-VOUCHER',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => false,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
            'weight' => null, 'height' => null, 'width' => null, 'length' => null, 'shipping_class' => null,
            'preview_image_path' => 'produkte/products/geschenkgutschein/envelope.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/envelope.png', 'is_main' => true, 'alt' => 'Geschenkgutschein Umschlag']
            ],
            'configurator_settings' => [
                'allow_text_pos' => false,
                'allow_logo' => false,
            ],
            'attributes' => [
                'Art' => 'Mehrzweck-Gutschein (Wertgutschein)',
                'Auslieferung' => 'E-Mail (Sofort) oder Postversand',
                'Gültigkeit' => '3 Jahre zum Jahresende (§ 195 BGB)'
            ],
            'tier_pricing' => [],
            'seo_title' => 'Geschenkgutschein online kaufen & verschenken | Mein-Seelenfunke',
            'seo_description' => 'Edler Geschenkgutschein von Mein-Seelenfunke. Flexibler Wert, mit persönlicher Botschaft. Digital per E-Mail oder als Postkarte mit Wachssiegel bestellen.',
            'completion_step' => 4
        ]);

        $catIds5 = ProductCategory::whereIn('name', ['Geschenksets', 'Bestseller'])->pluck('id');
        $p5->categories()->sync($catIds5);
    }
}

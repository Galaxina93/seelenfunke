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
        $sup5 = \App\Models\Product\ProductSupplier::where('name', 'HaoYiZF (Fuhao) - Temu')->first();
        $sup6 = \App\Models\Product\ProductSupplier::where('name', 'Thal-Versand / Amazon Business DE')->first();

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
        $p1 = Product::updateOrCreate(['slug' => 'seelen-kristall'], [
            'name' => 'Der Seelen Kristall',
            'type' => 'physical',
            'description' => 'Halten Sie besondere Momente für die Ewigkeit fest – mit einem meisterhaft geschliffenen K9-Kristall. Dank unserer detailverliebten 3D-Gravurmethode schwebt Ihr persönliches Wunschmotiv förmlich im Inneren des massiven, glasklaren Blocks. Der Seelen Kristall brilliert durch seine hohe optische Reinheit, die das Licht in faszinierenden Facetten bricht. Ob als tiefgründiges Geschenk für einen geliebten Menschen oder als bedeutungsvolle Erinnerung für Sie selbst: Er entfaltet in jedem Raum seine beeindruckende Präsenz und wird garantiert zum Blickfang. Jedes Exemplar wird in einer schützenden Premium-Geschenkbox geliefert, optimal vorbereitet für die sofortige Übergabe.',
            'short_description' => 'Hochgradig personalisierbares und exklusiv veredeltes Premium-Kristallglas. Inklusive maßgeschneiderter 3D-Innengravur und edler Geschenkpräsentation.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
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
                ['type' => 'image', 'path' => 'produkte/products/seelen-kristall/seelen-kristall_b.jpg', 'is_main' => true, 'alt' => 'Der Seelen Kristall – Personalisierter K9 Kristall mit Lasergravur'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-kristall/seelen-kristall-detail.jpg', 'is_main' => false, 'alt' => 'Der Seelen Kristall – Detailansicht Facettenschliff & Lasergravur'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-kristall/seelen-kristall-giftbox.jpg', 'is_main' => false, 'alt' => 'Der Seelen Kristall in schwarzer Seelenfunke Premium-Geschenkbox'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-kristall/seelen-kristall-dimensions.jpg', 'is_main' => false, 'alt' => 'Der Seelen Kristall – Technisches Maßblatt & Abmessungen (180 x 200 x 40 mm)'],
                ['type' => 'video', 'path' => 'produkte/products/seelen-kristall/seelen-kristall-promo.mp4', 'is_main' => false, 'alt' => 'Der Seelen Kristall – Produktvideo'],
            ],
            'configurator_settings' => [
                'template_package' => 'seelen-kristall',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
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
                'model_pos_x' => 0,
                'model_pos_y' => 0,
                'model_pos_z' => 0,
                'model_rot_x' => 0,
                'model_rot_y' => 0,
                'model_rot_z' => 0,
                'engraving_scale' => 68,
                'engraving_pos_x' => 0,
                'engraving_pos_y' => 0.30,
                'engraving_pos_z' => -0.64,
                'engraving_rot_x' => 0,
                'engraving_rot_y' => 180,
                'engraving_rot_z' => 0,
                'custom_points' => [
                    ['x' => 20, 'y' => 20], ['x' => 80, 'y' => 20],
                    ['x' => 80, 'y' => 80], ['x' => 20, 'y' => 80]
                ]
            ],
            'attributes' => [
                'Material' => 'Optisch reines K9 Kristallglas (Facettenschliff)',
                'Größe' => '180 x 200 x 40 mm',
                'Gewicht' => 'ca. 920 g (massiv)',
                'Veredelung' => 'Hochpräzise 3D-Lasergravur',
                'Lieferumfang' => 'Inkl. gepolsterter Seelenfunke Geschenkbox',
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
        $p2 = Product::updateOrCreate(['slug' => 'seelen-anhaenger'], [
            'name' => 'Der Seelenanhänger',
            'type' => 'physical',
            'description' => 'Der Seelenanhänger verkörpert pure Emotion in einer greifbaren Form. Dieses außergewöhnlich schwere und massiv gefertigte Aluminium-Herz besticht durch eine Handschmeichler-Haptik, die beruhigend und überzeugend zugleich wirkt. Durch unsere exklusive Laser-Veredelung können Sie das Herz sowohl auf der Vorder- als auch auf der Rückseite mit feinsten Botschaften, Koordinaten oder Initialen versehen lassen. Ein idealer ständiger Begleiter, sei es am Schlüsselbund oder als stiller Glücksbringer in der Hosentasche.',
            'short_description' => 'Massives, handschmeichelndes Aluminium-Herz mit beidseitiger, hochpräziser Lasergravur. Ein fühlbarer Ausdruck wahrer Zuneigung.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
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
                ['type' => 'image', 'path' => 'produkte/products/seelen-anhaenger/seelen-anhaenger_s.jpg', 'is_main' => true, 'alt' => 'Der Seelenanhänger – Massives Alu-Herz mit Lasergravur'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-anhaenger/seelen-anhaenger-back.jpg', 'is_main' => false, 'alt' => 'Der Seelenanhänger – Beidseitige Gravur mit Wunschdatum & Koordinaten'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-anhaenger/seelen-anhaenger-giftbox.jpg', 'is_main' => false, 'alt' => 'Der Seelenanhänger im edlen Seelenfunke Geschenketui'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-anhaenger/seelen-anhaenger-dimensions.jpg', 'is_main' => false, 'alt' => 'Der Seelenanhänger – Technisches Maßblatt & Abmessungen (45 x 40 x 2,3 mm)'],
                ['type' => 'video', 'path' => 'produkte/products/seelen-anhaenger/seelen-anhaenger-promo.mp4', 'is_main' => false, 'alt' => 'Der Seelenanhänger – Produktvideo'],
            ],
            'configurator_settings' => [
                'template_package' => 'seelen-anhaenger',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
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
                'Material' => 'Eloxiertes Luftfahrt-Aluminium (Massiv)',
                'Form' => 'Handschmeichler-Herz (Beidseitig gravierbar)',
                'Größe' => '45 x 40 x 2,3 mm',
                'Gewicht' => 'ca. 19 g',
                'Veredelung' => 'Abriebfeste Silber-Weiß Faserlaser-Gravur',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Der Seelenanhänger | Massives graviertes Alu-Herz als Geschenk',
            'seo_description' => 'Zeigen Sie Zuneigung, die man fühlen kann. Der Seelenanhänger: Ein schweres, massives Metallherz mit individueller Zwei-Seiten-Gravur. Online konfigurieren.',
            'completion_step' => 4
        ]);

        $catIds2 = ProductCategory::whereIn('name', ['Metall & Alu', 'Schmuck & Anhänger', 'Bestseller'])->pluck('id');
        $p2->categories()->sync($catIds2);
        $p2->tierPrices()->delete();
        $p2->tierPrices()->createMany([
            ['qty' => 3, 'percent' => 5],
            ['qty' => 5, 'percent' => 10],
            ['qty' => 10, 'percent' => 15]
        ]);

        // --- PRODUKT 3: Der Feierabend-Funke (Physisch) ---
        $p3 = Product::updateOrCreate(['slug' => 'weizenglas-personalisiert'], [
            'name' => 'Der Feierabend-Funke',
            'type' => 'physical',
            'description' => 'Krönen Sie den wohlverdienten Feierabend mit einem Glas, das genauso einzigartig ist wie Sie! Der Feierabend-Funke ist ein klassisch geformtes, hochwertiges Kristall-Weizenbierglas, das durch eine atemberaubende 360° Rundum-Gravur Ihr persönliches Highlight wird. Mittels feinster Laser-Rotationstechnik brennen wir Ihre Texte, Vereinslogos oder humorvollen Sprüche tief und abriebfest ins Material ein. Das Glas bleibt dabei zu 100% spülmaschinenfest und behält auch nach vielen Einsätzen seine vollkommene Brillanz.',
            'short_description' => 'Premium-Weizenbierglas (0,5l) versehen mit einer beeindruckenden, spülmaschinenfesten 360° Lasergravur. Perfekt als Vereins- oder Männergeschenk.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 2490,
            'compare_at_price' => null,
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
            'packaging_weight' => 120,
            'width' => 74,      // 7,4 cm
            'height' => 218,    // 21,8 cm
            'length' => 74,     // 7,4 cm
            'shipping_class' => 'paket_m',
            'preview_image_path' => 'produkte/products/weizenglas-personalisiert/overlay.png',
            'three_d_model_path' => 'produkte/products/weizenglas-personalisiert/beer_glas_3d.glb',
            'three_d_background_path' => 'produkte/products/weizenglas-personalisiert/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/weizenglas-personalisiert/beer_glas_main.jpg', 'is_main' => true, 'alt' => 'Der Feierabend-Funke – Personalisiertes Weizenbierglas'],
                ['type' => 'image', 'path' => 'produkte/products/weizenglas-personalisiert/weizenglas-detail.jpg', 'is_main' => false, 'alt' => 'Der Feierabend-Funke – Detailansicht der frostweißen Lasergravur'],
                ['type' => 'image', 'path' => 'produkte/products/weizenglas-personalisiert/weizenglas-lifestyle.jpg', 'is_main' => false, 'alt' => 'Der Feierabend-Funke – Perfektes Geschenk für Genießer'],
                ['type' => 'image', 'path' => 'produkte/products/weizenglas-personalisiert/weizenglas-dimensions.jpg', 'is_main' => false, 'alt' => 'Der Feierabend-Funke – Technisches Maßblatt & Abmessungen (218 x Ø 74 mm)'],
                ['type' => 'video', 'path' => 'produkte/products/weizenglas-personalisiert/weizenglas-promo.mp4', 'is_main' => false, 'alt' => 'Der Feierabend-Funke – Produktvideo'],
            ],
            'configurator_settings' => [
                'template_package' => 'weizenglas-personalisiert',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
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
                'Material' => 'Brillantes Kristallglas (Gastronomie-Qualität)',
                'Volumen' => '0,5 Liter (Randvoll 580 ml)',
                'Größe' => 'Ø 7,4 cm x 21,8 cm Höhe',
                'Veredelung' => '360° Rotations-Lasergravur',
                'Pflege' => '100% Spülmaschinenfest & abriebfest',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Der Feierabend-Funke | Weizenglas mit eigener 360° Gravur | Mein-Seelenfunke',
            'seo_description' => 'Gestalten Sie Ihr eigenes Weizenglas mit spülmaschinenfester 360° Rundum-Gravur online im 3D-Konfigurator. Perfekt als Geschenk.',
            'completion_step' => 4
        ]);

        $catIds3 = ProductCategory::whereIn('name', ['Glas & Kristall', 'Geschenksets'])->pluck('id');
        $p3->categories()->sync($catIds3);
        $p3->tierPrices()->delete();
        $p3->tierPrices()->createMany([
            ['qty' => 4, 'percent' => 5],
            ['qty' => 6, 'percent' => 10],
            ['qty' => 12, 'percent' => 15]
        ]);

        // --- PRODUKT 4: Der Seelen-Talisman (Physisch / NEU – Massiv Gold Edelstahl-Schlüsselanhänger) ---
        $p4 = Product::updateOrCreate(['slug' => 'seelen-talisman'], [
            'name' => 'Der Seelen-Talisman',
            'type' => 'physical',
            'description' => 'Ein edles Statement für jeden Tag: Der Seelen-Talisman ist aus massivem, spiegelpoliertem Edelstahl mit hochwertigem Gold-Finish gefertigt und besticht durch seine sanft abgerundete Tonnen-Kontur mit massivem Flachprofil-Schlüsselring. Durch unsere hochpräzise Faserlaser-Anlassgravur treten Ihre Wunschbotschaft, Koordinaten, Initialen oder Symbole in gestochen scharfem, abriebfestem Tief-Anthrazit auf dem glänzenden Gold hervor – auf Wunsch beidseitig veredelt! Ob als treuer Schutzbegleiter für den Lieblingsmenschen, als stilvoller Schlüsselanhänger zum neuen Zuhause oder als Jahrestags-Erinnerung: Der Seelen-Talisman liegt spürbar wertig in der Hand und wird im edlen Samtetui geliefert.',
            'short_description' => 'Massiver, spiegelpolierter Edelstahl-Schlüsselanhänger in edlem Gold-Finish mit kontraststarker, beidseitiger Faserlaser-Gravur.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 1990,
            'compare_at_price' => 2490,
            'purchase_price' => 116, // EK laut Temu-Beleg 15.10.2026: 18,60€ / 16 Stück = 1,16€ netto (1,48€ inkl. Einfuhrabgabe)
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 5,
            'packaging_cost' => 17, // Luftpolstertasche Karton.eu 0,17€
            'marketing_cost_percent' => 10.00,
            'sku' => 'STEEL-BAR-001',
            'product_supplier_id' => $sup5?->id,
            'reorder_url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=606203291790930&parent_order_sn=PO-076-11149563187830774&_oak_order_sn=076-11149531730550774&_oak_goods_num=2&sku_id=89857024078818',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 16,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 24,
            'packaging_weight' => 15,
            'height' => 50,
            'width' => 28,
            'length' => 2.2,
            'shipping_class' => 'paket_s',
            'preview_image_path' => 'produkte/products/seelen-talisman/overlay.png',
            'three_d_model_path' => 'produkte/products/seelen-talisman/seelen_talisman_3d.glb',
            'three_d_background_path' => 'produkte/products/seelen-talisman/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-main.jpg', 'is_main' => true, 'alt' => 'Der Seelen-Talisman – Spiegelpolierter Gold-Edelstahl-Schlüsselanhänger mit Gravur'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-back.jpg', 'is_main' => false, 'alt' => 'Der Seelen-Talisman – Beidseitige Gravur mit Wunschkoordinaten'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-lifestyle.jpg', 'is_main' => false, 'alt' => 'Der Seelen-Talisman am Schlüsselbund'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-giftbox.jpg', 'is_main' => false, 'alt' => 'Der Seelen-Talisman in schwarzer Seelenfunke Geschenkbox'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-dimensions.jpg', 'is_main' => false, 'alt' => 'Der Seelen-Talisman – Technisches Maßblatt & Abmessungen (50 x 28 x 2,2 mm)'],
                ['type' => 'video', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-promo.mp4', 'is_main' => false, 'alt' => 'Der Seelen-Talisman – Produktvideo'],
            ],
            'configurator_settings' => [
                'template_package' => 'seelen-talisman',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'area_shape' => 'custom',
                'area_left' => 35.2,
                'area_top' => 46.8,
                'area_width' => 29.6,
                'area_height' => 47.0,
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'metal',
                'model_scale' => 136,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => 0, 'model_rot_y' => 0, 'model_rot_z' => 0,
                // VORDERSEITE (1:1 deckungsgleich zum 800x800 2D-Overlay: W_canvas / maxPlaneSize = 2.222 / 3.0 = 74%)
                'engraving_scale' => 74,
                'engraving_pos_x' => 0, 'engraving_pos_y' => 0, 'engraving_pos_z' => 0,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 180, 'engraving_rot_z' => 0,
                // RÜCKSEITE (-Z Fläche der Gold-Platte, ungespiegelt bei Kamera-Rotation)
                'back_engraving_scale' => 74,
                'back_engraving_pos_x' => 0, 'back_engraving_pos_y' => 0, 'back_engraving_pos_z' => -0.66,
                'back_engraving_rot_x' => 0, 'back_engraving_rot_y' => 0, 'back_engraving_rot_z' => 0,
                'custom_points' => [
                    ['x' => 35.2, 'y' => 48.0],
                    ['x' => 43.0, 'y' => 46.8],
                    ['x' => 50.0, 'y' => 47.2],
                    ['x' => 57.0, 'y' => 46.8],
                    ['x' => 64.8, 'y' => 48.0],
                    ['x' => 64.8, 'y' => 87.5],
                    ['x' => 60.0, 'y' => 91.0],
                    ['x' => 55.0, 'y' => 93.0],
                    ['x' => 50.0, 'y' => 93.8],
                    ['x' => 45.0, 'y' => 93.0],
                    ['x' => 40.0, 'y' => 91.0],
                    ['x' => 35.2, 'y' => 87.5],
                ]
            ],
            'attributes' => [
                'Material' => 'Hochglanzpolierter Edelstahl (Massiv Gold-Finish)',
                'Form' => 'Gewölbte Dog-Tag Plakette mit Flachring (Beidseitig gravierbar)',
                'Größe' => '50 x 28 x 2,2 mm (ohne Schlüsselring)',
                'Gewicht' => 'ca. 24 g (massiv)',
                'Veredelung' => 'Tiefschwarze Faserlaser-Anlassgravur auf Gold',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Der Seelen-Talisman | Gold Edelstahl-Schlüsselanhänger mit Gravur',
            'seo_description' => 'Massiver goldener Edelstahl-Schlüsselanhänger mit individueller Vorder- & Rückseiten-Lasergravur. Jetzt im 3D-Konfigurator gestalten.',
            'completion_step' => 4
        ]);

        $catIds4 = ProductCategory::whereIn('name', ['Metall & Alu', 'Schmuck & Anhänger', 'Bestseller'])->pluck('id');
        $p4->categories()->sync($catIds4);
        $p4->tierPrices()->delete();
        $p4->tierPrices()->createMany([
            ['qty' => 3, 'percent' => 5],
            ['qty' => 5, 'percent' => 10],
            ['qty' => 10, 'percent' => 15]
        ]);

        // --- PRODUKT 5: Die Seelen-Pfote (Physisch / NEU – Edelstahl-Hundemarke Knochenform mit Pfoten-Durchbruch) ---
        $p5Paw = Product::updateOrCreate(['slug' => 'seelen-pfote-hundemarke'], [
            'name' => 'Die Seelen-Pfote',
            'type' => 'physical',
            'description' => 'Sicherheit und Liebe für Ihren treuesten Begleiter: Die Seelen-Pfote ist eine massive, spiegelpolierte Hundemarke in klassischer Knochenform aus rostfreiem V2A-Edelstahl mit integriertem Aufhängungs-Öhr und einem kunstvoll ausgestanzten Pfotenabdruck-Durchbruch auf der rechten Seite. Auf der Vorderseite verewigen wir den Namen Ihres Lieblings samt Wunsch-Symbol in kontraststarker, tief-anthrazitfarbener Faserlaser-Anlassgravur, während die Rückseite ausreichend Platz für Ihre Notfall-Telefonnummer, Adresse oder TASSO-Kennung bietet. Absolut wasserfest, rostfrei und unverwüstlich – für unbeschwerte Abenteuer bei jedem Wetter.',
            'short_description' => 'Massive, spiegelpolierte Edelstahl-Hundemarke in Knochenform mit echtem Pfoten-Durchbruch und beidseitiger Faserlaser-Gravur.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 1790,
            'compare_at_price' => 2190,
            'purchase_price' => 79, // EK laut Temu-Angebot (goods_id=601099514279197): 7,88€ / 10 Stück = 0,79€ pro Stück
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 5,
            'packaging_cost' => 17, // Luftpolstertasche 0,17€
            'marketing_cost_percent' => 10.00,
            'sku' => 'STEEL-PAW-001',
            'product_supplier_id' => $sup5?->id,
            'reorder_url' => 'https://www.temu.com/de/set-mit-10-edelstahl-hundeanhangern-in-knochenform-mit-pfotenabdrucken-zur--von-haustierdetails-g-601099514279197.html?_oak_mp_inf=EJ3ql5im1ogBGiBkNjZjMjc4ZjQ0YTI0NjlhYjE2MGNjNjhiY2Y1NzhiNyDqkKTNlzQ%3D&top_gallery_url=https%3A%2F%2Fimg.kwcdn.com%2Fproduct%2F1e78ea4c92%2F416669dd-5910-4266-893b-80df84f544b1_800x800.jpeg&spec_gallery_id=601099514279197&refer_page_sn=10009&freesia_scene=2&_oak_freesia_scene=2&_oak_rec_ext_1=Nzg4&_oak_gallery_order=778608168&search_key=Hundemarke&refer_page_el_sn=200049&ab_scene=1&_x_sessn_id=b339htugah&refer_page_name=search_result&refer_page_id=10009_1793042484946_vy0ba9cjoe',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 50,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 16,
            'packaging_weight' => 15,
            'height' => 26,
            'width' => 40,
            'length' => 1.8,
            'shipping_class' => 'paket_s',
            'preview_image_path' => 'produkte/products/seelen-pfote-hundemarke/overlay.png',
            'three_d_model_path' => 'produkte/products/seelen-pfote-hundemarke/seelen_pfote_3d.glb',
            'three_d_background_path' => 'produkte/products/seelen-pfote-hundemarke/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-main.jpg', 'is_main' => true, 'alt' => 'Die Seelen-Pfote – Polierte Edelstahl-Hundemarke Knochenform mit Pfoten-Durchbruch & Gravur'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-back.jpg', 'is_main' => false, 'alt' => 'Die Seelen-Pfote – Rückseite mit Notfall-Telefonnummer & TASSO-Hinweis'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-lifestyle.jpg', 'is_main' => false, 'alt' => 'Die Seelen-Pfote Hundemarke am Lederhalsband eines Golden Retrievers'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-giftbox.jpg', 'is_main' => false, 'alt' => 'Die Seelen-Pfote in edler Seelenfunke Geschenkbox'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-dimensions.jpg', 'is_main' => false, 'alt' => 'Die Seelen-Pfote – Technisches Maßblatt & Abmessungen (40 x 26 x 1,8 mm)'],
                ['type' => 'video', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-promo.mp4', 'is_main' => false, 'alt' => 'Die Seelen-Pfote – Produktvideo'],
            ],
            'configurator_settings' => [
                'template_package' => 'seelen-pfote-hundemarke',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'area_shape' => 'custom',
                'area_left' => 11.5,
                'area_top' => 30.0,
                'area_width' => 76.0,
                'area_height' => 40.0,
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'metal',
                'model_scale' => 121,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => 0, 'model_rot_y' => 0, 'model_rot_z' => 0,
                // VORDERSEITE (1:1 deckungsgleich zum 800x800 2D-Overlay: W_canvas / maxPlaneSize = 800 / 960 = 83%)
                'engraving_scale' => 83,
                'engraving_pos_x' => 0, 'engraving_pos_y' => 0, 'engraving_pos_z' => 0,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 180, 'engraving_rot_z' => 0,
                // RÜCKSEITE (-Z Fläche für Telefonnummer & Adresse)
                'back_engraving_scale' => 83,
                'back_engraving_pos_x' => 0, 'back_engraving_pos_y' => 0, 'back_engraving_pos_z' => -0.66,
                'back_engraving_rot_x' => 0, 'back_engraving_rot_y' => 0, 'back_engraving_rot_z' => 0,
                'custom_points' => [
                    ['x' => 19.2, 'y' => 30.0],
                    ['x' => 27.5, 'y' => 32.0],
                    ['x' => 33.8, 'y' => 37.5],
                    ['x' => 44.0, 'y' => 37.5],
                    ['x' => 56.0, 'y' => 37.5],
                    ['x' => 66.2, 'y' => 37.5],
                    ['x' => 72.5, 'y' => 32.0],
                    ['x' => 80.8, 'y' => 30.0],
                    ['x' => 88.0, 'y' => 36.0],
                    ['x' => 88.0, 'y' => 44.0],
                    ['x' => 84.0, 'y' => 50.0],
                    ['x' => 70.0, 'y' => 54.0],
                    ['x' => 66.2, 'y' => 62.5],
                    ['x' => 50.0, 'y' => 62.5],
                    ['x' => 33.8, 'y' => 62.5],
                    ['x' => 27.5, 'y' => 68.0],
                    ['x' => 19.2, 'y' => 70.0],
                    ['x' => 12.0, 'y' => 64.0],
                    ['x' => 12.0, 'y' => 56.0],
                    ['x' => 15.8, 'y' => 50.0],
                    ['x' => 12.0, 'y' => 44.0],
                    ['x' => 12.0, 'y' => 36.0],
                ]
            ],
            'attributes' => [
                'Material' => 'Hochglanzpolierter V2A-Edelstahl (Rostfrei & Wasserfest)',
                'Form' => 'Hundeknochen mit echtem Pfoten-Durchbruch (Beidseitig gravierbar)',
                'Größe' => '40 x 26 x 1,8 mm (inkl. integrierter Öse Ø 5 mm)',
                'Gewicht' => 'ca. 16 g (robust & alltagstauglich)',
                'Veredelung' => 'Tiefschwarze Faserlaser-Anlassgravur auf Silber-Edelstahl',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Die Seelen-Pfote | Edelstahl-Hundemarke mit Pfoten-Durchbruch & Gravur',
            'seo_description' => 'Polierte Edelstahl-Hundemarke in Knochenform mit ausgestanzter Pfote und beidseitiger Lasergravur. Jetzt im 3D-Konfigurator gestalten!',
            'completion_step' => 4
        ]);

        $catIdsPaw = ProductCategory::whereIn('name', ['Metall & Alu', 'Schmuck & Anhänger', 'Bestseller'])->pluck('id');
        $p5Paw->categories()->sync($catIdsPaw);
        $p5Paw->tierPrices()->delete();
        $p5Paw->tierPrices()->createMany([
            ['qty' => 2, 'percent' => 8],
            ['qty' => 3, 'percent' => 12],
            ['qty' => 5, 'percent' => 18]
        ]);

        // --- PRODUKT 4: KI-Agenten im E-Commerce (Digital) ---
/*        $p4 = Product::updateOrCreate(['slug' => 'ki-agenten-ecommerce-playbook'], [
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
            'three_d_active' => false,
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
            'preview_image_path' => 'produkte/products/ki-agenten-playbook/playbook-cover.jpg',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/ki-agenten-playbook/playbook-cover.jpg', 'is_main' => true, 'alt' => 'KI-Agenten im E-Commerce Cover'],
                ['type' => 'video', 'path' => 'produkte/products/ki-agenten-playbook/playbook-promo.mp4', 'is_main' => false, 'alt' => 'KI-Agenten im E-Commerce Promo-Video'],
                ['type' => 'image', 'path' => 'produkte/products/ki-agenten-playbook/playbook-perspective-tablet.jpg', 'is_main' => false, 'alt' => 'KI-Agenten im E-Commerce Tablet-Ansicht'],
                ['type' => 'image', 'path' => 'produkte/products/ki-agenten-playbook/playbook-perspective-devices.jpg', 'is_main' => false, 'alt' => 'KI-Agenten im E-Commerce Multi-Device-Ansicht']
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
        $p4->categories()->sync($catIds3);*/

        // --- PRODUKT 5: Geschenkgutschein (Digital / Konfigurierbar) ---
        $p5 = Product::updateOrCreate(['slug' => 'geschenkgutschein'], [
            'name' => 'Geschenkgutschein',
            'type' => 'digital',
            'description' => 'Verschenken Sie unvergessliche Momente mit unserem edlen Seelenfunken Geschenkgutschein. Der Gutschein kann wahlweise digital per E-Mail oder hochwertig gedruckt per Post bestellt werden. Sie können den Betrag flexibel wählen und eine persönliche Grußbotschaft hinzufügen. Der perfekte Ausdruck Ihrer Wertschätzung.',
            'is_personalizable' => true,
            'three_d_active' => false,
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
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-main.jpg', 'is_main' => true, 'is_ai_generated' => true, 'holiday' => 'Standard', 'alt' => 'Seelenfunke Geschenkgutschein im schwarz-goldenen Umschlag mit Wachssiegel'],
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-halloween.jpg', 'is_main' => false, 'is_ai_generated' => true, 'holiday' => 'Halloween', 'alt' => 'Seelenfunke Geschenkgutschein – Herbst & Lichter Edition'],
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-weihnachten.jpg', 'is_main' => false, 'is_ai_generated' => true, 'holiday' => 'Weihnachten', 'alt' => 'Seelenfunke Geschenkgutschein – Weihnachts-Edition'],
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-valentinstag.jpg', 'is_main' => false, 'is_ai_generated' => true, 'holiday' => 'Valentinstag', 'alt' => 'Seelenfunke Geschenkgutschein – Romantik & Valentinstag Edition'],
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-ostern.jpg', 'is_main' => false, 'is_ai_generated' => true, 'holiday' => 'Ostern', 'alt' => 'Seelenfunke Geschenkgutschein – Frühlings- & Oster-Edition'],
            ],
            'configurator_settings' => [
                'auto_seasonal_main_image' => true,
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

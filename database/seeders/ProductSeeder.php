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
        if (!\Illuminate\Support\Facades\Schema::hasColumn('products', 'collection_line')) {
            \Illuminate\Support\Facades\Schema::table('products', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('collection_line', 32)->default('manufaktur')->index()->after('type');
            });
        }

        $cats = [
            'Glas & Kristall' => 'physical',
            'Geschenksets' => 'physical',
            'Bestseller' => 'physical',
            'Metall & Alu' => 'physical',
            'Schmuck & Anhänger' => 'physical',
            'Atelier Echtgold & Silber' => 'physical',
            'Schiefer & Natur' => 'physical',
            'Gutscheine' => 'digital',
            'E-Books & Guides' => 'digital',
            'Beratung' => 'service',
            'Express-Service' => 'service',
        ];

        $sup1 = \App\Models\Product\ProductSupplier::where('name', 'Pujiang Wangzhe Crafts Co., Ltd.')->first();
        $sup2 = \App\Models\Product\ProductSupplier::where('name', 'Gifts Crafts Zone')->first();
        $sup3 = \App\Models\Product\ProductSupplier::where('name', 'Sendez')->first();
        $sup4 = \App\Models\Product\ProductSupplier::where('name', 'Mambocat')->first();
        $sup5 = \App\Models\Product\ProductSupplier::where('name', 'HaoYiZF (Fuhao) - Temu')->first();
        $sup6 = \App\Models\Product\ProductSupplier::where('name', 'CTHOLD (Wuxi Qiyue) - Temu & Amazon DE')->first();
        $sup8 = \App\Models\Product\ProductSupplier::where('name', 'Glücksfieber (Lübeck)')->first();
        $sup9 = \App\Models\Product\ProductSupplier::where('name', 'like', '%Print Equipment%')->first();
        $sup12 = \App\Models\Product\ProductSupplier::where('name', 'like', '%Silberketten-Store%')->first();
        $sup13Fbm = \App\Models\Product\ProductSupplier::where('name', 'like', '%Friedrich Binder%')->first();

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
            'description' => 'Halten Sie besondere Momente für die Ewigkeit fest, mit einem meisterhaft geschliffenen K9-Kristall. Dank unserer detailverliebten 3D-Gravurmethode schwebt Ihr persönliches Wunschmotiv förmlich im Inneren des massiven, glasklaren Blocks. Der Seelen Kristall brilliert durch seine hohe optische Reinheit, die das Licht in faszinierenden Facetten bricht. Ob als tiefgründiges Geschenk für einen geliebten Menschen oder als bedeutungsvolle Erinnerung für Sie selbst: Er entfaltet in jedem Raum seine beeindruckende Präsenz und wird zu einem besonderen Blickfang. Jedes Exemplar wird in unserer Manufaktur sorgfältig von Hand geprüft und bruchsicher geschützt verpackt geliefert.',
            'short_description' => 'Hochgradig personalisierbares und exklusiv veredeltes Premium-Kristallglas inklusive maßgeschneiderter 3D-Lasergravur.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 6990,
            'compare_at_price' => null,
            'purchase_price' => 536, // EK: ~268€ (285 USD net) / 50 Stück = ~5.36€ pro Stück
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 15,
            'packaging_cost' => 60, // Karton.eu 240stk Kartons für Trophäe = 0,60€ netto
            'marketing_cost_percent' => 15.00,
            'sku' => 'MSF-KRISTALL-3D-001',
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
            'shipping_class' => null, // DHL Standard (Gewichtsbasiert)
            'preview_image_path' => 'produkte/products/seelen-kristall/overlay.png',
            'three_d_model_path' => 'produkte/products/seelen-kristall/t_seelenk.glb',
            'three_d_background_path' => 'produkte/products/seelen-kristall/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/seelen-kristall/seelen-kristall_b.jpg', 'is_main' => true, 'is_hover' => false, 'is_beginner' => true, 'is_ai_generated' => true, 'alt' => 'Der Seelen Kristall: Personalisierter K9 Kristall mit Lasergravur'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-kristall/seelen-kristall-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Der Seelen Kristall: Funkelndes K9-Kristallglas als emotionales Geschenk in den Händen'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-kristall/seelen-kristall-dimensions.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Der Seelen Kristall: Technisches Maßblatt & Abmessungen (180 x 200 x 40 mm)'],
            ],
            'configurator_settings' => [
                'template_package' => 'seelen-kristall',
                'origin_badge' => 'handcrafted_in_germany',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'area_shape' => 'circle',
                'area_top' => 11.6,
                'area_left' => 16.0,
                'area_width' => 68.0,
                'area_height' => 68.0,
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'glass',
                'model_scale' => 128,
                'model_pos_x' => 0,
                'model_pos_y' => 0,
                'model_pos_z' => 0,
                'model_rot_x' => 0,
                'model_rot_y' => 0,
                'model_rot_z' => 0,
                'engraving_scale' => 75.5,
                'engraving_pos_x' => 0,
                'engraving_pos_y' => 0,
                'engraving_pos_z' => -0.52,
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
                'Herkunft' => 'Handveredelt in Deutschland',
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

        // Verpackungsmaterial (LUCID / /admin/product-packaging): Versandkarton + Polsterschutz (150 g gesamt)
        $p1->packagings()->delete();
        $p1->packagings()->createMany([
            ['material_type' => 'paper',   'weight_grams' => 130],
            ['material_type' => 'plastic', 'weight_grams' => 20],
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
            'price' => 2990,
            'compare_at_price' => null,
            'purchase_price' => 136, // EK: 13,67€ / 10 Stück = 1,36€ pro Stück
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 5,
            'packaging_cost' => 14, // Karton.eu 400stk Luftpolster Versandtüten = 0,14€ netto
            'marketing_cost_percent' => 10.00,
            'sku' => 'MSF-ALU-HERZ-001',
            'product_supplier_id' => $sup2?->id,
            'reorder_url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=601100677385875&sku_id=17596815295317&_oak_page_source=501&_x_sessn_id=53dsx2mzic&refer_page_name=shopping_cart&refer_page_id=10037_1770854253534_8i2pculylb&refer_page_sn=10037',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 150,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 19,
            'packaging_weight' => 15, // Luftpolster-Versandtüte (15 g)
            'height' => 40,
            'width' => 45,
            'length' => 2.3,
            'shipping_class' => 'brief', // Brief / Großbrief (Luftpolster-Umschlag)
            'preview_image_path' => 'produkte/products/seelen-anhaenger/overlay.png',
            'three_d_model_path' => 'produkte/products/seelen-anhaenger/seelenanhaenger_3d_.glb',
            'three_d_background_path' => 'produkte/products/seelen-anhaenger/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/seelen-anhaenger/seelen-anhaenger-back.jpg', 'is_main' => true, 'is_hover' => false, 'is_beginner' => true, 'is_ai_generated' => true, 'alt' => 'Der Seelenanhänger: Massives Alu-Herz mit beidseitiger Lasergravur'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-anhaenger/seelen-anhaenger-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Der Seelenanhänger: Graviertes Alu-Herz als treuer Begleiter am Rucksack'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-anhaenger/seelen-anhaenger-dimensions.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Der Seelenanhänger: Technisches Maßblatt & Abmessungen (45 x 40 x 2,3 mm)'],
            ],
            'configurator_settings' => [
                'template_package' => 'seelen-anhaenger',
                'origin_badge' => 'handcrafted_in_germany',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'xtool_plane_width' => 45.0,
                'xtool_plane_height' => 40.0,
                'area_top' => 54.19,
                'area_left' => 28.14,
                'area_width' => 43.72,
                'area_height' => 40.33,
                'area_shape' => 'custom',
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'metal',
                'model_scale' => 136,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => 0, 'model_rot_y' => 0, 'model_rot_z' => 0,
                // VORDERSEITE (1:1 deckungsgleich zum 800x800 2D-Overlay: 800 / (744 * 1.5) = 71.7%)
                'engraving_scale' => 71.7,
                'engraving_pos_x' => 0, 'engraving_pos_y' => 0, 'engraving_pos_z' => 0,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 180, 'engraving_rot_z' => 0,
                // RÜCKSEITE (-Z Fläche des Alu-Herzens, ungespiegelt bei Kamera-Rotation)
                'back_engraving_scale' => 71.7,
                'back_engraving_pos_x' => 0, 'back_engraving_pos_y' => 0, 'back_engraving_pos_z' => -0.66,
                'back_engraving_rot_x' => 0, 'back_engraving_rot_y' => 0, 'back_engraving_rot_z' => 0,
                'custom_points' => [
                    ['x' => 50.0, 'y' => 59.45],
                    ['x' => 51.13, 'y' => 58.73],
                    ['x' => 52.23, 'y' => 58.01],
                    ['x' => 53.34, 'y' => 57.29],
                    ['x' => 54.54, 'y' => 56.57],
                    ['x' => 55.82, 'y' => 55.84],
                    ['x' => 57.26, 'y' => 55.12],
                    ['x' => 58.83, 'y' => 54.52],
                    ['x' => 60.42, 'y' => 54.19],
                    ['x' => 62.68, 'y' => 54.48],
                    ['x' => 64.54, 'y' => 55.02],
                    ['x' => 66.13, 'y' => 55.74],
                    ['x' => 67.49, 'y' => 56.57],
                    ['x' => 68.67, 'y' => 57.6],
                    ['x' => 69.63, 'y' => 58.73],
                    ['x' => 70.38, 'y' => 59.97],
                    ['x' => 70.95, 'y' => 61.31],
                    ['x' => 71.41, 'y' => 62.75],
                    ['x' => 71.7, 'y' => 64.3],
                    ['x' => 71.84, 'y' => 65.55],
                    ['x' => 71.9, 'y' => 66.62],
                    ['x' => 71.82, 'y' => 67.69],
                    ['x' => 71.66, 'y' => 68.94],
                    ['x' => 71.35, 'y' => 70.59],
                    ['x' => 70.87, 'y' => 72.34],
                    ['x' => 70.21, 'y' => 74.1],
                    ['x' => 69.35, 'y' => 75.95],
                    ['x' => 68.31, 'y' => 77.81],
                    ['x' => 67.12, 'y' => 79.67],
                    ['x' => 65.72, 'y' => 81.52],
                    ['x' => 64.07, 'y' => 83.38],
                    ['x' => 62.27, 'y' => 85.23],
                    ['x' => 60.21, 'y' => 87.09],
                    ['x' => 57.94, 'y' => 88.95],
                    ['x' => 55.57, 'y' => 90.7],
                    ['x' => 53.47, 'y' => 92.25],
                    ['x' => 51.55, 'y' => 93.55],
                    ['x' => 50.0, 'y' => 94.52],
                    ['x' => 48.45, 'y' => 93.55],
                    ['x' => 46.53, 'y' => 92.25],
                    ['x' => 44.43, 'y' => 90.7],
                    ['x' => 42.06, 'y' => 88.95],
                    ['x' => 39.79, 'y' => 87.09],
                    ['x' => 37.73, 'y' => 85.23],
                    ['x' => 35.93, 'y' => 83.38],
                    ['x' => 34.28, 'y' => 81.52],
                    ['x' => 32.88, 'y' => 79.67],
                    ['x' => 31.68, 'y' => 77.81],
                    ['x' => 30.65, 'y' => 75.95],
                    ['x' => 29.79, 'y' => 74.1],
                    ['x' => 29.13, 'y' => 72.34],
                    ['x' => 28.65, 'y' => 70.59],
                    ['x' => 28.34, 'y' => 68.94],
                    ['x' => 28.18, 'y' => 67.69],
                    ['x' => 28.1, 'y' => 66.62],
                    ['x' => 28.16, 'y' => 65.55],
                    ['x' => 28.3, 'y' => 64.3],
                    ['x' => 28.59, 'y' => 62.75],
                    ['x' => 29.04, 'y' => 61.31],
                    ['x' => 29.62, 'y' => 59.97],
                    ['x' => 30.37, 'y' => 58.73],
                    ['x' => 31.33, 'y' => 57.6],
                    ['x' => 32.51, 'y' => 56.57],
                    ['x' => 33.87, 'y' => 55.74],
                    ['x' => 35.46, 'y' => 55.02],
                    ['x' => 37.32, 'y' => 54.48],
                    ['x' => 39.58, 'y' => 54.19],
                    ['x' => 41.17, 'y' => 54.52],
                    ['x' => 42.74, 'y' => 55.12],
                    ['x' => 44.18, 'y' => 55.84],
                    ['x' => 45.46, 'y' => 56.57],
                    ['x' => 46.66, 'y' => 57.29],
                    ['x' => 47.77, 'y' => 58.01],
                    ['x' => 48.87, 'y' => 58.73],
                ]
            ],
            'attributes' => [
                'Material' => 'Schwarz eloxiertes Aluminium (Massiv)',
                'Form' => 'Handschmeichler-Herz (Beidseitig gravierbar)',
                'Größe' => '45 x 40 x 2,3 mm',
                'Gewicht' => 'ca. 19 g',
                'Veredelung' => 'Abriebfeste Silber-Weiß Faserlaser-Gravur',
                'Herkunft' => 'Handveredelt in Deutschland',
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

        // Verpackungsmaterial (LUCID / /admin/product-packaging): Luftpolster-Versandtüte (15 g gesamt)
        $p2->packagings()->delete();
        $p2->packagings()->createMany([
            ['material_type' => 'composite', 'weight_grams' => 12],
            ['material_type' => 'paper',     'weight_grams' => 3],
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
            'price' => 2990,
            'compare_at_price' => null,
            'purchase_price' => 333, // EK: 19,99€ / 6 Stück = 3,33€ pro Stück
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 15,
            'packaging_cost' => 50, // Karton.eu 140stk 300x230x150 mm (Art.-Nr. 2633) = 0,50 € netto
            'marketing_cost_percent' => 12.50,
            'sku' => 'MSF-GLAS-WEIZEN-001',
            'product_supplier_id' => $sup4?->id,
            'reorder_url' => 'https://www.metro.de/marktplatz/product/70a1602b-8551-48a6-aff4-d763d68f1f33',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 200,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 450,
            'packaging_weight' => 180, // Karton.eu Art.-Nr. 2633 (300x230x150 mm) = 0,18 kg (180 g) lt. Rechnung 26169398
            'width' => 74,      // 7,4 cm
            'height' => 218,    // 21,8 cm
            'length' => 74,     // 7,4 cm
            'shipping_class' => null, // DHL Standard (Gewichtsbasiert)
            'preview_image_path' => 'produkte/products/weizenglas-personalisiert/overlay.png',
            'three_d_model_path' => 'produkte/products/weizenglas-personalisiert/beer_glas_3d.glb',
            'three_d_background_path' => 'produkte/products/weizenglas-personalisiert/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/weizenglas-personalisiert/beer_glas_main.jpg', 'is_main' => true, 'is_hover' => false, 'is_beginner' => true, 'is_ai_generated' => true, 'alt' => 'Der Feierabend-Funke: Personalisiertes Weizenbierglas'],
                ['type' => 'image', 'path' => 'produkte/products/weizenglas-personalisiert/weizenglas-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Der Feierabend-Funke: Frisch gezapftes Weizenglas mit Lasergravur im Biergarten'],
                ['type' => 'image', 'path' => 'produkte/products/weizenglas-personalisiert/weizenglas-dimensions.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Der Feierabend-Funke: Technisches Maßblatt & Abmessungen (218 x Ø 74 mm)'],
            ],
            'configurator_settings' => [
                'template_package' => 'weizenglas-personalisiert',
                'origin_badge' => 'made_in_germany',
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
                'Pflege' => 'Spülmaschinengeeignet & abriebfest',
                'Herkunft' => 'Handveredelt in Deutschland',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Der Feierabend-Funke | Weizenglas mit eigener 360° Gravur | Mein-Seelenfunke',
            'seo_description' => 'Gestalten Sie Ihr eigenes Weizenglas mit spülmaschinengeeigneter 360° Rundum-Gravur online im 3D-Konfigurator. Perfekt als Geschenk.',
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

        // Verpackungsmaterial (LUCID / /admin/product-packaging): Versandkarton + Polsterschutz (120 g gesamt)
        $p3->packagings()->delete();
        $p3->packagings()->createMany([
            ['material_type' => 'paper',   'weight_grams' => 100],
            ['material_type' => 'plastic', 'weight_grams' => 20],
        ]);

        // --- PRODUKT 4: Der Seelen-Talisman (Physisch / NEU – Massiv Gold Edelstahl-Schlüsselanhänger) ---
        $p4 = Product::updateOrCreate(['slug' => 'seelen-talisman'], [
            'name' => 'Der Seelen-Talisman',
            'type' => 'physical',
            'description' => 'Ein edles Statement für jeden Tag: Der Seelen-Talisman ist aus massivem, spiegelpoliertem Edelstahl mit hochwertigem Gold-Finish gefertigt und besticht durch seine sanft abgerundete Tonnen-Kontur mit massivem Flachprofil-Schlüsselring. Durch unsere hochpräzise Faserlaser-Anlassgravur treten Ihre Wunschbotschaft, Koordinaten, Initialen oder Symbole in gestochen scharfem, abriebfestem Tief-Anthrazit auf dem glänzenden Gold hervor, auf Wunsch auch beidseitig veredelt! Ob als treuer Schutzbegleiter für den Lieblingsmenschen, als stilvoller Schlüsselanhänger zum neuen Zuhause oder als Jahrestags-Erinnerung: Der Seelen-Talisman liegt spürbar wertig in der Hand und begleitet Sie zuverlässig durch den Alltag.',
            'short_description' => 'Massiver, spiegelpolierter Edelstahl-Schlüsselanhänger in edlem Gold-Finish mit kontraststarker, beidseitiger Faserlaser-Gravur.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 3690,
            'compare_at_price' => null,
            'purchase_price' => 116, // EK laut Temu-Beleg 15.10.2026: 18,60€ / 16 Stück = 1,16€ netto (1,48€ inkl. Einfuhrabgabe)
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 5,
            'packaging_cost' => 14, // Karton.eu 400stk Luftpolster Versandtüten = 0,14€ netto
            'marketing_cost_percent' => 10.00,
            'sku' => 'MSF-EDELSTAHL-TALISMAN-001',
            'product_supplier_id' => $sup5?->id,
            'reorder_url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=606203291790930&parent_order_sn=PO-076-11149563187830774&_oak_order_sn=076-11149531730550774&_oak_goods_num=2&sku_id=89857024078818',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 16,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 24,
            'packaging_weight' => 15, // Luftpolster-Versandtüte (15 g)
            'height' => 50,
            'width' => 28,
            'length' => 2.2,
            'shipping_class' => 'brief', // Brief / Großbrief (Luftpolster-Umschlag)
            'preview_image_path' => 'produkte/products/seelen-talisman/overlay.png',
            'three_d_model_path' => 'produkte/products/seelen-talisman/seelen_talisman_3d.glb',
            'three_d_background_path' => 'produkte/products/seelen-talisman/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-main.jpg', 'is_main' => true, 'is_hover' => false, 'is_beginner' => true, 'is_ai_generated' => true, 'alt' => 'Der Seelen-Talisman: Spiegelpolierter Gold-Edelstahl-Schlüsselanhänger mit Gravur'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-back.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Der Seelen-Talisman: Beidseitige Gravur mit Wunschkoordinaten'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Der Seelen-Talisman: Gold-Schlüsselanhänger am Haustür-Schlüsselbund beim Aufschließen'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-dimensions.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Der Seelen-Talisman: Technisches Maßblatt & Abmessungen (50 x 28 x 2,2 mm)'],
            ],
            'configurator_settings' => [
                'template_package' => 'seelen-talisman',
                'origin_badge' => 'handcrafted_in_germany',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'area_shape' => 'custom',
                'area_left' => 34.25,
                'area_top' => 41.43,
                'area_width' => 31.0,
                'area_height' => 52.81,
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
                    ['x' => 34.25, 'y' => 47.48],
                    ['x' => 35.22, 'y' => 46.53],
                    ['x' => 36.19, 'y' => 45.7],
                    ['x' => 37.16, 'y' => 44.95],
                    ['x' => 38.13, 'y' => 44.29],
                    ['x' => 39.09, 'y' => 43.71],
                    ['x' => 40.06, 'y' => 43.19],
                    ['x' => 41.03, 'y' => 42.73],
                    ['x' => 42, 'y' => 42.33],
                    ['x' => 42.97, 'y' => 41.98],
                    ['x' => 43.94, 'y' => 41.68],
                    ['x' => 44.91, 'y' => 41.43],
                    ['x' => 45.88, 'y' => 41.67],
                    ['x' => 46.84, 'y' => 42.99],
                    ['x' => 47.81, 'y' => 44.82],
                    ['x' => 48.78, 'y' => 46.34],
                    ['x' => 49.75, 'y' => 46.84],
                    ['x' => 50.72, 'y' => 46.09],
                    ['x' => 51.69, 'y' => 44.43],
                    ['x' => 52.66, 'y' => 42.62],
                    ['x' => 53.63, 'y' => 41.48],
                    ['x' => 54.59, 'y' => 41.43],
                    ['x' => 55.56, 'y' => 41.68],
                    ['x' => 56.53, 'y' => 41.98],
                    ['x' => 57.5, 'y' => 42.33],
                    ['x' => 58.47, 'y' => 42.73],
                    ['x' => 59.44, 'y' => 43.19],
                    ['x' => 60.41, 'y' => 43.71],
                    ['x' => 61.38, 'y' => 44.29],
                    ['x' => 62.34, 'y' => 44.95],
                    ['x' => 63.31, 'y' => 45.7],
                    ['x' => 64.28, 'y' => 46.53],
                    ['x' => 65.25, 'y' => 47.48],
                    ['x' => 65.25, 'y' => 49],
                    ['x' => 65.25, 'y' => 58],
                    ['x' => 65.25, 'y' => 68],
                    ['x' => 65.25, 'y' => 78],
                    ['x' => 65.25, 'y' => 86.2],
                    ['x' => 65.25, 'y' => 87.64],
                    ['x' => 64.12, 'y' => 88.73],
                    ['x' => 62.91, 'y' => 89.74],
                    ['x' => 61.63, 'y' => 90.66],
                    ['x' => 60.29, 'y' => 91.48],
                    ['x' => 58.89, 'y' => 92.2],
                    ['x' => 57.44, 'y' => 92.82],
                    ['x' => 55.96, 'y' => 93.33],
                    ['x' => 54.43, 'y' => 93.72],
                    ['x' => 52.89, 'y' => 94.01],
                    ['x' => 51.32, 'y' => 94.18],
                    ['x' => 49.75, 'y' => 94.24],
                    ['x' => 48.18, 'y' => 94.18],
                    ['x' => 46.61, 'y' => 94.01],
                    ['x' => 45.07, 'y' => 93.72],
                    ['x' => 43.54, 'y' => 93.33],
                    ['x' => 42.06, 'y' => 92.82],
                    ['x' => 40.61, 'y' => 92.2],
                    ['x' => 39.21, 'y' => 91.48],
                    ['x' => 37.87, 'y' => 90.66],
                    ['x' => 36.59, 'y' => 89.74],
                    ['x' => 35.38, 'y' => 88.73],
                    ['x' => 34.25, 'y' => 87.64],
                    ['x' => 34.25, 'y' => 86.2],
                    ['x' => 34.25, 'y' => 78],
                    ['x' => 34.25, 'y' => 68],
                    ['x' => 34.25, 'y' => 58],
                    ['x' => 34.25, 'y' => 49],
                ]
            ],
            'attributes' => [
                'Material' => 'Hochglanzpolierter Edelstahl (Massiv Gold-Finish)',
                'Form' => 'Gewölbte Dog-Tag Plakette mit Flachring (Beidseitig gravierbar)',
                'Größe' => '50 x 28 x 2,2 mm (5,0 x 2,8 cm Plakette, Ø 30 mm Schlüsselring)',
                'Gewicht' => 'ca. 24 g (massiv)',
                'Veredelung' => 'Tiefschwarze Faserlaser-Anlassgravur auf Gold',
                'Herkunft' => 'Handveredelt in Deutschland',
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

        // Verpackungsmaterial (LUCID / /admin/product-packaging): Luftpolster-Versandtüte (15 g gesamt)
        $p4->packagings()->delete();
        $p4->packagings()->createMany([
            ['material_type' => 'composite', 'weight_grams' => 12],
            ['material_type' => 'paper',     'weight_grams' => 3],
        ]);

        // --- PRODUKT 5: Die Seelen-Pfote (Physisch / Goldfarbene Edelstahl-Hundemarke Knochenform mit Pfoten-Durchbruch) ---
        $p5Paw = Product::updateOrCreate(['slug' => 'seelen-pfote-hundemarke'], [
            'name' => 'Die Seelen-Pfote',
            'type' => 'physical',
            'description' => 'Sicherheit und Liebe für Ihren treuesten Begleiter: Die Seelen-Pfote ist eine massive, spiegelpolierte Hundemarke in klassischer Knochenform aus rostfreiem Edelstahl in edlem Gold-Finish mit integriertem Aufhängungs-Öhr und einem kunstvoll ausgestanzten Pfotenabdruck-Durchbruch unten rechts. Auf der Vorderseite verewigen wir den Namen Ihres Lieblings samt Wunsch-Symbol in kontraststarker, dunkler Faserlaser-Anlassgravur, während die Rückseite ausreichend Platz für Ihre Notfall-Telefonnummer, Adresse oder TASSO-Kennung bietet. Absolut wasserfest, rostfrei und robust für unbeschwerte Abenteuer bei jedem Wetter.',
            'short_description' => 'Massive, spiegelpolierte Edelstahl-Hundemarke in edlem Gold-Finish in Knochenform mit echtem Pfoten-Durchbruch und beidseitiger Lasergravur.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 2990,
            'compare_at_price' => null,
            'purchase_price' => 98, // EK Komplett-Set: Temu Rohling PO-076-04964841144950774 (0,80€ netto) + Amazon Order 028-1563466-9621923: 1x Flacher Schlüsselring Gold B093R9HS4Y (0,14€ netto) + 1x Edelstahl-Spaltring Gold B0H6FG92JY (0,04€ netto) = 0,98€ netto pro Stück
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 5,
            'packaging_cost' => 14, // Karton.eu 400stk Luftpolster Versandtüten = 0,14€ netto
            'marketing_cost_percent' => 10.00,
            'sku' => 'MSF-EDELSTAHL-PFOTE-001',
            'product_supplier_id' => $sup6?->id ?? $sup5?->id,
            'reorder_url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=601099544143627&parent_order_sn=PO-076-04964841144950774&_oak_order_sn=076-04964799201910774&_oak_goods_num=3&_x_sessn_id=qexm6jf56e&refer_page_name=bgt_order_receipt&refer_page_id=10471_1793672371535_hlekpk8i9o&refer_page_sn=10471&is_back=1',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 30,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 12,
            'packaging_weight' => 15, // Luftpolster-Versandtüte (15 g)
            'height' => 22,
            'width' => 40,
            'length' => 1.8,
            'shipping_class' => 'brief', // Brief / Großbrief (Luftpolster-Umschlag)
            'preview_image_path' => 'produkte/products/seelen-pfote-hundemarke/overlay.png',
            'three_d_model_path' => 'produkte/products/seelen-pfote-hundemarke/seelen_pfote_3d.glb',
            'three_d_background_path' => 'produkte/products/seelen-pfote-hundemarke/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-main.jpg', 'is_main' => true, 'is_hover' => false, 'is_beginner' => true, 'is_ai_generated' => true, 'alt' => 'Die Seelen-Pfote: Goldfarbene Edelstahl-Hundemarke Knochenform mit Pfoten-Durchbruch & Gravur'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-back.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Die Seelen-Pfote: Goldene Rückseite mit Notfall-Telefonnummer & TASSO-Hinweis'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Die Seelen-Pfote Gold-Hundemarke am Lederhalsband eines Golden Retrievers'],
                ['type' => 'image', 'path' => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-dimensions.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Die Seelen-Pfote: Technisches Maßblatt & Abmessungen (40 x 22 x 1,8 mm)'],
            ],
            'configurator_settings' => [
                'template_package' => 'seelen-pfote-hundemarke',
                'origin_badge' => 'handcrafted_in_germany',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'area_shape' => 'custom',
                'area_left' => 11.38,
                'area_top' => 31.13,
                'area_width' => 77.25,
                'area_height' => 37.75,
                'xtool_plane_width' => 40.0,
                'xtool_plane_height' => 22.0,
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'metal',
                'model_scale' => 121,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => 0, 'model_rot_y' => 0, 'model_rot_z' => 0,
                // VORDERSEITE (1:1 deckungsgleich zum 800x800 2D-Overlay: W_canvas / maxPlaneSize = 800 / (640 * 1.5) = 83.33%)
                'engraving_scale' => 83.3,
                'engraving_pos_x' => 0, 'engraving_pos_y' => 0, 'engraving_pos_z' => 0,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 180, 'engraving_rot_z' => 0,
                // RÜCKSEITE (-Z Fläche für Telefonnummer & Adresse)
                'back_engraving_scale' => 83.3,
                'back_engraving_pos_x' => 0, 'back_engraving_pos_y' => 0, 'back_engraving_pos_z' => -0.66,
                'back_engraving_rot_x' => 0, 'back_engraving_rot_y' => 0, 'back_engraving_rot_z' => 0,
                'custom_points' => [
                    ['x' => 22.75, 'y' => 31.13],
                    ['x' => 24.78, 'y' => 31.43],
                    ['x' => 26.63, 'y' => 32.22],
                    ['x' => 28.37, 'y' => 33.29],
                    ['x' => 30.06, 'y' => 34.46],
                    ['x' => 31.77, 'y' => 35.53],
                    ['x' => 33.56, 'y' => 36.32],
                    ['x' => 35.5, 'y' => 36.63],
                    ['x' => 38.03, 'y' => 36.76],
                    ['x' => 40.24, 'y' => 37.12],
                    ['x' => 42.23, 'y' => 37.61],
                    ['x' => 44.1, 'y' => 38.14],
                    ['x' => 45.94, 'y' => 38.63],
                    ['x' => 47.88, 'y' => 38.99],
                    ['x' => 50, 'y' => 39.13],
                    ['x' => 52.12, 'y' => 38.99],
                    ['x' => 54.06, 'y' => 38.63],
                    ['x' => 55.9, 'y' => 38.14],
                    ['x' => 57.77, 'y' => 37.61],
                    ['x' => 59.76, 'y' => 37.12],
                    ['x' => 61.97, 'y' => 36.76],
                    ['x' => 64.5, 'y' => 36.63],
                    ['x' => 66.44, 'y' => 36.32],
                    ['x' => 68.23, 'y' => 35.53],
                    ['x' => 69.94, 'y' => 34.46],
                    ['x' => 71.63, 'y' => 33.29],
                    ['x' => 73.37, 'y' => 32.22],
                    ['x' => 75.22, 'y' => 31.43],
                    ['x' => 77.25, 'y' => 31.13],
                    ['x' => 79.6, 'y' => 31.3],
                    ['x' => 81.77, 'y' => 31.82],
                    ['x' => 83.71, 'y' => 32.65],
                    ['x' => 85.38, 'y' => 33.78],
                    ['x' => 86.74, 'y' => 35.18],
                    ['x' => 87.76, 'y' => 36.82],
                    ['x' => 88.4, 'y' => 38.69],
                    ['x' => 88.63, 'y' => 40.75],
                    ['x' => 88.5, 'y' => 42.78],
                    ['x' => 88.2, 'y' => 44.54],
                    ['x' => 87.79, 'y' => 46.06],
                    ['x' => 87.36, 'y' => 47.37],
                    ['x' => 87, 'y' => 48.5],
                    ['x' => 84.2, 'y' => 48.42],
                    ['x' => 81.36, 'y' => 48.48],
                    ['x' => 78.56, 'y' => 48.72],
                    ['x' => 75.89, 'y' => 49.19],
                    ['x' => 73.42, 'y' => 49.93],
                    ['x' => 71.25, 'y' => 51],
                    ['x' => 69.74, 'y' => 52.45],
                    ['x' => 68.65, 'y' => 54.24],
                    ['x' => 67.91, 'y' => 56.3],
                    ['x' => 67.44, 'y' => 58.56],
                    ['x' => 67.16, 'y' => 60.94],
                    ['x' => 67, 'y' => 63.38],
                    ['x' => 61.85, 'y' => 63.38],
                    ['x' => 56.58, 'y' => 63.38],
                    ['x' => 51.25, 'y' => 63.38],
                    ['x' => 45.92, 'y' => 63.38],
                    ['x' => 40.65, 'y' => 63.38],
                    ['x' => 35.5, 'y' => 63.38],
                    ['x' => 33.56, 'y' => 63.68],
                    ['x' => 31.77, 'y' => 64.47],
                    ['x' => 30.06, 'y' => 65.54],
                    ['x' => 28.37, 'y' => 66.71],
                    ['x' => 26.63, 'y' => 67.78],
                    ['x' => 24.78, 'y' => 68.57],
                    ['x' => 22.75, 'y' => 68.88],
                    ['x' => 20.4, 'y' => 68.7],
                    ['x' => 18.23, 'y' => 68.18],
                    ['x' => 16.29, 'y' => 67.35],
                    ['x' => 14.63, 'y' => 66.22],
                    ['x' => 13.26, 'y' => 64.82],
                    ['x' => 12.24, 'y' => 63.18],
                    ['x' => 11.6, 'y' => 61.31],
                    ['x' => 11.38, 'y' => 59.25],
                    ['x' => 11.58, 'y' => 56.66],
                    ['x' => 12.06, 'y' => 54.52],
                    ['x' => 12.63, 'y' => 52.75],
                    ['x' => 13.11, 'y' => 51.27],
                    ['x' => 13.31, 'y' => 50],
                    ['x' => 13.11, 'y' => 48.73],
                    ['x' => 12.63, 'y' => 47.25],
                    ['x' => 12.06, 'y' => 45.48],
                    ['x' => 11.58, 'y' => 43.34],
                    ['x' => 11.38, 'y' => 40.75],
                    ['x' => 11.66, 'y' => 38.41],
                    ['x' => 12.49, 'y' => 36.33],
                    ['x' => 13.81, 'y' => 34.55],
                    ['x' => 15.54, 'y' => 33.1],
                    ['x' => 17.65, 'y' => 32.03],
                    ['x' => 20.07, 'y' => 31.36],
                ]
            ],
            'attributes' => [
                'Material' => 'Hochglanzpolierter Edelstahl in edlem Gold-Finish (Rostfrei & Wasserfest)',
                'Form' => 'Hundeknochen mit echtem Pfoten-Durchbruch unten rechts (Beidseitig gravierbar)',
                'Größe' => '40 x 22 x 1,8 mm (1,57 x 0,87 in, inkl. integrierter Öse)',
                'Gewicht' => 'ca. 12 g / 0,43 oz (robust & alltagstauglich)',
                'Veredelung' => 'Kontraststarke Faserlaser-Anlassgravur auf Gold-Edelstahl',
                'Herkunft' => 'Handveredelt in Deutschland (Manufaktur Gifhorn)',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Die Seelen-Pfote | Goldene Edelstahl-Hundemarke mit Pfoten-Durchbruch & Gravur',
            'seo_description' => 'Polierte Edelstahl-Hundemarke in edlem Gold-Finish in Knochenform mit ausgestanzter Pfote und beidseitiger Lasergravur. Jetzt im 3D-Konfigurator gestalten!',
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

        // Verpackungsmaterial (LUCID / /admin/product-packaging): Luftpolster-Versandtüte (15 g gesamt)
        $p5Paw->packagings()->delete();
        $p5Paw->packagings()->createMany([
            ['material_type' => 'composite', 'weight_grams' => 12],
            ['material_type' => 'paper',     'weight_grams' => 3],
        ]);

        // --- PRODUKT 6: Das Goldene Seelen-Herz (Physisch / NEU – 14 Karat vergoldeter Edelstahl-Herzanhänger inkl. 44–50 cm Gold-Ankerkette von Glücksfieber DE) ---
        $p6GoldHeart = Product::updateOrCreate(['slug' => 'goldenes-seelen-herz'], [
            'name' => 'Das Goldene Seelen-Herz',
            'type' => 'physical',
            'description' => 'Ein Schmuckstück für die Ewigkeit, handveredelt in unserer Manufaktur in Deutschland und sofort tragfertig als komplettes Collier: Das Goldene Seelen-Herz vereint einen außergewöhnlich massiven (4,0 mm starken!) Edelstahl-Herzanhänger mit der perfekt abgestimmten, feingliedrigen Gold-Edelstahl-Ankerkette (44 cm + 6 cm Verlängerungskettchen für flexibles Tragen von 44 bis 50 cm). Beide Komponenten stammen aus dem Lübecker Fachgroßhandel Glücksfieber und bestehen aus vergoldetem Edelstahl (Ion-Plating): identischer, warmer Goldton, wasserfest, hautfreundlich und anlaufgeschützt. Anders als dünne Blechanhänger besticht das Herz durch sein spürbares Gewicht, sanft facettierte Kanten und eine kratzfeste Spiegeloberfläche. Mit unserem Präzisions-Faserlaser verewigen wir Ihre Initialen, Namen, ein besonderes Datum oder feine Symbole in tiefem, kontraststarkem Anthrazit-Schwarz direkt im Gold-Finish, auf Wunsch kostenlos auch auf Vorder- und Rückseite.',
            'short_description' => 'Massiver vergoldeter Edelstahl-Herzanhänger (4 mm Stärke) inklusive passender, verstellbarer Gold-Edelstahl-Ankerkette (44–50 cm) & beidseitiger Faserlaser-Gravur.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 4990,
            'compare_at_price' => null,
            'purchase_price' => 441, // EK bei Glücksfieber (Lübeck, DE) im Staffelbezug: Herz STA1274 (2,02€ netto) + Ankerkette STA268 (2,39€ netto) = 4,41€ netto Komplett-Set
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 5,
            'packaging_cost' => 14, // Karton.eu 400stk Luftpolster Versandtüten = 0,14€ netto
            'marketing_cost_percent' => 10.00,
            'sku' => 'MSF-GOLD-HERZ-001',
            'product_supplier_id' => $sup8?->id,
            'reorder_url' => 'https://www.gluecksfieber.de/shop/laserrohlinge/edelstahl-laserrohling-anhaenger-herz-mit-collierschlaufe-14-karat-vergoldet-25-x-22-x-4-mm-oese-3-x-6-mm-ion-plating-ip.html',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => 10,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 18,
            'packaging_weight' => 15, // Luftpolster-Versandtüte (15 g)
            'height' => 25,
            'width' => 22,
            'length' => 4.0,
            'shipping_class' => 'brief', // Brief / Großbrief (Luftpolster-Umschlag)
            'preview_image_path' => 'produkte/products/goldenes-seelen-herz/overlay.png',
            'three_d_model_path' => 'produkte/products/goldenes-seelen-herz/goldenes_seelen_herz_3d.glb',
            'three_d_background_path' => 'produkte/products/goldenes-seelen-herz/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-main.jpg', 'is_main' => true, 'is_hover' => false, 'is_beginner' => true, 'is_ai_generated' => true, 'alt' => 'Das Goldene Seelen-Herz: Vergoldeter Edelstahl-Herzanhänger inklusive Gold-Ankerkette (44–50 cm) & Gravur'],
                ['type' => 'image', 'path' => 'produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-back.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Das Goldene Seelen-Herz: Rückseite mit Wunschdatum & Koordinaten-Gravur inklusive Halskette'],
                ['type' => 'image', 'path' => 'produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Das Goldene Seelen-Herz Komplett-Set an der mitgelieferten 44–50 cm Gold-Ankerkette getragen'],
                ['type' => 'image', 'path' => 'produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-dimensions.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true, 'alt' => 'Das Goldene Seelen-Herz: Technisches Maßblatt & Abmessungen (25 x 22 x 4 mm inkl. 44–50 cm Ankerkette)'],
            ],
            'configurator_settings' => [
                'template_package' => 'goldenes-seelen-herz',
                'origin_badge' => 'handcrafted_in_germany',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'area_shape' => 'custom',
                'area_left' => 19.45,
                'area_top' => 36.96,
                'area_width' => 61.1,
                'area_height' => 55.01,
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'metal',
                'model_scale' => 133,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => 0, 'model_rot_y' => 0, 'model_rot_z' => 0,
                // VORDERSEITE (1:1 deckungsgleich zum 800x800 2D-Overlay: W_canvas / maxPlaneSize = 800 / 1062 = 75.3%)
                'engraving_scale' => 75,
                'engraving_pos_x' => 0, 'engraving_pos_y' => 0, 'engraving_pos_z' => 0,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 180, 'engraving_rot_z' => 0,
                // RÜCKSEITE (-Z Fläche der 4,0 mm starken 14k Gold-Platte bei zHalf = 0.088)
                'back_engraving_scale' => 75,
                'back_engraving_pos_x' => 0, 'back_engraving_pos_y' => 0, 'back_engraving_pos_z' => -1.78,
                'back_engraving_rot_x' => 0, 'back_engraving_rot_y' => 0, 'back_engraving_rot_z' => 0,
                'custom_points' => [
                    ['x' => 50, 'y' => 40.63],
                    ['x' => 52.36, 'y' => 39.47],
                    ['x' => 54.1, 'y' => 38.66],
                    ['x' => 55.93, 'y' => 37.9],
                    ['x' => 57.83, 'y' => 37.35],
                    ['x' => 59.86, 'y' => 37.07],
                    ['x' => 62.07, 'y' => 36.96],
                    ['x' => 64.38, 'y' => 37.05],
                    ['x' => 66.77, 'y' => 37.41],
                    ['x' => 69.21, 'y' => 37.99],
                    ['x' => 71.55, 'y' => 38.91],
                    ['x' => 73.74, 'y' => 40.17],
                    ['x' => 75.71, 'y' => 41.76],
                    ['x' => 77.37, 'y' => 43.64],
                    ['x' => 78.67, 'y' => 45.79],
                    ['x' => 79.63, 'y' => 48.15],
                    ['x' => 80.23, 'y' => 50.68],
                    ['x' => 80.55, 'y' => 53.27],
                    ['x' => 80.53, 'y' => 55.88],
                    ['x' => 80.21, 'y' => 58.48],
                    ['x' => 79.62, 'y' => 61.02],
                    ['x' => 78.75, 'y' => 63.46],
                    ['x' => 77.65, 'y' => 65.83],
                    ['x' => 76.4, 'y' => 68.11],
                    ['x' => 75, 'y' => 70.3],
                    ['x' => 73.54, 'y' => 72.44],
                    ['x' => 71.97, 'y' => 74.48],
                    ['x' => 70.28, 'y' => 76.47],
                    ['x' => 68.54, 'y' => 78.45],
                    ['x' => 66.68, 'y' => 80.34],
                    ['x' => 64.67, 'y' => 82.23],
                    ['x' => 62.57, 'y' => 84.14],
                    ['x' => 60.31, 'y' => 85.97],
                    ['x' => 57.84, 'y' => 87.82],
                    ['x' => 55.21, 'y' => 89.66],
                    ['x' => 52.46, 'y' => 91.29],
                    ['x' => 50, 'y' => 91.97],
                    ['x' => 47.54, 'y' => 91.29],
                    ['x' => 44.79, 'y' => 89.66],
                    ['x' => 42.16, 'y' => 87.82],
                    ['x' => 39.69, 'y' => 85.97],
                    ['x' => 37.43, 'y' => 84.14],
                    ['x' => 35.33, 'y' => 82.23],
                    ['x' => 33.32, 'y' => 80.34],
                    ['x' => 31.46, 'y' => 78.45],
                    ['x' => 29.72, 'y' => 76.47],
                    ['x' => 28.03, 'y' => 74.48],
                    ['x' => 26.46, 'y' => 72.44],
                    ['x' => 25, 'y' => 70.3],
                    ['x' => 23.6, 'y' => 68.11],
                    ['x' => 22.35, 'y' => 65.83],
                    ['x' => 21.25, 'y' => 63.46],
                    ['x' => 20.38, 'y' => 61.02],
                    ['x' => 19.79, 'y' => 58.48],
                    ['x' => 19.47, 'y' => 55.88],
                    ['x' => 19.45, 'y' => 53.27],
                    ['x' => 19.77, 'y' => 50.68],
                    ['x' => 20.37, 'y' => 48.15],
                    ['x' => 21.33, 'y' => 45.79],
                    ['x' => 22.63, 'y' => 43.64],
                    ['x' => 24.29, 'y' => 41.76],
                    ['x' => 26.26, 'y' => 40.17],
                    ['x' => 28.45, 'y' => 38.91],
                    ['x' => 30.79, 'y' => 37.99],
                    ['x' => 33.23, 'y' => 37.41],
                    ['x' => 35.62, 'y' => 37.05],
                    ['x' => 37.93, 'y' => 36.96],
                    ['x' => 40.14, 'y' => 37.07],
                    ['x' => 42.17, 'y' => 37.35],
                    ['x' => 44.07, 'y' => 37.9],
                    ['x' => 45.9, 'y' => 38.66],
                    ['x' => 47.64, 'y' => 39.47],
                ]
            ],
            'attributes' => [
                'Material' => 'Massiver Edelstahl (14 Karat vergoldet, Ion-Plating)',
                'Halskette (Inklusive)' => 'Gold-Edelstahl-Ankerkette (44 cm + 6 cm Verlängerung, 1,5 mm, Karabiner)',
                'Herz-Größe' => '25 x 22 x 4,0 mm (Collierschlaufe / Öse: 3 x 6 mm)',
                'Gewicht' => 'ca. 18 g Komplett-Set (14 g massives Herz + 4 g Ankerkette)',
                'Veredelung' => 'Beidseitige Faserlaser-Anlassgravur (Vorder- & Rückseite inklusive)',
                'Herkunft' => 'Handveredelt in Deutschland',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Das Goldene Seelen-Herz | Vergoldete Herz-Halskette (44–50 cm) mit Gravur | Handveredelt in Deutschland',
            'seo_description' => 'Massiver vergoldeter Edelstahl-Herzanhänger (25x22x4 mm) inklusive passender 44–50 cm Gold-Ankerkette und beidseitiger Lasergravur. Jetzt im 3D-Konfigurator gestalten!',
            'completion_step' => 4
        ]);

        $catIdsGoldHeart = ProductCategory::whereIn('name', ['Schmuck & Anhänger', 'Metall & Alu', 'Geschenksets', 'Bestseller'])->pluck('id');
        $p6GoldHeart->categories()->sync($catIdsGoldHeart);
        $p6GoldHeart->tierPrices()->delete();
        $p6GoldHeart->tierPrices()->createMany([
            ['qty' => 2, 'percent' => 5],
            ['qty' => 3, 'percent' => 10],
            ['qty' => 5, 'percent' => 15]
        ]);

        // Verpackungsmaterial (LUCID / /admin/product-packaging): Luftpolster-Versandtüte (15 g gesamt)
        $p6GoldHeart->packagings()->delete();
        $p6GoldHeart->packagings()->createMany([
            ['material_type' => 'composite', 'weight_grams' => 12],
            ['material_type' => 'paper',     'weight_grams' => 3],
        ]);

        // --- PRODUKT 7: Das Echtgold Seelen-Herz (Atelier Echtgold & Silber - 585er Echtgold-Herz + 333er Echtgold-Panzerkette im Set) ---
        // AKTUELL AUSGEBLENDET (Fokus zum Start auf Echtsilber & Echtgold ID-Gravurarmbänder):
        Product::withTrashed()->where('slug', 'echtgold-seelen-herz')->get()->each(function (Product $p) {
            $p->templates()->delete();
            $p->reviews()->delete();
            $p->packagings()->delete();
            $p->tierPrices()->delete();
            $p->categories()->detach();
            $p->forceDelete();
        });
/*        $p7EchtgoldHerz = Product::updateOrCreate(['slug' => 'echtgold-seelen-herz'], [
            'name' => 'Das Echtgold Seelen-Herz (585er Gold & 333er Goldkette)',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'description' => 'Ein bleibendes Familienerbstück aus echtem Edelmetall, auf Bestellung für Sie handveredelt in unserer Manufaktur in Gifhorn: Das Echtgold Seelen-Herz vereint einen hochglanzpolierten Herzanhänger aus echtem 585er Gelbgold (14 Karat Echtgold, gestempelt 585, 13 x 14 mm, ca. 0,80 g) mit einer feinen, diamantierten Panzerkette aus echtem 333er Gelbgold (8 Karat Echtgold, gestempelt 333, 0,8 mm Stärke, ca. 1,0 g, mit Federringverschluss). Beide Echtschmuck-Komponenten stammen aus dem deutschen Fachhandel (Silberketten-Store) und werden mit unserem 5W UV-Präzisionslaser berührungslos und materialschonend mit Ihren Wunsch-Initialen, Namen, einem Datum oder Symbol veredelt, auf Wunsch beidseitig auf Vorder- und Rückseite. Geliefert wird Ihr Unikat inklusive edlem Schmucketui und versichertem DHL-Paketversand.',
            'short_description' => 'Echter 585er Gelbgold-Herzanhänger (14 Karat Echtgold, 13 x 14 mm) im Komplett-Set mit echter 333er Gelbgold-Panzerkette (0,8 mm), beidseitiger UV-Lasergravur & Schmucketui.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 34900, // 349,00 € brutto Komplett-Set inkl. Schmucketui & versichertem Versand
            'compare_at_price' => null,
            'purchase_price' => 17059, // EK Silberketten-Store.de: 585er Gold-Herz (134,00€ brutto / 112,61€ netto) + 333er Gold-Panzerkette (69,00€ brutto / 57,98€ netto) = 203,00€ brutto / 170,59€ netto
            'laser_runtime_minutes' => 2,
            'electricity_wear_factor' => 15,
            'packaging_cost' => 166, // Schwarze Westpack-Schmuckschachtel (1,06€ netto) + Versandkarton (0,60€ netto) = 1,66€ netto
            'marketing_cost_percent' => 10.00,
            'sku' => 'MSF-ECHTGOLD-HERZ-001',
            'product_supplier_id' => $sup12?->id ?? $sup8?->id,
            'reorder_url' => 'https://www.silberketten-store.de/GOLDSCHMUCK/Kettenanhaenger/Gravuranhaenger-Herz-235559-400-585-Gold-10704',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke Atelier',
            'track_quantity' => true,
            'quantity' => 0, // Auftragsbezogene Einzelanfertigung (Startbestand 0)
            'continue_selling_when_out_of_stock' => true,
            'weight' => 3, // ca. 1,80 g Echtgold (0,80 g Herz + 1,00 g Kette) -> aufgerundet 3 g
            'packaging_weight' => 170, // 20 g Westpack Schmuckbox + 150 g Versandkarton
            'height' => 14,
            'width' => 13,
            'length' => 0.8,
            'shipping_class' => null, // Versicherter DHL-Paketversand (Haftung bis 500 €)
            'preview_image_path' => 'produkte/products/echtgold-seelen-herz/overlay.png',
            'three_d_model_path' => 'produkte/products/echtgold-seelen-herz/echtgold_seelen_herz_3d.glb',
            'three_d_background_path' => 'produkte/products/echtgold-seelen-herz/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/echtgold-seelen-herz/echtgold-seelen-herz-main.jpg', 'is_main' => true, 'is_hover' => false, 'is_ai_generated' => true, 'alt' => 'Das Echtgold Seelen-Herz: 585er Echtgold-Herzanhänger inklusive 333er Echtgold-Panzerkette & Gravur'],
                ['type' => 'image', 'path' => 'produkte/products/echtgold-seelen-herz/echtgold-seelen-herz-back.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'alt' => 'Das Echtgold Seelen-Herz: Beidseitige Präzisions-Gravur auf 585er Gelbgold'],
                ['type' => 'image', 'path' => 'produkte/products/echtgold-seelen-herz/echtgold-seelen-herz-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_ai_generated' => true, 'alt' => 'Das Echtgold Seelen-Herz Komplett-Set an der 333er Echtgold-Panzerkette getragen'],
                ['type' => 'image', 'path' => 'produkte/products/echtgold-seelen-herz/echtgold-seelen-herz-dimensions.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'alt' => 'Das Echtgold Seelen-Herz: Technisches Maßblatt & Abmessungen (13 x 14 mm 585er Gold-Herz + 0,8 mm 333er Goldkette)'],
            ],
            'configurator_settings' => [
                'template_package' => 'echtgold-seelen-herz',
                'origin_badge' => 'handcrafted_in_germany',
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'area_shape' => 'custom',
                'area_left' => 20.0,
                'area_top' => 38.0,
                'area_width' => 60.0,
                'area_height' => 52.5,
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'metal',
                'model_scale' => 133,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => 0, 'model_rot_y' => 0, 'model_rot_z' => 0,
                'engraving_scale' => 75,
                'engraving_pos_x' => 0, 'engraving_pos_y' => 0, 'engraving_pos_z' => 0,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 180, 'engraving_rot_z' => 0,
                'back_engraving_scale' => 75,
                'back_engraving_pos_x' => 0, 'back_engraving_pos_y' => 0, 'back_engraving_pos_z' => -1.78,
                'back_engraving_rot_x' => 0, 'back_engraving_rot_y' => 0, 'back_engraving_rot_z' => 0,
                'custom_points' => [
                    ['x' => 50.0, 'y' => 42.2],
                    ['x' => 54.5, 'y' => 39.2],
                    ['x' => 61.5, 'y' => 38.2],
                    ['x' => 68.5, 'y' => 39.0],
                    ['x' => 74.8, 'y' => 42.5],
                    ['x' => 78.5, 'y' => 48.2],
                    ['x' => 79.5, 'y' => 55.0],
                    ['x' => 78.2, 'y' => 62.5],
                    ['x' => 74.5, 'y' => 70.0],
                    ['x' => 69.2, 'y' => 77.2],
                    ['x' => 62.5, 'y' => 83.5],
                    ['x' => 55.5, 'y' => 88.2],
                    ['x' => 50.0, 'y' => 90.5],
                    ['x' => 44.5, 'y' => 88.2],
                    ['x' => 37.5, 'y' => 83.5],
                    ['x' => 30.8, 'y' => 77.2],
                    ['x' => 25.5, 'y' => 70.0],
                    ['x' => 21.8, 'y' => 62.5],
                    ['x' => 20.5, 'y' => 55.0],
                    ['x' => 21.5, 'y' => 48.2],
                    ['x' => 25.2, 'y' => 42.5],
                    ['x' => 31.5, 'y' => 39.0],
                    ['x' => 38.5, 'y' => 38.2],
                    ['x' => 45.5, 'y' => 39.2],
                ]
            ],
            'attributes' => [
                'Material Herzanhänger' => 'Echtes 585er Gelbgold (14 Karat Echtgold, gestempelt 585)',
                'Material Halskette (Inklusive)' => 'Echte 333er Gelbgold-Panzerkette (8 Karat Echtgold, 0,8 mm, Federring)',
                'Herz-Größe & Gewicht' => '13 x 14 mm (ca. 0,80 g 585er Gold + ca. 1,00 g 333er Goldkette)',
                'Veredelung' => 'Beidseitige 5W UV-Präzisions-Lasergravur (Vorder- & Rückseite inklusive)',
                'Etui & Versand' => 'Inklusive edlem Schmucketui & versichertem DHL-Paketversand',
                'Herkunft' => 'Handveredelt in Deutschland (Manufaktur Gifhorn)',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Das Echtgold Seelen-Herz | 585er Gold-Herz mit Gravur & 333er Goldkette | Mein-Seelenfunke Atelier',
            'seo_description' => 'Exklusives Echtgold-Set: 585er Gelbgold-Herzanhänger (14 Karat) inklusive 333er Echtgold-Panzerkette, beidseitiger Lasergravur und Schmucketui.',
            'completion_step' => 4
        ]);

        $catIdsEchtgoldHerz = ProductCategory::whereIn('name', ['Atelier Echtgold & Silber'])->pluck('id');
        $p7EchtgoldHerz->categories()->sync($catIdsEchtgoldHerz);
        $p7EchtgoldHerz->tierPrices()->delete();

        // Verpackungsmaterial (LUCID / /admin/product-packaging): Westpack Schmuckbox + Versandkarton (170 g gesamt)
        $p7EchtgoldHerz->packagings()->delete();
        $p7EchtgoldHerz->packagings()->createMany([
            ['material_type' => 'paper',   'weight_grams' => 148],
            ['material_type' => 'plastic', 'weight_grams' => 22],
        ]);
*/

        // --- PRODUKT 8: Das Echtsilber Seelen-Armband (Atelier Echtgold & Silber: FBM P001.53062HERZ.5.18.5 - 925er Sterlingsilber) ---
        $p8SilberArmband = Product::updateOrCreate(['slug' => 'id-armband-silber-herz'], [
            'name' => 'Das Echtsilber Seelen-Armband (925er Silber & Herz)',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'description' => 'Ein zeitloses Schmuckstück aus echtem Edelmetall, auf Bestellung für Sie handveredelt in unserer Manufaktur in Gifhorn: Das Echtsilber Seelen-Armband stammt aus der deutschen Traditionsmanufaktur Friedrich Binder (FBM, Mönsheim, gefertigt in Deutschland seit 1910) und besteht aus massivem 925er Sterlingsilber (gestempelt 925, ca. 2,50 g). Das 2,1 mm breite, fein diamantierte Figaro-Kettengeflecht trägt eine hochglanzpolierte Gravurplatte (24 x 5,5 mm) mit einem filigran ausgestanzten Herz-Durchbruch auf der linken Seite. Mit unserem 5W UV-Präzisionslaser veredeln wir die polierte Gravurfläche berührungslos und materialschonend mit Ihrem Wunschnamen, einem besonderen Datum oder einer liebevollen Botschaft, auf Wunsch kostenfrei beidseitig auf Vorder- und Rückseite. Dank des sicheren Federringverschlusses und der offenen Figaro-Glieder lässt sich die Damen-Standardlänge von 18,5 cm flexibel am Handgelenk tragen.',
            'short_description' => 'Massives 925er Sterlingsilber Seelen-Armband (Figaro diamantiert 2,1 mm, 18,5 cm, ca. 2,50 g) mit ausgestanztem Herz-Durchbruch und beidseitiger 5W UV-Lasergravur.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 7990, // 79,90 € brutto inkl. beidseitiger UV-Lasergravur
            'compare_at_price' => null,
            'purchase_price' => 1490, // B2B-EK FBM (P001.53062HERZ.5.18.5, 2,50 g 925er Silber): ca. 14,90 € netto
            'laser_runtime_minutes' => 2,
            'electricity_wear_factor' => 15,
            'packaging_cost' => 14, // Luftpolster-Versandtasche Standard (0,14 € netto; optionale Westpack-Geschenkbox als Extra wählbar)
            'marketing_cost_percent' => 10.00,
            'sku' => 'MSF-SILBER-ARMBAND-001',
            'product_supplier_id' => $sup13Fbm?->id ?? $sup8?->id,
            'reorder_url' => 'https://shop.fbm-chains.com/id-armband-figaro-diamantiert-2.1mm/P001.53062HERZ.5.18.5',
            'barcode' => '4028576028637',
            'brand' => 'Mein-Seelenfunke Atelier',
            'track_quantity' => true,
            'quantity' => 0, // Auftragsfertigung (Startbestand 0)
            'continue_selling_when_out_of_stock' => true,
            'weight' => 3, // 2,50 g 925er Sterlingsilber -> aufgerundet 3 g
            'packaging_weight' => 15, // 15 g Luftpolster-Versandtasche
            'width' => 185, // 185 mm (18,5 cm Gesamtlänge)
            'height' => 6,  // 5,5 mm Gravurplatten-Breite
            'length' => 2,  // 2,1 mm Kettenbreite / 1,2 mm Plattenstärke
            'shipping_class' => 'brief',
            'preview_image_path' => 'produkte/products/id-armband-silber-herz/overlay.png',
            'three_d_model_path' => 'produkte/products/id-armband-silber-herz/id_armband_silber_herz_3d.glb',
            'three_d_background_path' => 'produkte/products/id-armband-silber-herz/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/id-armband-silber-herz/id-armband-silber-herz-main.jpg', 'is_main' => true, 'is_hover' => false, 'is_ai_generated' => true, 'alt' => 'Das Echtsilber Seelen-Armband: Massives 925er Sterlingsilber Figaro-Armband mit Herz-Durchbruch & Lasergravur'],
                ['type' => 'image', 'path' => 'produkte/products/id-armband-silber-herz/id-armband-silber-herz-back.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'alt' => 'Das Echtsilber Seelen-Armband: Beidseitige Präzisions-UV-Lasergravur auf 925er Sterlingsilber'],
                ['type' => 'image', 'path' => 'produkte/products/id-armband-silber-herz/id-armband-silber-herz-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_ai_generated' => true, 'alt' => 'Das Echtsilber Seelen-Armband (18,5 cm, 2,1 mm Figaro diamantiert) mit Federringverschluss und 925er Punze'],
                ['type' => 'image', 'path' => 'produkte/products/id-armband-silber-herz/id-armband-silber-herz-dimensions.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'alt' => 'Das Echtsilber Seelen-Armband: Technisches Maßblatt & Abmessungen (18,5 cm Länge, 24 x 5,5 mm Gravurplatte)'],
            ],
            'configurator_settings' => [
                'template_package' => 'id-armband-silber-herz',
                'origin_badge' => 'handcrafted_in_germany',
                'out_of_stock_lead_days_min' => 3,
                'out_of_stock_lead_days_max' => 5,
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'area_shape' => 'custom',
                'area_left' => 35.1,
                'area_top' => 43.85,
                'area_width' => 43.5,
                'area_height' => 12.3,
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'silver',
                'engraving_Style' => 'dark',
                'model_scale' => 118,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => 0, 'model_rot_y' => 0, 'model_rot_z' => 0,
                'engraving_scale' => 85,
                'engraving_pos_x' => 0, 'engraving_pos_y' => 0, 'engraving_pos_z' => 0,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 180, 'engraving_rot_z' => 0,
                'back_engraving_scale' => 85,
                'back_engraving_pos_x' => 0, 'back_engraving_pos_y' => 0, 'back_engraving_pos_z' => -1.78,
                'back_engraving_rot_x' => 0, 'back_engraving_rot_y' => 0, 'back_engraving_rot_z' => 0,
                'custom_points' => [
                    ['x' => 36.25, 'y' => 43.85],
                    ['x' => 45, 'y' => 43.85],
                    ['x' => 56.85, 'y' => 43.85],
                    ['x' => 68, 'y' => 43.85],
                    ['x' => 77.45, 'y' => 43.85],
                    ['x' => 77.89, 'y' => 43.94],
                    ['x' => 78.26, 'y' => 44.19],
                    ['x' => 78.51, 'y' => 44.56],
                    ['x' => 78.6, 'y' => 45],
                    ['x' => 78.6, 'y' => 50],
                    ['x' => 78.6, 'y' => 55],
                    ['x' => 78.51, 'y' => 55.44],
                    ['x' => 78.26, 'y' => 55.81],
                    ['x' => 77.89, 'y' => 56.06],
                    ['x' => 77.45, 'y' => 56.15],
                    ['x' => 68, 'y' => 56.15],
                    ['x' => 56.85, 'y' => 56.15],
                    ['x' => 45, 'y' => 56.15],
                    ['x' => 36.25, 'y' => 56.15],
                    ['x' => 35.81, 'y' => 56.06],
                    ['x' => 35.44, 'y' => 55.81],
                    ['x' => 35.19, 'y' => 55.44],
                    ['x' => 35.1, 'y' => 55],
                    ['x' => 35.1, 'y' => 50],
                    ['x' => 35.1, 'y' => 45],
                    ['x' => 35.19, 'y' => 44.56],
                    ['x' => 35.44, 'y' => 44.19],
                    ['x' => 35.81, 'y' => 43.94],
                ],
            ],
            'attributes' => [
                'Edelmetall & Feingehalt' => 'Massives 925er Sterlingsilber (Echtsilber, gestempelt 925)',
                'Kettenart & Verschluss' => 'Figaro diamantiert (2,1 mm Breite) mit Federringverschluss',
                'Länge & Gewicht' => '18,5 cm Damen-Standardlänge (ca. 2,50 g Massivsilber)',
                'Gravurplatte' => '24 x 5,5 x 1,2 mm (hochglanzpoliert mit ausgestanztem Herz links)',
                'Veredelung' => 'Beidseitige 5W UV-Präzisions-Lasergravur (Vorder- & Rückseite inklusive)',
                'Herkunft' => 'Rohling Made in Germany (FBM Mönsheim), handveredelt in Gifhorn',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Das Echtsilber Seelen-Armband (925er Silber & Herz) | Mein-Seelenfunke Atelier',
            'seo_description' => 'Massives 925er Sterlingsilber Seelen-Armband (Figaro diamantiert 2,1 mm, 18,5 cm) mit Herz-Durchbruch und individueller, beidseitiger Lasergravur aus unserer Manufaktur.',
            'completion_step' => 4
        ]);

        $catIdsSilberArmband = ProductCategory::whereIn('name', ['Atelier Echtgold & Silber', 'Schmuck & Anhänger', 'Geschenksets'])->pluck('id');
        $p8SilberArmband->categories()->sync($catIdsSilberArmband);
        $p8SilberArmband->tierPrices()->delete();
        $p8SilberArmband->packagings()->delete();
        $p8SilberArmband->packagings()->createMany([
            ['material_type' => 'composite', 'weight_grams' => 12],
            ['material_type' => 'paper',     'weight_grams' => 3],
        ]);

        // --- PRODUKT 9: Das Gold-Vermeil Seelen-Armband (Atelier Echtgold & Silber: FBM P020.53062HERZ.5.18.5 - 925er Silber Gelb vergoldet) ---
        $p9GoldArmband = Product::updateOrCreate(['slug' => 'id-armband-gold-herz'], [
            'name' => 'Das Gold-Vermeil Seelen-Armband (925er Silber Gelb vergoldet & Herz)',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'description' => 'Edler Goldglanz auf echtem Edelmetall, auf Bestellung für Sie handveredelt in unserer Manufaktur in Gifhorn: Das Gold-Vermeil Seelen-Armband wird in der deutschen Traditionsmanufaktur Friedrich Binder (FBM, Mönsheim, gefertigt in Deutschland seit 1910) aus massivem 925er Sterlingsilber gefertigt und mit einer hochwertigen Gelbgold-Plattierung (925 Gelb vergoldet / Gold Vermeil, gestempelt 925, ca. 2,50 g) veredelt. Das 2,1 mm breite, fein diamantierte Figaro-Kettengeflecht trägt eine spiegelpolierte Gravurplatte (24 x 5,5 mm) mit einem ausgestanzten Herz-Durchbruch auf der linken Seite. Mit unserem 5W UV-Präzisionslaser gravieren wir Ihren Wunschnamen, Initialen oder ein besonderes Datum gestochen scharf rechts neben das Herz, auf Wunsch kostenfrei beidseitig auf Vorder- und Rückseite. Die Damen-Standardlänge von 18,5 cm mit Federringverschluss schmiegt sich elegant an jedes Handgelenk.',
            'short_description' => '925er Sterlingsilber Seelen-Armband mit echter Gelbgold-Vergoldung (Figaro diamantiert 2,1 mm, 18,5 cm, ca. 2,50 g), ausgestanztem Herz-Durchbruch und beidseitiger 5W UV-Lasergravur.',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 8990, // 89,90 € brutto inkl. beidseitiger UV-Lasergravur
            'compare_at_price' => null,
            'purchase_price' => 1890, // B2B-EK FBM (P020.53062HERZ.5.18.5, 2,50 g 925er Silber Gelb vergoldet): ca. 18,90 € netto
            'laser_runtime_minutes' => 2,
            'electricity_wear_factor' => 15,
            'packaging_cost' => 14, // Luftpolster-Versandtasche Standard (0,14 € netto; optionale Westpack-Geschenkbox als Extra wählbar)
            'marketing_cost_percent' => 10.00,
            'sku' => 'MSF-GOLD-ARMBAND-001',
            'product_supplier_id' => $sup13Fbm?->id ?? $sup8?->id,
            'reorder_url' => 'https://shop.fbm-chains.com/id-armband-figaro-diamantiert-2.1mm/P020.53062HERZ.5.18.5',
            'barcode' => '4053917317170',
            'brand' => 'Mein-Seelenfunke Atelier',
            'track_quantity' => true,
            'quantity' => 0, // Auftragsfertigung (Startbestand 0)
            'continue_selling_when_out_of_stock' => true,
            'weight' => 3, // 2,50 g 925er Sterlingsilber Gelb vergoldet -> aufgerundet 3 g
            'packaging_weight' => 15, // 15 g Luftpolster-Versandtasche
            'width' => 185, // 185 mm (18,5 cm Gesamtlänge)
            'height' => 6,  // 5,5 mm Gravurplatten-Breite
            'length' => 2,  // 2,1 mm Kettenbreite / 1,2 mm Plattenstärke
            'shipping_class' => 'brief',
            'preview_image_path' => 'produkte/products/id-armband-gold-herz/overlay.png',
            'three_d_model_path' => 'produkte/products/id-armband-gold-herz/id_armband_gold_herz_3d.glb',
            'three_d_background_path' => 'produkte/products/id-armband-gold-herz/header_bg.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/id-armband-gold-herz/id-armband-gold-herz-main.jpg', 'is_main' => true, 'is_hover' => false, 'is_ai_generated' => true, 'alt' => 'Das Gold-Vermeil Seelen-Armband: 925er Sterlingsilber Gelb vergoldet Figaro-Armband mit Herz-Durchbruch & Lasergravur'],
                ['type' => 'image', 'path' => 'produkte/products/id-armband-gold-herz/id-armband-gold-herz-back.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'alt' => 'Das Gold-Vermeil Seelen-Armband: Beidseitige Präzisions-UV-Lasergravur auf 925er Silber Gelb vergoldet'],
                ['type' => 'image', 'path' => 'produkte/products/id-armband-gold-herz/id-armband-gold-herz-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_ai_generated' => true, 'alt' => 'Das Gold-Vermeil Seelen-Armband (18,5 cm, 2,1 mm Figaro diamantiert) mit Federringverschluss und 925er Punze'],
                ['type' => 'image', 'path' => 'produkte/products/id-armband-gold-herz/id-armband-gold-herz-dimensions.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'alt' => 'Das Gold-Vermeil Seelen-Armband: Technisches Maßblatt & Abmessungen (18,5 cm Länge, 24 x 5,5 mm Gravurplatte)'],
            ],
            'configurator_settings' => [
                'template_package' => 'id-armband-gold-herz',
                'origin_badge' => 'handcrafted_in_germany',
                'out_of_stock_lead_days_min' => 4,
                'out_of_stock_lead_days_max' => 6,
                'auto_seasonal_main_image' => true,
                'enable_background_video' => true,
                'default_background_theme' => 'standard',
                'allow_text_pos' => true,
                'allow_logo' => true,
                'has_back_side' => true,
                'area_shape' => 'custom',
                'area_left' => 35.1,
                'area_top' => 43.85,
                'area_width' => 43.5,
                'area_height' => 12.3,
                'overlay_type' => 'plane',
                'cylinder_radius' => 50,
                'material_type' => 'gold',
                'engraving_Style' => 'dark',
                'model_scale' => 118,
                'model_pos_x' => 0, 'model_pos_y' => 0, 'model_pos_z' => 0,
                'model_rot_x' => 0, 'model_rot_y' => 0, 'model_rot_z' => 0,
                'engraving_scale' => 85,
                'engraving_pos_x' => 0, 'engraving_pos_y' => 0, 'engraving_pos_z' => 0,
                'engraving_rot_x' => 0, 'engraving_rot_y' => 180, 'engraving_rot_z' => 0,
                'back_engraving_scale' => 85,
                'back_engraving_pos_x' => 0, 'back_engraving_pos_y' => 0, 'back_engraving_pos_z' => -1.78,
                'back_engraving_rot_x' => 0, 'back_engraving_rot_y' => 0, 'back_engraving_rot_z' => 0,
                'custom_points' => [
                    ['x' => 36.25, 'y' => 43.85],
                    ['x' => 45, 'y' => 43.85],
                    ['x' => 56.85, 'y' => 43.85],
                    ['x' => 68, 'y' => 43.85],
                    ['x' => 77.45, 'y' => 43.85],
                    ['x' => 77.89, 'y' => 43.94],
                    ['x' => 78.26, 'y' => 44.19],
                    ['x' => 78.51, 'y' => 44.56],
                    ['x' => 78.6, 'y' => 45],
                    ['x' => 78.6, 'y' => 50],
                    ['x' => 78.6, 'y' => 55],
                    ['x' => 78.51, 'y' => 55.44],
                    ['x' => 78.26, 'y' => 55.81],
                    ['x' => 77.89, 'y' => 56.06],
                    ['x' => 77.45, 'y' => 56.15],
                    ['x' => 68, 'y' => 56.15],
                    ['x' => 56.85, 'y' => 56.15],
                    ['x' => 45, 'y' => 56.15],
                    ['x' => 36.25, 'y' => 56.15],
                    ['x' => 35.81, 'y' => 56.06],
                    ['x' => 35.44, 'y' => 55.81],
                    ['x' => 35.19, 'y' => 55.44],
                    ['x' => 35.1, 'y' => 55],
                    ['x' => 35.1, 'y' => 50],
                    ['x' => 35.1, 'y' => 45],
                    ['x' => 35.19, 'y' => 44.56],
                    ['x' => 35.44, 'y' => 44.19],
                    ['x' => 35.81, 'y' => 43.94],
                ],
            ],
            'attributes' => [
                'Edelmetall & Legierung' => 'Massives 925er Sterlingsilber mit Echtgold-Plattierung (925 Gelb vergoldet, gestempelt 925)',
                'Kettenart & Verschluss' => 'Figaro diamantiert (2,1 mm Breite) mit Federringverschluss',
                'Länge & Gewicht' => '18,5 cm Damen-Standardlänge (ca. 2,50 g Edelmetall)',
                'Gravurplatte' => '24 x 5,5 x 1,2 mm (spiegelpoliert mit ausgestanztem Herz links)',
                'Veredelung' => 'Beidseitige 5W UV-Präzisions-Lasergravur (Vorder- & Rückseite inklusive)',
                'Herkunft' => 'Rohling Made in Germany (FBM Mönsheim), handveredelt in Gifhorn',
            ],
            'tier_pricing' => [],
            'seo_title' => 'Das Gold-Vermeil Seelen-Armband (925er Silber Gelb vergoldet & Herz) | Mein-Seelenfunke Atelier',
            'seo_description' => 'Edles Seelen-Armband aus 925er Sterlingsilber Gelb vergoldet (Figaro diamantiert 2,1 mm, 18,5 cm) mit Herz-Durchbruch und beidseitiger Lasergravur.',
            'completion_step' => 4
        ]);

        $catIdsGoldArmband = ProductCategory::whereIn('name', ['Atelier Echtgold & Silber', 'Schmuck & Anhänger', 'Geschenksets'])->pluck('id');
        $p9GoldArmband->categories()->sync($catIdsGoldArmband);
        $p9GoldArmband->tierPrices()->delete();
        $p9GoldArmband->packagings()->delete();
        $p9GoldArmband->packagings()->createMany([
            ['material_type' => 'composite', 'weight_grams' => 12],
            ['material_type' => 'paper',     'weight_grams' => 3],
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
                ['type' => 'image', 'path' => 'produkte/products/ki-agenten-playbook/playbook-cover.jpg', 'is_main' => true, 'is_ai_generated' => true, 'alt' => 'KI-Agenten im E-Commerce Cover'],
                ['type' => 'image', 'path' => 'produkte/products/ki-agenten-playbook/playbook-perspective-tablet.jpg', 'is_main' => false, 'is_ai_generated' => true, 'alt' => 'KI-Agenten im E-Commerce Tablet-Ansicht'],
                ['type' => 'image', 'path' => 'produkte/products/ki-agenten-playbook/playbook-perspective-devices.jpg', 'is_main' => false, 'is_ai_generated' => true, 'alt' => 'KI-Agenten im E-Commerce Multi-Device-Ansicht']
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
            'collection_line' => 'voucher',
            'description' => 'Verschenken Sie unvergessliche Momente mit unserem edlen Seelenfunken Geschenkgutschein. Der Gutschein kann wahlweise digital per E-Mail oder als lasergravierte goldene Metallkarte im schwarzen Edel-Umschlag mit goldenem Siegel per Post bestellt werden. Sie können den Betrag flexibel wählen und eine persönliche Grußbotschaft hinzufügen. Der perfekte Ausdruck Ihrer Wertschätzung.',
            'is_personalizable' => true,
            'three_d_active' => false,
            'short_description' => 'Der flexible Seelenfunken Geschenkgutschein als E-Mail-PDF oder lasergravierte goldene Metallkarte im Edel-Umschlag mit Wachssiegel.',
            'status' => 'active',
            'price' => 0, // Wird dynamisch durch den Konfigurator/Warenkorb bestimmt
            'tax_class' => 'zero', // Mehrzweck-Gutschein gem. § 3 Abs. 14 UStG (Versteuerung erst bei Einlösung)
            'compare_at_price' => null,
            'sku' => 'MSF-GUTSCHEIN-001',
            'barcode' => '',
            'brand' => 'Mein-Seelenfunke',
            'track_quantity' => false,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
            'weight' => 25, 'packaging_weight' => 15, 'height' => 2, 'width' => 114, 'length' => 162, 'shipping_class' => 'brief',
            'preview_image_path' => 'produkte/products/geschenkgutschein/envelope.png',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-main.jpg', 'is_main' => true, 'is_hover' => false, 'is_ai_generated' => true, 'holiday' => 'Standard', 'alt' => 'Seelenfunke Geschenkgutschein im schwarz-goldenen Umschlag mit Wachssiegel'],
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-lifestyle.jpg', 'is_main' => false, 'is_hover' => true, 'is_ai_generated' => true, 'alt' => 'Seelenfunke Geschenkgutschein: Persönliche Übergabe im Schwarz-Gold-Umschlag mit Wachssiegel'],
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-halloween.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'holiday' => 'Halloween', 'alt' => 'Seelenfunke Geschenkgutschein: Herbst & Lichter Edition'],
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-weihnachten.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'holiday' => 'Weihnachten', 'alt' => 'Seelenfunke Geschenkgutschein: Weihnachts-Edition'],
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-valentinstag.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'holiday' => 'Valentinstag', 'alt' => 'Seelenfunke Geschenkgutschein: Romantik & Valentinstag Edition'],
                ['type' => 'image', 'path' => 'produkte/products/geschenkgutschein/gutschein-ostern.jpg', 'is_main' => false, 'is_hover' => false, 'is_ai_generated' => true, 'holiday' => 'Ostern', 'alt' => 'Seelenfunke Geschenkgutschein: Frühlings- & Oster-Edition'],
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
            'seo_description' => 'Edler Geschenkgutschein von Mein-Seelenfunke. Flexibler Wert, mit persönlicher Botschaft. Digital per E-Mail oder als lasergravierte goldene Metallkarte im Edel-Umschlag mit Wachssiegel bestellen.',
            'completion_step' => 4
        ]);

        $catIds5 = ProductCategory::whereIn('name', ['Gutscheine', 'Geschenksets', 'Bestseller'])->pluck('id');
        $p5->categories()->sync($catIds5);

        // Sicherstellen, dass bei allen bestehenden Produkten & Vorlagen jedes Bild als KI-generiert markiert ist
        // UND nur physische Produkte die einheitlichen Schwarz-Gold-Geschenkverpackungs-Optionen besitzen
        foreach (Product::withTrashed()->get() as $existingProduct) {
            $gallery = is_array($existingProduct->media_gallery) ? $existingProduct->media_gallery : [];
            $changed = false;
            if ($existingProduct->type === 'digital' && $existingProduct->collection_line !== 'voucher') {
                $existingProduct->collection_line = 'voucher';
                $changed = true;
            } elseif ($existingProduct->type === 'service' && $existingProduct->collection_line !== 'service') {
                $existingProduct->collection_line = 'service';
                $changed = true;
            } elseif ($existingProduct->type === 'physical' && !in_array($existingProduct->collection_line, ['manufaktur', 'atelier'], true)) {
                $existingProduct->collection_line = 'manufaktur';
                $changed = true;
            }
            foreach ($gallery as $idx => $item) {
                if (is_array($item) && ($item['type'] ?? 'image') === 'image' && empty($item['is_ai_generated'])) {
                    $gallery[$idx]['is_ai_generated'] = true;
                    $changed = true;
                }
            }
            if ($existingProduct->isPersonalizable() && $existingProduct->isPhysical()) {
                $hasBeginnerImg = collect($gallery)->contains(fn($m) => is_array($m) && !empty($m['is_beginner']));
                if (!$hasBeginnerImg && !empty($gallery)) {
                    foreach ($gallery as $idx => $item) {
                        if (is_array($item) && ($item['type'] ?? 'image') === 'image') {
                            $gallery[$idx]['is_beginner'] = true;
                            $changed = true;
                            break;
                        }
                    }
                }
            }
            if ($changed) {
                $existingProduct->media_gallery = $gallery;
            }

            $settings = is_array($existingProduct->configurator_settings) ? $existingProduct->configurator_settings : [];
            $hasLegacyWunschtext = collect($settings['custom_fields'] ?? [])->contains(function ($cf) {
                return is_array($cf) && ($cf['id'] ?? '') === 'cf_wunschtext' && (($cf['type'] ?? 'text') !== 'textarea' || (int) ($cf['max_length'] ?? 28) < 80);
            });
            if ($existingProduct->isPersonalizable() && $existingProduct->isPhysical() && (empty($settings['custom_fields']) || $hasLegacyWunschtext)) {
                $settings['custom_fields'] = $existingProduct->getDefaultBeginnerCustomFields();
                $existingProduct->configurator_settings = $settings;
                $changed = true;
            }
            if ($existingProduct->isPhysical()) {
                $effectiveAddons = $existingProduct->getEffectiveAddons(false);
                if (($settings['addons'] ?? null) !== $effectiveAddons) {
                    $settings['addons'] = $effectiveAddons;
                    $existingProduct->configurator_settings = $settings;
                    $changed = true;
                }
            } elseif (isset($settings['addons'])) {
                unset($settings['addons']);
                $existingProduct->configurator_settings = $settings;
                $changed = true;
            }

            if ($changed) {
                $existingProduct->saveQuietly();
            }
        }
        \App\Models\Product\ProductTemplate::withTrashed()->update(['is_ai_generated' => true]);

        // --- 3D-Modelle (.glb), Saison-Overlays & Technische Maßblätter nativ synchronisieren ---
        $this->call(ProductAssetSeeder::class);
    }
}
